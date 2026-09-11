@php
  $img = $product->imageUrl();
  $stock = max(0, (int) $product->stock_quantity);
  $discount = $product->discount_percent;
  $rating = max(0, min(5, (int) round((float) $product->rating)));
  $ctaAction = setting('product_cta_action', 'checkout') === 'cart' ? 'cart' : 'checkout';
  $cardCta = trim((string) setting('default_cta_text', ''));
  if ($cardCta === '') {
    $cardCta = $ctaAction === 'cart' ? 'Add to cart' : 'ORDER NOW';
  }
@endphp

<article class="product-card relative rounded-lg bg-white overflow-hidden flex flex-col h-full min-w-0">
  <a href="{{ route('product.show', $product) }}" class="block relative overflow-hidden shrink-0">
    @if($discount > 0)
      <span class="absolute left-0 top-0 z-10 bg-brand-500 text-white text-[10px] sm:text-[11px] font-bold px-1.5 sm:px-2 py-0.5">-{{ $discount }}%</span>
    @endif
    <img src="{{ $img }}" class="product-img aspect-square w-full object-cover" alt="{{ $product->name }}" width="400" height="400" loading="lazy" decoding="async" />
  </a>
  <div class="p-2 sm:p-2.5 flex-1 flex flex-col gap-1 min-w-0">
    <a href="{{ route('product.show', $product) }}" class="block truncate text-[13px] sm:text-sm text-ink hover:text-brand-600 leading-snug font-medium">{{ $product->name }}</a>
    <div class="product-card-price">
      <span class="whitespace-nowrap font-display font-extrabold text-brand-600 text-[13px] sm:text-base leading-none">{{ money($product->price) }}</span>
      @if($product->on_sale)
        <span class="whitespace-nowrap text-[11px] text-slate-400 line-through leading-none">{{ money($product->regular_price) }}</span>
      @endif
    </div>
    @if($rating > 0 || ($product->reviews_count ?? 0) > 0)
      <div class="flex items-center gap-1.5 min-w-0 leading-none">
        <span class="stars text-xs tracking-tight shrink-0" aria-label="{{ number_format($product->rating, 1) }} out of 5">
          <span>{{ str_repeat("\u{2605}", $rating) }}</span><span class="empty" style="color:#e5e7eb">{{ str_repeat("\u{2605}", 5 - $rating) }}</span>
        </span>
        @if(($product->reviews_count ?? 0) > 0)
          <span class="text-[10px] sm:text-[11px] text-slate-400 truncate">({{ $product->reviews_count }})</span>
        @endif
      </div>
    @endif
    @if($ctaAction === 'cart')
      <button type="button" class="add-to-cart mt-1 w-full rounded-md border border-brand-500 text-brand-600 text-xs font-bold py-1.5 sm:py-2 hover:bg-brand-500 hover:text-white transition disabled:opacity-50 disabled:cursor-not-allowed" data-product-id="{{ $product->id }}" data-title="{{ $product->name }}" data-open-after @disabled($stock < 1)>
        {{ $stock < 1 ? 'Sold out' : $cardCta }}
      </button>
    @else
      <button type="button" class="order-now mt-1 w-full rounded-md bg-brand-500 text-white text-xs font-bold py-1.5 sm:py-2 hover:bg-brand-600 transition disabled:opacity-50 disabled:cursor-not-allowed" data-order-now data-product-id="{{ $product->id }}" data-title="{{ $product->name }}" data-checkout-url="{{ route('checkout.show') }}" @disabled($stock < 1)>
        {{ $stock < 1 ? 'Sold out' : $cardCta }}
      </button>
    @endif
  </div>
</article>
