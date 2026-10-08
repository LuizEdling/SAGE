<?php

namespace App\Services\Chat;

use App\Enums\ConversationStatus;
use App\Enums\ExecutionStatus;
use App\Enums\MessageAuthor;
use App\Exceptions\InvalidFunctionParametersException;
use App\Models\AgentFunction;
use App\Models\ChatAccessKey;
use App\Models\Conversation;
use App\Models\Execution;
use App\Models\Message;
use App\Services\Agent\AgentClient;
use App\Services\Executions\ExecutionService;
use App\Services\Functions\FunctionCatalog;
use App\Services\Functions\ParameterValidator;
use Illuminate\Support\Facades\DB;

/**
 * Conversa do chat embutido no sistema legado: uma conversa aberta por
 * usuário do sistema legado (e por chave de chat), encerrada pelo botão
 * "Remover conversa" ou por inatividade. Encerrar não apaga nada: a conversa
 * continua no Painel para supervisão (§4.4).
 */
class ChatService
{
    /** Quantas execuções recentes da conversa o Agente recebe como memória. */
    private const EXECUCOES_NA_MEMORIA = 10;

    public function __construct(
        private readonly AgentClient $agent,
        private readonly FunctionCatalog $catalog,
        private readonly ParameterValidator $validator,
        private readonly ExecutionService $executions,
    ) {}

    /**
     * Conversa aberta do usuário, ou uma nova. A aberta que passou do limite
     * de inatividade é encerrada antes — a pergunta nova não deve herdar o
     * contexto de um atendimento de horas atrás.
     *
     * @param  array{id:int, login:string, nome?:?string}  $erpUser
     */
    public function resolveConversation(ChatAccessKey $key, array $erpUser): Conversation
    {
        $conversation = $this->currentConversation($key, $erpUser['id']);

        if ($conversation !== null) {
            return $conversation;
        }

        return Conversation::create([
            'chat_access_key_id' => $key->id,
            'erp_user_id' => $erpUser['id'],
            'erp_user_login' => $erpUser['login'],
            'erp_user_name' => $erpUser['nome'] ?? null,
            'status' => ConversationStatus::Aberta,
            'opened_at' => now(),
            'last_message_at' => now(),
        ]);
    }

    /** Conversa aberta e ainda ativa do usuário; encerra a que ficou inativa. */
    public function currentConversation(ChatAccessKey $key, int $erpUserId): ?Conversation
    {
        $conversation = Conversation::where('chat_access_key_id', $key->id)
            ->where('erp_user_id', $erpUserId)
            ->where('status', ConversationStatus::Aberta)
            ->latest('id')
            ->first();

        if ($conversation === null) {
            return null;
        }

        $limite = now()->subMinutes(config('services.sage_chat.idle_minutes'));

        if ($conversation->last_message_at !== null && $conversation->last_message_at->lt($limite)) {
            $this->close($conversation);

            return null;
        }

        return $conversation;
    }

    public function close(Conversation $conversation): void
    {
        if ($conversation->status === ConversationStatus::Encerrada) {
            return;
        }

        $conversation->update([
            'status' => ConversationStatus::Encerrada,
            'closed_at' => now(),
        ]);
    }

