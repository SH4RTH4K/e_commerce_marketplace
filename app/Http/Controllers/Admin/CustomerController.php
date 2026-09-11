<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        // Customers are derived from placed orders (grouped by phone).
        $query = Order::query()
            ->select('customer_name', 'customer_phone', 'customer_email')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(total) as total_spent')
            ->selectRaw('MAX(created_at) as last_order_at')
            ->groupBy('customer_name', 'customer_phone', 'customer_email');

        if ($term = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($term) {
                $q->where('customer_name', 'like', "%{$term}%")
                    ->orWhere('customer_phone', 'like', "%{$term}%");
            });
        }

        $customers = $query->orderByDesc('last_order_at')->paginate(20)->withQueryString();

        return Inertia::render('Admin/Customers/Index', compact('customers', 'term'));
    }

    public function show(string $phone)
    {
        $orders = Order::where('customer_phone', $phone)->latest()->get();
        abort_if($orders->isEmpty(), 404);

        return Inertia::render('Admin/Customers/Show', [
            'orders'   => $orders,
            'customer' => $orders->first(),
        ]);
    }
}
