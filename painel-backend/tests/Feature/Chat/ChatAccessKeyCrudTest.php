<?php

namespace Tests\Feature\Chat;

use App\Models\ChatAccessKey;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Remoção de chaves de chat: o histórico de conversas não pode se perder. */
class ChatAccessKeyCrudTest extends TestCase
{
    use RefreshDatabase;

    private function gestor(): User
    {
        return User::factory()->create(['is_master' => true, 'active' => true]);
    }

    public function test_chave_sem_conversas_e_removida(): void
    {
        $chave = ChatAccessKey::create(['key_hash' => str_repeat('a', 64), 'active' => true]);

        $this->actingAs($this->gestor(), 'sanctum')->deleteJson("/api/v1/chat-access-keys/{$chave->id}")->assertOk();

        $this->assertDatabaseMissing('chat_access_key', ['id' => $chave->id]);
    }

    public function test_chave_com_conversas_nao_e_removida_e_explica_o_motivo(): void
    {
        $chave = ChatAccessKey::create(['key_hash' => str_repeat('b', 64), 'active' => true]);
        Conversation::create(['chat_access_key_id' => $chave->id, 'status' => 'aberta', 'opened_at' => now()]);

        $this->actingAs($this->gestor(), 'sanctum')->deleteJson("/api/v1/chat-access-keys/{$chave->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'RESOURCE_IN_USE');

        $this->assertDatabaseHas('chat_access_key', ['id' => $chave->id]);
    }

    public function test_listagem_mostra_quantas_conversas_cada_chave_tem(): void
    {
        $chave = ChatAccessKey::create(['key_hash' => str_repeat('c', 64), 'active' => true]);
        Conversation::create(['chat_access_key_id' => $chave->id, 'status' => 'aberta', 'opened_at' => now()]);

        $this->actingAs($this->gestor(), 'sanctum')->getJson('/api/v1/chat-access-keys')
            ->assertOk()->assertJsonPath('data.data.0.conversations_count', 1);
    }
}
