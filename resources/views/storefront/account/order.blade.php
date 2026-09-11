@extends('layouts.storefront', ['headerVariant' => 'compact'])
@php $title = 'Order ' . $order->order_number; @endphp

@section('content')
<section class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-10">
  <a href="{{ route('account') }}#orders" class="text-sm font-semibold text-brand-600 hover:underline">&larr; Back to My Orders</a>

  <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="font-display text-2xl font-extrabold">{{ $order->order_number }}</h1>
      <p class="text-sm text-slate-500 mt-1">Placed {{ $order->created_at->format('d M Y, g:i A') }}</p>
    </div>
    <span class="text-xs font-semibold px-3 py-1.5 rounded-full bg-slate-100">{{ ucfirst($order->status) }}</span>
  </div>

  <div class="mt-8 rounded-2xl border border-slate-100 bg-white shadow-soft overflow-hidden">
    <div class="p-5 border-b border-slate-100">
      <h2 class="font-display font-extrabold text-sm">Items purchased</h2>
    </div>
    <div class="divide-y divide-slate-100">
      @foreach($order->items as $item)
        <div class="flex items-center gap-4 p-4">
          <img src="{{ $item->imageUrl() }}" class="h-16 w-14 object-cover bg-slate-100 rounded-lg shrink-0" alt="{{ $item->product_name }}" />
          <div class="flex-1 min-w-0">
            @if($item->product && $item->product->is_published)
              <a href="{{ route('product.show', $item->product) }}" class="font-semibold text-sm hover:text-brand-600">{{ $item->product_name }}</a>
            @else
              <p class="font-semibold text-sm">{{ $item->product_name }}</p>
            @endif
            @if($item->variant)<p class="text-xs text-slate-400">{{ $item->variant }}</p>@endif
            <p class="text-xs text-slate-500 mt-0.5">{{ money($item->unit_price) }} × {{ $item->quantity }}</p>
          </div>
          <span class="font-bold text-sm shrink-0">{{ money($item->line_total) }}</span>
        </div>
      @endforeach
    </div>
    <div class="p-5 border-t border-slate-100 text-sm space-y-1.5">
      <div class="flex justify-between text-slate-600"><span>Subtotal</span><span>{{ money($order->subtotal) }}</span></div>
      @if($order->discount_amount > 0)
        <div class="flex justify-between text-brand-600"><span>Coupon @if($order->coupon_code)({{ $order->coupon_code }})@endif</span><span>−{{ money($order->discount_amount) }}</span></div>
      @endif
      <div class="flex justify-between text-slate-600"><span>Shipping</span><span>{{ money($order->shipping_charge) }}</span></div>
      <div class="flex justify-between text-slate-600"><span>Tax</span><span>{{ money($order->tax) }}</span></div>
      <div class="flex justify-between font-extrabold text-base pt-2 border-t border-slate-100 mt-2"><span>Total</span><span class="text-brand-600">{{ money($order->total) }}</span></div>
    </div>
  </div>

  <div class="mt-6 grid sm:grid-cols-2 gap-4 text-sm">
    <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-soft">
      <h3 class="font-semibold text-xs text-slate-400 mb-2">Delivery</h3>
      <p class="font-semibold">{{ $order->customer_name }}</p>
      <p class="text-slate-500">{{ $order->shipping_address }}, {{ $order->city }}</p>
    </div>
    <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-soft">
      <h3 class="font-semibold text-xs text-slate-400 mb-2">Payment</h3>
      <p class="font-semibold">{{ $order->paymentMethodLabel() }}</p>
      <p class="text-slate-500">{{ ucfirst($order->payment_status) }}</p>
    </div>
  </div>
</section>
@endsection
