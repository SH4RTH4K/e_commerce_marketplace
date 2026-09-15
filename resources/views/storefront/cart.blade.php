@extends('layouts.storefront')

@php $title = 'Shopping Cart'; @endphp

@section('content')
<main class="max-w-[1440px] mx-auto px-5 py-6">
  <h1 class="text-2xl font-extrabold mb-5">Shopping Cart</h1>

  @if($items->isEmpty())
    <div class="bg-white rounded-md p-16 text-center">
      <p class="text-gray-500">Your cart is empty.</p>
      <a href="{{ route('shop') }}" class="inline-block mt-4 text-brand-600 font-medium text-sm">← Continue shopping</a>
    </div>
  @else
    <div class="grid lg:grid-cols-3 gap-4 sm:gap-6">
      <div class="lg:col-span-2">
        <div class="bg-white rounded-xl sm:rounded-md divide-y divide-gray-100 overflow-hidden">
          @foreach($items as $item)
            <article class="p-3.5 sm:p-4">
              {{-- Phone layout --}}
              <div class="sm:hidden">
                <div class="flex gap-3">
                  <a href="{{ route('product.show', $item->slug) }}" class="shrink-0">
                    <img src="{{ image_url($item->image, $item->name) }}" class="h-16 w-16 rounded-lg object-contain border border-gray-100 bg-gray-50" alt="{{ $item->name }}">
                  </a>
                  <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-2">
                      <a href="{{ route('product.show', $item->slug) }}" class="font-medium text-sm leading-snug hover:text-brand-600 line-clamp-2">{{ $item->name }}</a>
                      <form method="POST" action="{{ route('cart.remove') }}" class="shrink-0">
                        @csrf
                        <input type="hidden" name="key" value="{{ $item->key }}">
                        <button type="submit" class="p-1.5 -mr-1 -mt-1 text-gray-400 hover:text-accent-500" aria-label="Remove">
                          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2 -2l1 -12M9 7V4h6v3"/></svg>
                        </button>
                      </form>
                    </div>
                    @if(! empty($item->variant))
                      <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $item->variant }}</p>
                    @endif
                    <p class="text-accent-600 font-bold text-sm mt-1">{{ money($item->price) }}</p>
                  </div>
                </div>
                <div class="mt-3 flex items-center justify-between gap-3">
                  <form method="POST" action="{{ route('cart.update') }}" class="inline-flex items-center border border-gray-300 rounded-lg overflow-hidden">
                    @csrf
                    <input type="hidden" name="key" value="{{ $item->key }}">
                    <button type="submit" name="qty" value="{{ max(0, $item->qty - 1) }}" class="px-3.5 py-2.5 text-gray-600 hover:bg-gray-50 text-base leading-none" aria-label="Decrease">−</button>
                    <span class="w-9 text-center text-sm font-semibold tabular-nums">{{ $item->qty }}</span>
                    <button type="submit" name="qty" value="{{ $item->qty + 1 }}" class="px-3.5 py-2.5 text-gray-600 hover:bg-gray-50 text-base leading-none" aria-label="Increase">+</button>
                  </form>
                  <div class="font-semibold text-sm tabular-nums">{{ money($item->line_total) }}</div>
                </div>
              </div>

              {{-- Desktop layout --}}
              <div class="hidden sm:flex sm:items-center sm:gap-4">
                <a href="{{ route('product.show', $item->slug) }}" class="shrink-0">
                  <img src="{{ image_url($item->image, $item->name) }}" class="h-20 w-20 rounded-md object-contain border border-gray-100 bg-gray-50" alt="{{ $item->name }}">
                </a>
                <div class="flex-1 min-w-0">
                  <a href="{{ route('product.show', $item->slug) }}" class="font-medium hover:text-brand-600">{{ $item->name }}</a>
                  @if(! empty($item->variant))
                    <p class="text-xs text-gray-400 mt-0.5">{{ $item->variant }}</p>
                  @endif
                  <p class="text-accent-600 font-bold mt-1">{{ money($item->price) }}</p>
                </div>
                <form method="POST" action="{{ route('cart.update') }}" class="flex items-center border border-gray-300 rounded overflow-hidden">
                  @csrf
                  <input type="hidden" name="key" value="{{ $item->key }}">
                  <button type="submit" name="qty" value="{{ max(0, $item->qty - 1) }}" class="px-3 py-2 text-gray-600 hover:bg-gray-50" aria-label="Decrease">−</button>
                  <span class="w-10 text-center text-sm font-semibold tabular-nums">{{ $item->qty }}</span>
                  <button type="submit" name="qty" value="{{ $item->qty + 1 }}" class="px-3 py-2 text-gray-600 hover:bg-gray-50" aria-label="Increase">+</button>
                </form>
                <div class="w-28 text-right font-semibold tabular-nums">{{ money($item->line_total) }}</div>
                <form method="POST" action="{{ route('cart.remove') }}">
                  @csrf
                  <input type="hidden" name="key" value="{{ $item->key }}">
                  <button type="submit" class="text-gray-400 hover:text-accent-500 p-1" aria-label="Remove">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2 -2l1 -12M9 7V4h6v3"/></svg>
                  </button>
                </form>
              </div>
            </article>
          @endforeach
        </div>
        <a href="{{ route('shop') }}" class="inline-block mt-3 sm:mt-4 text-brand-600 font-medium text-sm">← Continue shopping</a>
      </div>
      <div>
        <div class="bg-white rounded-xl sm:rounded-md p-4 sm:p-6 lg:sticky lg:top-4">
          <h2 class="font-bold mb-3 sm:mb-4">Order Summary</h2>
          <div class="flex justify-between text-sm text-gray-600 mb-2"><span>Subtotal</span><span class="tabular-nums">{{ money($subtotal) }}</span></div>
          <div class="flex justify-between text-sm text-gray-600 mb-2 gap-3"><span>Shipping</span><span class="text-right text-xs sm:text-sm">Calculated at checkout</span></div>
          <div class="flex justify-between font-bold text-base sm:text-lg border-t border-gray-100 pt-3 sm:pt-4"><span>Total</span><span class="text-accent-600 tabular-nums">{{ money($subtotal) }}</span></div>
          <a href="{{ route('checkout.show') }}" class="block text-center bg-brand-600 hover:bg-brand-700 text-white font-semibold py-3.5 sm:py-3 rounded-lg sm:rounded-md mt-5 sm:mt-6">Proceed to Checkout</a>
        </div>
      </div>
    </div>
  @endif
</main>
@endsection
