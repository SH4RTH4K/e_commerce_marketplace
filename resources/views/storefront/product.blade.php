@extends('layouts.storefront')

@php
    $title = $product->name;
    $rawTitle = $product->meta_title ?: null;
    $metaDescription = $product->meta_description ?: $product->short_description;
    $metaKeywords = $product->meta_keywords;
    $ogImage = $product->imageUrl();
    $ogType = 'product';
    $bulletSpecs = collect($product->specificationBullets(6));
    if ($bulletSpecs->isEmpty()) {
        $bulletSpecs = collect(preg_split('/\r\n|\r|\n/', (string) ($product->short_description ?: '')))
            ->map(fn ($l) => trim($l, " \t-•"))
            ->filter()
            ->take(6)
            ->values();
    }
    $specRows = $product->specificationRows();
    $whatsapp = preg_replace('/\D+/', '', (string) setting('contact_phone', ''));
@endphp

@section('content')
<main class="max-w-[1440px] mx-auto px-5 py-6">
  <nav class="text-sm text-gray-500 mb-4">
    <a href="{{ route('home') }}" class="hover:text-brand-600">Home</a>
    /
    <a href="{{ route('shop.category', $product->category) }}" class="hover:text-brand-600">{{ $product->category?->name }}</a>
    /
    <span class="text-gray-700">{{ $product->name }}</span>
  </nav>

  @php
    $flashError = session('error') ?: $errors->first('cart');
    $flashValidation = [];
    foreach ($errors->all() as $msg) {
      if ($flashError && $msg === $flashError) {
        continue;
      }
      $flashValidation[] = $msg;
    }
    $flashValidation = array_values(array_unique($flashValidation));
  @endphp
  @if($flashError || count($flashValidation))
    <div class="mb-4 space-y-2" role="alert">
      @if($flashError)
        <div class="rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm font-medium px-4 py-3">{{ $flashError }}</div>
      @endif
      @foreach($flashValidation as $msg)
        <div class="rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm font-medium px-4 py-3">{{ $msg }}</div>
      @endforeach
    </div>
  @endif

  <div class="bg-white rounded-lg p-6 grid lg:grid-cols-2 gap-10">
    {{-- Gallery --}}
    <div>
      <div class="relative border border-gray-200 rounded-lg h-[420px] flex items-center justify-center">
        @if($product->on_sale)
          <span class="absolute top-3 left-3 bg-accent-500 text-white text-xs font-bold px-2 py-1 rounded">{{ $product->discount_percent }}% OFF</span>
        @endif
        <img id="galleryMain" data-gallery-main src="{{ $product->imageUrl() }}" class="max-h-[360px] object-contain" alt="{{ $product->name }}" />
      </div>
      @if($product->images->count() > 0)
        <div class="flex gap-3 mt-4 overflow-x-auto">
          @foreach($product->images as $img)
            <button type="button" data-thumb="{{ $img->url() }}" class="rounded-md border {{ $loop->first ? 'border-2 border-brand-600' : 'border border-gray-200' }} p-1 shrink-0">
              <img src="{{ $img->url() }}" class="h-16 w-16 object-contain" alt="{{ $img->alt }}">
            </button>
          @endforeach
        </div>
      @endif
    </div>

    {{-- Info --}}
    <div>
      @if($product->stock_quantity > 0)
        <span class="inline-block bg-blue-50 text-brand-600 text-xs font-semibold px-3 py-1 rounded">In Stock</span>
      @else
        <span class="inline-block bg-red-50 text-accent-600 text-xs font-semibold px-3 py-1 rounded">Out of Stock</span>
      @endif

      <h1 class="text-2xl font-extrabold mt-3">{{ $product->name }}</h1>
      <div class="flex items-center gap-2 mt-2 text-sm">
        <span class="text-yellow-400 tracking-tight">{{ str_repeat("\u{2605}", max(0, (int) round($product->rating))) }}</span><span class="text-gray-300 tracking-tight">{{ str_repeat("\u{2605}", max(0, 5 - (int) round($product->rating))) }}</span>
        <span class="text-gray-400">({{ number_format($product->rating, 1) }} / 5 based on {{ $product->reviews_count }} reviews)</span>
      </div>

      @php
        $initialVariant = collect([
          isset($sizes) && $sizes->isNotEmpty() ? 'Size: '.$sizes->first()->value : null,
          isset($colors) && $colors->isNotEmpty() ? 'Color: '.$colors->first()->value : null,
          isset($weights) && $weights->isNotEmpty() ? 'Weight: '.$weights->first()->value : null,
        ])->filter()->implode(', ');
        $initialPrice = $product->unitPriceForVariant($initialVariant !== '' ? $initialVariant : null);
        $initialCompare = $product->compareAtPriceForVariant($initialVariant !== '' ? $initialVariant : null);
        $inStock = $product->stock_quantity > 0;
        $oldQty = max(1, min(99, (int) old('qty', 1)));
      @endphp
      <div
        id="pdPricing"
        class="flex flex-wrap items-center gap-3 mt-4"
        data-base-price="{{ (float) $product->price }}"
        data-regular-price="{{ (float) $product->regular_price }}"
        data-on-sale="{{ $product->on_sale ? '1' : '0' }}"
      >
        <span id="pdPrice" class="text-3xl font-extrabold text-brand-600">{{ money($initialPrice) }}</span>
        <span id="pdCompare" class="text-gray-400 line-through {{ $initialCompare === null ? 'hidden' : '' }}">{{ $initialCompare !== null ? money($initialCompare) : '' }}</span>
        <span id="pdDiscount" class="bg-red-100 text-accent-600 text-xs font-bold px-2 py-1 rounded {{ $product->on_sale ? '' : 'hidden' }}">-{{ $product->discount_percent }}%</span>
      </div>

      @if($bulletSpecs->isNotEmpty())
        <ul class="mt-5 text-sm text-gray-600 space-y-1.5 list-disc list-inside">
          @foreach($bulletSpecs as $spec)
            <li>{{ $spec }}</li>
          @endforeach
        </ul>
      @elseif($product->short_description)
        <p class="mt-5 text-sm text-gray-600">{{ $product->short_description }}</p>
      @endif

      <hr class="my-6" />

      {{-- Native POST purchase form (works without JS) --}}
      <form id="pdPurchaseForm" method="POST" action="{{ route('cart.add') }}" class="space-y-4" data-purchase-form data-product-title="{{ $product->name }}">
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id }}" />
        <input type="hidden" name="variant" id="pdVariant" value="{{ old('variant', $initialVariant) }}" />

        @if($colors->isNotEmpty())
          <div class="mt-0" data-variant-group="Color">
            <p class="text-sm font-medium mb-2">Color</p>
            <div class="flex flex-wrap gap-2">
              @foreach($colors as $v)
                <button type="button" class="variant-btn px-4 py-1.5 rounded-md text-sm {{ $loop->first ? 'border-2 border-brand-600 text-brand-600 font-medium is-selected' : 'border border-gray-300' }}" data-value="{{ $v->value }}" data-price-delta="{{ (float) $v->price_delta }}">{{ $v->value }}</button>
              @endforeach
            </div>
          </div>
        @endif

        @if($sizes->isNotEmpty())
          <div class="mt-4" data-variant-group="Size">
            <p class="text-sm font-medium mb-2">Size</p>
            <div class="flex flex-wrap gap-2">
              @foreach($sizes as $v)
                <button type="button" class="variant-btn px-4 py-1.5 rounded-md text-sm {{ $loop->first ? 'border-2 border-brand-600 text-brand-600 font-medium is-selected' : 'border border-gray-300' }}" data-value="{{ $v->value }}" data-price-delta="{{ (float) $v->price_delta }}">{{ $v->value }}</button>
              @endforeach
            </div>
          </div>
        @endif

        @if($weights->isNotEmpty())
          <div class="mt-4" data-variant-group="Weight">
            <p class="text-sm font-medium mb-2">Option</p>
            <div class="flex flex-wrap gap-2">
              @foreach($weights as $v)
                <button type="button" class="variant-btn px-4 py-1.5 rounded-md text-sm {{ $loop->first ? 'border-2 border-brand-600 text-brand-600 font-medium is-selected' : 'border border-gray-300' }}" data-value="{{ $v->value }}" data-price-delta="{{ (float) $v->price_delta }}">{{ $v->value }}</button>
              @endforeach
            </div>
          </div>
        @endif

        <div class="mt-6 flex items-center gap-3">
          <div data-qty data-stepper class="flex items-center border border-gray-300 rounded-md overflow-hidden">
            <button type="button" data-step="-1" data-dec class="px-4 py-3 text-gray-600 hover:bg-gray-100" aria-label="Decrease quantity">−</button>
            <input
              id="pdQty"
              name="qty"
              type="number"
              min="1"
              max="99"
              value="{{ $oldQty }}"
              inputmode="numeric"
              class="w-12 text-center border-0 focus:ring-0 focus:outline-none"
              required
            />
            <button type="button" data-step="1" data-inc class="px-4 py-3 text-gray-600 hover:bg-gray-100" aria-label="Increase quantity">+</button>
          </div>

          <button
            type="submit"
            name="intent"
            value="buy"
            formaction="{{ route('cart.buy-now') }}"
            formmethod="POST"
            class="flex-1 bg-brand-600 hover:bg-brand-700 text-white font-bold py-3.5 rounded-md disabled:opacity-50 disabled:cursor-not-allowed"
            @disabled(! $inStock)
          >ORDER NOW</button>
        </div>

        <div class="mt-3 {{ $whatsapp ? 'grid grid-cols-2 gap-3' : '' }}">
          <button
            type="submit"
            name="intent"
            value="cart"
            class="{{ $whatsapp ? '' : 'w-full' }} bg-accent-500 hover:bg-accent-600 text-white font-bold py-3 rounded-md text-center disabled:opacity-50 disabled:cursor-not-allowed"
            @disabled(! $inStock)
          >Add to Cart</button>
          @if($whatsapp)
            <a href="https://wa.me/{{ $whatsapp }}?text={{ urlencode('Hi, I want to buy: '.$product->name) }}" target="_blank" rel="noopener" class="block bg-green-500 hover:bg-green-600 text-white font-bold py-3 rounded-md text-center">WhatsApp</a>
          @endif
        </div>
      </form>

      <div class="mt-6 text-sm text-gray-500 space-y-1">
        <p>SKU: <span class="text-gray-700">{{ $product->sku ?: 'N/A' }}</span></p>
        <p>Category: <a href="{{ route('shop.category', $product->category) }}" class="text-brand-600">{{ $product->category?->name }}</a></p>
      </div>
    </div>
  </div>

  {{-- Tabs --}}
  <div class="bg-white rounded-lg p-6 mt-6" data-tabs>
    <div class="border-b border-gray-200 flex gap-8">
      <button type="button" data-tab="desc" class="tab-active py-3 -mb-px border-b-2 font-medium">Description</button>
      <button type="button" data-tab="rev" class="py-3 -mb-px border-b-2 border-transparent text-gray-500 font-medium">Reviews ({{ $reviews->total() }})</button>
    </div>

    <div data-pane="desc" data-panel="desc" class="py-6">
      <p class="text-gray-500 mb-4">Specification</p>
      <table class="w-full text-sm border border-gray-100 table-fixed sm:table-auto">
        <tbody>
          <tr class="bg-gray-50"><td colspan="2" class="px-3 sm:px-4 py-2.5 font-bold text-brand-700">Basic Information</td></tr>
          <tr class="border-t border-gray-100">
            <td class="px-3 sm:px-4 py-2.5 w-[32%] sm:w-72 text-gray-600 align-top">Brand</td>
            <td class="px-3 sm:px-4 py-2.5 break-words">{{ $product->brand ?: "\u{2014}" }}</td>
          </tr>
          <tr class="border-t border-gray-100">
            <td class="px-3 sm:px-4 py-2.5 text-gray-600 align-top">Category</td>
            <td class="px-3 sm:px-4 py-2.5 break-words">{{ $product->category?->name }}</td>
          </tr>
          <tr class="border-t border-gray-100">
            <td class="px-3 sm:px-4 py-2.5 text-gray-600 align-top">Unit</td>
            <td class="px-3 sm:px-4 py-2.5 break-words">{{ $product->unit ?: "\u{2014}" }}</td>
          </tr>
          <tr class="border-t border-gray-100">
            <td class="px-3 sm:px-4 py-2.5 text-gray-600 align-top">SKU</td>
            <td class="px-3 sm:px-4 py-2.5 break-words">{{ $product->sku ?: 'N/A' }}</td>
          </tr>
          @if(! empty($specRows))
            <tr class="bg-gray-50 border-t border-gray-100"><td colspan="2" class="px-3 sm:px-4 py-2.5 font-bold text-brand-700">Product Specifications</td></tr>
            @foreach($specRows as $row)
              <tr class="border-t border-gray-100">
                <td class="px-3 sm:px-4 py-2.5 text-gray-600 align-top">{{ $row['label'] !== '' ? $row['label'] : 'Detail' }}</td>
                <td class="px-3 sm:px-4 py-2.5 break-words">{{ $row['value'] !== '' ? $row['value'] : "\u{2014}" }}</td>
              </tr>
            @endforeach
          @endif
        </tbody>
      </table>
      <h3 class="text-lg font-bold mt-8 mb-2">Description</h3>
      <div class="text-gray-600 max-w-4xl space-y-3">
        @foreach(preg_split('/\n\n+/', (string) $product->description) as $para)
          @if(trim($para))<p>{{ $para }}</p>@endif
        @endforeach
      </div>
    </div>

    <div id="reviews" data-pane="rev" data-panel="rev" class="py-6 hidden text-gray-600 space-y-6">
      @if($reviews->isEmpty())
        <p>No reviews yet. Be the first to review this product.</p>
      @else
        <div class="space-y-4">
          @foreach($reviews as $review)
            <article class="border border-gray-100 rounded-md p-4">
              <div class="text-sm tracking-tight"><span class="text-yellow-400">{{ str_repeat("\u{2605}", $review->rating) }}</span><span class="text-gray-300">{{ str_repeat("\u{2605}", 5 - $review->rating) }}</span></div>
              @if($review->title)<h3 class="font-bold mt-1 text-ink">{{ $review->title }}</h3>@endif
              <p class="mt-2 whitespace-pre-line">{{ $review->body }}</p>
              <p class="mt-2 text-xs text-gray-400">{{ $review->author_name }} &middot; {{ $review->created_at?->format('d M Y') }}</p>
            </article>
          @endforeach
        </div>
      @endif

      <div class="border border-gray-200 rounded-md p-5">
        <h3 class="font-bold text-ink">Write a review</h3>
        @guest
          <p class="mt-2 text-sm text-gray-500">Login is mandatory to leave a review.</p>
          <a href="{{ route('login', ['redirect' => url()->current().'#reviews']) }}" class="inline-block mt-3 bg-brand-600 text-white font-bold px-5 py-2.5 rounded-md">Sign in to review</a>
        @else
          @if(! $canReview)
            <p class="mt-2 text-sm">You already submitted a review for this product.</p>
          @else
            <form method="POST" action="{{ route('product.reviews.store', $product) }}" class="mt-4 space-y-3">
              @csrf
              <div>
                <label class="block text-sm font-medium mb-1">Rating</label>
                <select name="rating" class="border border-gray-300 rounded-md px-3 py-2 text-sm" required>
                  @for($i = 5; $i >= 1; $i--)
                    <option value="{{ $i }}" @selected((int) old('rating', 5) === $i)>{{ $i }} stars</option>
                  @endfor
                </select>
              </div>
              <input type="hidden" name="author_name" value="{{ auth()->user()->name }}" />
              <input type="hidden" name="author_email" value="{{ auth()->user()->email }}" />
              <div>
                <label class="block text-sm font-medium mb-1">Title</label>
                <input name="title" value="{{ old('title') }}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Review</label>
                <textarea name="body" rows="4" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">{{ old('body') }}</textarea>
              </div>
              <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-bold px-5 py-2.5 rounded-md">Submit review</button>
            </form>
          @endif
        @endguest
      </div>
    </div>
  </div>

  @if($related->isNotEmpty())
    <div class="bg-white rounded-lg p-5 mt-6 flex items-center justify-between">
      <h2 class="text-xl font-extrabold text-accent-500">Related Product</h2>
      <a href="{{ route('shop') }}" class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-sm font-bold px-5 py-2.5 rounded-lg shadow-sm shadow-brand-600/20 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200">
        See more
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      </a>
    </div>
    <div class="product-grid sm:grid-cols-3 lg:grid-cols-5 mt-5">
      @foreach($related as $rel)
        @include('storefront.partials.product-card', ['product' => $rel])
      @endforeach
    </div>
  @endif
