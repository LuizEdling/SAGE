<?php

namespace Tests\Feature\Chat;

use App\Models\AgentFunction;
use App\Models\Component;
use App\Models\ErpExecutionKey;
use App\Models\Execution;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Simular como usuário do sistema legado": sempre com credenciais — o usuário
 * de teste padrão (senha só no servidor) ou login e senha conferidos pelo
 * sistema legado.
 */
class SimulateAsUserTest extends TestCase
{
    use RefreshDatabase;

    private User $gestor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gestor = User::factory()->create(['is_master' => true, 'active' => true]);
        ErpExecutionKey::create(['access_key' => 'chave-de-execucao', 'api_url' => 'http://legado.test/plugins/sage/api', 'description' => 'Sistema legado', 'active' => true]);

        $modulo = Component::create(['name' => 'Regras', 'description' => 'Regras']);
        $this->leitura = AgentFunction::create([
            'code' => 'notificacao.evento.ler', 'name' => 'Ler', 'description' => 'Lê',
            'operation_type' => 'consulta', 'component_id' => $modulo->id,
        ]);
        $this->leitura->forceFill(['status' => 'aprovado', 'active' => true])->save();
    }

    private AgentFunction $leitura;

    private function diagnostico(?array $acao): array
    {
        return [
            'resumo' => 'r', 'causa_provavel' => 'c', 'modulo_afetado' => 'Regras', 'modulo_id' => 1,
            'confianca' => 0.7, 'fontes' => [], 'resposta_texto' => 'ok', 'iteracoes' => 1, 'consultas' => [],
            'aguardando_esclarecimento' => false, 'acao' => $acao, 'funcoes_candidatas' => [$this->leitura->id],
        ];
    }

    private function fakeFluxoComConsulta(): void
    {
        Http::fake([
            '*/diagnose' => Http::sequence()
                ->push($this->diagnostico(['tipo' => 'executar_leitura', 'function_id' => $this->leitura->id, 'parametros' => ['evento' => 'update']]))
                ->push($this->diagnostico(null)),
            '*/plugins/sage/api/functions/*' => Http::response(['status' => 'ok', 'dados' => [], 'pode_alterar' => false]),
        ]);
    }

    private function simular(array $corpo)
    {
        return $this->actingAs($this->gestor, 'sanctum')
            ->postJson('/api/v1/chat/simulate', ['content' => 'Ninguém recebe e-mail'] + $corpo);
    }

    public function test_sem_usuario_a_simulacao_so_usa_o_conhecimento(): void
    {
        Http::fake(['*/diagnose' => Http::response($this->diagnostico(null))]);

        $this->simular([])->assertOk();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/plugins/sage/'));
    }

    public function test_outro_usuario_exige_login_e_senha(): void
    {
        $this->simular(['usuario_erp' => ['login' => 'maria.silva']])->assertUnprocessable();
        $this->simular(['usuario_erp' => ['senha' => 'x']])->assertUnprocessable();
    }

    public function test_senha_vai_ao_sistema_legado_e_nao_volta_nem_e_gravada(): void
    {
        $this->fakeFluxoComConsulta();

        $resposta = $this->simular(['usuario_erp' => ['login' => 'maria.silva', 'senha' => 'senha-secreta-123']])->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/functions/notificacao.evento.ler/execute')
            && $request['usuario']['login'] === 'maria.silva'
            && $request['usuario']['senha'] === 'senha-secreta-123'
            && $request['usuario']['simulacao'] === true);

        // O Agente recebe o contexto do usuário, nunca a senha.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/diagnose')
            && str_contains($request->body(), 'senha-secreta-123'));

        $this->assertStringNotContainsString('senha-secreta-123', $resposta->getContent());
        $this->assertSame(0, Execution::count());
        $this->assertSame(0, Message::count());
    }

    public function test_usuario_padrao_usa_as_credenciais_do_servidor(): void
    {
        config(['services.legacy_system.simulation_login' => 'sage.simulacao', 'services.legacy_system.simulation_password' => 'segredo-do-servidor']);
        $this->fakeFluxoComConsulta();

        $resposta = $this->simular(['usuario_erp' => ['padrao' => true]])->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/functions/')
            && $request['usuario']['login'] === 'sage.simulacao'
            && $request['usuario']['senha'] === 'segredo-do-servidor');
        $this->assertStringNotContainsString('segredo-do-servidor', $resposta->getContent());
    }

    public function test_usuario_padrao_sem_configuracao_e_recusado(): void
    {
        config(['services.legacy_system.simulation_login' => null, 'services.legacy_system.simulation_password' => null]);
        Http::fake();

        $this->simular(['usuario_erp' => ['padrao' => true]])->assertUnprocessable();

        Http::assertNothingSent();
    }

    public function test_padrao_nao_se_mistura_com_outro_login(): void
    {
        $this->simular(['usuario_erp' => ['padrao' => true, 'login' => 'glpi', 'senha' => 'glpi']])->assertUnprocessable();
    }

    public function test_so_o_gestor_simula_como_usuario(): void
    {
        $comum = User::factory()->create(['is_master' => false, 'active' => true]);
        Http::fake();

        $this->actingAs($comum, 'sanctum')
            ->postJson('/api/v1/chat/simulate', ['content' => 'oi', 'usuario_erp' => ['login' => 'a', 'senha' => 'b']])
            ->assertForbidden();
    }

    public function test_tela_recebe_o_login_padrao_mas_nunca_a_senha(): void
    {
        config(['services.legacy_system.simulation_login' => 'sage.simulacao', 'services.legacy_system.simulation_password' => 'segredo-do-servidor']);

        $resposta = $this->actingAs($this->gestor, 'sanctum')->getJson('/api/v1/erp/execution-key')->assertOk();

        $resposta->assertJsonPath('data.simulation_login', 'sage.simulacao');
        $this->assertStringNotContainsString('segredo-do-servidor', $resposta->getContent());
    }

    public function test_memoria_enviada_pelo_navegador_chega_ao_agente_com_campos_vazios_como_objeto(): void
    {
        $this->fakeFluxoComConsulta();

        $this->simular([
            'usuario_erp' => ['padrao' => true],
            'execucoes_anteriores' => [
                ['function_id' => $this->leitura->id, 'tipo' => 'consulta', 'status' => 'executada', 'parametros' => [], 'dados' => []],
            ],
        ])->assertOk();

        $primeira = Http::recorded(fn ($request) => str_contains($request->url(), '/diagnose'))->first()[0];
        $enviado = json_decode($primeira->body())->execucoes_anteriores[0];

        // `[]` em vez de `{}` faria o Agente recusar a requisição.
        $this->assertSame($this->leitura->id, $enviado->function_id);
        $this->assertIsObject($enviado->parametros);
        $this->assertIsObject($enviado->dados);
    }

    public function test_memoria_com_tipo_invalido_e_recusada(): void
    {
        Http::fake();

        $this->simular(['execucoes_anteriores' => [['function_id' => 1, 'tipo' => 'apagar']]])->assertUnprocessable();
    }
}
