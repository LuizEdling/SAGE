<?php

namespace App\Models;

use App\Enums\ParameterType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['function_id', 'name', 'type', 'required', 'description'])]
class FunctionParameter extends Model
{
    protected $table = 'function_parameter';

    protected function casts(): array
    {
        return [
            'type' => ParameterType::class,
            'required' => 'boolean',
        ];
    }

    public function function(): BelongsTo
    {
        return $this->belongsTo(AgentFunction::class, 'function_id');
    }

    public function executionParameters(): HasMany
    {
        return $this->hasMany(ExecutionParameter::class);
    }
}
