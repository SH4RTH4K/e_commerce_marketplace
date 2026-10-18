<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustment extends Model
{
    protected $guarded = [];
    protected $casts = ['stock_before' => 'integer', 'stock_after' => 'integer', 'change' => 'integer', 'supplier_sync_paused' => 'boolean'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(DropshipSupplier::class, 'supplier_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
