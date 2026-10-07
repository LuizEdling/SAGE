<?php

namespace App\Transformers;

use App\Enums\ExecutionStatus;
use App\Models\Execution;
use Illuminate\Database\Eloquent\Model;

/**
 * Execução de uma função do sistema legado: consulta feita pelo Agente ou
 * alteração proposta e decidida pelo usuário. Base da auditoria do painel de
 * supervisão e dos cartões de autorização no widget.
 */
class ExecutionTransformer extends Transformer
{
    /** @param  Execution  $model */
    public function transform(Model $model): array
    {
        $message = $model->relationLoaded('message') ? $model->message : null;
        $conversation = $message?->relationLoaded('conversation') ? $message->conversation : null;

        return [
            'id' => $model->id,
            'status' => $model->status->value,
            'function' => [
                'id' => $model->function_id,
                'code' => $model->function?->code,
                'name' => $model->function?->name,
                'operation_type' => $model->function?->operation_type->value,
            ],
            'parameters' => $model->result['parametros'] ?? [],
            'result' => $model->result,
            'error_code' => $model->error_code,
            // Consulta autorizada por política não tem mensagem de confirmação.
            'confirmed_by_user' => $model->confirmation_message_id !== null,
            'expires_at' => $model->status === ExecutionStatus::Pendente
                ? $model->created_at->addMinutes(config('services.legacy_system.execution_ttl'))->toIso8601String()
                : null,
            'message_id' => $model->message_id,
            'conversation' => $conversation ? [
                'id' => $conversation->id,
                'erp_user_login' => $conversation->erp_user_login,
                'erp_user_name' => $conversation->erp_user_name,
            ] : null,
            'created_at' => $model->created_at?->toIso8601String(),
            'authorized_at' => $model->authorized_at?->toIso8601String(),
            'executed_at' => $model->executed_at?->toIso8601String(),
        ];
    }
}
