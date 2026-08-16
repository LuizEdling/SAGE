<?php

namespace App\Models;

use App\Enums\ExecutionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'message_id', 'function_id', 'confirmation_message_id', 'status',
    'result', 'error_code', 'authorized_at', 'executed_at',
])]
class Execution extends Model
{
    protected $table = 'execution';

    protected function casts(): array
    {
        return [
            'status' => ExecutionStatus::class,
            'result' => 'array',
            'authorized_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function confirmationMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'confirmation_message_id');
    }

    public function function(): BelongsTo
    {
        return $this->belongsTo(AgentFunction::class, 'function_id');
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(ExecutionParameter::class);
    }
}
