<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\CartService;
use App\Services\CouponService;
use App\Services\FakeOrderGuardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cart,
        private CouponService $coupons,
        private FakeOrderGuardService $fraudGuard,
    ) {}

    public function show()
    {
        $items = $this->cart->items();
        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('status', 'Your cart is empty.');
        }

        $subtotal = $this->cart->subtotal();
        $coupon   = $this->coupons->summary($subtotal);
        $zone     = old('shipping_zone', 'inside_dhaka');
        $isFreeShipping = $items->isNotEmpty() && $items->every(fn ($item) => (bool) ($item->product?->is_free_shipping ?? false));
        $totals   = $this->orderTotals($subtotal, $coupon['discount'], $zone, $isFreeShipping);

        return Inertia::render('Storefront/Checkout', [
            'items'          => $items,
            'subtotal'       => $subtotal,
            'isFreeShipping' => $isFreeShipping,
            'shipInside'     => $isFreeShipping ? 0.0 : (float) setting('shipping_inside_dhaka', 60),
            'shipOutside'    => $isFreeShipping ? 0.0 : (float) setting('shipping_outside_dhaka', 120),
            'taxPercent'     => (float) setting('tax_percent', 0),
            'user'           => Auth::user(),
            'couponCode'     => $coupon['code'],
            'discount'       => $coupon['discount'],
            'totals'         => $totals,
            'paySettings'    => [
                'cod_enabled' => (string) setting('pay_cod_enabled', '1') === '1',
                'bkash_enabled' => (string) setting('pay_bkash_enabled', '1') === '1',
                'nagad_enabled' => (string) setting('pay_nagad_enabled', '1') === '1',
                'rocket_enabled' => (string) setting('pay_rocket_enabled', '1') === '1',
                'bkash_number' => setting('bkash_number'),
                'nagad_number' => setting('nagad_number'),
                'rocket_number' => setting('rocket_number'),
            ],
        ]);
    }

    public function applyCoupon(Request $request)
    {
        $items = $this->cart->items();
        if ($items->isEmpty()) {
            return redirect()->route('cart.index');
        }

        $request->validate(['code' => ['required', 'string', 'max:40']]);

        $result = $this->coupons->apply($request->input('code'), $this->cart->subtotal());

        return $result['ok']
            ? redirect()->route('checkout.show')->with('status', $result['message'])
            : redirect()->route('checkout.show')->withInput()->withErrors(['coupon' => $result['message']]);
    }

    public function removeCoupon()
    {
        $this->coupons->remove();

        return redirect()->route('checkout.show')->with('status', 'Coupon removed.');
    }

    public function store(Request $request)
    {
        $items = $this->cart->items();
        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('status', 'Your cart is empty.');
        }

        $validated = $request->validate([
            'customer_name'   => ['required', 'string', 'max:120'],
            'customer_phone'  => ['required', 'string', 'max:20'],
            'customer_email'  => ['nullable', 'email', 'max:120'],
            'shipping_address'=> ['required', 'string', 'max:255'],
            'city'            => ['required', 'string', 'max:80'],
            'postal_code'     => ['nullable', 'string', 'max:20'],
            'shipping_zone'   => ['required', Rule::in(['inside_dhaka', 'outside_dhaka'])],
            'payment_method'  => ['required', Rule::in($this->availablePaymentMethods())],
            'payment_sender_number' => ['nullable', 'string', 'max:40', Rule::requiredIf(fn () => $request->payment_method !== 'cod')],
            'payment_txn_id'  => ['nullable', 'string', 'max:60', Rule::requiredIf(fn () => $request->payment_method !== 'cod')],
            'delivery_note'   => ['nullable', 'string', 'max:500'],
        ]);

        // ─── Always enforce: must be at least a plausible BD number format ────
        // Full smart validation is done by the Fake Order Guard below.
        // This basic check just ensures the phone field isn't completely empty garbage.
        $rawPhone = trim((string) ($validated['customer_phone'] ?? ''));
        if (! preg_match('/^[\+\d][\d\s\-]{7,19}$/', $rawPhone)) {
            return back()->withInput()->withErrors([
                'customer_phone' => 'একটি সঠিক ফোন নম্বর দিন। বাংলাদেশী নম্বর হতে হবে (যেমন: 01712345678)।',
            ]);
        }

        // ─── Fake Order Guard ─────────────────────────────────────────────────
        $blockError = $this->fraudGuard->check($request, $rawPhone);
        if ($blockError) {
            return back()->withInput()->withErrors([
                $blockError['field'] => $blockError['message'],
            ]);
        }
        // ─────────────────────────────────────────────────────────────────────

        // Collect IP and device fingerprint for order tracking (stored in DB for fraud investigation,
        // but never exposed to the frontend — see the safe order payload below).
        $clientIp   = $request->ip();
        $deviceHash = hash('sha256', $request->userAgent() ?? '');

        $subtotal = (float) $items->sum('line_total');
        $coupon   = $this->coupons->coupon();
        $discount = 0.0;

        if ($coupon) {
            $error = $coupon->validateForSubtotal($subtotal);
            if ($error) {
                $this->coupons->remove();

                return back()->withInput()->withErrors(['coupon' => $error]);
            }
            $discount = $coupon->calculateDiscount($subtotal);
        }

        $isFreeShipping = $items->isNotEmpty() && $items->every(fn ($item) => (bool) ($item->product?->is_free_shipping ?? false));
        $totals = $this->orderTotals($subtotal, $discount, $validated['shipping_zone'], $isFreeShipping);
        $shipping = $totals['shipping'];
        $tax      = $totals['tax'];
        $total    = $totals['total'];

        $isCod = $validated['payment_method'] === 'cod';

        try {
            $order = DB::transaction(function () use ($validated, $items, $subtotal, $discount, $shipping, $tax, $total, $isCod, $coupon, $clientIp, $deviceHash, $request) {
                // Generate the order number INSIDE the transaction so that the uniqueness
                // check and the insert are atomic — prevents TOCTOU duplicates under load.
                $order = Order::create([
                    'order_number'    => $this->generateOrderNumber(),
                    'user_id'         => Auth::id(),
                    'customer_name'   => $validated['customer_name'],
                    'customer_phone'  => $validated['customer_phone'],
                    'customer_email'  => $validated['customer_email'] ?? null,
                    'shipping_address'=> $validated['shipping_address'],
                    'city'            => $validated['city'],
                    'postal_code'     => $validated['postal_code'] ?? null,
                    'shipping_zone'   => $validated['shipping_zone'],
                    'coupon_id'       => $coupon?->id,
                    'coupon_code'     => $coupon?->code,
                    'ip_address'      => $clientIp,
                    'user_agent'      => $request->userAgent(),
                    'device_hash'     => $deviceHash,
                    'subtotal'        => $subtotal,
                    'discount_amount' => $discount,
                    'shipping_charge' => $shipping,
                    'tax'             => $tax,
                    'total'           => $total,
                    'payment_method'  => $validated['payment_method'],
                    'payment_sender_number' => $isCod ? null : ($validated['payment_sender_number'] ?? null),
                    'payment_txn_id'  => $isCod ? null : ($validated['payment_txn_id'] ?? null),
                    'payment_status'  => 'pending',
                    'status'          => 'pending',
                    'delivery_note'   => $validated['delivery_note'] ?? null,
                ]);

                foreach ($items as $item) {
                    $product = Product::where('id', $item->product_id)->lockForUpdate()->first();
                    if (! $product || $item->qty > $product->stock_quantity) {
                        $name = $product ? "\"{$product->name}\"" : "\"{$item->name}\"";
                        $avail = $product ? $product->stock_quantity : 0;
                        throw new \RuntimeException("{$name} only has {$avail} in stock. Please update your cart.");
                    }

                    OrderItem::create([
                        'order_id'     => $order->id,
                        'product_id'   => $item->product_id,
                        'product_name' => $item->name,
                        'image'        => $item->product->primaryImage()?->path,
                        'variant'      => $item->variant,
                        'unit_price'   => $item->price,
                        'quantity'     => $item->qty,
                        'line_total'   => $item->line_total,
                    ]);

                    $product->decrement('stock_quantity', $item->qty);
                }

                if ($coupon) {
                    $coupon->incrementUsage();
                }

                return $order;
            });
        } catch (\RuntimeException $e) {
            return back()
                ->withInput()
                ->withErrors(['cart' => $e->getMessage()]);
        }

        $this->cart->clear();
        $this->coupons->remove();

        // ─── Transactional emails (fire-and-forget; never interrupt redirect) ─
        $order->load('items');
        send_order_email(
            $order,
            'emails.order_placed',
            'Order received — ' . $order->order_number . ' | ' . site_name()
        );
        send_admin_order_alert($order);
        // ─────────────────────────────────────────────────────────────────────

        session(['recent_order' => $order->order_number]);

        return redirect()->route('order.confirmation', [
            'order' => $order->order_number,
            'token' => $order->confirmation_token,
        ]);
    }

    public function confirmation(Order $order, Request $request)
    {
        // Require the secret confirmation token to view this page.
        // Without it, anyone who guesses the order number can see customer PII.
        // (SEV-5: order number brute-force mitigation)
        $token = $request->query('token');
        if (! $token || ! hash_equals((string) $order->confirmation_token, (string) $token)) {
            return redirect()
                ->route('track')
                ->with('status', 'Use Track Order with your order number and phone to view order details.');
        }

        $order->load('items');

        // Build tracking payload for the client-side purchase event.
        // OrderConfirmation.jsx will call trackPurchase() with this data.
        $trackingOrder = [
            'order_number'    => $order->order_number,
            'total'           => (float) $order->total,
            'subtotal'        => (float) $order->subtotal,
            'tax'             => (float) $order->tax,
            'shipping_charge' => (float) $order->shipping_charge,
            'coupon_code'     => $order->coupon_code,
            'currency'        => setting('currency_code', 'BDT'),
            'items'           => $order->items->map(fn ($item) => [
                'product_id'   => (string) $item->product_id,
                'product_name' => $item->product_name,
                'unit_price'   => (float) $item->unit_price,
                'quantity'     => (int) $item->quantity,
                'variant'      => $item->variant,
            ])->values()->all(),
        ];

        // SECURITY: Never expose internal forensic/PII fields to the frontend.
        // ip_address, user_agent, device_hash, payment_txn_id, payment_sender_number
        // are only available to admin users via the admin panel.
        $safeOrder = $order->only([
            'order_number', 'customer_name', 'customer_phone', 'customer_email',
            'shipping_address', 'city', 'postal_code', 'shipping_zone',
            'subtotal', 'discount_amount', 'shipping_charge', 'tax', 'total',
            'coupon_code', 'payment_method', 'payment_status', 'status',
            'delivery_note', 'created_at',
        ]);
        $safeOrder['items'] = $order->items;

        return Inertia::render('Storefront/OrderConfirmation', [
            'order'         => $safeOrder,
            'trackingOrder' => $trackingOrder,
        ]);
    }

    private function orderTotals(float $subtotal, float $discount, string $shippingZone, bool $isFreeShipping = false): array
    {
        $taxable  = max(0, $subtotal - $discount);
        $shipping = $isFreeShipping ? 0.0 : ($shippingZone === 'inside_dhaka'
            ? (float) setting('shipping_inside_dhaka', 60)
            : (float) setting('shipping_outside_dhaka', 120));
        $tax      = round($taxable * (float) setting('tax_percent', 0) / 100, 2);
        $total    = $taxable + $shipping + $tax;

        return compact('shipping', 'tax', 'total');
    }

    /** COD is always available; mobile banking only when a merchant number is configured. */
    /** Enabled methods only; mobile banking also requires a merchant number. */
    private function availablePaymentMethods(): array
    {
        $methods = [];

        if ((string) setting('pay_cod_enabled', '1') === '1') {
            $methods[] = 'cod';
        }
        if ((string) setting('pay_bkash_enabled', '1') === '1' && setting('bkash_number')) {
            $methods[] = 'bkash';
        }
        if ((string) setting('pay_nagad_enabled', '1') === '1' && setting('nagad_number')) {
            $methods[] = 'nagad';
        }
        if ((string) setting('pay_rocket_enabled', '1') === '1' && setting('rocket_number')) {
            $methods[] = 'rocket';
        }

        return $methods;
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
