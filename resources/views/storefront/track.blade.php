@extends('layouts.storefront', ['headerVariant' => 'compact'])
@php $title = 'Track Order'; @endphp

@section('content')
<section class="mx-auto max-w-2xl px-4 py-12">
  <h1 class="font-display text-2xl sm:text-3xl font-extrabold mb-2">Track your order</h1>
  <p class="text-slate-500 mb-6">Enter your order number and the phone number used at checkout.</p>

  @if($errors->any())
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ route('track.find') }}" class="rounded-2xl bg-white border border-slate-100 p-6 grid sm:grid-cols-[1fr_1fr_auto] gap-3 items-end shadow-soft">
    @csrf
    <div><label class="block text-sm font-medium mb-1.5">Order number</label><input name="order_number" value="{{ old('order_number') }}" placeholder="ORD-260701-XXXX" required class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" /></div>
    <div><label class="block text-sm font-medium mb-1.5">Phone</label><input name="phone" value="{{ old('phone') }}" placeholder="01XXX-XXXXXX" required class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" /></div>
    <button class="rounded-full bg-brand-600 text-white font-semibold px-6 py-3 text-sm hover:bg-brand-700">Track</button>
  </form>

  @isset($order)
    @if($order)
      @php
        $steps = ['pending' => 'Placed', 'confirmed' => 'Confirmed', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];
        $order_index = array_search($order->status, array_keys($steps));
        $cancelled = $order->status === 'cancelled';
      @endphp
      <div class="mt-8 rounded-2xl bg-white border border-slate-100 p-6 shadow-soft">
        <div class="flex items-center justify-between flex-wrap gap-2 mb-6">
          <div>
            <p class="font-display text-lg font-extrabold">{{ $order->order_number }}</p>
            <p class="text-sm text-slate-500">Placed {{ $order->created_at->format('d M Y') }} &middot; {{ money($order->total) }}</p>
          </div>
          <span class="text-xs font-semibold px-3 py-1.5 rounded-full {{ $cancelled ? 'bg-red-100 text-red-700' : 'bg-brand-50 text-brand-700' }}">{{ ucfirst($order->status) }} · Payment {{ ucfirst($order->payment_status) }}</span>
        </div>

        @if($cancelled)
          <p class="text-sm text-red-600 font-semibold">This order was cancelled.</p>
        @else
          <div class="flex items-center">
            @foreach($steps as $key => $label)
              @php $done = $order_index !== false && $loop->index <= $order_index; @endphp
              <div class="flex-1 flex flex-col items-center relative">
                @if(!$loop->first)<div class="absolute right-1/2 top-3 h-0.5 w-full {{ $done ? 'bg-brand-600' : 'bg-slate-200' }}"></div>@endif
                <span class="relative z-10 grid h-6 w-6 place-items-center rounded-full text-[10px] font-bold {{ $done ? 'bg-brand-600 text-white' : 'bg-slate-200 text-slate-500' }}">{{ $loop->iteration }}</span>
                <span class="mt-2 text-[11px] font-semibold {{ $done ? 'text-ink' : 'text-slate-400' }}">{{ $label }}</span>
              </div>
            @endforeach
          </div>
        @endif

        <div class="mt-6 pt-5 border-t border-slate-100 divide-y divide-slate-100">
          @foreach($order->items as $item)
            <div class="flex items-center gap-3 py-3">
              <img src="{{ $item->imageUrl() }}" class="h-12 w-12 rounded-xl object-cover bg-slate-100" alt="{{ $item->product_name }}">
              <div class="flex-1 text-sm"><p class="font-semibold">{{ $item->product_name }}</p><p class="text-slate-400 text-xs">{{ money($item->unit_price) }} × {{ $item->quantity }}</p></div>
              <span class="text-sm font-bold text-brand-700">{{ money($item->line_total) }}</span>
            </div>
          @endforeach
        </div>
      </div>
    @endif
  @endisset
</section>
@endsection
