<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['knowledge_id', 'url', 'caption'])]
class KnowledgeImage extends Model
{
    protected $table = 'knowledge_image';

    public function knowledge(): BelongsTo
    {
        return $this->belongsTo(Knowledge::class);
    }
}
