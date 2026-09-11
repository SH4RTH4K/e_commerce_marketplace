<header class="site-header sticky top-0 z-40 bg-brand-500">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="flex h-14 sm:h-16 items-center gap-3 sm:gap-4 min-w-0">
      <button type="button" data-open-menu class="lg:hidden p-2 -ml-2 text-white shrink-0" aria-label="Menu">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>

      @include('partials.brand', ['light' => true, 'size' => 'sm'])

      <form action="{{ route('shop') }}" method="GET" class="hidden md:flex flex-1 max-w-xl min-w-0">
        <div class="flex w-full items-center rounded-full bg-white overflow-hidden shadow-sm">
          <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ setting('search_placeholder', 'Search…') }}" class="flex-1 h-9 px-4 text-sm focus:outline-none" />
          <button type="submit" class="h-9 px-4 bg-ink text-white text-sm font-bold hover:bg-black transition" aria-label="Search">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3-3"/></svg>
          </button>
        </div>
      </form>

      <div class="ml-auto flex items-center gap-1 sm:gap-2 shrink-0 text-white">
        <button type="button" data-toggle-search class="md:hidden p-2" aria-label="Search" aria-expanded="false" aria-controls="mobileSearchPanel">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3-3"/></svg>
        </button>
        @include('storefront.partials.account-dropdown', ['lightHeader' => true])
        <button type="button" data-open-cart class="relative p-2" aria-label="Cart">
          <svg class="h-6 w-6 sm:h-7 sm:w-7" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h15l-1.5 9h-12L6 6Zm0 0-.7-3H3"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/></svg>
          <span data-cart-count class="cart-count absolute top-0 right-0 grid h-5 w-5 place-items-center rounded-full bg-ink text-[10px] font-bold text-white {{ $cartCount ? '' : 'hidden' }}">{{ $cartCount }}</span>
        </button>
      </div>
    </div>
  </div>

  <div id="mobileSearchPanel" class="md:hidden hidden border-t border-brand-600/30 bg-brand-500">
    <form action="{{ route('shop') }}" method="GET" class="mx-auto max-w-7xl px-4 py-3">
      <div class="flex items-center rounded-full bg-white overflow-hidden shadow-sm">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ setting('search_placeholder', 'Search…') }}" class="flex-1 min-w-0 h-10 px-4 text-sm focus:outline-none" data-mobile-search-input autocomplete="off" />
        <button type="submit" class="shrink-0 h-10 px-5 bg-ink text-white text-sm font-bold">Search</button>
      </div>
    </form>
  </div>
</header>
