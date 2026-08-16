<?php

namespace App\Models;

use App\Enums\ConversationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'chat_access_key_id', 'erp_user_id', 'erp_user_login', 'erp_user_name',
    'status', 'opened_at', 'closed_at', 'last_message_at',
])]
class Conversation extends Model
{
    protected $table = 'conversation';

    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'chat_access_key_id' => 'integer',
            'erp_user_id' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    public function chatAccessKey(): BelongsTo
    {
        return $this->belongsTo(ChatAccessKey::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Carrega as mensagens em ordem de envio, com o conhecimento usado em cada
     * resposta e as funções executadas ou propostas a partir dela. A relação
     * não define ordem e o banco não a garante.
     */
    public function loadTranscript(): static
    {
        return $this->load([
            'messages' => fn ($query) => $query->orderBy('id'),
            'messages.knowledges',
            'messages.executions.function',
        ]);
    }
}
