<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\OperationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// `function` é palavra reservada em PHP, então o Model se chama AgentFunction
// mas aponta para a tabela `function` do schema do TCC (Diagrama de Classe.rtf).
// status/approved_by/approved_at não vêm do request — ver ApprovalService.
#[Fillable(['code', 'name', 'description', 'operation_type', 'component_id'])]
class AgentFunction extends Model
{
    protected $table = 'function';

    protected function casts(): array
    {
        return [
            'operation_type' => OperationType::class,
            'status' => ApprovalStatus::class,
            'approved_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(FunctionParameter::class, 'function_id');
    }

    /** Conhecimentos que indicam esta função (ver tabela knowledge_function). */
    public function knowledges(): BelongsToMany
    {
        return $this->belongsToMany(Knowledge::class, 'knowledge_function', 'function_id', 'knowledge_id');
    }
}
