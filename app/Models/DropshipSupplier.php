<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class DropshipSupplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'key',
        'name',
        'driver_key',
        'base_url',
        'api_key',
        'secret_key',
        'pricing_rules',
        'sync_rules',
        'price_field_mapping',
        'catalog_cache_seconds',
        'is_active',
    ];

    protected $hidden = [
        'api_key',
        'secret_key',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'secret_key' => 'encrypted',
            'pricing_rules' => 'array',
            'sync_rules' => 'array',
            'price_field_mapping' => 'array',
            'catalog_cache_seconds' => 'integer',
            'is_active' => 'boolean',
            'capabilities' => 'array',
            'last_connection_tested_at' => 'datetime',
            'last_connection_success_at' => 'datetime',
        ];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(DropshipSupplierCategory::class, 'supplier_id');
    }

    public function categoryMappings(): HasMany
    {
        return $this->hasMany(DropshipCategoryMapping::class, 'supplier_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(DropshipSupplierProduct::class, 'supplier_id');
    }

    public function variants(): HasManyThrough
    {
        return $this->hasManyThrough(
            DropshipSupplierVariant::class,
            DropshipSupplierProduct::class,
            'supplier_id',
            'supplier_product_row_id',
            'id',
            'id',
        );
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(DropshipSyncRun::class, 'supplier_id');
    }
}
