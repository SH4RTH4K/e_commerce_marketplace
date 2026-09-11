@extends('layouts.admin')
@section('title', 'Product Button')

@section('content')
<div class="space-y-6 max-w-xl">
  <div>
    <h2 class="font-display text-xl font-bold text-ink">Product Button</h2>
    <p class="text-sm text-gray-500 mt-1">Change the label and action used on product cards (homepage &amp; shop).</p>
  </div>

  <form method="POST" action="{{ route('admin.product-cta.update') }}" class="card p-4 sm:p-5 space-y-4">
    @csrf
    @method('PUT')

    <div>
      <label class="lbl">Button text</label>
      <input name="default_cta_text" class="inp" value="{{ old('default_cta_text', $buttonText) }}" required maxlength="40" placeholder="ORDER NOW" />
      @error('default_cta_text')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
      <p class="text-xs text-gray-400 mt-1">Examples: ORDER NOW, Add to cart, Buy Now</p>
    </div>

    <div>
      <label class="lbl">Button action</label>
      <select name="product_cta_action" class="inp">
        <option value="checkout" @selected(old('product_cta_action', $buttonAction) === 'checkout')>Go to checkout (Order Now)</option>
        <option value="cart" @selected(old('product_cta_action', $buttonAction) === 'cart')>Add to cart</option>
      </select>
      @error('product_cta_action')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
      <p class="text-xs text-gray-400 mt-1">Checkout replaces the cart with that product and opens checkout. Add to cart opens the cart drawer.</p>
    </div>

    <div class="pt-2">
      <button class="btn-primary">Save changes</button>
    </div>
  </form>
</div>
@endsection
