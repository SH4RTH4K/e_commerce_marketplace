@extends('layouts.storefront')

@php
    if ($activeCategory) {
        $rawTitle = $activeCategory->meta_title ?: null;
        $title = $activeCategory->name;
        $metaDescription = $activeCategory->meta_description;
        $metaKeywords = $activeCategory->meta_keywords;
    } else {
        $title = $q ? 'Search: ' . $q : 'Shop';
    }
    $total = $products->total();
    $maxPrice = max(1000, (int) ($priceCeiling ?? 100000));
    $minVal = request()->filled('min') ? max(0, (int) request('min')) : 0;
    $maxVal = request()->filled('max') ? min($maxPrice, max(0, (int) request('max'))) : $maxPrice;
    $brands = $brands ?? collect();
    $activeBrand = request('brand');
    $formAction = $activeCategory ? route('shop.category', $activeCategory) : route('shop');
@endphp

@section('content')
<main class="max-w-[1440px] mx-auto px-4 sm:px-5 py-5 sm:py-6">
  <div class="flex flex-col lg:flex-row gap-5 lg:gap-6">

    {{-- ===== FILTER SIDEBAR (drawer on phone) ===== --}}
    <aside id="filterPanel" class="filter-panel fixed inset-y-0 left-0 z-[80] w-[300px] max-w-[90%] -translate-x-full overflow-y-auto bg-white p-4 shadow-2xl lg:static lg:z-auto lg:w-[270px] lg:shrink-0 lg:translate-x-0 lg:overflow-visible lg:p-0 lg:shadow-none lg:bg-transparent">
      <form method="GET" action="{{ $formAction }}" id="shopFilters" class="bg-white rounded-md border border-gray-200 p-4 space-y-5">
        @if($minRating)
          <input type="hidden" name="min_rating" id="minRatingField" value="{{ $minRating }}">
        @endif
        <div class="flex items-center justify-between lg:hidden border-b border-gray-100 pb-3 -mt-1">
          <span class="font-extrabold text-ink">Filters</span>
          <button type="button" data-close-filter class="grid h-8 w-8 place-items-center rounded-md bg-gray-100 text-gray-500" aria-label="Close">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
          </button>
        </div>

        {{-- Search Products --}}
        <div>
          <h3 class="text-sm font-bold text-ink mb-2">Search Products</h3>
          <div class="flex overflow-hidden rounded border border-gray-200 focus-within:border-brand-600">
            <input type="text" name="q" value="{{ $q }}" placeholder="Search…" class="flex-1 min-w-0 px-3 py-2 text-sm focus:outline-none" />
            <button type="submit" class="bg-brand-600 text-white px-3 hover:bg-brand-700" aria-label="Search">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
            </button>
          </div>
        </div>

        {{-- Categories --}}
        <div>
          <h3 class="text-sm font-bold text-ink mb-2">Categories</h3>
          <ul class="space-y-1.5 text-sm">
            <li>
              <a href="{{ route('shop', request()->except(['page'])) }}" class="flex items-center gap-2.5 text-gray-700 hover:text-brand-600 {{ !$activeCategory ? 'text-brand-600 font-semibold' : '' }}">
                <span class="shop-box {{ !$activeCategory ? 'is-checked' : '' }}" aria-hidden="true"></span>
                <span class="flex-1 truncate">All Products</span>
                <span class="text-gray-400 text-xs">({{ $allProductsCount ?? $categories->sum('products_count') }})</span>
              </a>
            </li>
            @foreach($categories as $cat)
              <li>
                <a href="{{ route('shop.category', ['category' => $cat] + request()->except(['page'])) }}"
                   class="flex items-center gap-2.5 text-gray-700 hover:text-brand-600 {{ $activeCategory?->id === $cat->id ? 'text-brand-600 font-semibold' : '' }}">
                  <span class="shop-box {{ $activeCategory?->id === $cat->id ? 'is-checked' : '' }}" aria-hidden="true"></span>
                  <span class="flex-1 truncate">{{ $cat->name }}</span>
                  <span class="text-gray-400 text-xs">({{ $cat->products_count }})</span>
                </a>
              </li>
            @endforeach
          </ul>
        </div>

        {{-- Price Range --}}
        <div>
          <h3 class="text-sm font-bold text-ink mb-2">Price Range</h3>
          <div class="flex items-center gap-2">
            <div class="flex items-center flex-1 border border-gray-200 rounded overflow-hidden">
              <span class="pl-2 text-xs text-gray-400">{{ currency_symbol() }}</span>
              <input type="number" name="min" min="0" max="{{ $maxPrice }}" value="{{ request()->filled('min') ? $minVal : '' }}" placeholder="0" class="w-full min-w-0 px-1.5 py-2 text-sm focus:outline-none" />
            </div>
            <span class="text-gray-400 shrink-0">–</span>
            <div class="flex items-center flex-1 border border-gray-200 rounded overflow-hidden">
              <span class="pl-2 text-xs text-gray-400">{{ currency_symbol() }}</span>
              <input type="number" name="max" min="0" max="{{ $maxPrice }}" value="{{ request()->filled('max') ? $maxVal : '' }}" placeholder="{{ number_format($maxPrice) }}" class="w-full min-w-0 px-1.5 py-2 text-sm focus:outline-none" />
            </div>
          </div>
        </div>

        {{-- Average Rating --}}
        <div>
          <h3 class="text-sm font-bold text-ink mb-2">Average Rating</h3>
          <ul class="space-y-1.5 text-sm">
            @foreach([5, 4, 3, 2, 1] as $stars)
              @php $isActive = (string) ($minRating ?? '') === (string) $stars; @endphp
              <li>
                <a href="{{ request()->fullUrlWithQuery(['min_rating' => $stars, 'page' => null]) }}"
                   class="flex items-center gap-2.5 text-gray-700 hover:text-brand-600 {{ $isActive ? 'text-brand-600 font-semibold' : '' }}">
                  <span class="shop-box {{ $isActive ? 'is-checked' : '' }}" aria-hidden="true"></span>
                  <span class="text-amber-400 tracking-tight leading-none">{{ str_repeat("\u{2605}", $stars) }}</span>
                  <span class="text-gray-300 tracking-tight leading-none">{{ str_repeat("\u{2605}", 5 - $stars) }}</span>
                  <span class="text-xs text-gray-500">&amp; Up</span>
                </a>
              </li>
            @endforeach
            @if($minRating)
              <li>
                <a href="{{ request()->fullUrlWithQuery(['min_rating' => null, 'page' => null]) }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700 pl-6">Clear rating</a>
              </li>
            @endif
          </ul>
        </div>

        {{-- Product Tags (brands) --}}
        <div>
          <h3 class="text-sm font-bold text-ink mb-2">Product Tags</h3>
          @if($brands->isNotEmpty())
            <ul class="space-y-1.5 max-h-36 overflow-y-auto text-sm">
              @foreach($brands as $brand)
                <li>
                  <label class="flex items-center gap-2.5 cursor-pointer text-gray-700 hover:text-brand-600">
                    <input type="radio" name="brand" value="{{ $brand }}" class="shop-check" @checked($activeBrand === $brand)>
                    <span class="truncate">{{ $brand }}</span>
                  </label>
                </li>
              @endforeach
            </ul>
          @else
            <p class="text-xs text-gray-400">No tags yet</p>
          @endif
        </div>

        {{-- Product Status --}}
        <div>
          <h3 class="text-sm font-bold text-ink mb-2">Product Status</h3>
          <ul class="space-y-1.5 text-sm">
            <li>
              <label class="flex items-center gap-2.5 cursor-pointer text-gray-700 hover:text-brand-600">
                <input type="checkbox" name="on_sale" value="1" class="shop-check" @checked(request('on_sale') || request('flash'))>
                <span>On Sale</span>
              </label>
            </li>
            <li>
              <label class="flex items-center gap-2.5 cursor-pointer text-gray-700 hover:text-brand-600">
                <input type="checkbox" name="in_stock" value="1" class="shop-check" @checked(request('in_stock'))>
                <span>In Stock</span>
              </label>
            </li>
          </ul>
        </div>

        <div class="pt-1 space-y-2">
          <button type="submit" class="w-full rounded bg-brand-600 text-white text-sm font-bold py-2.5 hover:bg-brand-700 transition">
            Apply Filters
          </button>
          <a href="{{ $activeCategory ? route('shop.category', $activeCategory) : route('shop') }}" class="block text-center text-sm font-semibold text-accent-600 hover:text-accent-500">
            Reset All
          </a>
        </div>
      </form>
    </aside>

    {{-- ===== RESULTS ===== --}}
    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-3 mb-4 lg:hidden">
        <button type="button" data-open-filter class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-white px-3.5 py-2 text-sm font-semibold text-ink shadow-sm">
          <svg class="h-4 w-4 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 6h18M6 12h12M10 18h4"/></svg>
          Filters
        </button>
        <p class="text-sm text-gray-500"><span class="font-semibold text-ink">{{ $total }}</span> products</p>
      </div>

      <div class="bg-white rounded-md border border-gray-100 px-4 py-3 mb-4 flex items-center justify-between gap-3">
        <h1 class="text-base sm:text-lg font-extrabold text-accent-500 truncate">
          @if($activeCategory)
            Category: {{ $activeCategory->name }}
          @elseif($q)
            Search: {{ $q }}
          @else
            All Products
          @endif
        </h1>
        <form method="GET" action="{{ $formAction }}" class="shrink-0 hidden sm:block">
          @foreach(request()->except(['sort', 'page']) as $key => $val)
            @if(is_scalar($val) && $val !== '')
              <input type="hidden" name="{{ $key }}" value="{{ $val }}">
            @endif
          @endforeach
          <select name="sort" onchange="this.form.submit()" class="border border-gray-200 rounded text-sm px-3 py-1.5 focus:outline-none focus:border-brand-600">
            <option value="">Sort: Popular</option>
            <option value="newest" @selected($sort==='newest')>Newest</option>
            <option value="price_low" @selected($sort==='price_low')>Price: Low to High</option>
            <option value="price_high" @selected($sort==='price_high')>Price: High to Low</option>
            <option value="rating" @selected($sort==='rating')>Top rated</option>
          </select>
        </form>
      </div>

      @if($products->isEmpty())
        <div class="bg-white rounded-md border border-dashed border-gray-200 p-12 sm:p-16 text-center text-gray-500">
          <p class="font-semibold text-ink">No products found.</p>
          <a href="{{ route('shop') }}" class="mt-3 inline-flex text-brand-600 font-semibold text-sm">Browse all products</a>
        </div>
      @else
        <div class="product-grid sm:grid-cols-3 xl:grid-cols-4">
          @foreach($products as $product)
            @include('storefront.partials.product-card', ['product' => $product])
          @endforeach
        </div>

        @if($products->hasPages())
          <nav class="mt-8 flex items-center justify-center gap-1.5">
            @if($products->onFirstPage())
              <span class="px-3 py-2 border border-gray-200 rounded bg-white text-gray-300 text-sm">Prev</span>
            @else
              <a href="{{ $products->previousPageUrl() }}" class="px-3 py-2 border border-gray-200 rounded bg-white text-sm hover:border-brand-600">Prev</a>
            @endif
            @foreach($products->getUrlRange(max(1, $products->currentPage() - 2), min($products->lastPage(), $products->currentPage() + 2)) as $page => $url)
              <a href="{{ $url }}" class="min-w-9 h-9 grid place-items-center rounded text-sm {{ $page == $products->currentPage() ? 'bg-brand-600 text-white font-semibold' : 'border border-gray-200 bg-white hover:border-brand-600' }}">{{ $page }}</a>
            @endforeach
            @if($products->hasMorePages())
              <a href="{{ $products->nextPageUrl() }}" class="px-3 py-2 border border-gray-200 rounded bg-white text-sm hover:border-brand-600">Next</a>
            @else
              <span class="px-3 py-2 border border-gray-200 rounded bg-white text-gray-300 text-sm">Next</span>
            @endif
          </nav>
        @endif
      @endif
    </div>
  </div>
</main>
@endsection