    /**
     * Grava a pergunta, consulta o Agente (executando a consulta que ele
     * pedir) e grava a resposta com o diagnóstico, as fontes e as funções
     * usadas. Uma alteração proposta fica pendente, à espera do usuário.
     *
     * @param  array<string, mixed>  $contextoErp  perfil/entidade/página no momento da pergunta
     * @return array{0: Message, 1: Message} [mensagem do usuário, mensagem do Agente]
     */
    public function handleUserMessage(Conversation $conversation, string $content, array $contextoErp = []): array
    {
        // O histórico é o que veio ANTES desta mensagem: o Agente recebe a pergunta
        // atual à parte e, numa resposta a esclarecimento, procura nele a pergunta
        // original — se a própria mensagem entrasse aqui, ela a substituiria.
        [$historico, $respondendoEsclarecimento] = $this->historicoDaConversa($conversation);
        $execucoesAnteriores = $this->execucoesDaConversa($conversation);

        $userMessage = $conversation->messages()->create([
            'content' => $content,
            'author' => MessageAuthor::Usuario,
            // O perfil e a entidade ativos mudam durante a sessão do usuário
            // no sistema legado, por isso ficam na mensagem e não na conversa.
            'metadata' => $contextoErp !== [] ? json_encode(['contexto_erp' => $contextoErp]) : null,
        ]);

        $usuario = $conversation->erp_user_id !== null ? [
            'id' => $conversation->erp_user_id,
            'login' => $conversation->erp_user_login,
            'nome' => $conversation->erp_user_name,
        ] + $contextoErp : null;

        $resultado = $this->diagnoseWithFunctions(
            $content,
            $historico,
            $respondendoEsclarecimento,
            $usuario,
            $userMessage,
            $execucoesAnteriores,
            self::telaDaPagina($contextoErp['pagina'] ?? null, $contextoErp['pagina_titulo'] ?? null),
        );
        $diagnostico = $resultado['diagnostico'];

        $agentMessage = DB::transaction(function () use ($conversation, $resultado, $diagnostico) {
            $message = $conversation->messages()->create([
                'content' => $resultado['texto'],
                'author' => MessageAuthor::Agente,
                'metadata' => json_encode([
                    'resumo' => $diagnostico['resumo'] ?? null,
                    'causa_provavel' => $diagnostico['causa_provavel'] ?? null,
                    'modulo_afetado' => $diagnostico['modulo_afetado'] ?? null,
                    'modulo_id' => $diagnostico['modulo_id'] ?? null,
                    'confianca' => $diagnostico['confianca'] ?? 0,
                    'fontes' => $diagnostico['fontes'] ?? [],
                    'modulo_recurso' => $diagnostico['modulo_recurso'] ?? null,
                    'aguardando_esclarecimento' => $diagnostico['aguardando_esclarecimento'] ?? false,
                    // Quantas voltas o ciclo de refinamento do Agente deu até
                    // fechar este diagnóstico, e com quais consultas.
                    'iteracoes' => $diagnostico['iteracoes'] ?? 1,
                    'consultas' => $diagnostico['consultas'] ?? [],
                    'sustentado' => $diagnostico['sustentado'] ?? null,
                    // Faltou um parâmetro da função e o Agente perguntou ao usuário (trilha de supervisão).
                    'parametro_solicitado' => $diagnostico['parametro_solicitado'] ?? null,
                    // Funções vinculadas ao material desta resposta: o relato depois da decisão do
                    // usuário usa isso para dizer o que ainda dá para conferir.
                    'funcoes_candidatas' => $diagnostico['funcoes_candidatas'] ?? [],
                    // Funções que o Agente usou (consultas executadas) nesta resposta.
                    'funcoes' => array_map(fn (array $execucao) => [
                        'execution_id' => $execucao['execution_id'],
                        'codigo' => $execucao['codigo'],
                        'tipo' => $execucao['tipo'],
                        'status' => $execucao['status'],
                    ], $resultado['execucoes']),
                ]),
            ]);

            $knowledgeIds = collect($diagnostico['fontes'] ?? [])->pluck('knowledge_id')->unique()->filter();

            if ($knowledgeIds->isNotEmpty()) {
                $message->knowledges()->attach($knowledgeIds);
            }

            if ($resultado['proposta'] !== null) {
                $this->executions->propose($resultado['proposta']['function'], $resultado['proposta']['parametros'], $message);
            }

            $conversation->update(['last_message_at' => now()]);

            return $message;
        });

        return [$userMessage, $agentMessage];
    }

