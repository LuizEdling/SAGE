<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'route'])]
class Component extends Model
{
    protected $table = 'component';

    public function knowledges(): HasMany
    {
        return $this->hasMany(Knowledge::class);
    }

    public function functions(): HasMany
    {
        return $this->hasMany(AgentFunction::class, 'component_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ComponentImage::class);
    }
}
