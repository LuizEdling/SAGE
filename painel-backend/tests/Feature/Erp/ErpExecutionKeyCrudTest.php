<?php

namespace Tests\Feature\Erp;

use App\Models\ErpExecutionKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Gestão das chaves de execução na tela "Chaves de acesso" (só gestor).
 */
class ErpExecutionKeyCrudTest extends TestCase
{
    use RefreshDatabase;

    private function gestor(): User
    {
        return User::factory()->create(['is_master' => true, 'active' => true]);
    }

    public function test_criar_gera_a_chave_e_so_mostra_o_valor_completo_na_criacao(): void
    {
        $gestor = $this->gestor();

        $resposta = $this->actingAs($gestor, 'sanctum')
            ->postJson('/api/v1/erp-execution-keys', ['description' => 'Sistema legado', 'api_url' => 'http://legado.test/plugins/sage/api'])
            ->assertCreated()
            ->assertJsonPath('data.api_url', 'http://legado.test/plugins/sage/api');

        $completa = $resposta->json('data.access_key');
        $this->assertSame(64, strlen($completa));

        $lista = $this->actingAs($gestor, 'sanctum')->getJson('/api/v1/erp-execution-keys')->assertOk();
        $mascarada = $lista->json('data.data.0.access_key');
        $this->assertNotSame($completa, $mascarada);
        $this->assertStringEndsWith(substr($completa, -4), $mascarada);
    }

    public function test_ativar_desativar_e_remover(): void
    {
        $gestor = $this->gestor();
        $chave = ErpExecutionKey::create(['access_key' => 'k', 'api_url' => 'http://legado.test/plugins/sage/api', 'description' => 'Sistema legado', 'active' => true]);

        $this->actingAs($gestor, 'sanctum')->putJson("/api/v1/erp-execution-keys/{$chave->id}", ['active' => false])
            ->assertOk()->assertJsonPath('data.active', false);

        $this->actingAs($gestor, 'sanctum')->deleteJson("/api/v1/erp-execution-keys/{$chave->id}")->assertOk();
        $this->assertDatabaseMissing('erp_execution_key', ['id' => $chave->id]);
    }

    public function test_usuario_comum_nao_gerencia_chaves(): void
    {
        $comum = User::factory()->create(['is_master' => false, 'active' => true]);

        $this->actingAs($comum, 'sanctum')->getJson('/api/v1/erp-execution-keys')->assertForbidden();
        $this->actingAs($comum, 'sanctum')->postJson('/api/v1/erp-execution-keys', ['description' => 'x'])->assertForbidden();
    }

    public function test_criar_exige_url_valida_da_api_do_plugin(): void
    {
        $gestor = $this->gestor();

        $this->actingAs($gestor, 'sanctum')->postJson('/api/v1/erp-execution-keys', ['description' => 'Sem URL'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->actingAs($gestor, 'sanctum')->postJson('/api/v1/erp-execution-keys', ['description' => 'x', 'api_url' => 'ftp://legado/api'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_alterar_a_url_sem_trocar_a_chave(): void
    {
        $chave = ErpExecutionKey::create(['access_key' => 'k', 'api_url' => 'http://antigo.test/api', 'description' => 'Legado', 'active' => true]);

        $this->actingAs($this->gestor(), 'sanctum')
            ->putJson("/api/v1/erp-execution-keys/{$chave->id}", ['api_url' => 'https://novo.test/sage/api'])
            ->assertOk()->assertJsonPath('data.api_url', 'https://novo.test/sage/api')->assertJsonPath('data.active', true);
    }

    public function test_testar_conexao_le_o_manifesto_com_a_url_e_a_chave_dela(): void
    {
        $chave = ErpExecutionKey::create(['access_key' => 'k-teste', 'api_url' => 'http://legado.test/sage/api', 'description' => 'Legado', 'active' => false]);
        Http::fake(['http://legado.test/sage/api/manifest' => Http::response(['funcoes' => [['code' => 'a.b.c'], ['code' => 'a.b.d']]])]);

        $this->actingAs($this->gestor(), 'sanctum')
            ->postJson("/api/v1/erp-execution-keys/{$chave->id}/test-connection")
            ->assertOk()->assertJsonPath('data.ok', true)->assertJsonPath('data.functions', 2);

        Http::assertSent(fn ($request) => $request->hasHeader('X-Sage-Execution-Key', 'k-teste'));
    }

    public function test_testar_conexao_explica_chave_recusada(): void
    {
        $chave = ErpExecutionKey::create(['access_key' => 'k', 'api_url' => 'http://legado.test/sage/api', 'description' => 'Legado', 'active' => true]);
        Http::fake(['*' => Http::response(['status' => 'nao_autorizado'], 401)]);

        $this->actingAs($this->gestor(), 'sanctum')
            ->postJson("/api/v1/erp-execution-keys/{$chave->id}/test-connection")
            ->assertOk()->assertJsonPath('data.ok', false)->assertJsonPath('data.http_status', 401);
    }
}
