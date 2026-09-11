@extends('layouts.admin')
@section('title', 'Orders')

@section('content')
<div class="space-y-6">
  <div class="flex items-center justify-between">
    <h2 class="text-xl font-bold">Orders</h2>
  </div>

  <div class="flex flex-wrap gap-2 text-sm">
    @php
      $tabs = [
        'all' => 'All', 'pending_verification' => 'Pending verification',
        'confirmed' => 'Confirmed', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled',
      ];
    @endphp
    @foreach($tabs as $key => $label)
      @php $active = $status === $key || ($key === 'all' && !in_array($status, array_keys($tabs))); @endphp
      <a href="{{ route('admin.orders.index', ['status' => $key]) }}"
         class="px-3 py-1.5 rounded-lg {{ $active ? 'bg-primary text-white' : 'bg-white border border-gray-200 text-gray-600' }}">
        {{ $label }}
        @if(isset($counts[$key]))
          <span class="{{ $active ? 'opacity-80' : ($key === 'pending_verification' ? 'bg-amber-100 text-amber-700 rounded px-1' : 'text-gray-400') }}">{{ $counts[$key] }}</span>
        @endif
      </a>
    @endforeach
  </div>

  <div class="card">
    <form method="GET" class="p-4 border-b border-gray-200 flex flex-wrap gap-3">
      <input type="hidden" name="status" value="{{ $status }}">
      <input type="text" name="q" value="{{ $q }}" placeholder="Search order # or customer…" class="flex-1 min-w-[200px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40" />
      <select name="method" onchange="this.form.submit()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">All payment methods</option>
        @foreach(['bkash' => 'bKash', 'nagad' => 'Nagad', 'rocket' => 'Rocket', 'cod' => 'COD'] as $k => $v)
          <option value="{{ $k }}" @selected($method === $k)>{{ $v }}</option>
        @endforeach
      </select>
      <button class="btn-primary">Search</button>
    </form>

    <form id="ordersBulkForm" method="POST" action="{{ route('admin.orders.bulk') }}" data-bulk-form>
      @csrf
      <div data-bulk-bar class="px-4 py-3 border-b border-gray-200 bg-primary/5 flex flex-wrap items-center gap-3">
        <span class="text-sm font-medium text-ink"><span data-bulk-count>0</span> selected</span>
        <select name="bulk_action" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm bg-white">
          <option value="">Choose action…</option>
          <option value="verify">Verify payment</option>
          <option value="reject" data-confirm="Reject payment for :count selected order(s)?">Reject payment</option>
          <option value="delete" data-confirm="Permanently delete :count selected order(s)? This cannot be undone.">Delete</option>
        </select>
        <button type="submit" data-bulk-apply class="btn-primary text-sm py-1.5" disabled>Apply</button>
      </div>
    </form>

    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-left text-gray-500 bg-gray-50">
          <tr>
            <th class="px-5 py-3 font-medium w-10">
              <input type="checkbox" form="ordersBulkForm" data-bulk-select-all class="accent-primary h-4 w-4" title="Select all" aria-label="Select all" />
            </th>
            <th class="px-5 py-3 font-medium">Order</th>
            <th class="px-5 py-3 font-medium">Customer</th>
            <th class="px-5 py-3 font-medium">Total</th>
            <th class="px-5 py-3 font-medium">Payment</th>
            <th class="px-5 py-3 font-medium">Status</th>
            <th class="px-5 py-3 font-medium">Date</th>
            <th class="px-5 py-3 font-medium text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse($orders as $order)
            <tr class="hover:bg-gray-50">
              <td class="px-5 py-3">
                <input type="checkbox" form="ordersBulkForm" name="ids[]" value="{{ $order->id }}" data-bulk-row class="accent-primary h-4 w-4" aria-label="Select {{ $order->order_number }}" />
              </td>
              <td class="px-5 py-3"><a href="{{ route('admin.orders.show', $order) }}" class="font-medium text-primary">{{ $order->order_number }}</a><br><span class="text-gray-400 text-xs">{{ $order->items_count }} item(s)</span></td>
              <td class="px-5 py-3">{{ $order->customer_name }}<br><span class="text-gray-400 text-xs">{{ $order->customer_phone }}</span></td>
              <td class="px-5 py-3 font-medium">{{ money($order->total) }}</td>
              <td class="px-5 py-3">{{ $order->paymentMethodLabel() }} · <span class="px-2 py-0.5 text-xs rounded-full {{ $order->paymentBadge() }}">{{ ucfirst($order->payment_status) }}</span></td>
              <td class="px-5 py-3"><span class="px-2 py-1 text-xs rounded-full {{ $order->statusBadge() }}">{{ ucfirst($order->status) }}</span></td>
              <td class="px-5 py-3 text-gray-500">{{ $order->created_at->format('d M') }}</td>
              <td class="px-5 py-3 text-right whitespace-nowrap">
                @if($order->payment_status === 'pending')
                  <form method="POST" action="{{ route('admin.orders.verify', $order) }}" class="inline">@csrf<button class="px-2 py-1 text-xs rounded bg-primary text-white">Verify</button></form>
                  <form method="POST" action="{{ route('admin.orders.reject', $order) }}" class="inline">@csrf<button class="px-2 py-1 text-xs rounded bg-red-50 text-red-600 border border-red-200">Reject</button></form>
                @else
                  <a href="{{ route('admin.orders.show', $order) }}" class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-600">View</a>
                @endif
                <form method="POST" action="{{ route('admin.orders.destroy', $order) }}" class="inline" onsubmit="return confirm('Delete order {{ $order->order_number }} permanently? This cannot be undone.')">
                  @csrf @method('DELETE')
                  <button class="px-2 py-1 text-xs rounded bg-red-50 text-red-600 border border-red-200">Delete</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="px-5 py-10 text-center text-gray-400">No orders found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="p-4 border-t border-gray-200">{{ $orders->links() }}</div>
  </div>
</div>
@endsection
