<?php

namespace App\Models;

use App\Services\InventoryStockService;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Order extends Model
{
    protected $appends = ['awaiting_sourcing'];

    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'order_number',
        'confirmation_token',
        'user_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'shipping_address',
        'city',
        'postal_code',
        'shipping_zone',
        'subtotal',
        'discount_amount',
        'shipping_charge',
        'tax',
        'total',
        'coupon_id',
        'coupon_code',
        'payment_method',
        'payment_sender_number',
        'payment_txn_id',
        'payment_status',
        'status',
        'internal_note',
        'delivery_note',
        'courier_provider',
        'courier_tracking_code',
        'courier_consignment_id',
        'courier_status',
        'ip_address',
        'user_agent',
        'device_hash',
    ];

    /**
     * Auto-generate a cryptographically secure confirmation token on creation.
     * This token prevents brute-force enumeration of the order confirmation page
     * (SEV-5 fix: order numbers only have ~4 random chars — easily guessable).
     */
    protected static function booted(): void
    {
        static::saving(function (Order $order): void {
            if ($order->exists && $order->isDirty('status') && $order->getRawOriginal('status') === 'cancelled'
                && $order->status !== 'cancelled' && $order->hasSourcingItems()) {
                throw ValidationException::withMessages(['status' => 'Cancelled sourcing orders cannot be reopened. Create a new order to allocate stock again.']);
            }
            if ($order->exists && $order->isDirty('status') && in_array($order->status, ['confirmed', 'processing', 'shipped', 'delivered'], true)) {
                $order->assertSourcingReady();
            }
        });
        static::creating(function (Order $order) {
            if (empty($order->confirmation_token)) {
                $order->confirmation_token = bin2hex(random_bytes(24)); // 48 hex chars
            }
        });
    }

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_charge' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'meta_purchase_capi_sent_at' => 'datetime',
    ];

    public const STATUSES = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];

    public const PAYMENT_STATUSES = ['pending', 'verified', 'rejected'];

    public const PAYMENT_METHODS = ['cod', 'bkash', 'nagad', 'rocket'];

    public const MOBILE_BANKING_METHODS = ['bkash', 'nagad', 'rocket'];

    public function scopeAwaitingSourcing(Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled')->whereHas('items', fn (Builder $items) => $items
            ->where('sourcing_required', true)->whereColumn('reserved_stock_quantity', '<', 'quantity'));
    }

    public function getAwaitingSourcingAttribute(): bool
    {
        if ($this->status === 'cancelled' || ! $this->exists) {
            return false;
        }

        if ($this->relationLoaded('items')) {
            return $this->items->contains(fn (OrderItem $item) => $item->sourcing_outstanding > 0);
        }

        return $this->items()->where('sourcing_required', true)->whereColumn('reserved_stock_quantity', '<', 'quantity')->exists();
    }

    public function assertSourcingReady(): void
    {
        if ($this->status === 'cancelled' && $this->hasSourcingItems()) {
            throw ValidationException::withMessages(['status' => 'Cancelled sourcing orders cannot be dispatched. Create a new order to allocate stock again.']);
        }
        if ($this->items()->where('sourcing_required', true)->whereColumn('reserved_stock_quantity', '<', 'quantity')->exists()) {
            throw ValidationException::withMessages(['status' => 'Receive and allocate all sourced items before confirming or dispatching this order.']);
        }
    }

    private function hasSourcingItems(): bool
    {
        return $this->items()->where(fn (Builder $items) => $items
            ->where('sourcing_required', true)->orWhere('reserved_sourced_quantity', '>', 0))->exists();
    }

    public function scopeAwaitingPaymentVerification(Builder $query): Builder
    {
        return $query->where('payment_status', 'pending')
            ->whereIn('payment_method', self::MOBILE_BANKING_METHODS)
            ->whereIn('status', ['pending', 'confirmed', 'processing', 'shipped']);
    }

    /** Segment totals share the same search/date/method filters as the list. */
    public static function segmentCounts(?Builder $query = null): array
    {
        $counts = array_fill_keys(['all', ...self::STATUSES, 'pending_verification', 'awaiting_sourcing'], 0);
        $counts['awaiting_sourcing'] = (clone ($query ?? self::query()))->reorder()->awaitingSourcing()->count();
        $groups = (clone ($query ?? self::query()))->reorder()
            ->selectRaw('status, payment_method, payment_status, COUNT(*) AS aggregate')
            ->groupBy('status', 'payment_method', 'payment_status')->get();
        foreach ($groups as $group) {
            $count = (int) $group->aggregate;
            $counts['all'] += $count;
            if (in_array($group->status, self::STATUSES, true)) {
                $counts[$group->status] += $count;
            }
            if ($group->payment_status === 'pending'
                && in_array($group->payment_method, self::MOBILE_BANKING_METHODS, true)
                && in_array($group->status, ['pending', 'confirmed', 'processing', 'shipped'], true)) {
                $counts['pending_verification'] += $count;
            }
        }

        return $counts;
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    public function paymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            'cod' => 'Cash on Delivery',
            'bkash' => 'bKash',
            'nagad' => 'Nagad',
            'rocket' => 'Rocket',
            default => ucfirst($this->payment_method),
        };
    }

    public function isMobileBanking(): bool
    {
        return in_array($this->payment_method, self::MOBILE_BANKING_METHODS, true);
    }

    /** Tailwind badge classes for the fulfilment status. */
    public function statusBadge(): string
    {
        return match ($this->status) {
            'pending' => 'bg-gray-100 text-gray-600',
            'confirmed' => 'bg-blue-100 text-blue-700',
            'processing' => 'bg-indigo-100 text-indigo-700',
            'shipped' => 'bg-blue-100 text-blue-700',
            'delivered' => 'bg-green-100 text-green-700',
            'cancelled' => 'bg-red-100 text-red-700',
            default => 'bg-gray-100 text-gray-600',
        };
    }

    /** Tailwind badge classes for the payment status. */
    public function paymentBadge(): string
    {
        return match ($this->payment_status) {
            'pending' => 'bg-amber-100 text-amber-700',
            'verified' => 'bg-green-100 text-green-700',
            'rejected' => 'bg-red-100 text-red-700',
            default => 'bg-gray-100 text-gray-600',
        };
    }

    /** Return reserved stock to products (e.g. when an order is cancelled). */
    public function restoreStock(): void
    {
        app(InventoryStockService::class)->restoreOrderStock(auth()->user(), $this);
    }

    /** Release a coupon use when an order is cancelled/rejected. */
    public function releaseCoupon(): void
    {
        if ($this->coupon_id) {
            $this->coupon?->decrementUsage();
        }
    }

    public function shouldRestoreStockOnCancel(): bool
    {
        return ! in_array($this->status, ['cancelled'], true);
    }
}
