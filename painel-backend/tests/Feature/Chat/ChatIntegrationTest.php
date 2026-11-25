<?php

namespace Tests\Feature\Chat;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Endereço que o sistema legado usa para o chat, mostrado em Chaves de acesso. */
class ChatIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_gestor_ve_o_endereco_publico_da_api(): void
    {
        config(['services.sage_chat.public_api_url' => 'https://sage.exemplo/api/v1/']);
        $gestor = User::factory()->create(['is_master' => true, 'active' => true]);

        $this->actingAs($gestor, 'sanctum')->getJson('/api/v1/chat-integration')
            ->assertOk()
            ->assertJsonPath('data.api_url', 'https://sage.exemplo/api/v1')
            ->assertJsonPath('data.rate_limit_per_minute', 20);
    }

    public function test_usuario_comum_nao_ve(): void
    {
        $comum = User::factory()->create(['is_master' => false, 'active' => true]);

        $this->actingAs($comum, 'sanctum')->getJson('/api/v1/chat-integration')->assertForbidden();
    }
}
