<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class OrderListService
{
    public function filters(Request $request): array
    {
        $data = $request->validate([
            'status' => ['nullable', 'string'], 'q' => ['nullable', 'string', 'max:200'],
            'method' => ['nullable', 'string'], 'date_filter' => ['nullable', 'string'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from_date'), ['after_or_equal:from_date'])],
            'sort' => ['nullable', 'string'], 'per_page' => ['nullable', 'integer'],
        ]);
        $status = $data['status'] ?? 'all';
        $method = $data['method'] ?? '';
        $dateFilter = match ($data['date_filter'] ?? 'all') {
            '7_days' => '7days', '30_days' => '30days', 'year' => '1year', default => $data['date_filter'] ?? 'all',
        };
        if (! in_array($dateFilter, ['all', 'today', 'yesterday', '7days', '30days', 'this_month', 'last_month', 'this_year', '1year', 'custom'], true)) $dateFilter = 'all';
        // Preserve bookmarked legacy custom ranges without a date_filter parameter.
        if (! $request->filled('date_filter') && ($request->filled('from_date') || $request->filled('to_date'))) $dateFilter = 'custom';
        return [
            'status' => in_array($status, ['all', ...Order::STATUSES, 'pending_verification'], true) ? $status : 'all',
            'q' => trim($data['q'] ?? ''),
            'method' => in_array($method, Order::PAYMENT_METHODS, true) ? $method : '',
            'date_filter' => $dateFilter,
            'from_date' => $dateFilter === 'custom' ? ($data['from_date'] ?? '') : '',
            'to_date' => $dateFilter === 'custom' ? ($data['to_date'] ?? '') : '',
            'sort' => in_array($data['sort'] ?? '', ['newest', 'oldest', 'total_desc', 'total_asc'], true) ? $data['sort'] : 'newest',
            'per_page' => in_array((int) ($data['per_page'] ?? 15), [15, 25, 50, 100], true) ? (int) ($data['per_page'] ?? 15) : 15,
        ];
    }

    /** Base query deliberately excludes the selected segment for consistent counts. */
    public function query(array $filters): Builder
    {
        $query = Order::query();
        if ($filters['method'] !== '') $query->where('payment_method', $filters['method']);
        if ($filters['q'] !== '') {
            $query->where(fn ($query) => $query->where('order_number', 'like', '%'.$filters['q'].'%')
                ->orWhere('customer_name', 'like', '%'.$filters['q'].'%')
                ->orWhere('customer_phone', 'like', '%'.$filters['q'].'%')
                ->orWhere('customer_email', 'like', '%'.$filters['q'].'%')
                ->orWhere('payment_txn_id', 'like', '%'.$filters['q'].'%'));
        }
        $today = now('Asia/Dhaka')->startOfDay();
        [$from, $until] = match ($filters['date_filter']) {
            'today' => [$today->copy(), $today->copy()->addDay()],
            'yesterday' => [$today->copy()->subDay(), $today->copy()],
            '7days' => [$today->copy()->subDays(6), $today->copy()->addDay()],
            '30days' => [$today->copy()->subDays(29), $today->copy()->addDay()],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->addDay()],
            'last_month' => [$today->copy()->startOfMonth()->subMonth(), $today->copy()->startOfMonth()],
            'this_year' => [$today->copy()->startOfYear(), $today->copy()->addDay()],
            '1year' => [$today->copy()->subYear(), $today->copy()->addDay()],
            'custom' => [
                $filters['from_date'] !== '' ? Carbon::parse($filters['from_date'], 'Asia/Dhaka')->startOfDay() : null,
                $filters['to_date'] !== '' ? Carbon::parse($filters['to_date'], 'Asia/Dhaka')->startOfDay()->addDay() : null,
            ],
            default => [null, null],
        };
        if ($from) $query->where('created_at', '>=', $from->utc());
        if ($until) $query->where('created_at', '<', $until->utc());
        return $query;
    }

    public function segment(Builder $query, string $status): Builder
    {
        if ($status === 'pending_verification') return $query->awaitingPaymentVerification();
        if (in_array($status, Order::STATUSES, true)) $query->where('status', $status);
        return $query;
    }

    public function sort(Builder $query, string $sort): Builder
    {
        match ($sort) {
            'oldest' => $query->oldest('created_at')->orderBy('id'),
            'total_desc' => $query->orderByDesc('total')->latest('id'),
            'total_asc' => $query->orderBy('total')->latest('id'),
            default => $query->latest('created_at')->latest('id'),
        };
        return $query;
    }
}
