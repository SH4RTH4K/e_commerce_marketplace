<?php

namespace App\Http\Controllers;

use App\Models\AbandonedCheckout;
use App\Support\BdPhoneValidator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

        $phone = trim((string) ($data['customer_phone'] ?? ''));
        if ($phone !== '' && ! BdPhoneValidator::isValid($phone)) {
            throw ValidationException::withMessages([
                'customer_phone' => 'Enter a valid Bangladesh mobile number (01XXXXXXXXX).',
            ]);
        }

        $normalizedPhone = $phone !== '' ? BdPhoneValidator::normalize($phone) : null;

        AbandonedCheckout::updateOrCreate(
            ['session_id' => $session_id],
            [
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $normalizedPhone,
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
