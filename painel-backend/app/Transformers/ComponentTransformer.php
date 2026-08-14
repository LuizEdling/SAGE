<?php

namespace App\Transformers;

use App\Models\Component;
use Illuminate\Database\Eloquent\Model;

class ComponentTransformer extends Transformer
{
    public function __construct(
        private readonly ComponentImageTransformer $imageTransformer = new ComponentImageTransformer,
    ) {}

    /** @param  Component  $model */
    public function transform(Model $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model->name,
            'description' => $model->description,
            'route' => $model->route,
            'images' => $model->relationLoaded('images')
                ? $this->imageTransformer->collection($model->images)
                : [],
            'created_at' => $model->created_at?->toIso8601String(),
        ];
    }
}
