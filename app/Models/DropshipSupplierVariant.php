<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DropshipSupplierVariant extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'cost_price' => 'decimal:2',
            'max_price' => 'decimal:2',
            'stock_qty' => 'decimal:3',
            'is_available' => 'boolean',
            'raw_payload' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(DropshipSupplierProduct::class, 'supplier_product_row_id');
    }

    public function variantLink(): HasOne
    {
        return $this->hasOne(DropshipVariantLink::class, 'supplier_variant_row_id');
    }
}
