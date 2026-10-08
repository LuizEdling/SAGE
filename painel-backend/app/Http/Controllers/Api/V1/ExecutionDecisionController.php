<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Execution\DecideExecutionRequest;
use App\Models\Execution;
use App\Services\Chat\ChatService;
use App\Support\ApiResponse;
use App\Transformers\ConversationTransformer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

/**
 * "Autorizar execução de função" (caso de uso do usuário do sistema legado):
 * o usuário autoriza ou nega, no widget, a alteração proposta pelo Agente.
 * Autenticado pela chave de chat, como o resto da API do widget.
 */
class ExecutionDecisionController extends Controller
{
    public function __construct(
        private readonly ChatService $chat,
        private readonly ConversationTransformer $transformer,
    ) {}

    public function autorizar(DecideExecutionRequest $request, Execution $execution): JsonResponse
    {
        return $this->decide($request, $execution, true);
    }

    public function negar(DecideExecutionRequest $request, Execution $execution): JsonResponse
    {
        return $this->decide($request, $execution, false);
    }

    private function decide(DecideExecutionRequest $request, Execution $execution, bool $autorizar): JsonResponse
    {
        $usuario = $request->validated('usuario_erp');
        $conversation = $execution->message->conversation;

        // Só o dono da conversa decide, e só pela chave de chat dela. Outra
        // conversa responde como inexistente, para não revelar que o id existe.
        if (
            $conversation->chat_access_key_id !== $request->attributes->get('chatAccessKey')->id
            || $conversation->erp_user_id !== (int) $usuario['id']
        ) {
            throw new ModelNotFoundException;
        }

        $this->chat->decideExecution($execution, $autorizar, $usuario, $request->validated('token_autorizacao'));

        return ApiResponse::data(['conversation' => $this->transformer->item($conversation->refresh()->loadTranscript())]);
    }
}
