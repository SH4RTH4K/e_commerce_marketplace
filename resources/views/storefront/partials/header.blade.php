@php
  $promoText = trim((string) setting('header_promo_text', ''));
  $promoLink = trim((string) setting('header_promo_link', ''));
  // SECURITY: Only allow http/https links — prevent javascript: URL injection
  if ($promoLink !== '' && ! preg_match('/^https?:\/\//i', $promoLink)) {
      $promoLink = '';
  }
  // SECURITY: Safer than strip_tags with allowed tags (which still permits event attributes).
  // Escape everything, then restore only <b> and <strong> tags (no attributes possible).
  $promoSafe = preg_replace(
      ['/<b>/i', '/<\/b>/i', '/<strong>/i', '/<\/strong>/i'],
      ['<b>', '</b>', '<strong>', '</strong>'],
      e($promoText)
  );
@endphp

{{-- Top bar — desktop/tablet only --}}
@if($promoText !== '')
  <div class="hidden md:block bg-ink text-white text-xs">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex items-center justify-between h-9">
      <p class="text-white/70">
        @if($promoLink !== '')
          <a href="{{ $promoLink }}" class="hover:text-white">{!! $promoSafe !!}</a>
        @else
          {!! $promoSafe !!}
        @endif
      </p>
      <div class="flex items-center gap-4 text-white/70 ml-auto">
        <a href="{{ route('contact') }}" class="hover:text-white">Help &amp; Support</a>
        <a href="{{ route('track') }}" class="hover:text-white">Track Order</a>
      </div>
    </div>
  </div>
@else
  <div class="hidden md:block bg-ink text-white text-xs">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex items-center justify-end h-9 gap-4 text-white/70">
      <a href="{{ route('contact') }}" class="hover:text-white">Help &amp; Support</a>
      <a href="{{ route('track') }}" class="hover:text-white">Track Order</a>
    </div>
  </div>
@endif

<header class="site-header sticky top-0 z-40 bg-brand-500">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="flex h-16 items-center gap-4">
      <button type="button" data-open-menu class="lg:hidden p-2 -ml-2 text-white shrink-0" aria-label="Menu">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>

      @include('partials.brand', ['light' => true])

      <form action="{{ route('shop') }}" method="GET" class="hidden md:flex flex-1 max-w-2xl min-w-0">
        <div class="flex w-full items-center rounded-full bg-white overflow-hidden shadow-sm">
          <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ setting('search_placeholder', 'Search in ' . site_name() . "\u{2026}") }}" class="flex-1 h-10 px-5 text-sm focus:outline-none" />
          <button type="submit" class="h-10 px-6 bg-ink text-white text-sm font-bold hover:bg-black transition flex items-center gap-2 shrink-0" aria-label="Search">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3-3"/></svg>
            Search
          </button>
        </div>
      </form>

      <div class="ml-auto flex items-center gap-1 sm:gap-3 text-white shrink-0">
        <button type="button" data-toggle-search class="md:hidden p-2 text-white" aria-label="Search" aria-expanded="false" aria-controls="mobileSearchPanel">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3-3"/></svg>
        </button>

        @include('storefront.partials.account-dropdown', ['lightHeader' => true])

        <button type="button" data-open-cart class="relative p-2" aria-label="Cart">
          <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h15l-1.5 9h-12L6 6Zm0 0-.7-3H3"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/></svg>
          <span data-cart-count class="cart-count absolute top-0 right-0 grid h-5 w-5 place-items-center rounded-full bg-ink text-[10px] font-bold text-white {{ $cartCount ? '' : 'hidden' }}">{{ $cartCount }}</span>
        </button>
      </div>
    </div>
  </div>

  <div id="mobileSearchPanel" class="md:hidden hidden border-t border-brand-600/30 bg-brand-500">
    <form action="{{ route('shop') }}" method="GET" class="mx-auto max-w-7xl px-4 py-3">
      <div class="flex items-center rounded-full bg-white overflow-hidden shadow-sm">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ setting('search_placeholder', 'Search in ' . site_name() . "\u{2026}") }}" class="flex-1 min-w-0 h-10 px-4 text-sm focus:outline-none" data-mobile-search-input autocomplete="off" />
        <button type="submit" class="shrink-0 h-10 px-5 bg-ink text-white text-sm font-bold">Search</button>
      </div>
    </form>
  </div>

  @if($navCategories->isNotEmpty())
    <div class="hidden lg:block bg-brand-600/95">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex items-center gap-6 h-10 text-sm font-medium text-white/90 overflow-x-auto no-scrollbar">
        @foreach($navCategories as $cat)
          <a href="{{ route('shop.category', $cat) }}" class="whitespace-nowrap hover:text-white {{ optional($activeCategory ?? null)->id === $cat->id ? 'text-white font-semibold' : '' }}">
            {{ $cat->name }}
          </a>
        @endforeach
        @if($hasFlashSale ?? false)
          <a href="{{ route('shop', ['flash' => 1]) }}" class="ml-auto flex items-center gap-1.5 font-semibold text-white whitespace-nowrap shrink-0">
            {{ setting('home_hot_deal_title', 'Flash Sale') }}
          </a>
        @endif
      </div>
    </div>
  @endif
</header>
