<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\SimulateChatRequest;
use App\Services\Chat\ChatService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ChatController extends Controller
{
    public function __construct(private readonly ChatService $chat) {}

    // Usado pela tela "Simular atendimento" do Painel — só pra um gestor
    // testar o comportamento do Agente. Não usa chat_access_key (essa
    // credencial é do widget embutido no sistema legado, ver
    // WidgetChatController) e não grava Conversation/Message nenhum: é só um
    // "pergunte e veja a resposta", descartável, sem entrar no histórico.
    public function simulate(SimulateChatRequest $request): JsonResponse
    {
        $credenciais = $request->validated('usuario_erp');

        // Executar consultas no sistema legado em nome de alguém é tarefa de supervisão.
        if ($credenciais !== null && ! $request->user()->is_master) {
            throw new AuthorizationException('Só o gestor pode simular como usuário do sistema legado.');
        }

        $usuario = $credenciais === null ? null : $this->usuarioDaSimulacao($credenciais);

        // Sem mensagem de origem: consultas rodam, mas nada é registrado, e a
        // alteração proposta só é mostrada — nunca fica pendente.
        $resultado = $this->chat->diagnoseWithFunctions(
            $request->string('content')->toString(),
            $request->input('historico', []),
            $request->boolean('respondendo_esclarecimento'),
            $usuario,
            null,
            $request->validated('execucoes_anteriores', []),
            ChatService::telaDaPagina($request->validated('pagina'), $request->validated('pagina_titulo')),
        );

        $proposta = $resultado['proposta'];

        return ApiResponse::data([
            'resposta_texto' => $resultado['texto'],
            'diagnostico' => $resultado['diagnostico'] + [
                'execucoes' => $resultado['execucoes'],
                'proposta' => $proposta ? [
                    'codigo' => $proposta['function']->code,
                    'funcao' => $proposta['function']->name,
                    'parametros' => $proposta['parametros'],
                ] : null,
            ],
        ]);
    }

    /**
     * Sempre com credenciais, conferidas pelo sistema legado: o usuário de teste
     * padrão (cuja senha só existe no servidor) ou login e senha informados.
     * `simulacao` avisa o sistema legado que a senha precisa ser verificada.
     *
     * @param  array{padrao?: bool, login?: string, senha?: string}  $credenciais
     * @return array{login: string, senha: string, simulacao: true}
     */
    private function usuarioDaSimulacao(array $credenciais): array
    {
        if ($credenciais['padrao'] ?? false) {
            $login = config('services.legacy_system.simulation_login');
            $senha = config('services.legacy_system.simulation_password');

            if (! $login || ! $senha) {
                throw ValidationException::withMessages([
                    'usuario_erp' => 'O usuário de teste padrão não está configurado (SIMULATION_ERP_LOGIN e SIMULATION_ERP_PASSWORD).',
                ]);
            }

            return ['login' => $login, 'senha' => $senha, 'simulacao' => true];
        }

        return ['login' => $credenciais['login'], 'senha' => $credenciais['senha'], 'simulacao' => true];
    }
}
