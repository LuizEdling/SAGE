<?php

namespace Tests\Feature\Functions;

use App\Enums\ApprovalStatus;
use App\Models\AgentFunction;
use App\Models\Component;
use App\Models\ErpExecutionKey;
use App\Models\Knowledge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Funções do sistema legado no Painel: importação do manifesto do plugin,
 * ativação e vínculo com conhecimentos.
 */
class FunctionsTest extends TestCase
{
    use RefreshDatabase;

    private User $gestor;

    private Component $modulo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gestor = User::factory()->create(['is_master' => true, 'active' => true]);
        $this->modulo = Component::create(['name' => 'Chamados', 'description' => 'Chamados do GLPI']);
        ErpExecutionKey::create(['access_key' => 'chave-de-execucao', 'api_url' => 'http://legado.test/plugins/sage/api', 'description' => 'Sistema legado', 'active' => true]);
    }

    private function fakeManifesto(string $descricao = 'Lê o prazo de fechamento automático.'): void
    {
        Http::fake([
            '*/plugins/sage/api/manifest' => Http::response(['funcoes' => [[
                'code' => 'entidade.fechamento_automatico.ler',
                'name' => 'Consultar o fechamento automático de chamados',
                'description' => $descricao,
                'module' => 'Chamados',
                'operation_type' => 'consulta',
                'parameters' => [
                    ['name' => 'entidade_id', 'type' => 'integer', 'required' => false, 'description' => 'Entidade.'],
                ],
            ]]]),
        ]);
    }

    public function test_ativar_funcao_grava_de_fato(): void
    {
        $funcao = AgentFunction::create([
            'name' => 'F', 'description' => 'd', 'operation_type' => 'consulta', 'component_id' => $this->modulo->id,
        ]);

        $this->actingAs($this->gestor)->patchJson("/api/v1/functions/{$funcao->id}/active")
            ->assertOk()->assertJsonPath('data.active', true);

        $this->assertTrue($funcao->fresh()->active);
    }

    public function test_importa_o_manifesto_como_pendente_e_inativa(): void
    {
        $this->fakeManifesto();

        $this->actingAs($this->gestor)->postJson('/api/v1/functions/import-manifest')
            ->assertOk()->assertJsonPath('data.created', 1);

        $funcao = AgentFunction::where('code', 'entidade.fechamento_automatico.ler')->firstOrFail();
        $this->assertSame(ApprovalStatus::Pendente, $funcao->status);
        $this->assertFalse($funcao->active);
        $this->assertSame(1, $funcao->parameters()->count());

        Http::assertSent(fn ($request) => $request->hasHeader('X-Sage-Execution-Key', 'chave-de-execucao'));

        // Importar de novo sem mudança não altera nada.
        $this->actingAs($this->gestor)->postJson('/api/v1/functions/import-manifest')
            ->assertJsonPath('data.created', 0)->assertJsonPath('data.updated', 0);
    }

    public function test_funcao_alterada_no_sistema_legado_volta_a_pendente(): void
    {
        $this->fakeManifesto();
        $this->actingAs($this->gestor)->postJson('/api/v1/functions/import-manifest');

        $funcao = AgentFunction::where('code', 'entidade.fechamento_automatico.ler')->firstOrFail();
        $this->actingAs($this->gestor)->postJson("/api/v1/functions/{$funcao->id}/approve")->assertOk();

        Http::swap(new Factory);
        $this->fakeManifesto('Descrição nova vinda do sistema legado.');

        $this->actingAs($this->gestor)->postJson('/api/v1/functions/import-manifest')
            ->assertJsonPath('data.updated', 1);

        $this->assertSame(ApprovalStatus::Pendente, $funcao->fresh()->status);
    }

    public function test_sem_chave_de_execucao_ativa_a_importacao_e_recusada(): void
    {
        ErpExecutionKey::query()->update(['active' => false]);

        $this->actingAs($this->gestor)->postJson('/api/v1/functions/import-manifest')
            ->assertStatus(409)->assertJsonPath('error.code', 'NO_ACTIVE_EXECUTION_KEY');
    }

    public function test_so_o_gestor_importa_e_vincula_funcoes(): void
    {
        $comum = User::factory()->create(['is_master' => false, 'active' => true]);
        $funcao = AgentFunction::create([
            'name' => 'F', 'description' => 'd', 'operation_type' => 'consulta', 'component_id' => $this->modulo->id,
        ]);
        $conhecimento = Knowledge::create(['type' => 'base', 'content' => 'c', 'component_id' => $this->modulo->id]);

        $this->actingAs($comum)->postJson('/api/v1/functions/import-manifest')->assertStatus(403);
        $this->actingAs($comum)->putJson("/api/v1/knowledges/{$conhecimento->id}/functions", ['function_ids' => [$funcao->id]])
            ->assertStatus(403);

        $this->actingAs($this->gestor)->putJson("/api/v1/knowledges/{$conhecimento->id}/functions", ['function_ids' => [$funcao->id]])
            ->assertOk()->assertJsonPath('data.function_ids', [$funcao->id]);
    }

    public function test_funcao_nao_se_cadastra_nem_se_edita_pelo_painel(): void
    {
        $funcao = AgentFunction::create([
            'code' => 'a.b.c', 'name' => 'F', 'description' => 'd',
            'operation_type' => 'consulta', 'component_id' => $this->modulo->id,
        ]);

        // Funções nascem no sistema legado e entram pela importação do manifesto.
        $this->actingAs($this->gestor)->postJson('/api/v1/functions', ['name' => 'x'])->assertStatus(405);
        $this->actingAs($this->gestor)->putJson("/api/v1/functions/{$funcao->id}", ['name' => 'x'])->assertStatus(405);
        $this->actingAs($this->gestor)->postJson("/api/v1/functions/{$funcao->id}/parameters", ['name' => 'x'])->assertNotFound();
    }

    public function test_remover_funcao_decidida_e_so_do_gestor(): void
    {
        $comum = User::factory()->create(['is_master' => false, 'active' => true]);
        $funcao = AgentFunction::create([
            'code' => 'a.b.c', 'name' => 'F', 'description' => 'd',
            'operation_type' => 'consulta', 'component_id' => $this->modulo->id,
        ]);

        $this->actingAs($comum)->deleteJson("/api/v1/functions/{$funcao->id}")->assertOk();

        $aprovada = AgentFunction::create([
            'code' => 'd.e.f', 'name' => 'G', 'description' => 'd',
            'operation_type' => 'consulta', 'component_id' => $this->modulo->id,
        ]);
        $aprovada->forceFill(['status' => ApprovalStatus::Aprovado])->save();

        $this->actingAs($comum)->deleteJson("/api/v1/functions/{$aprovada->id}")->assertForbidden();
        $this->actingAs($this->gestor)->deleteJson("/api/v1/functions/{$aprovada->id}")->assertOk();
    }

    public function test_endereco_da_api_vem_da_chave_de_execucao_ativa(): void
    {
        ErpExecutionKey::create(['access_key' => 'chave-nova', 'api_url' => 'https://outro-sistema.test/erp/sage-api/', 'description' => 'Outro sistema', 'active' => true]);
        Http::fake(['https://outro-sistema.test/erp/sage-api/manifest' => Http::response(['funcoes' => []])]);

        $this->actingAs($this->gestor)->postJson('/api/v1/functions/import-manifest')->assertOk();

        Http::assertSent(fn ($request) => $request->url() === 'https://outro-sistema.test/erp/sage-api/manifest'
            && $request->hasHeader('X-Sage-Execution-Key', 'chave-nova'));
    }

    public function test_manifesto_com_item_invalido_recusa_so_esse_item(): void
    {
        $valido = [
            'code' => 'entidade.fechamento_automatico.ler', 'name' => 'Consultar', 'description' => 'Lê.',
            'module' => 'Chamados', 'operation_type' => 'consulta', 'parameters' => [],
        ];

        Http::fake(['*/plugins/sage/api/manifest' => Http::response(['funcoes' => [
            $valido,
            ['code' => '../../admin', 'name' => 'Invasora', 'description' => 'x', 'module' => 'Chamados', 'operation_type' => 'consulta'],
            ['code' => 'tipo.invalido', 'name' => 'T', 'description' => 'x', 'module' => 'Chamados', 'operation_type' => 'apagar'],
            ['code' => 'param.invalido', 'name' => 'P', 'description' => 'x', 'module' => 'Chamados', 'operation_type' => 'consulta',
                'parameters' => [['name' => 'x', 'type' => 'objeto', 'required' => true]]],
        ]])]);

        $resposta = $this->actingAs($this->gestor)->postJson('/api/v1/functions/import-manifest')->assertOk();

        $resposta->assertJsonPath('data.created', 1)->assertJsonPath('data.failed', 3);
        $this->assertDatabaseHas('function', ['code' => 'entidade.fechamento_automatico.ler']);
        $this->assertDatabaseMissing('function', ['code' => '../../admin']);
        $this->assertDatabaseMissing('function', ['code' => 'tipo.invalido']);
        $this->assertDatabaseMissing('function', ['code' => 'param.invalido']);
    }
}
