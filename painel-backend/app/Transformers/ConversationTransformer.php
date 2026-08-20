<?php

namespace App\Transformers;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Model;

class ConversationTransformer extends Transformer
{
    public function __construct(
        private readonly MessageTransformer $messageTransformer = new MessageTransformer,
    ) {}

    /** @param  Conversation  $model */
    public function transform(Model $model): array
    {
        return [
            'id' => $model->id,
            'status' => $model->status->value,
            // Usuário do sistema legado dono da conversa (nulo nas conversas
            // anteriores à integração).
            'erp_user' => $model->erp_user_id !== null ? [
                'id' => $model->erp_user_id,
                'login' => $model->erp_user_login,
                'name' => $model->erp_user_name,
            ] : null,
            'opened_at' => $model->opened_at?->toIso8601String(),
            'closed_at' => $model->closed_at?->toIso8601String(),
            'last_message_at' => $model->last_message_at?->toIso8601String(),
            'message_count' => $model->messages_count ?? null,
            'messages' => $model->relationLoaded('messages')
                ? $this->messageTransformer->collection($model->messages)
                : [],
        ];
    }
}
