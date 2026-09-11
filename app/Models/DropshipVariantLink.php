<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DropshipVariantLink extends Model
{
    protected $guarded = [];

    public function supplierVariant(): BelongsTo
    {
        return $this->belongsTo(DropshipSupplierVariant::class, 'supplier_variant_row_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