    /**
     * Diagnóstico com uso de funções do sistema legado, em duas fases: se o
     * Agente pede uma consulta, o Painel a executa e chama o Agente de novo
     * com o resultado (no máximo uma continuação). Sem `$usuario`, o Agente
     * não usa funções. Sem `$origem` (simulação), nada é registrado e a
     * alteração proposta só é devolvida, nunca registrada como pendente.
     *
     * @param  list<array{autor:string, conteudo:string}>  $historico
     * @param  array<string, mixed>|null  $usuario  usuário do sistema legado: id e/ou login, nome, perfil, entidade
     * @return array{diagnostico: array, texto: string, execucoes: list<array>, proposta: ?array{function: AgentFunction, parametros: array}}
     */
    /**
     * Tela do sistema legado em que o chat estava aberto, no formato do Agente
     * (`caminho` e `titulo`); null quando o sistema legado não informou nenhuma.
     *
     * @return array{caminho?: string, titulo?: string}|null
     */
    public static function telaDaPagina(?string $caminho, ?string $titulo): ?array
    {
        $tela = array_filter([
            'caminho' => $caminho !== null ? trim($caminho) : null,
            'titulo' => $titulo !== null ? trim($titulo) : null,
        ], fn ($valor) => $valor !== null && $valor !== '');

        return $tela === [] ? null : $tela;
    }

    public function diagnoseWithFunctions(
        string $content,
        array $historico,
        bool $respondendoEsclarecimento,
        ?array $usuario,
        ?Message $origem,
        array $execucoesAnteriores = [],
        ?array $tela = null,
    ): array {
        $funcoes = ($usuario !== null ? [
            'funcoes' => $this->catalog->forAgent(),
            'contexto_erp' => array_filter([
                'usuario_login' => $usuario['login'] ?? null,
                'usuario_nome' => $usuario['nome'] ?? null,
                'perfil' => $usuario['perfil'] ?? null,
                'interface' => $usuario['interface'] ?? null,
                'entidade_id' => $usuario['entidade_id'] ?? null,
                'entidade_nome' => $usuario['entidade'] ?? null,
            ], fn ($valor) => $valor !== null),
            // O que a conversa já conferiu e alterou: o Agente continua de onde parou.
            'execucoes_anteriores' => $this->execucoesParaAgente($execucoesAnteriores),
        ] : [])
            // A tela em que o chat está aberto: o Agente entende "esta tela" e desempata o módulo.
            + ($tela !== null ? ['tela' => $tela] : []);

        [$diagnostico, $texto] = $this->agent->diagnose($content, $historico, $respondendoEsclarecimento, $funcoes);
        $execucoes = [];

        if (($diagnostico['acao']['tipo'] ?? null) === 'executar_leitura' && $usuario !== null) {
            $execucao = $this->executarLeitura($diagnostico['acao'], $usuario, $origem);
            $execucoes[] = $execucao;

            [$diagnostico, $texto] = $this->agent->diagnose($content, $historico, $respondendoEsclarecimento, $funcoes + [
                'continuacao' => [
                    'fontes' => $diagnostico['fontes'] ?? [],
                    'modulo_id' => $diagnostico['modulo_id'] ?? null,
                    'consultas' => $diagnostico['consultas'] ?? [],
                    'iteracoes' => $diagnostico['iteracoes'] ?? 1,
                    'sustentado' => $diagnostico['sustentado'] ?? null,
                    'funcoes_candidatas' => $diagnostico['funcoes_candidatas'] ?? [],
                    // Objetos, não listas: array vazio do PHP vira `[]` em JSON
                    // e o Agente espera `{}` nesses campos.
                    'execucao' => ['parametros' => (object) $execucao['parametros'], 'resultado' => (object) $execucao['resultado']] + $execucao,
                ],
            ]);
        }

        return [
            'diagnostico' => $diagnostico,
            'texto' => $texto,
            'execucoes' => $execucoes,
            'proposta' => $this->proposta($diagnostico['acao'] ?? null),
        ];
    }

