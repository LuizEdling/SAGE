<?php

namespace Tests\Feature\Chat;

use App\Enums\ConversationStatus;
use App\Models\ChatAccessKey;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\AccessKeyGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * API do chat embutido no sistema legado: autenticação pela chave de chat e
 * uma conversa por usuário do sistema legado.
 */
class WidgetChatTest extends TestCase
{
    use RefreshDatabase;

    private string $chave;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chave = 'chave-de-chat-de-teste';
        ChatAccessKey::create([
            'key_hash' => app(AccessKeyGenerator::class)->hash($this->chave),
            'active' => true,
        ]);

        Http::fake([
            '*/diagnose' => Http::response([
                'resumo' => 'r', 'causa_provavel' => 'c', 'modulo_afetado' => 'Chamados',
                'confianca' => 0.7, 'fontes' => [], 'resposta_texto' => 'Resposta do agente.',
                'iteracoes' => 1, 'consultas' => [], 'aguardando_esclarecimento' => false,
            ]),
        ]);
    }

    private function enviar(int $erpUserId, string $content = 'Meu chamado fechou sozinho', ?string $chave = null)
    {
        return $this->withToken($chave ?? $this->chave)->postJson('/api/v1/chat/messages', [
            'content' => $content,
            'usuario_erp' => [
                'id' => $erpUserId, 'login' => "usuario{$erpUserId}", 'nome' => "Usuário {$erpUserId}",
                'perfil' => 'Self-Service', 'interface' => 'helpdesk', 'entidade_id' => 1, 'recursivo' => false,
            ],
            'pagina' => '/Helpdesk',
        ]);
    }

    public function test_sem_chave_ou_com_chave_invalida_responde_401(): void
    {
        $this->postJson('/api/v1/chat/messages', ['content' => 'oi'])
            ->assertStatus(401)->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->enviar(7, chave: 'chave-errada')->assertStatus(401);
    }

    public function test_chave_desativada_responde_401(): void
    {
        ChatAccessKey::query()->update(['active' => false]);

        $this->enviar(7)->assertStatus(401);
    }

    public function test_token_de_usuario_do_painel_nao_vale_no_chat_do_widget(): void
    {
        $token = User::factory()->create()->createToken('painel')->plainTextToken;

        $this->enviar(7, chave: $token)->assertStatus(401);
    }

    public function test_mensagem_valida_cria_conversa_do_usuario_e_grava_contexto(): void
    {
        $resposta = $this->enviar(7)->assertStatus(201);

        $resposta->assertJsonPath('data.conversation.erp_user.login', 'usuario7')
            ->assertJsonPath('data.conversation.status', 'aberta')
            ->assertJsonCount(2, 'data.conversation.messages');

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/diagnose')
            && $request['question'] === 'Meu chamado fechou sozinho');

        $mensagemUsuario = Conversation::first()->messages()->oldest('id')->first();
        $this->assertSame('helpdesk', json_decode($mensagemUsuario->metadata, true)['contexto_erp']['interface']);
    }

    public function test_historico_enviado_ao_agente_nao_inclui_a_mensagem_atual(): void
    {
        $this->enviar(7, 'Meu chamado fechou sozinho')->assertStatus(201);
        $this->enviar(7, 'estava solucionado')->assertStatus(201);

        // A segunda chamada ao Agente leva só o que veio antes da pergunta atual;
        // senão, numa resposta a esclarecimento, a busca perderia a pergunta original.
        $chamadas = Http::recorded(fn ($request) => str_contains($request->url(), '/diagnose'));
        $segunda = $chamadas->last()[0];

        $this->assertSame('estava solucionado', $segunda['question']);
        $this->assertSame(
            ['Meu chamado fechou sozinho', 'Resposta do agente.'],
            array_column($segunda['historico'], 'conteudo')
        );
    }

    public function test_pergunta_de_parametro_fica_na_mensagem_e_a_resposta_volta_como_esclarecimento(): void
    {
        $pergunta = [
            'resumo' => 'r', 'causa_provavel' => 'c', 'modulo_afetado' => 'Ativos', 'confianca' => 0.7, 'fontes' => [],
            'resposta_texto' => 'Qual é o nome da licença?', 'iteracoes' => 1, 'consultas' => [],
            'aguardando_esclarecimento' => true,
            'parametro_solicitado' => ['codigo' => 'licenca.vagas.ler', 'tipo' => 'consulta', 'parametros' => [['nome' => 'licenca_nome', 'motivo' => 'ausente']]],
        ];
        // O fake do setUp responderia primeiro: aqui a sequência é própria deste teste.
        Http::swap(new Factory);
        $resposta = [...$pergunta, 'aguardando_esclarecimento' => false, 'parametro_solicitado' => null, 'resposta_texto' => 'Pronto.'];
        Http::fake(['*/diagnose' => Http::sequence()->push($pergunta)->push($resposta)]);

        $primeira = $this->enviar(7, 'A opção de adicionar na licença não aparece.')->assertStatus(201);

        $metadata = json_decode(Message::findOrFail($primeira->json('data.agent_message_id'))->metadata, true);
        $this->assertSame('licenca.vagas.ler', $metadata['parametro_solicitado']['codigo']);
        $this->assertTrue($metadata['aguardando_esclarecimento']);

        $this->enviar(7, 'Office')->assertStatus(201);

        // O Agente recebe a resposta marcada como resposta a esclarecimento, com a pergunta original no histórico.
        $segunda = Http::recorded(fn ($request) => str_contains($request->url(), '/diagnose'))->last()[0];
        $this->assertTrue($segunda['respondendo_esclarecimento']);
        $this->assertSame('Office', $segunda['question']);
        $this->assertSame('A opção de adicionar na licença não aparece.', $segunda['historico'][0]['conteudo']);
    }

    public function test_cada_usuario_tem_a_propria_conversa(): void
    {
        $maria = $this->enviar(7)->json('data.conversation.id');
        $joao = $this->enviar(8)->json('data.conversation.id');
        $mariaDeNovo = $this->enviar(7, 'Outra pergunta')->json('data.conversation.id');

        $this->assertNotSame($maria, $joao);
        $this->assertSame($maria, $mariaDeNovo);
    }

    public function test_conversa_atual_e_encerramento(): void
    {
        $id = $this->enviar(7)->json('data.conversation.id');

        $this->withToken($this->chave)->getJson('/api/v1/chat/conversations/current?erp_user_id=7')
            ->assertOk()->assertJsonPath('data.conversation.id', $id);

        // Outro usuário não encerra a conversa da Maria — e nem descobre que ela existe.
        $this->withToken($this->chave)->postJson("/api/v1/chat/conversations/{$id}/close", ['erp_user_id' => 8])
            ->assertStatus(404);

        $this->withToken($this->chave)->postJson("/api/v1/chat/conversations/{$id}/close", ['erp_user_id' => 7])
            ->assertOk()->assertJsonPath('data.conversation.status', 'encerrada');

        // Encerrar não apaga: a conversa continua no banco para o Painel.
        $this->assertSame(ConversationStatus::Encerrada, Conversation::find($id)->status);

        $this->withToken($this->chave)->getJson('/api/v1/chat/conversations/current?erp_user_id=7')
            ->assertOk()->assertJsonPath('data.conversation', null);

        $this->assertNotSame($id, $this->enviar(7)->json('data.conversation.id'));
    }

    public function test_conversa_inativa_e_encerrada_e_a_proxima_pergunta_abre_outra(): void
    {
        $id = $this->enviar(7)->json('data.conversation.id');

        $this->travel(config('services.sage_chat.idle_minutes') + 1)->minutes();

        $novo = $this->enviar(7)->json('data.conversation.id');

        $this->assertNotSame($id, $novo);
        $this->assertSame(ConversationStatus::Encerrada, Conversation::find($id)->status);
    }
}
