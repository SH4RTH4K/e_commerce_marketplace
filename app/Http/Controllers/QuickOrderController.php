<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\CouponService;
use App\Services\FakeOrderGuardService;
use App\Support\BdPhoneValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class QuickOrderController extends Controller
{
    public function __construct(
        private CouponService $coupons,
        private FakeOrderGuardService $fraudGuard,
    ) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id'     => ['required', 'integer', 'exists:products,id'],
            'qty'            => ['required', 'integer', 'min:1', 'max:100'],
            'variant'        => ['nullable', 'string', 'max:120'],
            'customer_name'  => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'shipping_address' => ['required', 'string', 'max:255'],
            'shipping_zone'    => ['required', Rule::in(['inside_dhaka', 'outside_dhaka'])],
            'coupon_code'      => ['nullable', 'string', 'max:40'],
            'delivery_note'    => ['nullable', 'string', 'max:500'],
        ]);

        // ─── Basic phone format check ─────────────────────────────────────────
        $rawPhone = trim((string) ($validated['customer_phone'] ?? ''));
        if (! preg_match('/^[\+\d][\d\s\-]{7,19}$/', $rawPhone)) {
            return response()->json(['errors' => [
                'customer_phone' => 'একটি সঠিক ফোন নম্বর দিন। বাংলাদেশী নম্বর হতে হবে (যেমন: 01712345678)।',
            ]], 422);
        }

        // ─── Fake Order Guard ─────────────────────────────────────────────────
        $blockError = $this->fraudGuard->check($request, $rawPhone);
        if ($blockError) {
            return response()->json(['errors' => [
                $blockError['field'] => $blockError['message'],
            ]], 422);
        }
        // ─────────────────────────────────────────────────────────────────────

        $clientIp   = $request->ip();
        $deviceHash = hash('sha256', $request->userAgent() ?? '');

        // ─── Coupon ───────────────────────────────────────────────────────────
        $discount = 0.0;
        $coupon   = null;
        if (! empty($validated['coupon_code'])) {
            $result = $this->coupons->apply($validated['coupon_code'], 0); // subtotal TBD below
            if (! $result['ok']) {
                return response()->json(['errors' => ['coupon_code' => $result['message']]], 422);
            }
            $coupon = $this->coupons->coupon();
        }

        // ─── Product & Pricing ────────────────────────────────────────────────
        $product = Product::findOrFail($validated['product_id']);
        $variant = isset($validated['variant']) && trim((string) $validated['variant']) !== ''
            ? trim((string) $validated['variant'])
            : null;
        $price   = $product->unitPriceForVariant($variant);
        $qty     = (int) $validated['qty'];
        $subtotal = $price * $qty;

        if ($coupon) {
            $couponError = $coupon->validateForSubtotal($subtotal);
            if ($couponError) {
                $this->coupons->remove();
                return response()->json(['errors' => ['coupon_code' => $couponError]], 422);
            }
            $discount = $coupon->calculateDiscount($subtotal);
        }

        $shippingZone = $validated['shipping_zone'];
        $isFreeShipping = (bool) ($product->is_free_shipping ?? false);
        $shipping = $isFreeShipping ? 0.0 : ($shippingZone === 'inside_dhaka'
            ? (float) setting('shipping_inside_dhaka', 60)
            : (float) setting('shipping_outside_dhaka', 120));
        $taxPercent = (float) setting('tax_percent', 0);
        $taxable    = max(0, $subtotal - $discount);
        $tax        = round($taxable * $taxPercent / 100, 2);
        $total      = $taxable + $shipping + $tax;

        // ─── Stock check ──────────────────────────────────────────────────────
        if ($product->stock_quantity < $qty) {
            return response()->json(['errors' => [
                'form' => "দুঃখিত, এই পণ্যের স্টকে মাত্র {$product->stock_quantity}টি আছে।",
            ]], 422);
        }

        // ─── Create Order ─────────────────────────────────────────────────────
        try {
            $order = DB::transaction(function () use (
                $validated, $product, $price, $qty, $subtotal, $discount,
                $shipping, $tax, $total, $coupon, $clientIp, $deviceHash, $request, $shippingZone
            ) {
                $product->refresh()->lockForUpdate();
                if ($product->stock_quantity < $qty) {
                    throw new \RuntimeException("দুঃখিত, এই পণ্যের স্টকে মাত্র {$product->stock_quantity}টি আছে।");
                }

                $order = Order::create([
                    'order_number'     => $this->generateOrderNumber(),
                    'user_id'          => Auth::id(),
                    'customer_name'    => $validated['customer_name'],
                    'customer_phone'   => $validated['customer_phone'],
                    'customer_email'   => Auth::user()?->email,
                    'shipping_address' => $validated['shipping_address'],
                    'city'             => $shippingZone === 'inside_dhaka' ? 'ঢাকা' : 'ঢাকার বাইরে',
                    'shipping_zone'    => $shippingZone,
                    'coupon_id'        => $coupon?->id,
                    'coupon_code'      => $coupon?->code,
                    'ip_address'       => $clientIp,
                    'user_agent'       => $request->userAgent(),
                    'device_hash'      => $deviceHash,
                    'subtotal'         => $subtotal,
                    'discount_amount'  => $discount,
                    'shipping_charge'  => $shipping,
                    'tax'              => $tax,
                    'total'            => $total,
                    'payment_method'   => 'cod',
                    'payment_status'   => 'pending',
                    'status'           => 'pending',
                    'internal_note'    => $validated['variant'] ? 'Variant: ' . $validated['variant'] : null,
                    'delivery_note'    => $validated['delivery_note'] ?? null,
                ]);

                OrderItem::create([
                    'order_id'     => $order->id,
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'image'        => $product->primaryImage()?->path,
                    'variant'      => $validated['variant'] ?? null,
                    'unit_price'   => $price,
                    'quantity'     => $qty,
                    'line_total'   => $price * $qty,
                ]);

                $product->decrement('stock_quantity', $qty);

                if ($coupon) {
                    $coupon->incrementUsage();
                }

                return $order;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['errors' => ['form' => $e->getMessage()]], 422);
        }

        // Emails
        $order->load('items');
        send_order_email($order, 'emails.order_placed', 'Order received — ' . $order->order_number . ' | ' . site_name());
        send_admin_order_alert($order);

        if ($coupon) {
            $this->coupons->remove();
        }

        return response()->json([
            'success'      => true,
            'order_number' => $order->order_number,
            'total'        => $total,
            'redirect'     => route('order.confirmation', [
                'order' => $order->order_number,
                'token' => $order->confirmation_token,
            ]),
        ]);
    }

    private function generateOrderNumber(): string
    {
        $attempts = 0;
        do {
            if (++$attempts > 15) {
                return 'ORD-' . now()->format('ymd') . '-' . strtoupper(Str::random(8));
            }
            $number = 'ORD-' . now()->format('ymd') . '-' . strtoupper(Str::random(4));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
