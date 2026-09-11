<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Coupon extends Model
{
    protected $guarded = [];

    protected $casts = [
        'value'            => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount'     => 'decimal:2',
        'starts_at'        => 'datetime',
        'expires_at'       => 'datetime',
        'is_active'        => 'boolean',
    ];

    public const TYPES = ['percentage', 'fixed'];

    protected static function booted(): void
    {
        static::saving(function (Coupon $coupon) {
            $coupon->code = Str::upper(trim($coupon->code));
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'percentage' => 'Percentage',
            'fixed'      => 'Fixed amount',
            default      => ucfirst($this->type),
        };
    }

    public function valueLabel(): string
    {
        return $this->type === 'percentage'
            ? rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.') . '%'
            : money($this->value);
    }

    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    /** @return string|null Error message, or null if valid. */
    public function validateForSubtotal(float $subtotal): ?string
    {
        if (! $this->isCurrentlyActive()) {
            return 'This coupon is not valid.';
        }

        if ($this->min_order_amount !== null && $subtotal < (float) $this->min_order_amount) {
            return 'Order subtotal must be at least ' . money($this->min_order_amount) . ' to use this coupon.';
        }

        return null;
    }

    public function calculateDiscount(float $subtotal): float
    {
        if ($this->validateForSubtotal($subtotal) !== null) {
            return 0.0;
        }

        $discount = $this->type === 'percentage'
            ? round($subtotal * (float) $this->value / 100, 2)
            : (float) $this->value;

        if ($this->max_discount !== null) {
            $discount = min($discount, (float) $this->max_discount);
        }

        return min($discount, $subtotal);
    }

    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }

    public function decrementUsage(): void
    {
        if ($this->used_count > 0) {
            $this->decrement('used_count');
        }
    }
}
