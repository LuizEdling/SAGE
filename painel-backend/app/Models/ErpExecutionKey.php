<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['access_key', 'api_url', 'description', 'active'])]
class ErpExecutionKey extends Model
{
    protected $table = 'erp_execution_key';

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
