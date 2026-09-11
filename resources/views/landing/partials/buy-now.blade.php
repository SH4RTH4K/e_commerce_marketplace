{{-- Shared buy-now form for landing CTAs --}}
@php
  $qty = $qty ?? 1;
  $btnClass = $btnClass ?? '';
  $btnText = $btnText ?? ($c['cta_text'] ?? 'Order Now');
  $formClass = $formClass ?? 'inline';
@endphp
<form method="POST" action="{{ route('cart.buy-now') }}" class="{{ $formClass }}">
  @csrf
  <input type="hidden" name="product_id" value="{{ $product->id }}" />
  <input type="hidden" name="qty" value="{{ $qty }}" />
  <button type="submit" class="{{ $btnClass }}">{{ $btnText }}</button>
</form>
