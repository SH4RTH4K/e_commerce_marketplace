<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbandonedCheckout;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AbandonedCheckoutController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->string('filter')->toString();
        $checkouts = AbandonedCheckout::when($filter === 'recovered', fn ($query) => $query->where('is_recovered', true))
            ->when($filter === 'active', fn ($query) => $query->where('is_recovered', false))
            ->latest('last_active_at')
            ->paginate(15)
            ->withQueryString();
            
        $stats = [
            'total_abandoned'        => AbandonedCheckout::where('is_recovered', false)->count(),
            'total_abandoned_amount' => AbandonedCheckout::where('is_recovered', false)->sum('cart_total'),
            'total_recovered'        => AbandonedCheckout::where('is_recovered', true)->count(),
            'total_recovered_amount' => AbandonedCheckout::where('is_recovered', true)->sum('cart_total'),
        ];

        return Inertia::render('Admin/AbandonedCheckouts/Index', [
            'checkouts' => $checkouts,
            'stats'     => $stats,
            'filter'    => in_array($filter, ['active', 'recovered'], true) ? $filter : 'all',
        ]);
    }

    public function recover($id)
    {
        AbandonedCheckout::findOrFail($id)->update(['is_recovered' => true]);
        return back()->with('status', 'Checkout marked as recovered.');
    }

    public function createOrder($id)
    {
        $abandoned = AbandonedCheckout::findOrFail($id);

        $customerName    = trim((string) ($abandoned->customer_name ?: 'Customer'));
        $customerPhone   = trim((string) ($abandoned->customer_phone ?: '01000000000'));
        $customerEmail   = $abandoned->customer_email ?: null;
        $shippingAddress = trim((string) ($abandoned->shipping_address ?: 'Address not specified'));

        $isDhaka = Str::contains(mb_strtolower($shippingAddress), 'dhaka') || Str::contains(mb_strtolower($shippingAddress), 'ঢাকা');
        $shippingZone = $isDhaka ? 'inside_dhaka' : 'outside_dhaka';
        $city = $isDhaka ? 'Dhaka' : 'Other';

        $cartItems = is_array($abandoned->cart_items) ? $abandoned->cart_items : [];

        $newOrder = DB::transaction(function () use ($abandoned, $customerName, $customerPhone, $customerEmail, $shippingAddress, $shippingZone, $city, $cartItems) {
            $subtotal = 0;
            $itemsToCreate = [];

            foreach ($cartItems as $item) {
                $productId = $item['product_id'] ?? $item['id'] ?? null;
                $qty = max(1, (int) ($item['qty'] ?? $item['quantity'] ?? 1));

                $product = null;
                if ($productId) {
                    $product = Product::with('images')->find($productId);
                }
                if (! $product && ! empty($item['name'])) {
                    $product = Product::with('images')->where('name', $item['name'])->first();
                }

                if ($product) {
                    $unitPrice = isset($item['price']) && (float) $item['price'] > 0
                        ? (float) $item['price']
                        : (float) ($product->sale_price > 0 ? $product->sale_price : $product->regular_price);
                    $lineTotal = $unitPrice * $qty;
                    $subtotal += $lineTotal;

                    $itemsToCreate[] = [
                        'product_id'   => $product->id,
                        'product_name' => $product->name,
                        'image'        => $product->primaryImage()?->path,
                        'unit_price'   => $unitPrice,
                        'quantity'     => $qty,
                        'line_total'   => $lineTotal,
                        'product'      => $product,
                    ];
                } else {
                    $name = $item['name'] ?? 'Product Item';
                    $unitPrice = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
                    $lineTotal = $unitPrice * $qty;
                    $subtotal += $lineTotal;

                    $itemsToCreate[] = [
                        'product_id'   => null,
                        'product_name' => $name,
                        'image'        => $item['image'] ?? null,
                        'unit_price'   => $unitPrice,
                        'quantity'     => $qty,
                        'line_total'   => $lineTotal,
                        'product'      => null,
                    ];
                }
            }

            if ($subtotal <= 0 && $abandoned->cart_total > 0) {
                $subtotal = (float) $abandoned->cart_total;
            }

            $shipFee = (float) ($shippingZone === 'inside_dhaka'
                ? setting('shipping_inside_dhaka', 60)
                : setting('shipping_outside_dhaka', 120));

            $taxPercent = (float) setting('tax_percent', 0);
            $tax = round($subtotal * $taxPercent / 100, 2);
            $total = $subtotal + $shipFee + $tax;

            // Unique order number
            $attempts = 0;
            do {
                $number = 'ORD-' . now()->format('ymd') . '-' . strtoupper(Str::random(4));
            } while (++$attempts <= 15 && Order::where('order_number', $number)->exists());

            $note = 'Recovered from Abandoned Cart #' . $abandoned->id;
            if (! empty($abandoned->admin_note)) {
                $note .= ' | Note: ' . $abandoned->admin_note;
            }

            $order = Order::create([
                'order_number'    => $number,
                'user_id'         => auth()->id(),
                'customer_name'   => $customerName,
                'customer_phone'  => $customerPhone,
                'customer_email'  => $customerEmail,
                'shipping_address'=> $shippingAddress,
                'city'            => $city,
                'postal_code'     => null,
                'shipping_zone'   => $shippingZone,
                'internal_note'   => $note,
                'ip_address'      => request()->ip(),
                'user_agent'      => request()->userAgent(),
                'device_hash'     => hash('sha256', request()->userAgent() ?? ''),
                'subtotal'        => $subtotal,
                'discount_amount' => 0,
                'shipping_charge' => $shipFee,
                'tax'             => $tax,
                'total'           => $total,
                'payment_method'  => 'cod',
                'payment_status'  => 'pending',
                'status'          => 'confirmed',
            ]);

            foreach ($itemsToCreate as $itemData) {
                OrderItem::create([
                    'order_id'     => $order->id,
                    'product_id'   => $itemData['product_id'],
                    'product_name' => $itemData['product_name'],
                    'image'        => $itemData['image'],
                    'unit_price'   => $itemData['unit_price'],
                    'quantity'     => $itemData['quantity'],
                    'line_total'   => $itemData['line_total'],
                ]);

                if ($itemData['product']) {
                    $itemData['product']->decrement('stock_quantity', $itemData['quantity']);
                }
            }

            $abandoned->update(['is_recovered' => true]);

            return $order;
        });

        return redirect()->route('admin.orders.show', $newOrder->id)
            ->with('status', 'Order #' . $newOrder->order_number . ' successfully created and recovered from abandoned cart!');
    }

    public function destroy($id)
    {
        AbandonedCheckout::findOrFail($id)->delete();
        return back()->with('status', 'Checkout record deleted.');
    }

    public function updateNote(Request $request, $id)
    {
        $request->validate(['admin_note' => 'nullable|string|max:1000']);
        AbandonedCheckout::findOrFail($id)->update(['admin_note' => $request->admin_note]);
        return back()->with('status', 'Note updated successfully.');
    }
}
