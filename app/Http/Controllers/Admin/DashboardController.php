<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $revenue = (float) Order::where('payment_status', 'verified')->sum('total');

        // Last 14 days revenue series for the chart.
        $series = collect(range(13, 0))->map(function ($daysAgo) {
            $day = Carbon::today()->subDays($daysAgo);
            $total = (float) Order::whereDate('created_at', $day)
                ->where('payment_status', 'verified')
                ->sum('total');

            return ['label' => $day->format('d M'), 'value' => $total];
        });

        return Inertia::render('Admin/Dashboard', [
            'ordersCount'    => Order::count(),
            'pendingCount'   => Order::where('payment_status', 'pending')->count(),
            'revenue'        => $revenue,
            'productsCount'  => Product::count(),
            'pendingOrders'  => Order::where('payment_status', 'pending')->latest()->take(6)->get()->map(fn($o) => [
                'id' => $o->id, 'order_number' => $o->order_number, 'customer_name' => $o->customer_name,
                'total' => $o->total, 'payment_method' => $o->payment_method,
            ]),
            'recentOrders'   => Order::latest()->take(8)->get()->map(fn($o) => [
                'id' => $o->id, 'order_number' => $o->order_number, 'customer_name' => $o->customer_name,
                'total' => $o->total, 'status' => $o->status, 'payment_status' => $o->payment_status,
                'payment_method' => $o->payment_method,
                'payment_method_label' => $o->paymentMethodLabel(),
                'created_at_formatted' => $o->created_at->format('d M'),
                'created_at' => $o->created_at,
            ]),
            'series'         => $series,
            'seriesMax'      => max(1, $series->max('value')),
            'stats'          => [
                'confirmed'  => Order::where('status', 'confirmed')->count(),
                'pending'    => Order::where('status', 'pending')->count(),
                'shipped'    => Order::where('status', 'shipped')->count(),
                'cancelled'  => Order::where('status', 'cancelled')->count(),
                'low_stock'  => Product::where('stock_quantity', '<=', 5)->where('stock_quantity', '>', 0)->count(),
                'users'      => \App\Models\User::count(),
            ],
        ]);
    }
}
