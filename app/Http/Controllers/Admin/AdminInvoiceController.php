<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\Request;

class AdminInvoiceController extends Controller
{
    public function show(Order $order)
    {
        $order->load(['items.product']);
        $store = $this->getStoreDetails();

        return view('admin.orders.invoice', [
            'orders' => collect([$order]),
            'store'  => $store,
            'isBulk' => false,
        ]);
    }

    public function bulk(Request $request)
    {
        $ids = $request->query('ids', '');
        if (is_string($ids)) {
            $idArray = array_filter(array_map('intval', explode(',', $ids)));
        } else {
            $idArray = (array) $ids;
        }

        if (empty($idArray)) {
            return redirect()->route('admin.orders.index')->with('error', 'No orders selected for printing.');
        }

        // Cap at 200 IDs to prevent admin-triggered DB/memory exhaustion
        if (count($idArray) > 200) {
            return redirect()->route('admin.orders.index')->with('error', 'You can print a maximum of 200 invoices at once. Please select fewer orders.');
        }

        $orders = Order::with(['items.product'])
            ->whereIn('id', $idArray)
            ->latest()
            ->get();

        if ($orders->isEmpty()) {
            return redirect()->route('admin.orders.index')->with('error', 'Selected orders not found.');
        }

        $store = $this->getStoreDetails();

        return view('admin.orders.invoice', [
            'orders' => $orders,
            'store'  => $store,
            'isBulk' => true,
        ]);
    }

    public function customerInvoice(string $orderNumber, Request $request)
    {
        $order = Order::with(['items.product'])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $token = (string) $request->query('token', '');
        $user = $request->user();

        $authorized = false;

        // 1. Staff / Admin is authorized
        if ($user && $user->isAdmin()) {
            $authorized = true;
        }

        // 2. Logged-in customer who placed the order is authorized
        if ($user && $order->user_id && (int) $order->user_id === (int) $user->id) {
            $authorized = true;
        }

        // 3. Valid confirmation token matches the order
        if ($token !== '' && hash_equals((string) $order->confirmation_token, $token)) {
            $authorized = true;
        }

        if (! $authorized) {
            abort(403, 'A valid order token or account login is required to view this invoice.');
        }

        $store = $this->getStoreDetails();

        return view('admin.orders.invoice', [
            'orders' => collect([$order]),
            'store'  => $store,
            'isBulk' => false,
            'isCustomer' => true,
        ]);
    }

    private function getStoreDetails(): array
    {
        return [
            'name'       => setting('site_name', config('app.name', 'Shopzy')),
            'tagline'    => setting('tagline', 'Quality Online Shopping'),
            'logo'       => logo_url(),
            'phone'      => setting('contact_phone', '+880 1700-000000'),
            'email'      => setting('contact_email', 'support@shopzy.com'),
            'address'    => setting('contact_address', 'Dhaka, Bangladesh'),
            'currency'   => setting('currency_symbol', '৳'),
            'footer_text'=> setting('footer_text', 'Thank you for shopping with us!'),
        ];
    }
}
