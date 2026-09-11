@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')
<div class="space-y-6">
  <!-- Stat cards -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    @php
      $cards = [
        ['Total orders', number_format($ordersCount), 'text-gray-900'],
        ['Pending verification', number_format($pendingCount), 'text-amber-600'],
        ['Verified revenue', money($revenue), 'text-primary'],
        ['Products', number_format($productsCount), 'text-gray-900'],
      ];
    @endphp
    @foreach($cards as [$label, $value, $color])
      <div class="card p-5">
        <p class="text-sm text-gray-400">{{ $label }}</p>
        <p class="mt-1 text-2xl font-bold {{ $color }}">{{ $value }}</p>
      </div>
    @endforeach
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Revenue chart -->
    <div class="card p-5 lg:col-span-2">
      <h2 class="font-semibold">Revenue (last 14 days)</h2>
      <div class="mt-5 flex items-end gap-1.5 h-48">
        @foreach($series as $point)
          <div class="flex-1 flex flex-col items-center justify-end h-full group">
            <div class="w-full bg-primary/80 hover:bg-primary rounded-t transition-all" style="height: {{ max(2, (int) round($point['value'] / $seriesMax * 100)) }}%" title="{{ $point['label'] }}: {{ money($point['value']) }}"></div>
          </div>
        @endforeach
      </div>
      <div class="mt-2 flex justify-between text-[10px] text-gray-400">
        <span>{{ $series->first()['label'] ?? '' }}</span>
        <span>{{ $series->last()['label'] ?? '' }}</span>
      </div>
    </div>

    <!-- Pending verification -->
    <div class="card p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-semibold">Pending Payment Verification</h2>
        <a href="{{ route('admin.orders.index', ['status' => 'pending_verification']) }}" class="text-xs text-primary">View all</a>
      </div>
      <div class="space-y-3">
        @forelse($pendingOrders as $order)
          <div class="flex items-center gap-3">
            <div class="flex-1 min-w-0">
              <a href="{{ route('admin.orders.show', $order) }}" class="text-sm font-medium text-primary">{{ $order->order_number }}</a>
              <p class="text-xs text-gray-400 truncate">{{ $order->customer_name }} &middot; {{ $order->paymentMethodLabel() }}</p>
            </div>
            <span class="text-sm font-semibold">{{ money($order->total) }}</span>
            <form method="POST" action="{{ route('admin.orders.verify', $order) }}">@csrf<button class="px-2 py-1 text-xs rounded bg-primary text-white">Verify</button></form>
          </div>
        @empty
          <p class="text-sm text-gray-400">No pending payments 🎉</p>
        @endforelse
      </div>
    </div>
  </div>

  <!-- Recent orders -->
  <div class="card">
    <div class="flex items-center justify-between p-5 border-b border-gray-200">
      <h2 class="font-semibold">Recent Orders</h2>
      <a href="{{ route('admin.orders.index') }}" class="text-sm text-primary">All orders</a>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-left text-gray-500 bg-gray-50">
          <tr><th class="px-5 py-3 font-medium">Order</th><th class="px-5 py-3 font-medium">Customer</th><th class="px-5 py-3 font-medium">Total</th><th class="px-5 py-3 font-medium">Payment</th><th class="px-5 py-3 font-medium">Status</th><th class="px-5 py-3 font-medium">Date</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @foreach($recentOrders as $order)
            <tr class="hover:bg-gray-50">
              <td class="px-5 py-3"><a href="{{ route('admin.orders.show', $order) }}" class="font-medium text-primary">{{ $order->order_number }}</a></td>
              <td class="px-5 py-3">{{ $order->customer_name }}</td>
              <td class="px-5 py-3">{{ money($order->total) }}</td>
              <td class="px-5 py-3">{{ $order->paymentMethodLabel() }} · <span class="px-2 py-0.5 text-xs rounded-full {{ $order->paymentBadge() }}">{{ ucfirst($order->payment_status) }}</span></td>
              <td class="px-5 py-3"><span class="px-2 py-1 text-xs rounded-full {{ $order->statusBadge() }}">{{ ucfirst($order->status) }}</span></td>
              <td class="px-5 py-3 text-gray-500">{{ $order->created_at->format('d M') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
