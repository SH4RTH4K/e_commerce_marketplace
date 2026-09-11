<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationUpdateSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'run_migrations' => 'boolean',
            'clear_cache' => 'boolean',
            'health_check' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }
}
