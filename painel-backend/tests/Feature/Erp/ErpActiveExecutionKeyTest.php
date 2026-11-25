<?php

namespace Tests\Feature\Erp;

use App\Models\ErpExecutionKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chave de execução em uso, mostrada na simulação (só gestor).
 */
class ErpActiveExecutionKeyTest extends TestCase
{
    use RefreshDatabase;

    private function gestor(): User
    {
        return User::factory()->create(['is_master' => true, 'active' => true]);
    }

    public function test_gestor_ve_a_chave_ativa_mais_recente(): void
    {
        ErpExecutionKey::create(['access_key' => 'antiga', 'api_url' => 'http://legado.test/plugins/sage/api', 'description' => 'Antiga', 'active' => true]);
        $recente = ErpExecutionKey::create(['access_key' => 'nova', 'api_url' => 'http://legado.test/plugins/sage/api', 'description' => 'Nova', 'active' => true]);
        ErpExecutionKey::create(['access_key' => 'inativa', 'api_url' => 'http://legado.test/plugins/sage/api', 'description' => 'Inativa', 'active' => false]);

        $this->actingAs($this->gestor(), 'sanctum')->getJson('/api/v1/erp/execution-key')
            ->assertOk()
            ->assertJsonPath('data.execution_key.id', $recente->id);
    }

    public function test_sem_chave_ativa_devolve_nulo(): void
    {
        $this->actingAs($this->gestor(), 'sanctum')->getJson('/api/v1/erp/execution-key')
            ->assertOk()
            ->assertJsonPath('data.execution_key', null);
    }

    public function test_usuario_comum_nao_acessa(): void
    {
        $comum = User::factory()->create(['is_master' => false, 'active' => true]);

        $this->actingAs($comum, 'sanctum')->getJson('/api/v1/erp/execution-key')->assertForbidden();
    }
}
