<?php

namespace App\Transformers;

use App\Models\ComponentImage;
use Illuminate\Database\Eloquent\Model;

class ComponentImageTransformer extends Transformer
{
    /** @param  ComponentImage  $model */
    public function transform(Model $model): array
    {
        return [
            'id' => $model->id,
            'url' => $model->url,
            'caption' => $model->caption,
        ];
    }
}
