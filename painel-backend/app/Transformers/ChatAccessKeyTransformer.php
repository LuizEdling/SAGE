<?php

namespace App\Transformers;

use App\Models\ChatAccessKey;
use Illuminate\Database\Eloquent\Model;

class ChatAccessKeyTransformer extends Transformer
{
    public function __construct(
        private readonly UserTransformer $userTransformer = new UserTransformer,
    ) {}

    /** @param  ChatAccessKey  $model */
    public function transform(Model $model): array
    {
        return [
            'id' => $model->id,
            'active' => $model->active,
            // Conversas que chegaram por esta chave; chave usada não pode ser removida.
            'conversations_count' => $model->conversations_count ?? null,
            'created_by' => $model->relationLoaded('createdBy') && $model->createdBy
                ? $this->userTransformer->item($model->createdBy)
                : null,
            'created_at' => $model->created_at?->toIso8601String(),
        ];
    }

    // Único ponto em que o valor em claro existe. O banco guarda só o hash, então
    // depois desta resposta a chave não é recuperável por ninguém — nem pelo painel.
    public function itemWithPlainKey(ChatAccessKey $model, string $plainKey): array
    {
        return [...$this->transform($model), 'access_key' => $plainKey];
    }
}
