<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DropshipSupplierProduct extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'max_price' => 'decimal:2',
            'stock_qty' => 'decimal:3',
            'is_available' => 'boolean',
            'raw_payload' => 'array',
            'fetched_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(DropshipSupplier::class, 'supplier_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(DropshipSupplierVariant::class, 'supplier_product_row_id');
    }

    public function productLink(): HasOne
    {
        return $this->hasOne(DropshipProductLink::class, 'supplier_product_row_id');
    }
}
