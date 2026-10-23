<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sourcing_required' => 'boolean',
        'reserved_stock_quantity' => 'integer',
        'reserved_sourced_quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'unit_discount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    protected $appends = ['image_url', 'sourcing_outstanding'];

    public function getSourcingOutstandingAttribute(): int
    {
        return $this->sourcing_required ? max(0, (int) $this->quantity - (int) $this->reserved_stock_quantity) : 0;
    }

    public function getImageUrlAttribute(): string
    {
        return $this->imageUrl();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function imageUrl(): string
    {
        return image_url($this->image, $this->product_name);
    }

    public function effectiveLineTotal(): float
    {
        if ((float) $this->unit_discount > 0) {
            return round(max(0, (float) $this->unit_price - (float) $this->unit_discount) * (int) $this->quantity, 2);
        }

        return (float) $this->line_total > 0
            ? (float) $this->line_total
            : round((float) $this->unit_price * (int) $this->quantity, 2);
    }
}
