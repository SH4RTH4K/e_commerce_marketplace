@extends('layouts.storefront')

@php
  $title = 'Order Confirmed';
@endphp




@section('content')
<section class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-14">
  <div class="text-center">
    <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-100 text-emerald-600">
      <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
    </div>
    <h1 class="mt-5 font-display text-2xl sm:text-3xl font-extrabold">Thank you for your order!</h1>
    @if($order->payment_method === 'cod')
      <p class="mt-2 text-slate-500">Your order <b class="text-ink">{{ $order->order_number }}</b> is confirmed. Pay the product amount on delivery.</p>
    @else
      <p class="mt-2 text-slate-500">Your order <b class="text-ink">{{ $order->order_number }}</b> has been placed and is now <b class="text-amber-600">pending verification</b>.</p>
      @if($order->isMobileBanking())
        <p class="mt-1 text-sm text-slate-500">We'll verify your {{ $order->paymentMethodLabel() }} payment shortly.</p>
      @endif
    @endif
  </div>

  <div class="mt-10 rounded-2xl border border-slate-100 bg-white shadow-soft overflow-hidden">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
      <h2 class="font-display font-extrabold">Order summary</h2>
      <span class="text-xs px-2.5 py-1 rounded-full {{ $order->payment_method === 'cod' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }} font-semibold">{{ $order->status }}</span>
    </div>
    <div class="divide-y divide-slate-100">
      @foreach($order->items as $item)
        <div class="flex items-center gap-4 p-4">
          <img src="{{ $item->imageUrl() }}" class="h-14 w-12 object-cover bg-slate-100 rounded-lg" alt="{{ $item->product_name }}" />
          <div class="flex-1"><p class="font-medium text-sm">{{ $item->product_name }}</p><p class="text-xs text-slate-400">{{ $item->variant }} &middot; {{ money($item->unit_price) }} × {{ $item->quantity }}</p></div>
          <div class="font-medium text-sm">{{ money($item->line_total) }}</div>
        </div>
      @endforeach
    </div>
    <div class="p-5 border-t border-slate-100 text-sm space-y-1.5">
      <div class="flex justify-between text-slate-600"><span>Subtotal</span><span>{{ money($order->subtotal) }}</span></div>
      @if($order->discount_amount > 0)
        <div class="flex justify-between text-brand-600"><span>Coupon @if($order->coupon_code)({{ $order->coupon_code }})@endif</span><span>−{{ money($order->discount_amount) }}</span></div>
      @endif
      <div class="flex justify-between text-slate-600"><span>Shipping ({{ shipping_zone_label($order->shipping_zone) }})</span><span>{{ money($order->shipping_charge) }}</span></div>
      <div class="flex justify-between text-slate-600"><span>Tax</span><span>{{ money($order->tax) }}</span></div>
      @if($order->payment_method === 'cod')
        <div class="flex justify-between font-semibold text-brand-700 pt-2 border-t border-slate-100 mt-2"><span>Pay now (delivery)</span><span>{{ money($order->shipping_charge) }}</span></div>
        <div class="flex justify-between font-semibold text-amber-800"><span>Pay after delivery</span><span>{{ money(max(0, (float) $order->total - (float) $order->shipping_charge)) }}</span></div>
      @endif
      <div class="flex justify-between font-extrabold text-base pt-2 border-t border-slate-100 mt-2"><span>Order total</span><span class="text-brand-600">{{ money($order->total) }}</span></div>
    </div>
  </div>

  <div class="mt-8 grid sm:grid-cols-2 gap-4 text-sm">
    <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-soft">
      <h3 class="font-semibold text-xs text-slate-400 mb-2">Delivery to</h3>
      <p class="font-semibold">{{ $order->customer_name }}</p>
      <p class="text-slate-500">{{ $order->customer_phone }}</p>
      <p class="text-slate-500 mt-1">{{ $order->shipping_address }}, {{ $order->city }} {{ $order->postal_code }}</p>
    </div>
    <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-soft">
      <h3 class="font-semibold text-xs text-slate-400 mb-2">Payment</h3>
      <p class="font-semibold">{{ $order->paymentMethodLabel() }}</p>
      @if($order->payment_method === 'cod')
        <p class="text-slate-500 mt-1">Pay now: {{ money($order->shipping_charge) }} (delivery)</p>
        <p class="text-slate-500">Pay after delivery: {{ money(max(0, (float) $order->total - (float) $order->shipping_charge)) }}</p>
      @elseif($order->isMobileBanking())
        <p class="text-slate-500">Sender: {{ $order->payment_sender_number }}</p>
        <p class="text-slate-500">Txn: {{ $order->payment_txn_id }}</p>
      @endif
      <p class="text-slate-500 mt-1">Status: <span class="font-semibold {{ $order->payment_status === 'verified' ? 'text-emerald-600' : 'text-amber-600' }}">{{ ucfirst($order->payment_status) }}</span></p>
    </div>
  </div>

  <div class="mt-8 text-center">
    <a href="{{ route('shop') }}" class="inline-flex rounded-full bg-brand-600 text-white text-sm font-semibold px-6 py-3 hover:bg-brand-700">Continue shopping</a>
  </div>
</section>
@endsection
