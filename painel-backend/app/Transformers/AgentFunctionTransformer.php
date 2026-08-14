<?php

namespace App\Transformers;

use App\Models\AgentFunction;
use Illuminate\Database\Eloquent\Model;

class AgentFunctionTransformer extends Transformer
{
    public function __construct(
        private readonly ComponentTransformer $componentTransformer = new ComponentTransformer,
        private readonly UserTransformer $userTransformer = new UserTransformer,
        private readonly FunctionParameterTransformer $parameterTransformer = new FunctionParameterTransformer,
    ) {}

    /** @param  AgentFunction  $model */
    public function transform(Model $model): array
    {
        return [
            'id' => $model->id,
            // Identificador da implementação no sistema legado (nulo nas
            // funções cadastradas à mão, que ainda não têm implementação).
            'code' => $model->code,
            'name' => $model->name,
            'description' => $model->description,
            'operation_type' => $model->operation_type->value,
            'status' => $model->status->value,
            'active' => $model->active,
            'component' => $model->relationLoaded('component') && $model->component
                ? $this->componentTransformer->item($model->component)
                : null,
            'approved_by' => $model->relationLoaded('approver') && $model->approver
                ? $this->userTransformer->item($model->approver)
                : null,
            'approved_at' => $model->approved_at?->toIso8601String(),
            'parameters' => $model->relationLoaded('parameters')
                ? $this->parameterTransformer->collection($model->parameters)
                : [],
            'created_at' => $model->created_at?->toIso8601String(),
        ];
    }
}
