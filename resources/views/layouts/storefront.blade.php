<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <meta name="app-base" content="{{ rtrim(request()->getBasePath() ?: (parse_url(url('/'), PHP_URL_PATH) ?: ''), '/') }}" />
  <meta name="theme-color" content="#f15a24" />

  @include('partials.seo')

  {{-- DNS prefetch for external origins --}}
  <link rel="dns-prefetch" href="https://fonts.googleapis.com" />
  <link rel="dns-prefetch" href="https://fonts.gstatic.com" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

  {{-- Google Fonts with font-display=swap to eliminate render-blocking --}}
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Mulish:wght@400;500;600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media='all'" />
  <noscript><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Mulish:wght@400;500;600;700;800&display=swap" rel="stylesheet" /></noscript>

  {{-- Local compiled Tailwind utilities — replaces 130 KB render-blocking CDN script --}}
  @php
    $themeBase = rtrim(request()->getBasePath() ?: '', '/');
  @endphp
  {{-- Local Tailwind utilities (compiled, no CDN script needed) --}}
  <link rel="stylesheet" href="{{ $themeBase }}/theme/css/tailwind-local.css?v=2" />
  <link rel="stylesheet" href="{{ $themeBase }}/theme/css/style.css?v=hostfix6" />

  {{-- LCP hint: preload hero image (injected by JS below if available) --}}
  @isset($heroPreloadUrl)
  <link rel="preload" as="image" href="{{ $heroPreloadUrl }}" fetchpriority="high" />
  @endisset
  <style>
    /* Critical hostfix — product grids / flash row stay intact if style.css is slow/404 */
    .product-card .product-img{display:block;width:100%;max-width:100%;height:auto!important;aspect-ratio:1/1;object-fit:cover}
    .product-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));column-gap:.375rem;row-gap:.5rem;width:100%;max-width:100%;min-width:0;align-items:start}
    .product-grid>*{min-width:0;max-width:100%}
    .flash-row{display:flex;gap:.5rem;overflow-x:auto;overscroll-behavior-x:contain;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;padding-bottom:.125rem}
    .flash-row__item{flex:0 0 calc((100% - .5rem) / 2.15);min-width:0;scroll-snap-align:start}
    .flash-row__item>.product-card{height:100%}
    @media (min-width:640px){
      .product-grid{gap:.75rem;align-items:stretch}
      .flash-row{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.75rem;overflow-x:visible;scroll-snap-type:none}
      .flash-row__item{flex:none;width:auto;max-width:none}
    }
  </style>
  <link rel="icon" href="{{ favicon_url() }}" />
  <link rel="apple-touch-icon" href="{{ favicon_url() }}" />

  <script type="application/ld+json">
  {!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => site_name(),
    'url' => url('/'),
    'logo' => logo_url(),
    'telephone' => setting('contact_phone'),
    'email' => setting('contact_email'),
  ], JSON_UNESCAPED_SLASHES) !!}
  </script>

  @include('partials.tracking-head')
</head>

<body class="@yield('body_class', 'bg-[#f3f4f6] text-ink antialiased has-floating-chat')">
  @include('partials.tracking-body')

  @hasSection('checkout_header')
    @yield('checkout_header')
  @elseif(($headerVariant ?? 'full') === 'compact')
    @include('storefront.partials.header-compact')
  @else
    @include('storefront.partials.header')
  @endif

  @if(session('status') && ! request()->routeIs('product.show'))
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 mt-4">
      <div class="bg-white border border-brand-200 text-brand-700 text-sm font-semibold px-4 py-3 rounded-xl">{{ session('status') }}</div>
    </div>
  @endif

  @yield('content')

  @include('storefront.partials.footer')
  <div id="overlay" data-drawer-overlay class="fixed inset-0 bg-ink/50 z-[60] opacity-0 pointer-events-none transition-opacity backdrop-blur-sm"></div>
  @include('storefront.partials.cart-drawer')
  @include('storefront.partials.mobile-menu')

  <button type="button" id="backToTop" data-back-top class="fixed z-40 rounded-full shadow-lg flex items-center justify-center opacity-0 pointer-events-none transition-opacity bg-brand-600 text-white right-6 bottom-6 h-11 w-11" aria-label="Back to top">
    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 19V5m-6 6 6-6 6 6"/></svg>
  </button>

  @include('partials.floating-chat')


  {{-- defer: scripts load after HTML parse, no render-blocking --}}
  <script src="{{ $themeBase }}/theme/js/storefront.js?v=hostfix6" defer></script>
  @if(session('cart_open'))
    <script>window.addEventListener('DOMContentLoaded',()=>window.Storefront&&window.Storefront.openCart());</script>
  @endif
  <script src="{{ $themeBase }}/theme/js/upload-fallback.js?v=tmp2" defer></script>
  @stack('scripts')
</body>
</html>
