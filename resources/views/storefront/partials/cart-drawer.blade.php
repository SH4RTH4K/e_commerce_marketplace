<aside id="cartDrawer" data-cart-drawer class="fixed top-0 right-0 h-full w-full max-w-sm bg-white z-[80] shadow-2xl translate-x-full transition-transform duration-300 flex flex-col">
  <div class="flex items-center justify-between p-5 border-b border-slate-100 shrink-0">
    <h2 class="font-display text-lg font-extrabold">Your cart</h2>
    <button type="button" data-close-cart class="p-2 text-slate-500 hover:text-ink" aria-label="Close">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
    </button>
  </div>

  <div class="flex-1 min-h-0 relative">
    <div id="cartItems" class="absolute inset-0 overflow-y-auto px-5 text-sm {{ $cartItems->isEmpty() ? 'hidden' : '' }}">
      @include('storefront.partials.cart-drawer-items', ['items' => $cartItems, 'subtotal' => $cartSubtotal, 'footer' => false])
    </div>

    <div id="cartEmpty" class="absolute inset-0 overflow-y-auto px-5 text-center {{ $cartItems->isNotEmpty() ? 'hidden' : '' }}">
      <div class="h-full grid place-items-center">
        <div>
          <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-brand-50 text-brand-500">
            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h15l-1.5 9h-12L6 6Zm0 0-.7-3H3"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/></svg>
          </div>
          <p class="mt-4 font-semibold text-ink">Your cart is empty</p>
          <p class="text-sm text-slate-500">Add something you love.</p>
          <button type="button" data-close-cart class="mt-5 rounded-full bg-brand-500 text-white text-sm font-semibold px-5 py-2.5 hover:bg-brand-600 transition">Continue shopping</button>
        </div>
      </div>
    </div>
  </div>

  <div id="cartFooter" class="p-5 border-t border-slate-100 space-y-3 shrink-0 bg-white relative z-20 {{ $cartItems->isEmpty() ? 'hidden' : '' }}" style="padding-bottom: max(1.25rem, env(safe-area-inset-bottom, 0px));">
    <div class="flex items-center justify-between">
      <span class="text-sm text-slate-500">Subtotal</span>
      <span id="cartSubtotal" class="font-display text-lg font-extrabold cart-total">{{ money($cartSubtotal) }}</span>
    </div>
    <a href="{{ route('cart.index') }}" data-cart-view class="block text-center text-sm font-semibold text-slate-600 hover:text-ink">View cart</a>
    <a href="{{ route('checkout.show') }}" data-cart-checkout class="block text-center rounded-full bg-brand-500 text-white font-bold py-3.5 hover:bg-brand-600 transition relative z-30">Checkout</a>
  </div>
</aside>