</main>
@endsection

@push('scripts')
<script>
(function () {
  const form = document.getElementById('pdPurchaseForm');
  const variantInput = document.getElementById('pdVariant');
  const pdPricing = document.getElementById('pdPricing');
  const formatMoney = (n) => '৳' + Math.round(Number(n) || 0).toLocaleString('en-BD');

  function selectedVariantLabel() {
    const parts = [];
    document.querySelectorAll('[data-variant-group]').forEach((group) => {
      const sel = group.querySelector('.variant-btn.is-selected');
      if (sel) parts.push(group.dataset.variantGroup + ': ' + sel.dataset.value);
    });
    return parts.join(', ');
  }

  function syncVariant() {
    if (!variantInput) return;
    variantInput.value = selectedVariantLabel();
  }

  function updateProductPrice() {
    if (!pdPricing) return;
    const base = parseFloat(pdPricing.dataset.basePrice || '0') || 0;
    const regular = parseFloat(pdPricing.dataset.regularPrice || '0') || 0;
    const onSale = pdPricing.dataset.onSale === '1';
    let delta = 0;
    document.querySelectorAll('[data-variant-group] .variant-btn.is-selected').forEach((btn) => {
      delta += parseFloat(btn.dataset.priceDelta || '0') || 0;
    });
    const price = Math.max(0, base + delta);
    const compare = onSale ? Math.max(0, regular + delta) : null;
    const priceEl = document.getElementById('pdPrice');
    const compareEl = document.getElementById('pdCompare');
    if (priceEl) priceEl.textContent = formatMoney(price);
    if (compareEl) {
      if (compare !== null) {
        compareEl.textContent = formatMoney(compare);
        compareEl.classList.remove('hidden');
      } else {
        compareEl.classList.add('hidden');
      }
    }
  }

  document.querySelectorAll('[data-variant-group] .variant-btn').forEach((b) => {
    b.addEventListener('click', () => {
      const group = b.closest('[data-variant-group]');
      group.querySelectorAll('.variant-btn').forEach((x) => {
        x.classList.remove('is-selected', 'border-2', 'border-brand-600', 'text-brand-600', 'font-medium');
        x.classList.add('border', 'border-gray-300');
      });
      b.classList.add('is-selected', 'border-2', 'border-brand-600', 'text-brand-600', 'font-medium');
      b.classList.remove('border-gray-300');
      syncVariant();
      updateProductPrice();
    });
  });

  syncVariant();
  updateProductPrice();

  if (form) {
    form.addEventListener('submit', function (e) {
      syncVariant();
      const qtyEl = form.querySelector('#pdQty');
      if (qtyEl) {
        qtyEl.value = String(Math.max(1, Math.min(99, parseInt(qtyEl.value, 10) || 1)));
      }

      const submitter = e.submitter;
      const actionUrl = (submitter && submitter.getAttribute('formaction')) || form.getAttribute('action') || '';
      const isBuyNow = /buy-now/i.test(actionUrl) || (submitter && submitter.value === 'buy');

      // Order Now → normal navigation to checkout
      if (isBuyNow) return;

      // Add to Cart → AJAX + toast + cart drawer
      e.preventDefault();
      const sf = window.Storefront;
      if (!sf || typeof sf.addToCart !== 'function') {
        form.submit();
        return;
      }

      const productId = form.querySelector('[name="product_id"]')?.value;
      const qty = Math.max(1, parseInt(qtyEl?.value || '1', 10) || 1);
      const variant = (variantInput?.value || '').trim() || null;
      const title = form.dataset.productTitle || '';

      sf.addToCart(productId, qty, variant, title, true);
    });
  }

  const wantReviews = location.hash === '#reviews';
  if (wantReviews) {
    const btn = document.querySelector('[data-tabs] [data-tab="rev"]');
    if (btn) btn.click();
    document.getElementById('reviews')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
})();
</script>
@endpush
