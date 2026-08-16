<?php

namespace App\Transformers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserTransformer extends Transformer
{
    /** @param  User  $model */
    public function transform(Model $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model->name,
            'email' => $model->email,
            'is_master' => $model->is_master,
            'active' => $model->active,
            'created_at' => $model->created_at?->toIso8601String(),
        ];
    }
}
