<?php

namespace App\Transformers;

use App\Models\ErpExecutionKey;
use Illuminate\Database\Eloquent\Model;

class ErpExecutionKeyTransformer extends Transformer
{
    /** @param  ErpExecutionKey  $model */
    public function transform(Model $model): array
    {
        return [
            'id' => $model->id,
            'access_key' => $this->mask($model->access_key),
            'api_url' => $model->api_url,
            'description' => $model->description,
            'active' => $model->active,
            'created_at' => $model->created_at?->toIso8601String(),
        ];
    }

    public function itemWithFullKey(ErpExecutionKey $model): array
    {
        return [...$this->transform($model), 'access_key' => $model->access_key];
    }

    private function mask(string $key): string
    {
        return str_repeat('•', 8).substr($key, -4);
    }
}
