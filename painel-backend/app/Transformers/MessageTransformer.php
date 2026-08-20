<?php

namespace App\Transformers;

use App\Models\Message;
use Illuminate\Database\Eloquent\Model;

class MessageTransformer extends Transformer
{
    /** @param  Message  $model */
    public function transform(Model $model): array
    {
        return [
            'id' => $model->id,
            'content' => $model->content,
            'author' => $model->author->value,
            'knowledge_ids' => $model->relationLoaded('knowledges')
                ? $model->knowledges->pluck('id')->all()
                : [],
            'metadata' => $model->metadata ? json_decode($model->metadata, true) : null,
            // Funções executadas (consulta) ou propostas (alteração) a partir
            // desta mensagem — viram os cartões de autorização no widget.
            'executions' => $model->relationLoaded('executions')
                ? (new ExecutionTransformer)->collection($model->executions)
                : [],
            'created_at' => $model->created_at?->toIso8601String(),
        ];
    }
}
