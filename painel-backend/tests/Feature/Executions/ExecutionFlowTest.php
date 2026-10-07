<?php

namespace Tests\Feature\Executions;

use App\Enums\ExecutionStatus;
use App\Models\AgentFunction;
use App\Models\ChatAccessKey;
use App\Models\Component;
use App\Models\ErpExecutionKey;
use App\Models\Execution;
use App\Services\AccessKeyGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Funções do sistema legado no chat: consulta executada pelo Painel a pedido
 * do Agente, alteração proposta e decidida pelo usuário no widget.
 */
class ExecutionFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $chave = 'chave-de-chat';

    private AgentFunction $leitura;

    private AgentFunction $alteracao;

    protected function setUp(): void
    {
        parent::setUp();

        ChatAccessKey::create(['key_hash' => app(AccessKeyGenerator::class)->hash($this->chave), 'active' => true]);
        ErpExecutionKey::create(['access_key' => 'chave-de-execucao', 'api_url' => 'http://legado.test/plugins/sage/api', 'description' => 'Sistema legado', 'active' => true]);
        $modulo = Component::create(['name' => 'Ativos', 'description' => 'Ativos']);

        $this->leitura = $this->funcao('licenca.vagas.ler', 'consulta', $modulo->id, [['licenca_nome', 'string', true]]);
        $this->alteracao = $this->funcao('licenca.exceder_cota.alterar', 'alteracao', $modulo->id, [
            ['licenca_nome', 'string', true], ['permitir', 'boolean', true],
        ]);
    }

    private function funcao(string $code, string $tipo, int $componentId, array $parametros): AgentFunction
    {
        $funcao = AgentFunction::create([
            'code' => $code, 'name' => $code, 'description' => $code,
            'operation_type' => $tipo, 'component_id' => $componentId,
        ]);
        $funcao->forceFill(['status' => 'aprovado', 'active' => true])->save();

        foreach ($parametros as [$nome, $tipoParametro, $obrigatorio]) {
            $funcao->parameters()->create(['name' => $nome, 'type' => $tipoParametro, 'required' => $obrigatorio]);
        }

        return $funcao;
    }

    private function diagnostico(?array $acao, string $texto = ''): array
    {
        return [
            'resumo' => 'r', 'causa_provavel' => 'c', 'modulo_afetado' => 'Ativos', 'modulo_id' => 1,
            'confianca' => 0.7, 'fontes' => [], 'resposta_texto' => $texto, 'iteracoes' => 1, 'consultas' => [],
            'aguardando_esclarecimento' => false, 'acao' => $acao, 'funcoes_candidatas' => [$this->leitura->id, $this->alteracao->id],
        ];
    }

    /** Pergunta que gera consulta + proposta; devolve a execução proposta. */
    private function perguntarEReceberProposta(): Execution
    {
        Http::fake([
            '*/diagnose' => Http::sequence()
                ->push($this->diagnostico(['tipo' => 'executar_leitura', 'function_id' => $this->leitura->id, 'parametros' => ['licenca_nome' => 'Office']]))
                ->push($this->diagnostico(
                    ['tipo' => 'propor_alteracao', 'function_id' => $this->alteracao->id, 'parametros' => ['licenca_nome' => 'Office', 'permitir' => true]],
                    'As vagas acabaram. Clique em Autorizar.'
                ))
                ->push($this->diagnostico(null, 'Pronto, fiz a alteração.')),
            '*/plugins/sage/api/functions/*' => Http::response([
                'status' => 'ok', 'dados' => ['vagas' => 2, 'em_uso' => 3], 'pode_alterar' => true,
            ]),
        ]);

        $this->withToken($this->chave)->postJson('/api/v1/chat/messages', [
            'content' => 'Não consigo adicionar mais um computador na licença',
            'usuario_erp' => ['id' => 9, 'login' => 'carlos.souza', 'perfil_id' => 6, 'entidade_id' => 0],
        ])->assertStatus(201);

        return Execution::where('function_id', $this->alteracao->id)->firstOrFail();
    }

    public function test_consulta_e_executada_sem_confirmacao_e_alteracao_fica_pendente(): void
    {
        $proposta = $this->perguntarEReceberProposta();

        $consulta = Execution::where('function_id', $this->leitura->id)->firstOrFail();
        $this->assertSame(ExecutionStatus::Executada, $consulta->status);
        $this->assertNull($consulta->confirmation_message_id);

        $this->assertSame(ExecutionStatus::Pendente, $proposta->status);
        $this->assertSame(['licenca_nome' => 'Office', 'permitir' => true], $proposta->result['parametros']);

        // A alteração não foi enviada ao sistema legado: só a consulta.
        Http::assertSentCount(3);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'exceder_cota'));
    }

    public function test_autorizar_executa_com_o_token_e_registra_a_confirmacao(): void
    {
        $proposta = $this->perguntarEReceberProposta();

        $this->withToken($this->chave)->postJson("/api/v1/executions/{$proposta->id}/authorize", [
            'usuario_erp' => ['id' => 9, 'login' => 'carlos.souza'],
            'token_autorizacao' => 'token-do-glpi',
        ])->assertOk();

        $proposta->refresh();
        $this->assertSame(ExecutionStatus::Executada, $proposta->status);
        $this->assertNotNull($proposta->confirmation_message_id);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'licenca.exceder_cota.alterar/execute')
            && $request['token_autorizacao'] === 'token-do-glpi');
    }

    public function test_autorizar_sem_token_e_recusado(): void
    {
        $proposta = $this->perguntarEReceberProposta();

        $this->withToken($this->chave)->postJson("/api/v1/executions/{$proposta->id}/authorize", [
            'usuario_erp' => ['id' => 9, 'login' => 'carlos.souza'],
        ])->assertStatus(422);

        $this->assertSame(ExecutionStatus::Pendente, $proposta->fresh()->status);
    }

    public function test_outro_usuario_nao_decide_a_alteracao(): void
    {
        $proposta = $this->perguntarEReceberProposta();

        $this->withToken($this->chave)->postJson("/api/v1/executions/{$proposta->id}/deny", [
            'usuario_erp' => ['id' => 7, 'login' => 'maria.silva'],
        ])->assertStatus(404);

        $this->assertSame(ExecutionStatus::Pendente, $proposta->fresh()->status);
    }

    public function test_negar_nao_chama_o_sistema_legado(): void
    {
        $proposta = $this->perguntarEReceberProposta();

        $this->withToken($this->chave)->postJson("/api/v1/executions/{$proposta->id}/deny", [
            'usuario_erp' => ['id' => 9, 'login' => 'carlos.souza'],
        ])->assertOk();

        $this->assertSame(ExecutionStatus::Negada, $proposta->fresh()->status);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'exceder_cota'));
    }

    public function test_proposta_expirada_nao_pode_ser_autorizada(): void
    {
        $proposta = $this->perguntarEReceberProposta();

        $this->travel(config('services.legacy_system.execution_ttl') + 1)->minutes();

        $this->withToken($this->chave)->postJson("/api/v1/executions/{$proposta->id}/authorize", [
            'usuario_erp' => ['id' => 9, 'login' => 'carlos.souza'],
            'token_autorizacao' => 'token-do-glpi',
        ])->assertStatus(409)->assertJsonPath('error.code', 'EXECUTION_EXPIRED');

        $this->assertSame(ExecutionStatus::Negada, $proposta->fresh()->status);
        $this->assertSame('expirada', $proposta->fresh()->result['motivo']);
    }

    private function ultimaChamadaAoAgente(): array
    {
        return json_decode(
            Http::recorded(fn ($request) => str_contains($request->url(), '/diagnose'))->last()[0]->body(),
            true
        );
    }

    public function test_execucoes_da_conversa_voltam_ao_agente_na_pergunta_seguinte(): void
    {
        $this->perguntarEReceberProposta();

        $this->withToken($this->chave)->postJson('/api/v1/chat/messages', [
            'content' => 'E agora?',
            'usuario_erp' => ['id' => 9, 'login' => 'carlos.souza', 'perfil_id' => 6, 'entidade_id' => 0],
        ])->assertStatus(201);

        // O Agente recebe o que a conversa já conferiu (a consulta executada) e não a proposta pendente.
        $memoria = $this->ultimaChamadaAoAgente()['execucoes_anteriores'];

        $this->assertCount(1, $memoria);
        $this->assertSame($this->leitura->id, $memoria[0]['function_id']);
        $this->assertSame('consulta', $memoria[0]['tipo']);
        $this->assertSame(['licenca_nome' => 'Office'], $memoria[0]['parametros']);
        $this->assertSame(2, $memoria[0]['dados']['vagas']);
    }

    public function test_relato_leva_as_candidatas_e_a_memoria_para_sugerir_o_que_falta(): void
    {
        $proposta = $this->perguntarEReceberProposta();

        $this->withToken($this->chave)->postJson("/api/v1/executions/{$proposta->id}/authorize", [
            'usuario_erp' => ['id' => 9, 'login' => 'carlos.souza'],
            'token_autorizacao' => 'token-do-glpi',
        ])->assertOk();

        $relato = $this->ultimaChamadaAoAgente();

        $this->assertSame([$this->leitura->id, $this->alteracao->id], $relato['continuacao']['funcoes_candidatas']);
        $this->assertEqualsCanonicalizing(
            ['consulta', 'alteracao'],
            array_column($relato['execucoes_anteriores'], 'tipo')
        );
    }
}
