<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbandonedCheckout;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $range = $request->query('range', '30d');
        $customStart = $request->query('start_date');
        $customEnd = $request->query('end_date');

        // ── 1. Calculate Date Bounds ──
        $now = Carbon::now();
        switch ($range) {
            case 'today':
                $start = Carbon::today();
                $end = Carbon::today()->endOfDay();
                $prevStart = Carbon::yesterday();
                $prevEnd = Carbon::yesterday()->endOfDay();
                $rangeLabel = 'Today';
                break;

            case '7d':
                $start = Carbon::today()->subDays(6)->startOfDay();
                $end = Carbon::today()->endOfDay();
                $prevStart = (clone $start)->subDays(7);
                $prevEnd = (clone $start)->subSecond();
                $rangeLabel = 'Last 7 Days';
                break;

            case 'this_month':
                $start = Carbon::now()->startOfMonth();
                $end = Carbon::now()->endOfDay();
                $prevStart = Carbon::now()->subMonth()->startOfMonth();
                $prevEnd = Carbon::now()->subMonth()->endOfMonth();
                $rangeLabel = 'This Month (' . $now->format('F Y') . ')';
                break;

            case 'last_month':
                $start = Carbon::now()->subMonth()->startOfMonth();
                $end = Carbon::now()->subMonth()->endOfMonth();
                $prevStart = Carbon::now()->subMonths(2)->startOfMonth();
                $prevEnd = Carbon::now()->subMonths(2)->endOfMonth();
                $rangeLabel = 'Last Month (' . Carbon::now()->subMonth()->format('F Y') . ')';
                break;

            case 'ytd':
                $start = Carbon::now()->startOfYear();
                $end = Carbon::now()->endOfDay();
                $prevStart = Carbon::now()->subYear()->startOfYear();
                $prevEnd = Carbon::now()->subYear()->endOfYear();
                $rangeLabel = 'Year to Date (' . $now->format('Y') . ')';
                break;

            case 'all':
                $start = Carbon::createFromTimestamp(0);
                $end = Carbon::now()->endOfDay();
                $prevStart = null;
                $prevEnd = null;
                $rangeLabel = 'All Time';
                break;

            case 'custom':
                $start = $customStart ? Carbon::parse($customStart)->startOfDay() : Carbon::today()->subDays(29)->startOfDay();
                $end = $customEnd ? Carbon::parse($customEnd)->endOfDay() : Carbon::today()->endOfDay();
                $diffDays = $start->diffInDays($end) + 1;
                $prevStart = (clone $start)->subDays($diffDays);
                $prevEnd = (clone $start)->subSecond();
                $rangeLabel = $start->format('d M Y') . ' - ' . $end->format('d M Y');
                break;

            case '30d':
            default:
                $range = '30d';
                $start = Carbon::today()->subDays(29)->startOfDay();
                $end = Carbon::today()->endOfDay();
                $prevStart = (clone $start)->subDays(30);
                $prevEnd = (clone $start)->subSecond();
                $rangeLabel = 'Last 30 Days';
                break;
        }

        // ── 2. Current Period Metrics ──
        $currentOrdersQuery = Order::whereBetween('created_at', [$start, $end]);
        $totalOrders = (clone $currentOrdersQuery)->count();
        $grossRevenue = (float) (clone $currentOrdersQuery)->sum('total');
        $deliveredRevenue = (float) (clone $currentOrdersQuery)->where('status', 'delivered')->sum('total');
        
        $deliveredCount = (clone $currentOrdersQuery)->where('status', 'delivered')->count();
        $shippedCount = (clone $currentOrdersQuery)->where('status', 'shipped')->count();
        $confirmedCount = (clone $currentOrdersQuery)->where('status', 'confirmed')->count();
        $pendingCount = (clone $currentOrdersQuery)->where('status', 'pending')->count();
        $cancelledCount = (clone $currentOrdersQuery)->where('status', 'cancelled')->count();

        $aov = $totalOrders > 0 ? round($grossRevenue / $totalOrders, 2) : 0;
        $resolvedCount = $deliveredCount + $cancelledCount;
        $deliverySuccessRate = $resolvedCount > 0 ? round(($deliveredCount / $resolvedCount) * 100, 1) : 0;
        $cancellationRate = $totalOrders > 0 ? round(($cancelledCount / $totalOrders) * 100, 1) : 0;

        // ── 3. Previous Period Metrics for Growth % ──
        $growth = [
            'revenue' => 0,
            'orders' => 0,
            'delivered' => 0,
            'aov' => 0,
        ];

        if ($prevStart && $prevEnd) {
            $prevOrdersQuery = Order::whereBetween('created_at', [$prevStart, $prevEnd]);
            $prevTotalOrders = (clone $prevOrdersQuery)->count();
            $prevRevenue = (float) (clone $prevOrdersQuery)->sum('total');
            $prevDelivered = (clone $prevOrdersQuery)->where('status', 'delivered')->count();
            $prevAov = $prevTotalOrders > 0 ? $prevRevenue / $prevTotalOrders : 0;

            $calcGrowth = fn($curr, $prev) => $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : ($curr > 0 ? 100 : 0);

            $growth = [
                'revenue'   => $calcGrowth($grossRevenue, $prevRevenue),
                'orders'    => $calcGrowth($totalOrders, $prevTotalOrders),
                'delivered' => $calcGrowth($deliveredCount, $prevDelivered),
                'aov'       => $calcGrowth($aov, $prevAov),
            ];
        }

        // ── 4. This Month vs Last Month Dedicated Card ──
        $thisMonthStart = Carbon::now()->startOfMonth();
        $thisMonthEnd = Carbon::now()->endOfDay();
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        $thisMonthOrders = Order::whereBetween('created_at', [$thisMonthStart, $thisMonthEnd]);
        $lastMonthOrders = Order::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd]);

        $thisMonthRev = (float) (clone $thisMonthOrders)->sum('total');
        $lastMonthRev = (float) (clone $lastMonthOrders)->sum('total');
        $thisMonthCount = (clone $thisMonthOrders)->count();
        $lastMonthCount = (clone $lastMonthOrders)->count();
        $thisMonthDelivered = (clone $thisMonthOrders)->where('status', 'delivered')->count();
        $lastMonthDelivered = (clone $lastMonthOrders)->where('status', 'delivered')->count();
        $thisMonthCancelled = (clone $thisMonthOrders)->where('status', 'cancelled')->count();
        $lastMonthCancelled = (clone $lastMonthOrders)->where('status', 'cancelled')->count();

        $monthComparison = [
            'this_month_name'      => Carbon::now()->format('F'),
            'last_month_name'      => Carbon::now()->subMonth()->format('F'),
            'this_month_revenue'   => $thisMonthRev,
            'last_month_revenue'   => $lastMonthRev,
            'revenue_diff_percent' => $lastMonthRev > 0 ? round((($thisMonthRev - $lastMonthRev) / $lastMonthRev) * 100, 1) : ($thisMonthRev > 0 ? 100 : 0),
            'this_month_orders'    => $thisMonthCount,
            'last_month_orders'    => $lastMonthCount,
            'orders_diff_percent'  => $lastMonthCount > 0 ? round((($thisMonthCount - $lastMonthCount) / $lastMonthCount) * 100, 1) : ($thisMonthCount > 0 ? 100 : 0),
            'this_month_delivered' => $thisMonthDelivered,
            'last_month_delivered' => $lastMonthDelivered,
            'this_month_cancelled' => $thisMonthCancelled,
            'last_month_cancelled' => $lastMonthCancelled,
            'this_month_aov'       => $thisMonthCount > 0 ? round($thisMonthRev / $thisMonthCount, 2) : 0,
            'last_month_aov'       => $lastMonthCount > 0 ? round($lastMonthRev / $lastMonthCount, 2) : 0,
        ];

        // ── 5. Time Series Chart Data ──
        $chartData = [];
        $diffDays = $start->diffInDays($end);

        if ($range === 'today') {
            // Hourly series for Today
            for ($h = 0; $h <= Carbon::now()->hour; $h++) {
                $hStart = Carbon::today()->addHours($h);
                $hEnd = (clone $hStart)->endOfHour();
                $hOrders = Order::whereBetween('created_at', [$hStart, $hEnd]);
                $chartData[] = [
                    'label'     => $hStart->format('ga'),
                    'full_date' => $hStart->format('d M, g:i A'),
                    'revenue'   => (float) (clone $hOrders)->sum('total'),
                    'orders'    => (clone $hOrders)->count(),
                    'delivered' => (clone $hOrders)->where('status', 'delivered')->count(),
                ];
            }
        } elseif ($diffDays <= 31) {
            // Daily series
            $period = CarbonPeriod::create($start->copy()->startOfDay(), '1 day', $end->copy()->startOfDay());
            foreach ($period as $date) {
                $dStart = $date->copy()->startOfDay();
                $dEnd = $date->copy()->endOfDay();
                $dOrders = Order::whereBetween('created_at', [$dStart, $dEnd]);
                $chartData[] = [
                    'label'     => $date->format('d M'),
                    'full_date' => $date->format('D, d M Y'),
                    'revenue'   => (float) (clone $dOrders)->sum('total'),
                    'orders'    => (clone $dOrders)->count(),
                    'delivered' => (clone $dOrders)->where('status', 'delivered')->count(),
                    'cancelled' => (clone $dOrders)->where('status', 'cancelled')->count(),
                ];
            }
        } else {
            // Group by month/week for larger ranges (e.g. YTD, All time)
            $ordersGrouped = Order::whereBetween('created_at', [$start, $end])
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as m_key, DATE_FORMAT(created_at, '%b %Y') as label, SUM(total) as revenue, COUNT(*) as orders, SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered")
                ->groupBy('m_key', 'label')
                ->orderBy('m_key')
                ->get();

            foreach ($ordersGrouped as $row) {
                $chartData[] = [
                    'label'     => $row->label,
                    'full_date' => $row->label,
                    'revenue'   => (float) $row->revenue,
                    'orders'    => (int) $row->orders,
                    'delivered' => (int) $row->delivered,
                ];
            }
        }

        // ── 6. Top Selling Products Leaderboard ──
        $topProducts = OrderItem::whereHas('order', function ($q) use ($start, $end) {
                $q->whereBetween('created_at', [$start, $end])
                  ->where('status', '!=', 'cancelled');
            })
            ->select('product_id', 'product_name', DB::raw('SUM(quantity) as units_sold'), DB::raw('SUM(line_total) as total_revenue'))
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_revenue')
            ->take(8)
            ->get()
            ->map(function ($item) {
                $product = Product::with('images', 'category')->find($item->product_id);
                return [
                    'id'            => $item->product_id,
                    'name'          => $item->product_name ?: ($product?->name ?? 'Deleted Product'),
                    'sku'           => $product?->sku ?? 'N/A',
                    'category'      => $product?->category?->name ?? 'General',
                    'image'         => $product?->images->first()?->url() ?? $product?->thumbnail_url ?? null,
                    'units_sold'    => (int) $item->units_sold,
                    'total_revenue' => (float) $item->total_revenue,
                    'stock'         => $product?->stock_quantity ?? 0,
                ];
            });

        // ── 7. Category Revenue Share ──
        $categoryBreakdown = Category::all()->map(function ($cat) use ($start, $end) {
            $catRevenue = (float) OrderItem::whereHas('product', fn($q) => $q->where('category_id', $cat->id))
                ->whereHas('order', fn($q) => $q->whereBetween('created_at', [$start, $end])->where('status', '!=', 'cancelled'))
                ->sum('line_total');

            $catSold = (int) OrderItem::whereHas('product', fn($q) => $q->where('category_id', $cat->id))
                ->whereHas('order', fn($q) => $q->whereBetween('created_at', [$start, $end])->where('status', '!=', 'cancelled'))
                ->sum('quantity');

            return [
                'id'       => $cat->id,
                'name'     => $cat->name,
                'revenue'  => $catRevenue,
                'quantity' => $catSold,
            ];
        })->filter(fn($c) => $c['revenue'] > 0)->sortByDesc('revenue')->values();

        $catTotalRevenue = $categoryBreakdown->sum('revenue');
        $categoryBreakdown = $categoryBreakdown->map(function ($c) use ($catTotalRevenue) {
            $c['share_percent'] = $catTotalRevenue > 0 ? round(($c['revenue'] / $catTotalRevenue) * 100, 1) : 0;
            return $c;
        });

        // ── 8. Payment Method Distribution ──
        $paymentMethods = Order::whereBetween('created_at', [$start, $end])
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as revenue'))
            ->groupBy('payment_method')
            ->get()
            ->map(function ($pm) use ($totalOrders) {
                $labels = [
                    'cod'    => 'Cash on Delivery',
                    'bkash'  => 'bKash',
                    'nagad'  => 'Nagad',
                    'rocket' => 'Rocket',
                ];
                return [
                    'method'        => $pm->payment_method ?: 'cod',
                    'label'         => $labels[$pm->payment_method] ?? strtoupper($pm->payment_method),
                    'count'         => (int) $pm->count,
                    'revenue'       => (float) $pm->revenue,
                    'share_percent' => $totalOrders > 0 ? round(($pm->count / $totalOrders) * 100, 1) : 0,
                ];
            });

        // ── 9. Courier Delivery Performance ──
        $courierStats = Order::whereBetween('created_at', [$start, $end])
            ->whereNotNull('courier_provider')
            ->where('courier_provider', '!=', '')
            ->select(
                'courier_provider',
                DB::raw('COUNT(*) as total_shipped'),
                DB::raw("SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered"),
                DB::raw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as returned")
            )
            ->groupBy('courier_provider')
            ->get()
            ->map(function ($c) {
                $shipped = (int) $c->total_shipped;
                $del = (int) $c->delivered;
                return [
                    'provider'      => ucfirst($c->courier_provider),
                    'total_shipped' => $shipped,
                    'delivered'     => $del,
                    'returned'      => (int) $c->returned,
                    'success_rate'  => $shipped > 0 ? round(($del / $shipped) * 100, 1) : 0,
                ];
            });

        // ── 10. Reviews & Customer Sentiment ──
        $totalReviews = ProductReview::count();
        $approvedReviews = ProductReview::approved()->count();
        $pendingReviews = ProductReview::pending()->count();
        $avgRating = (float) (ProductReview::approved()->avg('rating') ?? 0);
        
        $starCounts = [
            5 => ProductReview::approved()->where('rating', 5)->count(),
            4 => ProductReview::approved()->where('rating', 4)->count(),
            3 => ProductReview::approved()->where('rating', 3)->count(),
            2 => ProductReview::approved()->where('rating', 2)->count(),
            1 => ProductReview::approved()->where('rating', 1)->count(),
        ];

        // ── 11. Customer & CRM Insights ──
        $newCustomersCount = User::where('role', 'customer')
            ->whereBetween('created_at', [$start, $end])
            ->count();
        $totalCustomersCount = User::where('role', 'customer')->count();

        $abandonedCount = AbandonedCheckout::whereBetween('created_at', [$start, $end])->count();
        $recoveredAbandoned = AbandonedCheckout::whereBetween('created_at', [$start, $end])->where('is_recovered', true)->count();
        $recoveredRevenue = (float) AbandonedCheckout::whereBetween('created_at', [$start, $end])->where('is_recovered', true)->sum('cart_total');

        return Inertia::render('Admin/Analytics', [
            'range'                => $range,
            'rangeLabel'           => $rangeLabel,
            'startDate'            => $start->format('Y-m-d'),
            'endDate'              => $end->format('Y-m-d'),
            'metrics'              => [
                'gross_revenue'         => $grossRevenue,
                'delivered_revenue'     => $deliveredRevenue,
                'total_orders'          => $totalOrders,
                'delivered_orders'      => $deliveredCount,
                'shipped_orders'        => $shippedCount,
                'confirmed_orders'      => $confirmedCount,
                'pending_orders'        => $pendingCount,
                'cancelled_orders'      => $cancelledCount,
                'aov'                   => $aov,
                'delivery_success_rate' => $deliverySuccessRate,
                'cancellation_rate'     => $cancellationRate,
            ],
            'growth'               => $growth,
            'monthComparison'      => $monthComparison,
            'chartData'            => $chartData,
            'topProducts'          => $topProducts,
            'categoryBreakdown'    => $categoryBreakdown,
            'paymentMethods'       => $paymentMethods,
            'courierStats'         => $courierStats,
            'reviewsSummary'       => [
                'total'           => $totalReviews,
                'approved'        => $approvedReviews,
                'pending'         => $pendingReviews,
                'average_rating'  => round($avgRating, 1),
                'stars'           => $starCounts,
            ],
            'customerSummary'      => [
                'new_customers'      => $newCustomersCount,
                'total_customers'    => $totalCustomersCount,
                'abandoned_carts'    => $abandonedCount,
                'recovered_carts'    => $recoveredAbandoned,
                'recovery_rate'      => $abandonedCount > 0 ? round(($recoveredAbandoned / $abandonedCount) * 100, 1) : 0,
                'recovered_revenue'  => $recoveredRevenue,
            ],
        ]);
    }
}
