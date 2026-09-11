<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $orders = Order::query()
            ->with(['items.product'])
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if ($user->phone) {
                    $q->orWhere('customer_phone', $user->phone);
                }
            })
            ->latest()
            ->get();

        return Inertia::render('Storefront/Account/Index', compact('user', 'orders'));
    }

    public function showOrder(Order $order)
    {
        $this->authorizeOrder($order);
        $order->load(['items.product']);

        return Inertia::render('Storefront/Account/Show', compact('order'));
    }

    private function authorizeOrder(Order $order): void
    {
        $user = Auth::user();

        $owns = $order->user_id === $user->id
            || ($user->phone && $order->customer_phone === $user->phone);

        abort_unless($owns, 403);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'phone'       => ['nullable', 'string', 'max:40'],
            'address'     => ['nullable', 'string', 'max:255'],
            'city'        => ['nullable', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:20'],
        ]);

        Auth::user()->update($data);

        return back()->with('status', 'Profile updated.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)],
        ]);

        Auth::user()->update(['password' => $request->input('password')]);

        return back()->with('status', 'Password changed.');
    }
}