    /**
     * Decisão do usuário sobre uma alteração proposta. A decisão vira
     * mensagem do usuário (a confirmação do DBML), a alteração é executada
     * (ou não) e o Agente relata o resultado — antes → depois, ou a falha.
     *
     * @param  array<string, mixed>  $usuario  usuário do sistema legado, lido da sessão pelo plugin
     * @return array{0: Message, 1: Message} [confirmação do usuário, relato do Agente]
     */
    public function decideExecution(Execution $execution, bool $autorizar, array $usuario, ?string $token): array
    {
        $this->executions->assertDecidable($execution);

        $proposta = $execution->message;
        $conversation = $proposta->conversation;

        $confirmacao = $conversation->messages()->create([
            'content' => ($autorizar ? 'Autorizo: ' : 'Não autorizo: ').$execution->function->name,
            'author' => MessageAuthor::Usuario,
        ]);

        $execucao = $autorizar
            ? $this->executions->authorize($execution, $confirmacao, $this->usuarioParaSistemaLegado($usuario), (string) $token)
            : $this->executions->deny($execution, $confirmacao);

        // O relato parte do mesmo material da proposta; o Agente só monta o
        // texto do que de fato aconteceu no sistema.
        $metadata = json_decode($proposta->metadata ?? '{}', true);
        [, $texto] = $this->agent->diagnose($proposta->content, [], false, [
            'funcoes' => $this->catalog->forAgent(),
            'contexto_erp' => ['usuario_login' => $conversation->erp_user_login],
            'execucoes_anteriores' => $this->execucoesParaAgente($this->execucoesDaConversa($conversation)),
            'continuacao' => [
                'funcoes_candidatas' => $metadata['funcoes_candidatas'] ?? [],
                'fontes' => $metadata['fontes'] ?? [],
                'modulo_id' => $metadata['modulo_id'] ?? null,
                'consultas' => $metadata['consultas'] ?? [],
                'iteracoes' => $metadata['iteracoes'] ?? 1,
                'sustentado' => $metadata['sustentado'] ?? null,
                'execucao' => ['parametros' => (object) $execucao['parametros'], 'resultado' => (object) $execucao['resultado']] + $execucao,
            ],
        ]);

        $relato = $conversation->messages()->create([
            'content' => $texto,
            'author' => MessageAuthor::Agente,
            'metadata' => json_encode([
                'funcoes' => [[
                    'execution_id' => $execution->id,
                    'codigo' => $execucao['codigo'],
                    'tipo' => $execucao['tipo'],
                    'status' => $execucao['status'],
                ]],
            ]),
        ]);

        $conversation->update(['last_message_at' => now()]);

        return [$confirmacao, $relato];
    }

    /** Executa a consulta pedida; função ou parâmetro inválido vira falha (o Agente responde pelo conhecimento). */
    private function executarLeitura(array $acao, array $usuario, ?Message $origem): array
    {
        $function = $this->catalog->find((int) $acao['function_id']);

        $falha = fn (string $codigo) => [
            'execution_id' => null,
            'function_id' => (int) $acao['function_id'],
            'codigo' => $function?->code ?? '',
            'tipo' => 'consulta',
            'parametros' => [],
            'status' => 'falhou',
            'resultado' => [],
            'erro_codigo' => $codigo,
        ];

        // Só consulta roda sem autorização; qualquer outra coisa é recusada aqui.
        if ($function === null || $function->operation_type->value !== 'consulta') {
            return $falha('FUNCAO_INDISPONIVEL');
        }

        try {
            $parametros = $this->validator->validate($function, (array) ($acao['parametros'] ?? []));
        } catch (InvalidFunctionParametersException) {
            return $falha('INVALIDO');
        }

        return $this->executions->runRead($function, $parametros, $this->usuarioParaSistemaLegado($usuario), $origem);
    }

    /** @return array{function: AgentFunction, parametros: array}|null */
    private function proposta(?array $acao): ?array
    {
        if (($acao['tipo'] ?? null) !== 'propor_alteracao') {
            return null;
        }

        $function = $this->catalog->find((int) $acao['function_id']);

        if ($function === null || $function->operation_type->value !== 'alteracao') {
            return null;
        }

        try {
            return ['function' => $function, 'parametros' => $this->validator->validate($function, (array) ($acao['parametros'] ?? []))];
        } catch (InvalidFunctionParametersException) {
            return null;
        }
    }

