<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\BdPhoneValidator;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TrackOrderController extends Controller
{
    public function show()
    {
        return Inertia::render('Storefront/Track', ['order' => null]);
    }

    public function find(Request $request)
    {
        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:50'],
            'phone'        => ['required', 'string', 'max:50'],
        ]);

        $orderNumber = trim($data['order_number']);
        $rawPhone = trim($data['phone']);
        $phoneNorm = BdPhoneValidator::normalize($rawPhone);

        $order = Order::with(['items.product.images'])
            ->where('order_number', $orderNumber)
            ->where(function ($q) use ($rawPhone, $phoneNorm) {
                $q->where('customer_phone', $rawPhone)
                  ->orWhere('customer_phone', $phoneNorm);
            })
            ->first();

        if (! $order) {
            return back()->withErrors(['order_number' => 'No order found with that number and phone.'])->withInput();
        }

        return Inertia::render('Storefront/Track', compact('order'));
    }
}

