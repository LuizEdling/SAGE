<?php

namespace App\Models;

use App\Enums\MessageAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['conversation_id', 'content', 'author', 'metadata'])]
class Message extends Model
{
    protected $table = 'message';

    protected function casts(): array
    {
        return [
            'author' => MessageAuthor::class,
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function knowledges(): BelongsToMany
    {
        return $this->belongsToMany(Knowledge::class, 'message_knowledge');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(Execution::class);
    }
}
