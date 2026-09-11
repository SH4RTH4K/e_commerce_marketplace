@extends('layouts.admin')
@section('title', 'Order Detail')

@section('content')
<div class="space-y-6">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <a href="{{ route('admin.orders.index') }}" class="text-sm text-gray-500 hover:text-primary">&larr; Back to orders</a>
      <h2 class="text-xl font-bold mt-1">Order {{ $order->order_number }}</h2>
    </div>
    <div class="flex items-center gap-2">
      <span class="px-2 py-1 text-xs rounded-full {{ $order->statusBadge() }}">{{ ucfirst($order->status) }}</span>
      <span class="px-2 py-1 text-xs rounded-full {{ $order->paymentBadge() }}">Payment: {{ ucfirst($order->payment_status) }}</span>
      <span class="px-3 py-1 text-sm rounded-full bg-gray-100 text-gray-600">Placed {{ $order->created_at->format('d M Y, g:i A') }}</span>
      <form method="POST" action="{{ route('admin.orders.destroy', $order) }}" onsubmit="return confirm('Delete order {{ $order->order_number }} permanently? This cannot be undone.')">
        @csrf @method('DELETE')
        <button class="px-3 py-1.5 text-xs rounded-lg bg-red-50 text-red-600 border border-red-200 font-medium">Delete order</button>
      </form>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left -->
    <div class="lg:col-span-2 space-y-6">
      <!-- Items -->
      <div class="card">
        <h3 class="font-semibold p-5 border-b border-gray-200">Items</h3>
        <div class="divide-y divide-gray-100">
          @foreach($order->items as $item)
            <div class="flex items-center gap-4 p-4">
              <img src="{{ $item->imageUrl() }}" class="h-14 w-14 object-cover bg-gray-100 rounded-lg" alt="{{ $item->product_name }}">
              <div class="flex-1"><p class="font-medium">{{ $item->product_name }}</p><p class="text-gray-400 text-sm">{{ $item->variant }} &middot; {{ money($item->unit_price) }} × {{ $item->quantity }}</p></div>
              <div class="font-medium">{{ money($item->line_total) }}</div>
            </div>
          @endforeach
        </div>
        <div class="p-5 border-t border-gray-200 text-sm space-y-1">
          <div class="flex justify-between text-gray-600"><span>Subtotal</span><span>{{ money($order->subtotal) }}</span></div>
          @if($order->discount_amount > 0)
            <div class="flex justify-between text-primary"><span>Coupon @if($order->coupon_code)({{ $order->coupon_code }})@endif</span><span>−{{ money($order->discount_amount) }}</span></div>
          @endif
          <div class="flex justify-between text-gray-600"><span>Shipping ({{ shipping_zone_label($order->shipping_zone) }})</span><span>{{ money($order->shipping_charge) }}</span></div>
          <div class="flex justify-between text-gray-600"><span>Tax</span><span>{{ money($order->tax) }}</span></div>
          @if($order->payment_method === 'cod')
            <div class="flex justify-between text-primary pt-1"><span>Pay now (delivery)</span><span>{{ money($order->shipping_charge) }}</span></div>
            <div class="flex justify-between text-amber-700"><span>Pay after delivery</span><span>{{ money(max(0, (float) $order->total - (float) $order->shipping_charge)) }}</span></div>
          @endif
          <div class="flex justify-between font-bold text-base pt-2"><span>Total</span><span>{{ money($order->total) }}</span></div>
        </div>
      </div>

      <!-- Payment verification -->
      <div class="card p-5">
        <h3 class="font-semibold mb-4">Payment — verify against your statement</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div><p class="text-gray-400">Method</p><p class="font-medium">{{ $order->paymentMethodLabel() }}</p></div>
          <div><p class="text-gray-400">Amount</p><p class="font-medium">{{ money($order->total) }}</p></div>
          <div><p class="text-gray-400">Sender number</p><p class="font-medium">{{ $order->payment_sender_number ?? "\u{2014}" }}</p></div>
          <div><p class="text-gray-400">Transaction ID</p><p class="font-medium">{{ $order->payment_txn_id ?? "\u{2014}" }}</p></div>
        </div>
        @if($order->payment_status === 'pending')
          <div class="flex gap-3 mt-5">
            <form method="POST" action="{{ route('admin.orders.verify', $order) }}">@csrf<button class="bg-primary hover:bg-primary/90 text-white text-sm font-medium px-5 py-2.5 rounded-lg">✓ Verify payment</button></form>
            <form method="POST" action="{{ route('admin.orders.reject', $order) }}">@csrf<button class="bg-red-50 text-red-600 border border-red-200 text-sm font-medium px-5 py-2.5 rounded-lg">✕ Reject</button></form>
          </div>
        @else
          <p class="mt-4 text-sm text-gray-500">Payment is <b class="{{ $order->payment_status === 'verified' ? 'text-primary' : 'text-red-600' }}">{{ $order->payment_status }}</b>. Change it below if needed.</p>
        @endif
      </div>
    </div>

    <!-- Right -->
    <div class="space-y-6">
      <form method="POST" action="{{ route('admin.orders.update', $order) }}" class="card p-5 space-y-3">
        @csrf @method('PATCH')
        <h3 class="font-semibold mb-1">Update order</h3>
        <div>
          <label class="lbl">Order status</label>
          <select name="status" class="inp">
            @foreach(\App\Models\Order::STATUSES as $s)
              <option value="{{ $s }}" @selected($order->status === $s)>{{ ucfirst($s) }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="lbl">Payment status</label>
          <select name="payment_status" class="inp">
            @foreach(\App\Models\Order::PAYMENT_STATUSES as $s)
              <option value="{{ $s }}" @selected($order->payment_status === $s)>{{ ucfirst($s) }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="lbl">Internal note</label>
          <textarea name="internal_note" rows="3" class="inp" placeholder="Add a note for staff…">{{ $order->internal_note }}</textarea>
        </div>
        <button class="w-full btn-primary">Save</button>
      </form>

      <div class="card p-5 text-sm">
        <h3 class="font-semibold mb-3">Customer</h3>
        <p class="font-medium">{{ $order->customer_name }}</p>
        <p class="text-gray-500">{{ $order->customer_phone }}</p>
        @if($order->customer_email)<p class="text-gray-500">{{ $order->customer_email }}</p>@endif
        <p class="text-gray-500 mt-2">{{ $order->shipping_address }}, {{ $order->city }} {{ $order->postal_code }}</p>
      </div>
    </div>
  </div>
</div>
@endsection
