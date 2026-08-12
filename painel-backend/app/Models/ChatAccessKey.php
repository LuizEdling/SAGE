<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key_hash', 'created_by_user_id', 'active'])]
class ChatAccessKey extends Model
{
    protected $table = 'chat_access_key';

    // O hash nunca deve vazar em serialização acidental — o Transformer já não
    // o expõe, isto é a segunda barreira.
    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
