<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbandonedCheckout;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $revenue = (float) Order::where('payment_status', 'verified')->sum('total');
        $orderSegments = Order::segmentCounts();

        $today = Carbon::today();
        $dailyAnalytics = Order::whereBetween('created_at', [$today->copy()->subDays(13)->startOfDay(), $today->copy()->endOfDay()])
            ->selectRaw('DATE(created_at) as order_date, COUNT(*) as orders_count, SUM(CASE WHEN payment_status = ? THEN total ELSE 0 END) as verified_revenue, SUM(CASE WHEN payment_status = ? THEN 1 ELSE 0 END) as verified_orders', ['verified', 'verified'])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get()
            ->keyBy('order_date');

        // Keep empty days in the chart so the trend always spans a full 14 days.
        $series = collect(range(13, 0))->map(function ($daysAgo) use ($today, $dailyAnalytics) {
            $day = $today->copy()->subDays($daysAgo);
            $metrics = $dailyAnalytics->get($day->toDateString());

            return [
                'label' => $day->format('d M'),
                'value' => (float) ($metrics?->verified_revenue ?? 0),
                'orders' => (int) ($metrics?->orders_count ?? 0),
                'verifiedOrders' => (int) ($metrics?->verified_orders ?? 0),
            ];
        });

        $currentWeek = $series->slice(-7);
        $previousWeek = $series->slice(0, 7);
        $currentWeekRevenue = (float) $currentWeek->sum('value');
        $previousWeekRevenue = (float) $previousWeek->sum('value');
        $currentWeekOrders = (int) $currentWeek->sum('orders');
        $previousWeekOrders = (int) $previousWeek->sum('orders');
        $currentWeekVerifiedOrders = (int) $currentWeek->sum('verifiedOrders');
        $currentWeekAllOrders = $currentWeekOrders;

        return Inertia::render('Admin/Dashboard', [
            'ordersCount' => Order::count(),
            'pendingCount' => Order::where('payment_status', 'pending')->count(),
            'revenue' => $revenue,
            'productsCount' => Product::count(),
            'pendingOrders' => Order::where('payment_status', 'pending')->latest()->take(6)->get()->map(fn ($o) => [
                'id' => $o->id, 'order_number' => $o->order_number, 'customer_name' => $o->customer_name,
                'total' => $o->total, 'payment_method' => $o->payment_method,
            ]),
            'recentOrders' => Order::latest()->take(8)->get()->map(fn ($o) => [
                'id' => $o->id, 'order_number' => $o->order_number, 'customer_name' => $o->customer_name,
                'total' => $o->total, 'status' => $o->status, 'payment_status' => $o->payment_status,
                'payment_method' => $o->payment_method,
                'payment_method_label' => $o->paymentMethodLabel(),
                'created_at_formatted' => $o->created_at->format('d M'),
                'created_at' => $o->created_at,
            ]),
            'series' => $series,
            'seriesMax' => max(1, $series->max('value')),
            'insights' => [
                'revenue_7d' => $currentWeekRevenue,
                'revenue_change' => $previousWeekRevenue > 0 ? round((($currentWeekRevenue - $previousWeekRevenue) / $previousWeekRevenue) * 100, 1) : null,
                'orders_7d' => $currentWeekOrders,
                'orders_change' => $previousWeekOrders > 0 ? round((($currentWeekOrders - $previousWeekOrders) / $previousWeekOrders) * 100, 1) : null,
                'average_order_value' => $currentWeekVerifiedOrders > 0 ? round($currentWeekRevenue / $currentWeekVerifiedOrders, 2) : 0,
                'payment_verification_rate' => $currentWeekAllOrders > 0 ? round(($currentWeekVerifiedOrders / $currentWeekAllOrders) * 100, 1) : 0,
            ],
            'stats' => [
                'order_segments' => $orderSegments,
                'confirmed' => Order::where('status', 'confirmed')->count(),
                'pending' => Order::where('status', 'pending')->count(),
                'shipped' => Order::where('status', 'shipped')->count(),
                'cancelled' => Order::where('status', 'cancelled')->count(),
                'abandoned_carts' => request()->user()?->hasPermission('abandoned_carts.view')
                    ? AbandonedCheckout::where('is_recovered', false)->count()
                    : null,
                'low_stock' => Product::whereRaw('(stock_quantity + sourced_stock_quantity) BETWEEN 1 AND 5')->count(),
                'users' => User::count(),
            ],
        ]);
    }
}
