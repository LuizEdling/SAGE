<?php

namespace App\Transformers;

use App\Models\FunctionParameter;
use Illuminate\Database\Eloquent\Model;

class FunctionParameterTransformer extends Transformer
{
    /** @param  FunctionParameter  $model */
    public function transform(Model $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model->name,
            'type' => $model->type->value,
            'required' => $model->required,
            'description' => $model->description,
        ];
    }
}
