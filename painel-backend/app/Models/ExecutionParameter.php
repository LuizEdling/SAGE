<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['execution_id', 'function_parameter_id', 'value'])]
class ExecutionParameter extends Model
{
    protected $table = 'execution_parameter';

    public function execution(): BelongsTo
    {
        return $this->belongsTo(Execution::class);
    }

    public function functionParameter(): BelongsTo
    {
        return $this->belongsTo(FunctionParameter::class);
    }
}
