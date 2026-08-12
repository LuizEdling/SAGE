<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['component_id', 'url', 'caption'])]
class ComponentImage extends Model
{
    protected $table = 'component_image';

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }
}
