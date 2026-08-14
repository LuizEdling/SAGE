<?php

namespace App\Transformers;

use App\Models\KnowledgeImage;
use Illuminate\Database\Eloquent\Model;

class KnowledgeImageTransformer extends Transformer
{
    /** @param  KnowledgeImage  $model */
    public function transform(Model $model): array
    {
        return [
            'id' => $model->id,
            'url' => $model->url,
            'caption' => $model->caption,
        ];
    }
}
