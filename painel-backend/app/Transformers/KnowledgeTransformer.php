<?php

namespace App\Transformers;

use App\Models\Knowledge;
use Illuminate\Database\Eloquent\Model;

class KnowledgeTransformer extends Transformer
{
    public function __construct(
        private readonly ComponentTransformer $componentTransformer = new ComponentTransformer,
        private readonly UserTransformer $userTransformer = new UserTransformer,
        private readonly KnowledgeImageTransformer $imageTransformer = new KnowledgeImageTransformer,
    ) {}

    /** @param  Knowledge  $model */
    public function transform(Model $model): array
    {
        return [
            'id' => $model->id,
            'type' => $model->type->value,
            'content' => $model->content,
            'status' => $model->status->value,
            'valid_until' => $model->valid_until?->toIso8601String(),
            'qdrant_point_id' => $model->qdrant_point_id,
            'component' => $model->relationLoaded('component') && $model->component
                ? $this->componentTransformer->item($model->component)
                : null,
            'approved_by' => $model->relationLoaded('approver') && $model->approver
                ? $this->userTransformer->item($model->approver)
                : null,
            'approved_at' => $model->approved_at?->toIso8601String(),
            'images' => $model->relationLoaded('images')
                ? $this->imageTransformer->collection($model->images)
                : [],
            // Funções indicadas para o problema deste conhecimento (§4.3).
            'function_ids' => $model->relationLoaded('functions')
                ? $model->functions->pluck('id')->all()
                : [],
            'created_at' => $model->created_at?->toIso8601String(),
        ];
    }
}
