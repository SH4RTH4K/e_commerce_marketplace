<aside id="mobileMenu" class="mobile-menu fixed inset-y-0 left-0 z-[80] flex w-80 max-w-[85%] -translate-x-full flex-col bg-white shadow-2xl lg:hidden transition-transform duration-300">
  <div class="flex items-center justify-between p-5 border-b border-slate-100 bg-brand-500 text-white">
    <span class="font-display text-lg font-extrabold">Menu</span>
    <button type="button" data-close-menu class="p-2" aria-label="Close">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
    </button>
  </div>

  <div class="border-b border-slate-100 bg-gradient-to-br from-brand-50 to-white p-5">
    @auth
      <a href="{{ route('account') }}" class="flex items-center gap-3 rounded-2xl">
        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-brand-500 text-lg font-extrabold text-white shadow-sm">
          {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
        </span>
        <span class="min-w-0">
          <span class="block truncate text-base font-extrabold text-ink">{{ auth()->user()->name }}</span>
          <span class="block truncate text-xs text-slate-500">{{ auth()->user()->email }}</span>
        </span>
      </a>
      <div class="mt-4 grid grid-cols-2 gap-2">
        <a href="{{ route('account') }}" class="rounded-xl bg-white px-3 py-2.5 text-center text-sm font-semibold text-ink shadow-sm ring-1 ring-slate-100 hover:bg-brand-50">My Account</a>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="w-full rounded-xl bg-brand-500 px-3 py-2.5 text-center text-sm font-semibold text-white hover:bg-brand-600">Log out</button>
        </form>
      </div>
    @else
      <p class="text-base font-extrabold text-ink">Welcome to {{ site_name() }}</p>
      <p class="mt-1 text-sm text-slate-500">Sign in to track orders and manage your account.</p>
      <div class="mt-4 grid grid-cols-2 gap-2">
        <a href="{{ route('login') }}" class="rounded-xl bg-brand-500 px-3 py-2.5 text-center text-sm font-semibold text-white hover:bg-brand-600">Sign in</a>
        <a href="{{ route('register') }}" class="rounded-xl bg-white px-3 py-2.5 text-center text-sm font-semibold text-ink shadow-sm ring-1 ring-slate-100 hover:bg-brand-50">Register</a>
      </div>
    @endauth
  </div>

  <nav class="flex-1 overflow-y-auto p-5 text-base font-medium">
    <p class="mb-2 px-4 text-[11px] font-bold uppercase tracking-wider text-slate-400">Browse</p>
    <div class="space-y-1">
      <a href="{{ route('home') }}" class="block rounded-lg px-4 py-3 hover:bg-brand-50">Home</a>
      <a href="{{ route('shop') }}" class="block rounded-lg px-4 py-3 hover:bg-brand-50">Shop</a>
      @if($hasFlashSale ?? false)
        <a href="{{ route('shop', ['flash' => 1]) }}" class="block rounded-lg px-4 py-3 text-brand-600 hover:bg-brand-50">{{ setting('home_hot_deal_title', 'Flash Sale') }}</a>
      @endif
      <a href="{{ route('track') }}" class="block rounded-lg px-4 py-3 hover:bg-brand-50">Track Order</a>
      <a href="{{ route('contact') }}" class="block rounded-lg px-4 py-3 hover:bg-brand-50">Contact</a>
    </div>

    <p class="mb-2 mt-5 px-4 text-[11px] font-bold uppercase tracking-wider text-slate-400">Categories</p>
    <div class="space-y-1">
      @foreach($navCategories as $cat)
        <a href="{{ route('shop.category', $cat) }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 hover:bg-brand-50 transition-colors">
          <span class="h-9 w-9 shrink-0 overflow-hidden rounded-xl bg-slate-100 ring-1 ring-slate-200">
            <img
              src="{{ $cat->imageUrl() }}"
              alt="{{ $cat->name }}"
              class="h-full w-full object-cover"
              onerror="this.style.display='none';this.parentElement.innerHTML='<span class=\'grid h-full w-full place-items-center text-lg\'>{{ $cat->icon }}</span>'"
            >
          </span>
          <span class="font-medium text-ink">{{ $cat->name }}</span>
        </a>
      @endforeach
    </div>
  </nav>
</aside>
