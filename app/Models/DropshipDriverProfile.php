<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DropshipDriverProfile extends Model
{
    protected $fillable = [
        'key', 'name', 'auth_key_header', 'auth_secret_header', 'products_path',
        'categories_path', 'collection_path', 'pagination_param', 'default_currency',
        'field_mapping', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'field_mapping' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
