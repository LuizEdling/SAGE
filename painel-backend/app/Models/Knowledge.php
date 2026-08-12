<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\KnowledgeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// status/approved_by/approved_at nunca vêm do request do usuário — ficam de
// fora do Fillable de propósito (StoreKnowledgeRequest/UpdateKnowledgeRequest
// também nunca os incluem no validated(), então liberar qdrant_point_id aqui
// é seguro: só o RagIngestionDispatcher/IngestKnowledgeJob escrevem nele).
#[Fillable(['type', 'content', 'component_id', 'valid_until', 'qdrant_point_id'])]
class Knowledge extends Model
{
    protected $table = 'knowledge';

    protected function casts(): array
    {
        return [
            'type' => KnowledgeType::class,
            'status' => ApprovalStatus::class,
            'approved_at' => 'datetime',
            'valid_until' => 'datetime',
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

    public function images(): HasMany
    {
        return $this->hasMany(KnowledgeImage::class);
    }

    /**
     * Funções indicadas para o problema descrito neste conhecimento. Os ids
     * vão para o payload dos trechos no Qdrant (§4.3).
     */
    public function functions(): BelongsToMany
    {
        return $this->belongsToMany(AgentFunction::class, 'knowledge_function', 'knowledge_id', 'function_id');
    }

    public function messages(): BelongsToMany
    {
        return $this->belongsToMany(Message::class, 'message_knowledge');
    }
}