    /**
     * Consultas e alterações já executadas nesta conversa (as mais recentes), no formato
     * que o Agente espera. É a memória que deixa a conversa seguir de onde parou: o
     * Agente não repete uma consulta já feita e sabe o que já foi corrigido.
     *
     * @return list<array<string, mixed>>
     */
    private function execucoesDaConversa(Conversation $conversation): array
    {
        return Execution::query()
            ->whereHas('message', fn ($q) => $q->where('conversation_id', $conversation->id))
            ->where('status', ExecutionStatus::Executada)
            ->with('function')
            ->latest('id')
            ->take(self::EXECUCOES_NA_MEMORIA)
            ->get()
            ->reverse()
            ->values()
            ->map(function (Execution $execution) {
                $alteracao = $execution->function->operation_type->value === 'alteracao';

                return [
                    'function_id' => $execution->function_id,
                    'tipo' => $execution->function->operation_type->value,
                    'status' => 'executada',
                    'parametros' => $execution->result['parametros'] ?? [],
                    'dados' => $alteracao ? [] : ($execution->result['dados'] ?? []),
                    'antes' => $alteracao ? ($execution->result['antes'] ?? null) : null,
                    'depois' => $alteracao ? ($execution->result['depois'] ?? null) : null,
                ];
            })
            ->all();
    }

    /**
     * Campos que o Agente espera como objeto: um array vazio do PHP viraria `[]` em
     * JSON e o Agente recusaria (422), como na continuação.
     *
     * @param  list<array<string, mixed>>  $execucoes
     * @return list<array<string, mixed>>
     */
    private function execucoesParaAgente(array $execucoes): array
    {
        return array_map(fn (array $execucao) => [
            'function_id' => $execucao['function_id'],
            'tipo' => $execucao['tipo'],
            'status' => $execucao['status'] ?? 'executada',
            'parametros' => (object) ($execucao['parametros'] ?? []),
            'dados' => (object) ($execucao['dados'] ?? []),
            'antes' => isset($execucao['antes']) ? (object) $execucao['antes'] : null,
            'depois' => isset($execucao['depois']) ? (object) $execucao['depois'] : null,
        ], $execucoes);
    }

    /** Quem executa no sistema legado: o usuário que conversa, no perfil e entidade em que perguntou. */
    public function usuarioParaSistemaLegado(array $usuario): array
    {
        return array_filter([
            'id' => $usuario['id'] ?? null,
            'login' => $usuario['login'] ?? null,
            'perfil_id' => $usuario['perfil_id'] ?? null,
            'entidade_id' => $usuario['entidade_id'] ?? null,
            'recursivo' => $usuario['recursivo'] ?? null,
            // Só na simulação do Painel: o sistema legado confere a senha (nunca é gravada).
            'senha' => $usuario['senha'] ?? null,
            'simulacao' => $usuario['simulacao'] ?? null,
        ], fn ($valor) => $valor !== null);
    }

    /**
     * Últimos turnos da conversa + se a última fala do Agente foi uma
     * pergunta de esclarecimento. Sem isso, uma resposta curta do usuário
     * ("é pessoa jurídica") chegaria ao Agente sem nenhum contexto.
     *
     * @return array{0: list<array{autor:string, conteudo:string}>, 1: bool}
     */
    private function historicoDaConversa(Conversation $conversation): array
    {
        $mensagens = $conversation->messages()->latest('id')->take(8)->get()->reverse()->values();

        $historico = $mensagens->map(fn ($mensagem) => [
            'autor' => $mensagem->author->value === MessageAuthor::Usuario->value ? 'usuario' : 'agente',
            'conteudo' => $mensagem->content,
        ])->all();

        $ultimaDoAgente = $mensagens->last(fn ($m) => $m->author === MessageAuthor::Agente);
        $metadata = json_decode($ultimaDoAgente->metadata ?? '{}', true);

        return [$historico, (bool) ($metadata['aguardando_esclarecimento'] ?? false)];
    }
}
