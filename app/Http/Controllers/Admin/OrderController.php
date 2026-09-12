<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\BdCourierService;
use App\Models\BdCourierCheck;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Models\Product;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::query()->latest();

        $status = $request->input('status', 'all');
        if ($status === 'pending_verification') {
            $query->where('payment_status', 'pending');
        } elseif (in_array($status, Order::STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($method = $request->input('method')) {
            if (in_array($method, Order::PAYMENT_METHODS, true)) {
                $query->where('payment_method', $method);
            }
        }

        if ($term = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($term) {
                $q->where('order_number', 'like', "%{$term}%")
                    ->orWhere('customer_name', 'like', "%{$term}%")
                    ->orWhere('customer_phone', 'like', "%{$term}%");
            });
        }

        $dateFilter = $request->input('date_filter', 'all');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $nowDhaka = now('Asia/Dhaka');

        if ($dateFilter === 'today') {
            $query->whereBetween('created_at', [
                $nowDhaka->copy()->startOfDay(),
                $nowDhaka->copy()->endOfDay(),
            ]);
        } elseif ($dateFilter === 'yesterday') {
            $query->whereBetween('created_at', [
                $nowDhaka->copy()->subDay()->startOfDay(),
                $nowDhaka->copy()->subDay()->endOfDay(),
            ]);
        } elseif ($dateFilter === '7days' || $dateFilter === '7_days') {
            $query->where('created_at', '>=', $nowDhaka->copy()->subDays(7)->startOfDay());
        } elseif ($dateFilter === '30days' || $dateFilter === '30_days') {
            $query->where('created_at', '>=', $nowDhaka->copy()->subDays(30)->startOfDay());
        } elseif ($dateFilter === 'this_month') {
            $query->where('created_at', '>=', $nowDhaka->copy()->startOfMonth());
        } elseif ($dateFilter === 'last_month') {
            $query->whereBetween('created_at', [
                $nowDhaka->copy()->subMonth()->startOfMonth(),
                $nowDhaka->copy()->subMonth()->endOfMonth(),
            ]);
        } elseif ($dateFilter === '1year' || $dateFilter === 'year' || $dateFilter === 'this_year') {
            $query->where('created_at', '>=', $nowDhaka->copy()->subYear()->startOfDay());
        } elseif ($dateFilter === 'custom' || (! empty($fromDate) || ! empty($toDate))) {
            if (! empty($fromDate) && ! empty($toDate)) {
                $query->whereBetween('created_at', [
                    \Illuminate\Support\Carbon::parse($fromDate, 'Asia/Dhaka')->startOfDay(),
                    \Illuminate\Support\Carbon::parse($toDate, 'Asia/Dhaka')->endOfDay(),
                ]);
            } elseif (! empty($fromDate)) {
                $query->where('created_at', '>=', \Illuminate\Support\Carbon::parse($fromDate, 'Asia/Dhaka')->startOfDay());
            } elseif (! empty($toDate)) {
                $query->where('created_at', '<=', \Illuminate\Support\Carbon::parse($toDate, 'Asia/Dhaka')->endOfDay());
            }
        }

        $orders = $query->withCount('items')->paginate(15)->withQueryString();

        $counts = [
            'all'                  => Order::count(),
            'pending_verification' => Order::where('payment_status', 'pending')->count(),
            'confirmed'            => Order::where('status', 'confirmed')->count(),
            'shipped'              => Order::where('status', 'shipped')->count(),
            'delivered'            => Order::where('status', 'delivered')->count(),
            'cancelled'            => Order::where('status', 'cancelled')->count(),
        ];

        $ordersData = $orders->through(fn($o) => [
            'id' => $o->id, 'order_number' => $o->order_number, 'customer_name' => $o->customer_name,
            'customer_phone' => $o->customer_phone, 'total' => $o->total, 'status' => $o->status,
            'payment_status' => $o->payment_status, 'payment_method' => $o->payment_method,
            'payment_method_label' => $o->paymentMethodLabel(),
            'internal_note' => $o->internal_note,
            'delivery_note' => $o->delivery_note,
            'items_count' => $o->items_count,
            'created_at_formatted' => $o->created_at?->setTimezone('Asia/Dhaka')->format('d M Y'),
            'created_at_time' => $o->created_at?->setTimezone('Asia/Dhaka')->format('h:i A'),
            'created_at_full' => $o->created_at?->setTimezone('Asia/Dhaka')->format('d M Y, h:i A'),
            'created_at_human' => $o->created_at?->setTimezone('Asia/Dhaka')->diffForHumans(),
            'created_at' => $o->created_at,
            'courier_provider' => $o->courier_provider,
            'courier_tracking_code' => $o->courier_tracking_code,
            'ip_address'  => $o->ip_address,
            'device_hash' => $o->device_hash,
        ]);

        // Reuse the latest saved checks so ratios render immediately. Missing
        // or stale values are refreshed in the background by the orders page.
        $courierRatios = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('bd_courier_checks')) {
            $phones = $orders->getCollection()
                ->pluck('customer_phone')
                ->filter()
                ->map(fn($phone) => BdCourierCheck::normalizePhone((string) $phone))
                ->filter()
                ->unique()
                ->values();

            if ($phones->isNotEmpty()) {
                $courierRatios = BdCourierCheck::query()
                    ->whereIn('phone', $phones)
                    ->get([
                        'phone', 'success_ratio', 'risk_level', 'total_parcels',
                        'successful_parcels', 'cancelled_parcels', 'last_checked_at',
                    ])
                    ->keyBy('phone')
                    ->map(fn($check) => [
                        'success_ratio' => (float) $check->success_ratio,
                        'risk_level' => $check->risk_level,
                        'total_parcels' => (int) $check->total_parcels,
                        'successful_parcels' => (int) $check->successful_parcels,
                        'cancelled_parcels' => (int) $check->cancelled_parcels,
                        'last_checked_at' => $check->last_checked_at?->toIso8601String(),
                    ])
                    ->all();
            }
        }


        $courierStats = [
            'total_shipped'   => Order::whereNotNull('courier_provider')->where('courier_provider', '!=', '')->count(),
            'total_delivered' => Order::whereNotNull('courier_provider')->where('courier_provider', '!=', '')->where('status', 'delivered')->count(),
            'total_returned'  => Order::whereNotNull('courier_provider')->where('courier_provider', '!=', '')->where('status', 'cancelled')->count(),
            'total_in_transit'=> Order::whereNotNull('courier_provider')->where('courier_provider', '!=', '')->whereIn('status', ['shipped', 'confirmed'])->count(),
            'providers' => [
                'steadfast' => [
                    'total'      => Order::where('courier_provider', 'steadfast')->count(),
                    'delivered'  => Order::where('courier_provider', 'steadfast')->where('status', 'delivered')->count(),
                    'cancelled'  => Order::where('courier_provider', 'steadfast')->where('status', 'cancelled')->count(),
                    'in_transit' => Order::where('courier_provider', 'steadfast')->whereIn('status', ['shipped', 'confirmed'])->count(),
                ],
                'pathao' => [
                    'total'      => Order::where('courier_provider', 'pathao')->count(),
                    'delivered'  => Order::where('courier_provider', 'pathao')->where('status', 'delivered')->count(),
                    'cancelled'  => Order::where('courier_provider', 'pathao')->where('status', 'cancelled')->count(),
                    'in_transit' => Order::where('courier_provider', 'pathao')->whereIn('status', ['shipped', 'confirmed'])->count(),
                ],
                'redx' => [
                    'total'      => Order::where('courier_provider', 'redx')->count(),
                    'delivered'  => Order::where('courier_provider', 'redx')->where('status', 'delivered')->count(),
                    'cancelled'  => Order::where('courier_provider', 'redx')->where('status', 'cancelled')->count(),
                    'in_transit' => Order::where('courier_provider', 'redx')->whereIn('status', ['shipped', 'confirmed'])->count(),
                ],
            ]
        ];

        return Inertia::render('Admin/Orders/Index', [
            'orders'             => $ordersData,
            'status'             => $status,
            'counts'             => $counts,
            'method'             => $request->input('method'),
            'dateFilter'         => $dateFilter,
            'fromDate'           => $fromDate,
            'toDate'             => $toDate,
            'q'                  => $term,
            'courierStats'       => $courierStats,
            'courierRatios'      => $courierRatios,
            'bdcourierConfigured'=> (string) setting('fog_enabled', '1') === '1'
                && (string) setting('fog_bdcourier_enabled', '0') === '1'
                && (bool) ! empty(setting('fog_bdcourier_api_key', ''))
                && \Illuminate\Support\Facades\Schema::hasTable('bd_courier_checks'),
        ]);
    }

    public function create()
    {
        $initialProducts = Product::where('is_published', true)
            ->with('images')
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'price' => (float) ($p->sale_price > 0 ? $p->sale_price : $p->regular_price),
                'stock_quantity' => $p->stock_quantity,
                'image' => $p->images->where('is_primary', true)->first()?->path,
            ]);

        return Inertia::render('Admin/Orders/Create', [
            'initialProducts' => $initialProducts,
            'shipInside'  => (float) setting('shipping_inside_dhaka', 60),
            'shipOutside' => (float) setting('shipping_outside_dhaka', 120),
            'taxPercent'  => (float) setting('tax_percent', 0),
        ]);
    }

    public function productSearch(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q', ''));

        $query = Product::where('is_published', true)->with('images');

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('sku', 'like', "%{$term}%");
            });
        }

        $products = $query->limit(25)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'price' => (float) ($p->sale_price > 0 ? $p->sale_price : $p->regular_price),
                'stock_quantity' => $p->stock_quantity,
                'image' => $p->images->where('is_primary', true)->first()?->path,
            ]);

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name'   => ['required', 'string', 'max:120'],
            'customer_phone'  => ['required', 'string', 'max:20'],
            'customer_email'  => ['nullable', 'email', 'max:120'],
            'shipping_address'=> ['required', 'string', 'max:255'],
            'city'            => ['required', 'string', 'max:80'],
            'postal_code'     => ['nullable', 'string', 'max:20'],
            'shipping_zone'   => ['required', Rule::in(['inside_dhaka', 'outside_dhaka'])],
            'internal_note'   => ['nullable', 'string', 'max:2000'],
            'delivery_note'   => ['nullable', 'string', 'max:2000'],
            
            'items'           => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty'     => ['required', 'integer', 'min:1'],
            'items.*.price'   => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_charge' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $order = DB::transaction(function () use ($validated, $request) {
                $subtotal = 0;
                $orderItems = [];

                foreach ($validated['items'] as $item) {
                    $product = Product::where('id', $item['product_id'])->lockForUpdate()->first();
                    
                    if (! $product || $item['qty'] > $product->stock_quantity) {
                        $name = $product ? "\"{$product->name}\"" : "Product ID {$item['product_id']}";
                        $avail = $product ? $product->stock_quantity : 0;
                        throw new \RuntimeException("{$name} only has {$avail} in stock.");
                    }

                    $customPrice = isset($item['price']) && is_numeric($item['price']) && (float) $item['price'] >= 0
                        ? (float) $item['price']
                        : null;

                    $price = $customPrice !== null
                        ? $customPrice
                        : (float) ($product->sale_price > 0 ? $product->sale_price : $product->regular_price);

                    $lineTotal = round($price * $item['qty'], 2);
                    $subtotal += $lineTotal;

                    $orderItems[] = [
                        'product' => $product,
                        'qty' => $item['qty'],
                        'price' => $price,
                        'lineTotal' => $lineTotal,
                    ];
                }

                $discount = (float) ($validated['discount_amount'] ?? 0);
                $taxable = max(0, $subtotal - $discount);
                
                $shipping = (float) ($validated['shipping_charge'] ?? ($validated['shipping_zone'] === 'inside_dhaka' 
                    ? setting('shipping_inside_dhaka', 60) 
                    : setting('shipping_outside_dhaka', 120)));
                    
                $tax = round($taxable * (float) setting('tax_percent', 0) / 100, 2);
                $total = $taxable + $shipping + $tax;

                // Generate Order Number
                $attempts = 0;
                do {
                    $number = 'ORD-' . now()->format('ymd') . '-' . strtoupper(Str::random(4));
                } while (++$attempts <= 15 && Order::where('order_number', $number)->exists());

                $order = Order::create([
                    'order_number'    => $number,
                    'user_id'         => auth()->id(), // Admin user ID, or could be null
                    'customer_name'   => $validated['customer_name'],
                    'customer_phone'  => $validated['customer_phone'],
                    'customer_email'  => $validated['customer_email'] ?? null,
                    'shipping_address'=> $validated['shipping_address'],
                    'city'            => $validated['city'],
                    'postal_code'     => $validated['postal_code'] ?? null,
                    'shipping_zone'   => $validated['shipping_zone'],
                    'internal_note'   => $validated['internal_note'] ?? null,
                    'delivery_note'   => $validated['delivery_note'] ?? null,
                    'ip_address'      => $request->ip(),
                    'user_agent'      => $request->userAgent(),
                    'device_hash'     => hash('sha256', $request->userAgent() ?? ''),
                    'subtotal'        => $subtotal,
                    'discount_amount' => $discount,
                    'shipping_charge' => $shipping,
                    'tax'             => $tax,
                    'total'           => $total,
                    'payment_method'  => 'cod',
                    'payment_status'  => 'pending',
                    'status'          => 'confirmed', // Manual orders are usually pre-confirmed
                ]);

                foreach ($orderItems as $itemData) {
                    $p = $itemData['product'];
                    OrderItem::create([
                        'order_id'     => $order->id,
                        'product_id'   => $p->id,
                        'product_name' => $p->name,
                        'image'        => $p->primaryImage()?->path,
                        'unit_price'   => $itemData['price'],
                        'quantity'     => $itemData['qty'],
                        'line_total'   => $itemData['lineTotal'],
                    ]);
                    $p->decrement('stock_quantity', $itemData['qty']);
                }

                return $order;
            });
            
            return redirect()->route('admin.orders.index')->with('status', "Order {$order->order_number} created successfully.");

        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        }
    }

    public function show(Order $order)
    {
        $order->load('items');
        $items = $order->items->map(fn($item) => [
            'id' => $item->id, 'product_name' => $item->product_name, 'variant_label' => $item->variant,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'line_total' => (float) $item->line_total > 0
                ? $item->line_total
                : round((float) $item->unit_price * (int) $item->quantity, 2),
        ]);

        // Load the latest saved result without making an API call on every page view.
        $bdcourier = null;
        if ((string) setting('fog_bdcourier_enabled', '0') === '1'
            && $order->customer_phone
            && \Illuminate\Support\Facades\Schema::hasTable('bd_courier_checks')) {
            $savedCheck = BdCourierCheck::query()
                ->where('phone', BdCourierCheck::normalizePhone($order->customer_phone))
                ->first();
            $bdcourier = $savedCheck?->response;

            // Older saved responses may contain labels such as "HIGH RISK".
            // Normalize them before sending the payload to the UI so every
            // client version receives the same canonical risk value.
            if (is_array($bdcourier)) {
                $riskLevel = BdCourierService::canonicalRiskLevel($bdcourier['_risk_level'] ?? null)
                    ?? BdCourierService::canonicalRiskLevel($bdcourier['_risk_label'] ?? null);

                if ($riskLevel !== null) {
                    $bdcourier['_risk_level'] = $riskLevel;
                    $bdcourier['_risk_label'] = ucfirst($riskLevel);
                }
            }
        }

        return Inertia::render('Admin/Orders/Show', [
            'order' => array_merge($order->toArray(), [
                'payment_method_label'    => $order->paymentMethodLabel(),
                'items'                   => $items,
                'created_at_formatted'    => $order->created_at->format('d M Y, h:i A'),
                'courier_provider'        => $order->courier_provider,
                'courier_tracking_code'   => $order->courier_tracking_code,
                'courier_consignment_id'  => $order->courier_consignment_id,
                'courier_status'          => $order->courier_status,
            ]),
            'bdcourier' => $bdcourier,
        ]);
    }

    /** Verify the manual payment (accept the order). */
    public function verify(Order $order)
    {
        $order->update([
            'payment_status' => 'verified',
            'status'         => $order->status === 'pending' ? 'confirmed' : $order->status,
        ]);

        // Send payment-verified confirmation to customer (silent on error)
        $order->load('items');
        send_order_email(
            $order,
            'emails.order_verified',
            'Payment confirmed — ' . $order->order_number . ' | ' . site_name()
        );

        return back()->with('status', "Payment verified for {$order->order_number}.");
    }

    /** Reject the manual payment. */
    public function reject(Order $order)
    {
        if ($order->shouldRestoreStockOnCancel()) {
            $order->restoreStock();
            $order->releaseCoupon();
        }

        $order->update([
            'payment_status' => 'rejected',
            'status'         => 'cancelled',
        ]);

        return back()->with('status', "Payment rejected for {$order->order_number}.");
    }

    /** Update fulfilment status, payment status, customer details and internal note from the detail page. */
    public function update(Request $request, Order $order)
    {
        $data = $request->validate([
            'status'           => ['sometimes', 'required', Rule::in(Order::STATUSES)],
            'payment_status'   => ['sometimes', 'required', Rule::in(Order::PAYMENT_STATUSES)],
            'internal_note'    => ['nullable', 'string', 'max:2000'],
            'customer_name'    => ['sometimes', 'required', 'string', 'max:120'],
            'customer_phone'   => ['sometimes', 'required', 'string', 'max:20'],
            'customer_email'   => ['nullable', 'email', 'max:120'],
            'shipping_address' => ['sometimes', 'required', 'string', 'max:255'],
            'city'             => ['sometimes', 'nullable', 'string', 'max:80'],
            'postal_code'      => ['nullable', 'string', 'max:20'],
            'shipping_zone'    => ['sometimes', 'nullable', Rule::in(['inside_dhaka', 'outside_dhaka'])],
            'delivery_note'    => ['nullable', 'string', 'max:2000'],
        ]);

        if (isset($data['status'])) {
            $wasNotCancelled = $order->status !== 'cancelled';
            $becomingCancelled = $data['status'] === 'cancelled' && $wasNotCancelled;

            if ($becomingCancelled) {
                $order->restoreStock();
                $order->releaseCoupon();
            }
        }

        $order->update($data);

        return back()->with('status', "Order {$order->order_number} updated.");
    }

    /** Permanently delete an order (restores stock/coupon if still active). */
    public function destroy(Order $order)
    {
        $number = $order->order_number;

        if ($order->shouldRestoreStockOnCancel()) {
            $order->restoreStock();
            $order->releaseCoupon();
        }

        $order->delete();

        return redirect()->route('admin.orders.index')->with('status', "Order {$number} deleted.");
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'ids'         => ['required', 'array', 'min:1'],
            'ids.*'       => ['integer', 'exists:orders,id'],
            'bulk_action' => ['required', 'in:verify,reject,delete'],
        ]);

        $orders = Order::whereIn('id', $data['ids'])->get();
        $count = 0;

        foreach ($orders as $order) {
            if ($data['bulk_action'] === 'verify') {
                if ($order->payment_status !== 'pending') {
                    continue;
                }
                $order->update([
                    'payment_status' => 'verified',
                    'status'         => $order->status === 'pending' ? 'confirmed' : $order->status,
                ]);
                // Send payment-verified confirmation to customer (silent on error)
                $order->load('items');
                send_order_email(
                    $order,
                    'emails.order_verified',
                    'Payment confirmed — ' . $order->order_number . ' | ' . site_name()
                );
                $count++;
            } elseif ($data['bulk_action'] === 'reject') {
                if ($order->payment_status !== 'pending') {
                    continue;
                }
                if ($order->shouldRestoreStockOnCancel()) {
                    $order->restoreStock();
                    $order->releaseCoupon();
                }
                $order->update([
                    'payment_status' => 'rejected',
                    'status'         => 'cancelled',
                ]);
                $count++;
            } else {
                if ($order->shouldRestoreStockOnCancel()) {
                    $order->restoreStock();
                    $order->releaseCoupon();
                }
                $order->delete();
                $count++;
            }
        }

        $label = match ($data['bulk_action']) {
            'verify' => 'verified',
            'reject' => 'rejected',
            default  => 'deleted',
        };

        return back()->with('status', "{$count} order(s) {$label}.");
    }

    /**
     * On-demand BD Courier fraud check for a phone number.
     * Called via fetch() from the Fraud & History tab.
     */
    public function checkFraud(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ]);

        $phone = BdCourierCheck::normalizePhone($request->input('phone'));
        if (! preg_match('/^01[3-9]\d{8}$/', $phone)) {
            return response()->json(['error' => 'Enter a valid Bangladesh phone number (01XXXXXXXXX).'], 422);
        }

        if ((string) setting('fog_enabled', '1') !== '1' || (string) setting('fog_bdcourier_enabled', '0') !== '1') {
            return response()->json(['error' => 'BD Courier is disabled in Fake Order Guard settings.'], 422);
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('bd_courier_checks')) {
            return response()->json(['error' => 'Courier history migration is pending. Run php artisan migrate first.'], 503);
        }

        $apiKey = (string) setting('fog_bdcourier_api_key', '');
        if (empty($apiKey)) {
            return response()->json(['error' => 'BD Courier API key is not configured in settings.'], 422);
        }

        $service = new BdCourierService($apiKey);
        $result  = $service->check($phone);

        if (! $result) {
            return response()->json(['error' => 'Could not reach BD Courier API. Check your API key or try again.'], 422);
        }

        $history = BdCourierCheck::record(
            $phone,
            $result,
            'admin_order',
            $request->integer('order_id') ?: null,
            $request->string('customer_name')->toString()
        );
        $result['_history'] = $history ? [
            'id' => $history->id,
            'check_count' => $history->check_count,
            'last_checked_at' => $history->last_checked_at?->toIso8601String(),
        ] : null;

        return response()->json($result);
    }

    /**
     * Return order history for the same phone number as the given order.
     * Called via fetch() from the Fraud & History tab.
     */
    public function customerHistory(Request $request): JsonResponse
    {
        $request->validate([
            'phone'    => ['required', 'string', 'max:20'],
            'order_id' => ['nullable', 'integer'],
        ]);

        $phone = trim($request->input('phone'));
        $normPhone = \App\Support\BdPhoneValidator::normalize($phone);

        $orders = Order::where(function ($q) use ($phone, $normPhone) {
            $q->where('customer_phone', $phone)
              ->orWhere('customer_phone', $normPhone)
              ->orWhere('customer_phone', '+88' . $normPhone)
              ->orWhere('customer_phone', '88' . $normPhone);
        })
            ->latest()
            ->limit(50)
            ->get(['id', 'order_number', 'total', 'status', 'payment_status', 'created_at']);

        $total     = $orders->count();
        $delivered = $orders->where('status', 'delivered')->count();
        $cancelled = $orders->where('status', 'cancelled')->count();
        $successRate = $total > 0 ? round(($delivered / $total) * 100, 1) : 0;

        return response()->json([
            'orders'       => $orders->map(fn($o) => [
                'id'           => $o->id,
                'order_number' => $o->order_number,
                'total'        => (float) $o->total,
                'status'       => $o->status,
                'payment_status' => $o->payment_status,
                'date'         => $o->created_at->format('d M Y'),
            ])->values(),
            'summary' => [
                'total'        => $total,
                'delivered'    => $delivered,
                'cancelled'    => $cancelled,
                'success_rate' => $successRate,
            ],
        ]);
    }
}
