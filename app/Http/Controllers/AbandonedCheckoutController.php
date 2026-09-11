<?php

namespace App\Http\Controllers;

use App\Models\AbandonedCheckout;
use Illuminate\Http\Request;

class AbandonedCheckoutController extends Controller
{
    public function ping(Request $request)
    {
        $session_id = $request->session()->getId();

        $data = $request->validate([
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:40',
            'customer_email' => 'nullable|email|max:120',
            'shipping_address' => 'nullable|string|max:500',
            'cart_items' => 'nullable|array',
            'cart_total' => 'nullable|numeric',
        ]);

        if (empty($data['customer_phone']) && empty($data['customer_email'])) {
            return response()->json(['status' => 'skipped']);
        }

        AbandonedCheckout::updateOrCreate(
            ['session_id' => $session_id],
            [
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'customer_email' => $data['customer_email'] ?? null,
                'shipping_address' => $data['shipping_address'] ?? null,
                'cart_items' => $data['cart_items'] ?? [],
                'cart_total' => $data['cart_total'] ?? 0,
                'last_active_at' => now(),
                'is_recovered' => false,
            ]
        );

        return response()->json(['status' => 'tracked']);
    }
}
