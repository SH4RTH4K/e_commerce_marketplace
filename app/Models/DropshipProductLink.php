<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DropshipProductLink extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'product_created_by_integration' => 'boolean',
            'field_sync_rules' => 'array',
            'pricing_snapshot' => 'array',
            'last_data_synced_at' => 'datetime',
            'last_price_synced_at' => 'datetime',
            'last_stock_synced_at' => 'datetime',
            'last_images_synced_at' => 'datetime',
        ];
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(DropshipSupplierProduct::class, 'supplier_product_row_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
