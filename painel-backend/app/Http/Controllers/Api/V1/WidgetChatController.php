<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\ErpUserRequest;
use App\Http\Requests\Chat\StoreWidgetMessageRequest;
use App\Models\ChatAccessKey;
use App\Models\Conversation;
use App\Services\Chat\ChatService;
use App\Support\ApiResponse;
use App\Transformers\ConversationTransformer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API do chat embutido no sistema legado (§4.4: "API responsável por receber
 * as mensagens enviadas pelos usuários finais e retornar as respostas geradas
 * pelos agentes, mantendo o registro de cada conversa"). Autenticada pela
 * chave de chat (middleware `chat.key`), não por usuário do Painel.
 */
class WidgetChatController extends Controller
{
    public function __construct(
        private readonly ChatService $chat,
        private readonly ConversationTransformer $transformer,
    ) {}

    public function store(StoreWidgetMessageRequest $request): JsonResponse
    {
        $usuario = $request->validated('usuario_erp');

        $conversation = $this->chat->resolveConversation($this->chatAccessKey($request), $usuario);

        [$userMessage, $agentMessage] = $this->chat->handleUserMessage(
            $conversation,
            $request->string('content')->toString(),
            array_filter([
                'perfil_id' => $usuario['perfil_id'] ?? null,
                'perfil' => $usuario['perfil'] ?? null,
                'interface' => $usuario['interface'] ?? null,
                'entidade_id' => $usuario['entidade_id'] ?? null,
                'entidade' => $usuario['entidade'] ?? null,
                'recursivo' => $usuario['recursivo'] ?? null,
                'pagina' => $request->validated('pagina'),
                'pagina_titulo' => $request->validated('pagina_titulo'),
            ], fn ($valor) => $valor !== null),
        );

        $conversation->loadTranscript();

        return ApiResponse::data([
            'conversation' => $this->transformer->item($conversation),
            'message_id' => $userMessage->id,
            'agent_message_id' => $agentMessage->id,
        ], status: 201);
    }

    /** Conversa aberta do usuário, para o widget restaurar o histórico ao abrir. */
    public function current(ErpUserRequest $request): JsonResponse
    {
        $conversation = $this->chat->currentConversation(
            $this->chatAccessKey($request),
            $request->integer('erp_user_id'),
        );

        $conversation?->loadTranscript();

        return ApiResponse::data([
            'conversation' => $conversation ? $this->transformer->item($conversation) : null,
        ]);
    }

    /** Botão "Remover conversa": encerra (não apaga — o Painel mantém o histórico). */
    public function close(ErpUserRequest $request, Conversation $conversation): JsonResponse
    {
        // Conversa de outra chave ou de outro usuário responde como inexistente,
        // para não revelar que o id existe.
        if (
            $conversation->chat_access_key_id !== $this->chatAccessKey($request)->id
            || $conversation->erp_user_id !== $request->integer('erp_user_id')
        ) {
            throw new ModelNotFoundException;
        }

        $this->chat->close($conversation);

        return ApiResponse::data(['conversation' => $this->transformer->item($conversation->refresh())]);
    }

    private function chatAccessKey(Request $request): ChatAccessKey
    {
        return $request->attributes->get('chatAccessKey');
    }
}
