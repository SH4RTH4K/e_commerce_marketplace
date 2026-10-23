<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;

class CustomerOrderPayload
{
    /** @return array<string, mixed> */
    public static function from(Order $order): array
    {
        return [
            ...$order->only([
                'id', 'order_number', 'customer_name', 'customer_phone', 'customer_email',
                'shipping_address', 'city', 'postal_code', 'shipping_zone',
                'subtotal', 'discount_amount', 'shipping_charge', 'tax', 'total', 'coupon_code',
                'payment_method', 'payment_status', 'payment_txn_id', 'status', 'delivery_note',
                'courier_provider', 'courier_tracking_code', 'courier_status', 'created_at',
            ]),
            'payment_method_label' => $order->paymentMethodLabel(),
            'awaiting_sourcing' => $order->awaiting_sourcing,
            'items' => $order->items->map(fn (OrderItem $item): array => [
                ...$item->only([
                    'id', 'product_id', 'product_name', 'image', 'image_url', 'variant',
                    'unit_price', 'unit_discount', 'quantity', 'line_total',
                    'sourcing_required', 'sourcing_outstanding',
                ]),
                'product' => $item->product ? [
                    ...$item->product->only(['name', 'slug', 'url_key']),
                    'images' => $item->product->images->map(fn ($image): array => $image->only(['path', 'is_primary']))->values()->all(),
                ] : null,
            ])->values()->all(),
        ];
    }
}
