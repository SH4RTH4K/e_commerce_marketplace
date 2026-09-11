@extends('layouts.storefront')

@section('content')
  @php
    $ctaDefault = setting('default_cta_text', 'Shop now');
    $viewMore = setting('home_view_more_label', 'View all');
    $featuredProducts = $trending;
    $flashEndsAt = setting('flash_sale_ends_at');
    $flashEndsIso = $flashEndsAt ? \Illuminate\Support\Carbon::parse($flashEndsAt)->toIso8601String() : null;
    $brandsMarquee = collect(explode(',', (string) setting('brands_marquee', '')))->map(fn ($b) => trim($b))->filter()->values();
    $showBrands = setting('show_brands_marquee', '1') === '1' && $brandsMarquee->isNotEmpty();
    // Pass first hero image URL for <link rel=preload> in layout head
    $heroPreloadUrl = $heroBanners->first()?->image ? $heroBanners->first()->imageUrl() : null;
    $couponStyles = [
      ['bg' => 'bg-brand-500', 'btn' => 'bg-white text-brand-600'],
      ['bg' => 'bg-ink', 'btn' => 'bg-brand-500 text-white'],
      ['bg' => 'bg-rose-500', 'btn' => 'bg-white text-rose-600'],
      ['bg' => 'bg-emerald-600', 'btn' => 'bg-white text-emerald-700'],
    ];
  @endphp

  {{-- Hero grid --}}
  <section class="mx-auto max-w-7xl px-3 sm:px-6 lg:px-8 pt-4 min-w-0">
    <div class="hero-home-grid{{ $categories->isNotEmpty() ? ' has-cats' : '' }}{{ $features->isNotEmpty() ? ' has-features' : '' }} grid {{ $categories->isNotEmpty() ? 'lg:grid-cols-[220px_1fr_240px]' : ($features->isNotEmpty() ? 'lg:grid-cols-[1fr_240px]' : '') }} gap-4 items-stretch">
      @if($categories->isNotEmpty())
        <div class="hero-cats-desktop hidden lg:block rounded-xl bg-white overflow-hidden border border-slate-100 self-stretch">
          <ul class="py-2 text-sm">
            @foreach($categories->take(10) as $cat)
              <li>
                <a href="{{ route('shop.category', $cat) }}" class="cat-link flex items-center justify-between px-4 py-2.5 font-medium hover:bg-brand-50 hover:text-brand-600">
                  <span class="flex items-center gap-2.5">{{ $cat->icon ?: '📦' }} {{ $cat->name }}</span>
                  <svg class="h-4 w-4 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 6 6 6-6 6"/></svg>
                </a>
              </li>
            @endforeach
          </ul>
        </div>
      @endif

      <div id="heroSlider" class="hero-slider relative rounded-xl overflow-hidden select-none min-w-0 w-full self-stretch reveal">
        <div class="hero-track" data-hero-track>
          @forelse($heroBanners as $i => $slide)
            <div data-slide class="hero-slide bg-gradient-to-r from-brand-600 to-brand-400">
              @if($slide->image)
                <img src="{{ $slide->imageUrl() }}" class="pointer-events-none absolute inset-0 h-full w-full object-cover opacity-30" alt="{{ $slide->title }}" width="1280" height="480" draggable="false" @if($i === 0) fetchpriority="high" decoding="async" @else loading="lazy" decoding="async" @endif />
              @endif
              <div class="relative h-full flex flex-col justify-center p-5 pb-12 sm:p-12 max-w-md text-white min-w-0">
                @if($slide->badge)
                  <span class="inline-block w-fit rounded-full bg-white text-brand-600 text-xs font-bold px-3 py-1.5">{{ $slide->badge }}</span>
                @endif
                @if($slide->title)
                  @if($i === 0)
                    <h1 class="mt-3 sm:mt-4 font-display text-2xl sm:text-4xl lg:text-5xl font-extrabold leading-tight break-words">{{ $slide->title }}</h1>
                  @else
                    <h2 class="mt-3 sm:mt-4 font-display text-2xl sm:text-4xl lg:text-5xl font-extrabold leading-tight break-words">{{ $slide->title }}</h2>
                  @endif
                @endif
                @if($slide->subtitle)
                  <p class="mt-2 sm:mt-3 text-white/85 text-sm sm:text-base">{{ $slide->subtitle }}</p>
                @endif
                <a href="{{ $slide->linkHref() }}" class="btn-shine mt-5 sm:mt-6 inline-flex w-fit items-center gap-2 rounded-full bg-ink text-white font-bold px-5 sm:px-6 py-2.5 sm:py-3 text-sm sm:text-base hover:bg-black transition">
                  {{ $slide->button_text ?: $ctaDefault }}
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </a>
              </div>
            </div>
          @empty
            @if(setting('hero_fallback_title') || setting('hero_fallback_badge'))
              <div data-slide class="hero-slide bg-gradient-to-r from-brand-600 to-brand-400">
                <div class="relative h-full flex flex-col justify-center p-5 pb-12 sm:p-12 max-w-md text-white min-w-0">
                  @if(setting('hero_fallback_badge'))
                    <span class="inline-block w-fit rounded-full bg-white text-brand-600 text-xs font-bold px-3 py-1.5">{{ setting('hero_fallback_badge') }}</span>
                  @endif
                  @if(setting('hero_fallback_title'))
                    <h1 class="mt-3 sm:mt-4 font-display text-2xl sm:text-4xl lg:text-5xl font-extrabold leading-tight">{{ setting('hero_fallback_title') }}</h1>
                  @endif
                  @if(setting('hero_fallback_subtitle'))
                    <p class="mt-2 sm:mt-3 text-white/85 text-sm sm:text-base">{{ setting('hero_fallback_subtitle') }}</p>
                  @endif
                  <a href="{{ route('shop') }}" class="btn-shine mt-5 sm:mt-6 inline-flex w-fit items-center gap-2 rounded-full bg-ink text-white font-bold px-5 sm:px-6 py-2.5 sm:py-3 text-sm sm:text-base hover:bg-black transition">{{ $ctaDefault }}</a>
                </div>
              </div>
            @endif
          @endforelse
        </div>

        @if($heroBanners->count() > 1)
          <div class="pointer-events-none absolute bottom-4 left-1/2 z-10 flex -translate-x-1/2 items-center gap-2">
            @foreach($heroBanners as $di => $dot)
              <button type="button" data-dot class="pointer-events-auto h-2 rounded-full {{ $di === 0 ? 'w-6 bg-white' : 'w-2 bg-white/50' }}" aria-label="Slide {{ $di + 1 }}"></button>
            @endforeach
          </div>
        @endif
      </div>

      @if($features->isNotEmpty())
        <div class="hero-feature-stack hidden lg:flex flex-col gap-3 self-stretch">
          @foreach($features->take(4) as $feature)
            <div class="hero-feature-card rounded-xl bg-white border border-slate-100 p-4 flex items-center gap-3 flex-1 min-h-0">
              <span class="grid h-10 w-10 place-items-center rounded-full bg-brand-50 text-brand-600 shrink-0">
                @if($feature->icon)
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="{{ $feature->icon }}"/></svg>
                @else
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 7h11v8H3zM14 10h4l3 3v2h-7"/></svg>
                @endif
              </span>
              <div class="min-w-0">
                <p class="text-sm font-bold leading-tight truncate">{{ $feature->title }}</p>
                @if($feature->subtitle)<p class="text-xs text-slate-500 truncate">{{ $feature->subtitle }}</p>@endif
              </div>
            </div>
          @endforeach
        </div>
      @endif
    </div>

    @if($features->isNotEmpty())
      <div class="hero-feature-mobile grid grid-cols-2 gap-x-3 gap-y-3 lg:hidden mt-4">
        @foreach($features->take(4) as $feature)
          <div class="rounded-xl bg-white border border-slate-100 p-3 flex items-center gap-2.5 min-w-0">
            <span class="grid h-9 w-9 place-items-center rounded-full bg-brand-50 text-brand-600 shrink-0">
              @if($feature->icon)
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="{{ $feature->icon }}"/></svg>
              @else
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 7h11v8H3zM14 10h4l3 3v2h-7"/></svg>
              @endif
            </span>
            <div class="min-w-0">
              <p class="text-[13px] font-bold leading-snug truncate">{{ $feature->title }}</p>
              @if($feature->subtitle)<p class="text-[11px] text-slate-500 truncate">{{ $feature->subtitle }}</p>@endif
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </section>

  @if($flashProducts->isNotEmpty())
    <section class="mx-auto max-w-7xl px-3 sm:px-6 lg:px-8 py-6">
      <div class="rounded-2xl bg-gradient-to-r from-rose-600 via-brand-600 to-brand-500 p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
          <div class="flex flex-wrap items-center gap-3 sm:gap-4 min-w-0">
            <h2 class="font-display text-xl sm:text-2xl font-extrabold text-white flex items-center gap-2 truncate">{{ setting('home_hot_deal_title', 'Flash Sale') }}</h2>
            @if($flashEndsIso)
              <div data-countdown-end="{{ $flashEndsIso }}" class="flex items-center gap-1.5 font-display font-bold text-white shrink-0">
                <span class="cd-chip w-9 h-9 grid place-items-center text-sm bg-white/20 rounded"><span data-h>00</span></span><span>:</span>
                <span class="cd-chip w-9 h-9 grid place-items-center text-sm bg-white/20 rounded"><span data-m>00</span></span><span>:</span>
                <span class="cd-chip w-9 h-9 grid place-items-center text-sm bg-white/20 rounded"><span data-s>00</span></span>
              </div>
            @endif
          </div>
          <a href="{{ route('shop', ['flash' => 1]) }}" class="text-sm font-bold text-white hover:underline flex items-center gap-1 shrink-0">
            {{ strtoupper($viewMore) }}
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
          </a>
        </div>
        <div class="flash-row no-scrollbar">
          @foreach($flashProducts->take(5) as $product)
            <div class="flash-row__item">
              @include('storefront.partials.product-card', ['product' => $product])
            </div>
          @endforeach
        </div>
      </div>
    </section>
  @endif

  @if($categories->isNotEmpty())
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-4">
      <div class="rounded-2xl bg-white border border-slate-100 p-5">
        <h2 class="font-display text-lg font-extrabold mb-4">{{ setting('home_categories_title', 'Categories') }}</h2>
        <div class="grid grid-cols-4 sm:grid-cols-6 lg:grid-cols-10 gap-4">
          @foreach($categories->take(10) as $cat)
            <a href="{{ route('shop.category', $cat) }}" class="cat-circle text-center reveal">
              <div class="cat-circle-img mx-auto h-16 w-16 rounded-full overflow-hidden bg-slate-100">
                @if($cat->image)
                  <img src="{{ $cat->imageUrl() }}" class="h-full w-full object-cover" alt="{{ $cat->name }}" width="64" height="64" loading="lazy" decoding="async" />
                @else
                  <div class="h-full w-full grid place-items-center text-2xl">{{ $cat->icon ?: '📦' }}</div>
                @endif
              </div>
              <p class="mt-2 text-xs font-medium line-clamp-2">{{ $cat->name }}</p>
            </a>
          @endforeach
        </div>
      </div>
    </section>
  @endif

  @if($coupons->isNotEmpty())
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-4">
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach($coupons as $ci => $coupon)
          @php $style = $couponStyles[$ci % count($couponStyles)]; @endphp
          <div class="relative overflow-hidden rounded-xl {{ $style['bg'] }} p-5 text-white reveal {{ $ci > 0 ? 'reveal-delay-' . min($ci, 3) : '' }}">
            <p class="text-xs font-bold text-white/80 tracking-wide">{{ $coupon->code }}</p>
            <h3 class="mt-1 font-display text-lg font-bold leading-tight">{{ $coupon->valueLabel() }} off</h3>
            @if($coupon->description)
              <p class="text-xs text-white/70 mt-1 line-clamp-2">{{ $coupon->description }}</p>
            @elseif($coupon->min_order_amount)
              <p class="text-xs text-white/70 mt-1">Min. order {{ money($coupon->min_order_amount) }}</p>
            @endif
            <a href="{{ route('checkout.show') }}" class="mt-2 inline-flex rounded-full {{ $style['btn'] }} text-xs font-bold px-4 py-1.5">Use at checkout</a>
          </div>
        @endforeach
      </div>
    </section>
  @endif

  @if($bestSellers->isNotEmpty())
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
      <div class="flex items-center justify-between mb-5">
        <h2 class="font-display text-2xl font-extrabold border-l-4 border-brand-500 pl-3">{{ setting('home_tabs_title', 'Best Sellers') }}</h2>
        <a href="{{ route('shop', ['best' => 1]) }}" class="text-sm font-bold text-brand-600 hover:underline">{{ $viewMore }}</a>
      </div>
      <div class="product-grid sm:grid-cols-3 lg:grid-cols-6">
        @foreach($bestSellers->take(12) as $product)
          @include('storefront.partials.product-card', ['product' => $product])
        @endforeach
      </div>
    </section>
  @endif

  @if($newArrivals->isNotEmpty())
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
      <div class="flex items-center justify-between mb-5">
        <h2 class="font-display text-2xl font-extrabold border-l-4 border-brand-500 pl-3">{{ setting('home_deal_week_title', 'Latest Products') }}</h2>
        <a href="{{ route('shop', ['new' => 1]) }}" class="text-sm font-bold text-brand-600 hover:underline">{{ $viewMore }}</a>
      </div>
      <div class="product-grid sm:grid-cols-3 lg:grid-cols-6">
        @foreach($newArrivals->take(12) as $product)
          @include('storefront.partials.product-card', ['product' => $product])
        @endforeach
      </div>
    </section>
  @endif

  @if($featuredProducts->isNotEmpty())
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
      <div class="flex items-center justify-between mb-5">
        <h2 class="font-display text-2xl font-extrabold border-l-4 border-brand-500 pl-3">{{ setting('home_featured_title', 'Just For You') }}</h2>
        <a href="{{ route('shop', ['featured' => 1]) }}" class="text-sm font-bold text-brand-600 hover:underline">{{ $viewMore }}</a>
      </div>
      <div class="product-grid sm:grid-cols-3 lg:grid-cols-6">
        @foreach($featuredProducts->take(12) as $product)
          @include('storefront.partials.product-card', ['product' => $product])
        @endforeach
      </div>
      <div class="mt-8 text-center reveal">
        <a href="{{ route('shop') }}" class="inline-flex items-center gap-2 rounded-full border-2 border-brand-500 text-brand-600 px-8 py-3 font-bold hover:bg-brand-500 hover:text-white transition">{{ $viewMore }}</a>
      </div>
    </section>
  @endif

  @if($showBrands)
    <section class="py-8 bg-white border-y border-slate-100">
      <p class="text-center text-xs uppercase tracking-widest text-slate-400 mb-5">{{ setting('home_brands_label', 'Official brand stores') }}</p>
      <div class="marquee">
        <div class="marquee__track gap-14 px-6 text-2xl font-display font-extrabold text-slate-300">
          @foreach($brandsMarquee->concat($brandsMarquee) as $brand)
            <span>{{ $brand }}</span>
          @endforeach
        </div>
      </div>
    </section>
  @endif
@endsection
