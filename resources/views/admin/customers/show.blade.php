@extends('layouts.admin')
@section('title', 'Customer')

@section('content')
<div class="space-y-6">
  <div>
    <a href="{{ route('admin.customers.index') }}" class="text-sm text-gray-500 hover:text-primary">&larr; Back to customers</a>
    <h2 class="text-xl font-bold mt-1">{{ $customer->customer_name }}</h2>
    <p class="text-gray-500 text-sm">{{ $customer->customer_phone }} @if($customer->customer_email)· {{ $customer->customer_email }}@endif</p>
  </div>

  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="card p-5"><p class="text-sm text-gray-400">Orders</p><p class="mt-1 text-2xl font-bold">{{ $orders->count() }}</p></div>
    <div class="card p-5"><p class="text-sm text-gray-400">Total spent</p><p class="mt-1 text-2xl font-bold text-primary">{{ money($orders->sum('total')) }}</p></div>
    <div class="card p-5"><p class="text-sm text-gray-400">Delivered</p><p class="mt-1 text-2xl font-bold">{{ $orders->where('status','delivered')->count() }}</p></div>
    <div class="card p-5"><p class="text-sm text-gray-400">Last order</p><p class="mt-1 text-lg font-bold">{{ $orders->first()->created_at->format('d M Y') }}</p></div>
  </div>

  <div class="card overflow-x-auto">
    <h3 class="font-semibold p-5 border-b border-gray-200">Order history</h3>
    <table class="w-full text-sm">
      <thead class="text-left text-gray-500 bg-gray-50">
        <tr><th class="px-5 py-3 font-medium">Order</th><th class="px-5 py-3 font-medium">Total</th><th class="px-5 py-3 font-medium">Payment</th><th class="px-5 py-3 font-medium">Status</th><th class="px-5 py-3 font-medium">Date</th></tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @foreach($orders as $order)
          <tr class="hover:bg-gray-50">
            <td class="px-5 py-3"><a href="{{ route('admin.orders.show', $order) }}" class="font-medium text-primary">{{ $order->order_number }}</a></td>
            <td class="px-5 py-3">{{ money($order->total) }}</td>
            <td class="px-5 py-3">{{ $order->paymentMethodLabel() }} · <span class="px-2 py-0.5 text-xs rounded-full {{ $order->paymentBadge() }}">{{ ucfirst($order->payment_status) }}</span></td>
            <td class="px-5 py-3"><span class="px-2 py-1 text-xs rounded-full {{ $order->statusBadge() }}">{{ ucfirst($order->status) }}</span></td>
            <td class="px-5 py-3 text-gray-500">{{ $order->created_at->format('d M Y') }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection
