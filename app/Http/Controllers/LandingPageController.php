<?php

namespace App\Http\Controllers;

use App\Models\LandingPage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\FakeOrderGuardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LandingPageController extends Controller
{
    public function __construct(private FakeOrderGuardService $fraudGuard) {}

    public function show(Request $request, string $slug)
    {
        $page = LandingPage::with(['product.images'])->where('slug', $slug)->firstOrFail();

        if (! $page->is_active) {
            $user = Auth::user();
            $canPreview = $user && method_exists($user, 'isAdmin') && $user->isAdmin();
            if (! ($request->boolean('preview') && $canPreview)) {
                abort(404);
            }
        }

        session(['from_landing_page' => $page->slug]);

        $view = match ($page->design) {
            'campaign' => 'landing.campaign',
            'nuraya'   => 'landing.nuraya',
            default    => 'landing.chilora',
        };

        return view($view, [
            'page'    => $page,
            'product' => $page->product,
            'c'       => $page->content ?? [],
        ]);
    }

    public function order(Request $request, string $slug)
    {
        $page = LandingPage::with('product')->where('slug', $slug)->firstOrFail();
        $product = $page->product;

        if (! $product) {
            return $this->errorResponse($request, 'এই ক্যাম্পেইনের সাথে কোনো প্রোডাক্ট সংযুক্ত নেই।');
        }

        $validated = $request->validate([
            'customer_name'    => ['required', 'string', 'max:120'],
            'customer_phone'   => ['required', 'string', 'max:20'],
            'shipping_address' => ['required', 'string', 'max:255'],
            'shipping_zone'    => ['required', Rule::in(['inside_dhaka', 'outside_dhaka'])],
            'city'             => ['nullable', 'string', 'max:80'],
            'package_index'    => ['nullable', 'integer'],
            'qty'              => ['nullable', 'integer', 'min:1', 'max:99'],
            'order_notes'      => ['nullable', 'string', 'max:500'],
        ]);

        // ─── 0. Anti-Bot Honeypot Trap ───
        if ($request->filled('b_trap') || $request->filled('website_url')) {
            return $this->errorResponse($request, 'Invalid request detected.');
        }

        $rawName = trim((string) ($validated['customer_name'] ?? ''));
        $rawAddress = trim((string) ($validated['shipping_address'] ?? ''));

        // ─── 1. Name Quality Check ───
        if (mb_strlen($rawName) < 3 || preg_match('/^[\d\W_]+$/', $rawName) || preg_match('/^(test|fake|asdf|qwerty|12345|none|null|dummy)$/i', $rawName)) {
            return $this->errorResponse($request, 'অনুগ্রহ করে আপনার সঠিক সম্পূর্ণ নাম লিখুন।', 'customer_name');
        }

        // ─── 2. Address Quality Check ───
        if (mb_strlen($rawAddress) < 5 || preg_match('/^(test|fake|asdf|qwerty|12345|dhaka|bangladesh)$/i', $rawAddress)) {
            return $this->errorResponse($request, 'অনুগ্রহ করে আপনার সঠিক ও বিস্তারিত ডেলিভারি ঠিকানা লিখুন (বাড়ি/রোড/এলাকা)।', 'shipping_address');
        }

        $rawPhone = trim((string) ($validated['customer_phone'] ?? ''));
        if (! preg_match('/^[\+\d][\d\s\-]{7,19}$/', $rawPhone)) {
            return $this->errorResponse($request, 'একটি সঠিক বাংলাদেশী ফোন নম্বর দিন (যেমন: 01712345678)।', 'customer_phone');
        }

        $clientIp   = $request->ip();
        $userAgent  = $request->userAgent() ?? '';
        $deviceHash = hash('sha256', $userAgent);

        // ─── Fake Order Guard ───
        $blockError = $this->fraudGuard->check($request, $rawPhone);
        if ($blockError) {
            return $this->errorResponse($request, $blockError['message'], $blockError['field']);
        }

        $c = $page->content ?? [];

        // Determine Package, Quantity, Subtotal
        $packages = $c['package_options'] ?? [];
        $packageIndex = isset($validated['package_index']) && isset($packages[$validated['package_index']])
            ? (int) $validated['package_index']
            : null;

        $qty = 1;
        $unitPrice = (float) ($c['offer_price'] ?? ($product->sale_price ?: $product->regular_price));
        $lineTotal = $unitPrice;
        $orderItemName = $product->name;

        if ($packageIndex !== null && isset($packages[$packageIndex])) {
            $selectedPkg = $packages[$packageIndex];
            $orderItemName = $selectedPkg['name'] ?? $product->name;
            $lineTotal = (float) ($selectedPkg['price'] ?? $unitPrice);
            $qty = (int) ($selectedPkg['qty'] ?? 1);
            $unitPrice = $qty > 0 ? ($lineTotal / $qty) : $lineTotal;
        } else {
            $qty = max(1, (int) ($validated['qty'] ?? 1));
            $lineTotal = $unitPrice * $qty;
        }

        // Calculate Shipping Fee
        $shippingInside = (float) ($c['shipping_inside'] ?? setting('shipping_inside_dhaka', 70));
        $shippingOutside = (float) ($c['shipping_outside'] ?? setting('shipping_outside_dhaka', 130));
        $shippingFee = $validated['shipping_zone'] === 'inside_dhaka' ? $shippingInside : $shippingOutside;

        $subtotal = $lineTotal;
        $total = $subtotal + $shippingFee;

        try {
            $order = DB::transaction(function () use ($validated, $product, $orderItemName, $qty, $unitPrice, $lineTotal, $subtotal, $shippingFee, $total, $clientIp, $deviceHash, $request, $page) {
                $order = Order::create([
                    'order_number'          => $this->generateOrderNumber(),
                    'confirmation_token'    => Str::random(32),
                    'user_id'               => Auth::id(),
                    'customer_name'         => $validated['customer_name'],
                    'customer_phone'        => $validated['customer_phone'],
                    'shipping_address'      => $validated['shipping_address'],
                    'city'                  => $validated['city'] ?? ($validated['shipping_zone'] === 'inside_dhaka' ? 'Dhaka' : 'Outside Dhaka'),
                    'shipping_zone'         => $validated['shipping_zone'],
                    'ip_address'            => $clientIp,
                    'user_agent'            => $request->userAgent(),
                    'device_hash'           => $deviceHash,
                    'subtotal'              => $subtotal,
                    'discount_amount'       => 0,
                    'shipping_charge'       => $shippingFee,
                    'tax'                   => 0,
                    'total'                 => $total,
                    'payment_method'        => 'cod',
                    'payment_status'        => 'pending',
                    'status'                => 'pending',
                    'delivery_note'         => $validated['order_notes'] ?? null,
                    'internal_note'         => "Landing Page Campaign: {$page->title}",
                ]);

                // Create Order Item
                OrderItem::create([
                    'order_id'     => $order->id,
                    'product_id'   => $product->id,
                    'product_name' => $orderItemName,
                    'image'        => $product->primaryImage()?->path,
                    'variant'      => null,
                    'unit_price'   => $unitPrice,
                    'quantity'     => $qty,
                    'line_total'   => $lineTotal,
                ]);

                if ($product->stock_quantity > 0) {
                    $product->decrement('stock_quantity', min($qty, $product->stock_quantity));
                }

                return $order;
            });
        } catch (\Throwable $e) {
            return $this->errorResponse($request, 'অর্ডার প্রসেস করার সময় সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।');
        }

        // Transactional alerts
        try {
            $order->load('items');
            send_order_email($order, 'emails.order_placed', 'Order received — ' . $order->order_number . ' | ' . site_name());
            send_admin_order_alert($order);
        } catch (\Throwable) {}

        session(['recent_order' => $order->order_number]);

        $redirectUrl = route('order.confirmation', [
            'order' => $order->order_number,
            'token' => $order->confirmation_token,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok'           => true,
                'message'      => 'আপনার অর্ডারটি সফলভাবে গ্রহণ করা হয়েছে!',
                'redirect_url' => $redirectUrl,
            ]);
        }

        return redirect($redirectUrl)->with('status', 'আপনার অর্ডারটি সফলভাবে গ্রহণ করা হয়েছে!');
    }

    private function errorResponse(Request $request, string $message, string $field = 'order')
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok'      => false,
                'message' => $message,
                'errors'  => [$field => [$message]],
            ], 422);
        }

        return back()->withInput()->withErrors([$field => $message])->with('error', $message);
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
