<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title>{{ $c['meta_title'] ?? $page->title }}</title>
  <meta name="description" content="{{ $c['meta_description'] ?? '' }}" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&family=Hind+Siliguri:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com" data-cfasync="false"></script>
  <script data-cfasync="false">
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            ink:        "#0c1e36",
            sky:        "#0284c7",
            "sky-deep": "#0369a1",
            "sky-soft": "#e0f2fe",
            blue:       "#2563eb",
            emerald:    "#10b981",
            amber:      "#f59e0b",
            coral:      "#ea580c",
            rose:       "#f43f5e",
            line:       "#dbeafe",
            muted:      "#475569",
          },
          maxWidth: { "7xl": "80rem" },
          fontFamily: {
            sans: ["Plus Jakarta Sans", "Hind Siliguri", "sans-serif"],
            display: ["Fredoka", "Hind Siliguri", "sans-serif"],
          },
        },
      },
    };
  </script>
  <link rel="stylesheet" href="{{ asset('landing/chilora/css/style.css') }}?v=v4" />

  {{-- SVG Icons Library --}}
  <svg xmlns="http://www.w3.org/2000/svg" class="hidden" aria-hidden="true">
    <symbol id="ico-phone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1.18h3a2 2 0 0 1 2 1.72c.127.96.36 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.91a16 16 0 0 0 6.29 6.29l.91-.86a2 2 0 0 1 2.11-.45c.907.34 1.85.573 2.81.7a2 2 0 0 1 1.72 2.03z"/>
    </symbol>
    <symbol id="ico-star" viewBox="0 0 24 24">
      <polygon fill="currentColor" points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
    </symbol>
    <symbol id="ico-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <polyline points="20 6 9 17 4 12"/>
    </symbol>
    <symbol id="ico-truck" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
    </symbol>
    <symbol id="ico-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
    </symbol>
    <symbol id="ico-award" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>
    </symbol>
    <symbol id="ico-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
    </symbol>
    <symbol id="ico-cart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
    </symbol>
    <symbol id="ico-sparkles" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M12 3l1.912 5.885L20 10.797l-4.756 4.318L16.364 21 12 17.618 7.636 21l1.12-5.885L4 10.797l6.088-1.912L12 3z"/>
    </symbol>
    <symbol id="ico-gift" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>
    </symbol>
    <symbol id="ico-globe" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
    </symbol>
    <symbol id="ico-mic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/>
    </symbol>
    <symbol id="ico-palette" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="13.5" cy="6.5" r=".5"/><circle cx="17.5" cy="10.5" r=".5"/><circle cx="8.5" cy="7.5" r=".5"/><circle cx="6.5" cy="12.5" r=".5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.563-2.512 5.563-5.563C22 6.5 17.5 2 12 2z"/>
    </symbol>
    <symbol id="ico-edit" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
    </symbol>
    <symbol id="ico-battery" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <rect x="1" y="6" width="18" height="12" rx="2" ry="2"/><line x1="23" y1="13" x2="23" y2="11"/><polyline points="7 12 10 9 10 15 13 12"/>
    </symbol>
    <symbol id="ico-volume" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/>
    </symbol>
    <symbol id="ico-book" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
    </symbol>
    <symbol id="ico-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
    </symbol>
  </svg>
</head>
<body id="top" class="{{ ($page->sectionVisible('hero') || $page->sectionVisible('order')) ? 'has-mobile-cta' : '' }}">
  <div id="site-header"></div>

  @php
    $offer   = (float) ($c['offer_price']   ?? $product->price);
    $regular = (float) ($c['regular_price'] ?? $product->regular_price);
    $heroImg = $page->mediaUrl($c['hero_image']    ?? '') ?: $product->imageUrl();
    $thumb   = $page->mediaUrl($c['product_thumb'] ?? '') ?: $heroImg;
    $phone   = $c['phone'] ?? '';
    $tel     = preg_replace('/\D+/', '', $phone);
    $wa      = $c['whatsapp'] ?? $tel;
    $show    = fn (string $s) => $page->sectionVisible($s);
    $cta     = $c['cta_text'] ?? 'অর্ডার করতে চাই';

    // Helper for benefit icons
    $getBenefitIcon = function($iconKey, $index = 0) {
      $key = strtolower(trim((string)$iconKey));
      if (str_contains($key, 'globe') || str_contains($key, 'ভাষা') || str_contains($key, '🌐') || $index === 0) return '#ico-globe';
      if (str_contains($key, 'mic') || str_contains($key, 'কথা') || str_contains($key, 'উচ্চারণ') || str_contains($key, '🗣') || $index === 1) return '#ico-mic';
      if (str_contains($key, 'palette') || str_contains($key, 'আনন্দ') || str_contains($key, '🎨') || $index === 2) return '#ico-palette';
      if (str_contains($key, 'edit') || str_contains($key, 'লেখা') || str_contains($key, 'মার্কার') || str_contains($key, '✍') || $index === 3) return '#ico-edit';
      if (str_contains($key, 'shield') || str_contains($key, 'টেকসই') || str_contains($key, 'নিরাপদ') || str_contains($key, '🛡') || $index === 4) return '#ico-shield';
      if (str_contains($key, 'battery') || str_contains($key, 'চার্জ') || str_contains($key, 'ব্যাটারি') || str_contains($key, '🔋') || $index === 5) return '#ico-battery';
      return '#ico-sparkles';
    };

    // Feature card icon mapping
    $getFeatureIcon = function($index) {
      $icons = ['#ico-battery', '#ico-mic', '#ico-sparkles', '#ico-book', '#ico-volume', '#ico-lock'];
      return $icons[$index % count($icons)];
    };
  @endphp

  {{-- ══════════════════════════════════════════ HERO ══ --}}
  @if($show('hero'))
  <section class="hero">
    <div class="max-w-7xl mx-auto px-4 grid lg:grid-cols-2 gap-8 lg:gap-12 items-center">
      <div class="reveal order-2 lg:order-1">
        @if(!empty($c['hero_badge']))
          <p class="hero-badge mb-5">
            <svg viewBox="0 0 24 24"><use href="#ico-award"/></svg>
            <span>{{ $c['hero_badge'] }}</span>
          </p>
        @endif
        <h1 class="font-display text-[1.95rem] sm:text-4xl lg:text-[2.85rem] font-bold leading-[1.22] text-ink mb-4 whitespace-pre-line">{{ $c['hero_title'] ?? $page->title }}</h1>
        <p class="text-muted text-base sm:text-lg leading-relaxed mb-6 max-w-xl">{{ $c['hero_subtitle'] ?? '' }}</p>
        
        <div class="flex flex-wrap gap-3 mb-6">
          <span class="price-chip">
            <span>মূল্য:</span>
            <strong class="text-lg">৳{{ number_format($offer, 0) }}/-</strong>
            @if($regular > $offer)
              <span class="text-xs text-muted line-through">৳{{ number_format($regular, 0) }}</span>
            @endif
          </span>
          @if($phone)
            <a href="tel:{{ $tel }}" class="phone-chip">
              <svg><use href="#ico-phone"/></svg>
              <span>{{ $phone }}</span>
            </a>
          @endif
        </div>

        <div class="cta-wrap">
          @include('landing.partials.buy-now', [
            'btnText'  => $cta,
            'btnClass' => 'cta-order',
          ])
        </div>
      </div>

      <div class="reveal order-1 lg:order-2">
        <div class="relative">
          <img src="{{ $heroImg }}" alt="{{ $page->title }}" class="product-shot w-full object-cover aspect-[5/4]" width="720" height="560" loading="eager" />
        </div>
      </div>
    </div>
  </section>

  {{-- Trust badges strip --}}
  <div class="trust-strip max-w-7xl mx-auto px-4">
    <span class="trust-badge">
      <svg><use href="#ico-truck"/></svg>
      <span>ক্যাশ অন ডেলিভারি</span>
    </span>
    <span class="text-sky-200 hidden sm:inline">|</span>
    <span class="trust-badge">
      <svg><use href="#ico-shield"/></svg>
      <span>৬ মাসের ওয়ারেন্টি</span>
    </span>
    <span class="text-sky-200 hidden sm:inline">|</span>
    <span class="trust-badge">
      <svg><use href="#ico-gift"/></svg>
      <span>প্র্যাকটিস কিট ফ্রি</span>
    </span>
    <span class="text-sky-200 hidden sm:inline">|</span>
    <span class="trust-badge">
      <svg><use href="#ico-check"/></svg>
      <span>১০০% কোয়ালিটি গ্যারান্টি</span>
    </span>
  </div>
  @endif

  {{-- ══════════════════════════════════════════ OFFER / COUNTDOWN ══ --}}
  @if($show('offer'))
  <section id="offer" class="countdown-section">
    <div class="max-w-7xl mx-auto px-4 text-center reveal relative z-10">
      @if(!empty($c['offer_badge']))
        <p class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/20 border border-amber-400/30 px-4 py-1.5 text-sm font-bold text-amber-300 mb-4 shadow-sm">
          <svg class="w-4 h-4 text-amber-400"><use href="#ico-sparkles"/></svg>
          <span>{{ $c['offer_badge'] }}</span>
        </p>
      @endif
      
      <h2 class="font-display text-2xl sm:text-4xl font-bold text-white mb-6">{{ $c['offer_title'] ?? 'অফার শেষ হওয়ার আগে অর্ডার করুন' }}</h2>
      
      <div class="flex justify-center gap-3 sm:gap-4 mb-7">
        <div class="countdown-cell">
          <div id="cd-h" class="font-display">00</div>
          <div class="text-[11px] sm:text-xs font-bold uppercase tracking-wider opacity-85 mt-1">ঘণ্টা</div>
        </div>
        <div class="countdown-cell">
          <div id="cd-m" class="font-display">00</div>
          <div class="text-[11px] sm:text-xs font-bold uppercase tracking-wider opacity-85 mt-1">মিনিট</div>
        </div>
        <div class="countdown-cell">
          <div id="cd-s" class="font-display">00</div>
          <div class="text-[11px] sm:text-xs font-bold uppercase tracking-wider opacity-85 mt-1">সেকেন্ড</div>
        </div>
      </div>

      <p class="text-white/90 text-base sm:text-lg font-semibold mb-7">
        রেগুলার মূল্য <span class="line-through text-rose-400 decoration-2 decoration-rose-500">৳{{ number_format($regular, 0) }}/-</span> টাকা &nbsp;·&nbsp; স্পেশাল অফার <strong class="text-amber-400 text-xl sm:text-2xl font-extrabold ml-1">৳{{ number_format($offer, 0) }}/-</strong>
      </p>

      @include('landing.partials.buy-now', [
        'btnText'  => $cta,
        'btnClass' => 'cta-order !bg-amber-500 hover:!bg-amber-600 !shadow-amber-500/30',
      ])
    </div>
  </section>
  @endif

  {{-- ══════════════════════════════════════════ FEATURES ══ --}}
  @if($show('features'))
  <section id="features" class="py-16 sm:py-20">
    <div class="max-w-7xl mx-auto px-4">
      <div class="text-center max-w-2xl mx-auto mb-12 sm:mb-14 reveal">
        <p class="inline-flex items-center gap-1.5 text-sky font-bold mb-2.5 text-sm uppercase tracking-wide">
          <svg class="w-4 h-4"><use href="#ico-gift"/></svg>
          <span>কম্বো প্যাকেজে যা যা পাচ্ছেন</span>
        </p>
        <h2 class="font-display text-3xl sm:text-4xl font-bold text-ink mb-3">{{ $c['features_heading'] ?? '' }}</h2>
        <p class="text-muted text-base sm:text-lg">{{ $c['features_intro'] ?? '' }}</p>
      </div>

      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
        @foreach($c['features'] ?? [] as $idx => $f)
          <article class="feature-card reveal">
            <div class="feature-icon-badge">
              <svg><use href="{{ $getFeatureIcon($idx) }}"/></svg>
            </div>
            <h3 class="font-bold text-lg text-ink mb-2">{{ $f['title'] ?? '' }}</h3>
            <p class="text-sm text-muted leading-relaxed">{{ $f['body'] ?? '' }}</p>
          </article>
        @endforeach
      </div>

      <div class="text-center mt-11 reveal">
        @include('landing.partials.buy-now', [
          'btnText'  => $cta,
          'btnClass' => 'cta-order',
        ])
      </div>
    </div>
  </section>
  @endif

  {{-- ══════════════════════════════════════════ WHY / BENEFITS ══ --}}
  @if($show('why'))
  <section id="why" class="py-16 sm:py-20 bg-gradient-to-b from-sky-soft/40 to-white border-y border-line">
    <div class="max-w-7xl mx-auto px-4">
      <div class="text-center max-w-2xl mx-auto mb-12 sm:mb-14 reveal">
        <p class="inline-flex items-center gap-1.5 text-sky-deep font-bold mb-2.5 text-sm uppercase tracking-wide">
          <svg class="w-4 h-4 text-emerald"><use href="#ico-check"/></svg>
          <span>কেন এই পণ্য সেরা</span>
        </p>
        <h2 class="font-display text-3xl sm:text-4xl font-bold text-ink">{{ $c['why_heading'] ?? '' }}</h2>
      </div>

      <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5 sm:gap-6">
        @foreach($c['benefits'] ?? [] as $i => $b)
          <article class="benefit-card reveal">
            <div class="benefit-icon">
              <svg><use href="{{ $getBenefitIcon($b['icon'] ?? '', $i) }}"/></svg>
            </div>
            <div class="min-w-0">
              <h3 class="font-bold text-base sm:text-lg text-ink mb-1.5">{{ $b['title'] ?? '' }}</h3>
              <p class="text-sm text-muted leading-relaxed">{{ $b['body'] ?? '' }}</p>
            </div>
          </article>
        @endforeach
      </div>
    </div>
  </section>
  @endif

  {{-- ══════════════════════════════════════════ REVIEWS ══ --}}
  @if($show('reviews'))
  <section id="reviews" class="py-16 sm:py-20">
    <div class="max-w-7xl mx-auto px-4">
      <div class="text-center mb-10 sm:mb-12 reveal">
        <p class="inline-flex items-center gap-1.5 text-amber-500 font-bold mb-2 text-sm uppercase tracking-wide">
          <svg class="w-4 h-4 fill-amber-400"><use href="#ico-star"/></svg>
          <span>সন্তুষ্ট গ্রাহকদের প্রতিক্রিয়া</span>
        </p>
        <h2 class="font-display text-3xl sm:text-4xl font-bold text-ink">{{ $c['reviews_heading'] ?? '' }}</h2>
      </div>

      <div class="review-rail reveal">
        @foreach($c['testimonials'] ?? [] as $t)
          <figure class="review-card">
            @if(!empty($t['image']))
              <img src="{{ $page->mediaUrl($t['image']) }}" alt="" class="w-full aspect-[4/3] object-cover bg-slate-100" width="640" height="480" loading="lazy" />
            @else
              <div class="w-full aspect-[4/3] bg-gradient-to-br from-sky-soft to-sky-100 flex items-center justify-center">
                <svg class="w-12 h-12 text-sky/40"><use href="#ico-book"/></svg>
              </div>
            @endif
            <figcaption class="p-4 sm:p-5">
              <div class="stars">
                @for($s=0; $s<5; $s++)<svg><use href="#ico-star"/></svg>@endfor
              </div>
              <p class="text-sm text-muted leading-relaxed font-medium mt-1">{{ $t['text'] ?? '' }}</p>
            </figcaption>
          </figure>
        @endforeach
      </div>
    </div>
  </section>
  @endif

  {{-- ══════════════════════════════════════════ PHONE CTA ══ --}}
  @if($show('phone_cta'))
  <section id="call" class="call-cta-section">
    <div class="max-w-7xl mx-auto px-4 text-center reveal">
      <p class="font-bold opacity-90 mb-2 uppercase tracking-wide text-sm flex items-center justify-center gap-1.5">
        <svg class="w-4 h-4 text-amber-300 fill-amber-300"><use href="#ico-star"/></svg>
        <span>সরাসরি যোগাযোগ ও অর্ডার</span>
      </p>
      <h2 class="font-display text-3xl sm:text-4xl font-bold mb-7">{{ $c['phone_cta_title'] ?? 'ফোনে অর্ডার করতে চান?' }}</h2>
      @if($phone)
        <a href="tel:{{ $tel }}" class="call-cta-btn">
          <svg><use href="#ico-phone"/></svg>
          <span>{{ $phone }}</span>
        </a>
      @endif
    </div>
  </section>
  @endif

  {{-- ══════════════════════════════════════════ ORDER ══ --}}
  @if($show('order'))
  <section id="order" class="py-16 sm:py-20">
    <div class="max-w-7xl mx-auto px-4">
      <div class="text-center mb-10 sm:mb-12 reveal">
        <p class="inline-flex items-center gap-1.5 text-sky font-bold mb-2 text-sm uppercase tracking-wide">
          <svg class="w-4 h-4"><use href="#ico-cart"/></svg>
          <span>সহজ চেকআউট</span>
        </p>
        <h2 class="font-display text-3xl sm:text-4xl font-bold text-ink mb-2">{{ $c['order_heading'] ?? '' }}</h2>
        <p class="text-muted text-sm sm:text-base max-w-lg mx-auto">{{ $c['order_subtext'] ?? '' }}</p>
      </div>

      <form id="order-form" method="POST" action="{{ route('landing.order', $page->slug) }}" class="order-panel reveal max-w-2xl mx-auto p-6 sm:p-8 grid gap-6 bg-white rounded-3xl border border-line shadow-xl">
        @csrf
        
        <div>
          <h3 class="font-bold text-ink mb-3 text-base sm:text-lg">পণ্য বিবরণী ও পরিমাণ</h3>
          <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between rounded-2xl border border-line p-4 bg-sky-soft/40">
            <div class="flex gap-3.5 items-center">
              <img src="{{ $thumb }}" alt="" class="h-16 w-16 rounded-xl object-cover border border-line bg-white" width="120" height="120" />
              <div>
                <p class="font-bold text-sm sm:text-base text-ink leading-snug">{{ $c['product_label'] ?? $product->name }}</p>
                <p class="text-sm mt-1">
                  <span class="line-through text-muted mr-1.5">৳ {{ number_format($regular, 0) }}</span>
                  <span class="text-sky-deep font-extrabold text-base">৳ {{ number_format($offer, 0) }}</span>
                </p>
              </div>
            </div>
            
            <div class="flex items-center gap-2 self-end sm:self-center" aria-label="Quantity">
              <button type="button" id="qty-minus" class="qty-btn" aria-label="Decrease">−</button>
              <input id="qty" name="qty" type="number" min="1" max="99" value="1" class="w-14 text-center rounded-xl border border-line py-2 font-bold text-ink bg-white" />
              <button type="button" id="qty-plus" class="qty-btn" aria-label="Increase">+</button>
            </div>
          </div>
        </div>

        <div class="space-y-4">
          <h3 class="font-bold text-ink text-base sm:text-lg">ডেলিভারির তথ্য</h3>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">আপনার নাম <span class="text-red-500">*</span></label>
            <input type="text" name="customer_name" required placeholder="আপনার সম্পূর্ণ নাম লিখুন" class="w-full px-4 py-3 rounded-xl border border-line text-sm focus:ring-2 focus:ring-sky focus:outline-none" />
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">মোবাইল নম্বর <span class="text-red-500">*</span></label>
            <input type="tel" name="customer_phone" required placeholder="১১ ডিজিটের মোবাইল নম্বর (যেমন: 01712345678)" class="w-full px-4 py-3 rounded-xl border border-line text-sm focus:ring-2 focus:ring-sky focus:outline-none" />
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">সম্পূর্ণ ঠিকানা <span class="text-red-500">*</span></label>
            <textarea name="shipping_address" required rows="2" placeholder="বাড়ি নং, রোড নং, এলাকা, থানা ও জেলা" class="w-full px-4 py-3 rounded-xl border border-line text-sm focus:ring-2 focus:ring-sky focus:outline-none"></textarea>
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">ডেলিভারি এরিয়া <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-2 gap-3">
              <label class="cursor-pointer border-2 border-line rounded-xl p-3 flex items-center justify-between hover:border-sky">
                <input type="radio" name="shipping_zone" value="inside_dhaka" checked class="accent-sky" data-shipping="70" />
                <span class="text-xs sm:text-sm font-bold text-ink ml-2">ঢাকার ভিতরে (৳৭০)</span>
              </label>
              <label class="cursor-pointer border-2 border-line rounded-xl p-3 flex items-center justify-between hover:border-sky">
                <input type="radio" name="shipping_zone" value="outside_dhaka" class="accent-sky" data-shipping="130" />
                <span class="text-xs sm:text-sm font-bold text-ink ml-2">ঢাকার বাইরে (৳১৩০)</span>
              </label>
            </div>
          </div>
        </div>

        <div class="rounded-2xl bg-ink text-white p-5 text-sm space-y-2.5">
          <div class="flex justify-between items-center text-slate-300 text-sm">
            <span>পণ্য মূল্য:</span>
            <span id="order-subtotal" class="font-bold text-white">৳ {{ number_format($offer, 0) }}</span>
          </div>
          <div class="flex justify-between items-center text-slate-300 text-sm">
            <span>ডেলিভারি চার্জ:</span>
            <span id="order-shipping" class="font-bold text-white">৳ 70</span>
          </div>
          <div class="flex justify-between items-center font-bold text-lg border-t border-white/20 pt-3 mt-3">
            <span>সর্বমোট পরিশোধযোগ্য:</span>
            <span id="order-total" class="text-amber-400 text-xl font-extrabold">৳ {{ number_format($offer + 70, 0) }}</span>
          </div>
        </div>

        <button type="submit" id="submit-order" class="w-full cta-order !py-4 text-lg !rounded-xl !bg-emerald-600 hover:!bg-emerald-700 shadow-xl shadow-emerald-600/25">
          <svg class="w-5 h-5"><use href="#ico-cart"/></svg>
          <span>{{ $c['cta_text'] ?? 'অর্ডার কনফার্ম করুন (ক্যাশ অন ডেলিভারি)' }}</span>
        </button>

        <div id="order-error" class="hidden p-3 bg-red-50 text-red-600 text-xs font-semibold rounded-xl text-center border border-red-200"></div>
      </form>
    </div>
  </section>
  @endif

  <div id="site-footer"></div>

  {{-- Mobile sticky CTA bar --}}
  @if(($show('hero') || $show('order')) && $show('order'))
  <div class="mobile-cta" aria-label="Quick order">
    <div class="price">
      <strong>৳{{ number_format($offer, 0) }}</strong>
      @if($regular > $offer)
        <span>৳{{ number_format($regular, 0) }}</span>
      @endif
    </div>
    @include('landing.partials.buy-now', [
      'btnText'   => $cta,
      'btnClass'  => 'cta-order',
      'formClass' => 'shrink-0',
    ])
  </div>
  @endif

  {{-- WhatsApp Floating Button --}}
  @if($wa && $show('brand'))
    <a href="https://wa.me/{{ $wa }}" class="whatsapp-fab" aria-label="WhatsApp" target="_blank" rel="noopener">
      <svg viewBox="0 0 448 512" fill="currentColor" aria-hidden="true"><path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"/></svg>
    </a>
  @endif

  {{-- Back to top button --}}
  <button type="button" id="back-top" class="back-top" aria-label="Back to top">
    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
  </button>
  
  <div id="toast" class="toast rounded-full bg-ink text-white px-5 py-2.5 text-sm font-semibold shadow-xl" role="status"></div>

  <script>
    window.CHILORA = {
      brand:         @json($c['brand'] ?? 'Chilora'),
      phone:         @json($phone),
      warranty:      @json($c['warranty'] ?? ''),
      promo_left:    @json($c['promo_left'] ?? ''),
      footer_blurb:  @json($c['footer_blurb'] ?? ''),
      offerEndHours: {{ (int) ($c['offer_end_hours'] ?? 18) }},
      offerPrice:    {{ $offer }},
      regularPrice:  {{ $regular }},
      useStoreCheckout: true,
      hideChrome:    @json(! $show('brand')),
      buyNow: {
        url:       @json(route('cart.buy-now')),
        productId: {{ (int) $product->id }},
        csrf:      @json(csrf_token()),
        cta:       @json($cta),
      },
      sections: {
        offer:     @json($show('offer')),
        features:  @json($show('features')),
        why:       @json($show('why')),
        reviews:   @json($show('reviews')),
        phone_cta: @json($show('phone_cta')),
        order:     @json($show('order')),
      }
    };
  </script>
  <script>
    (function() {
      const qtyInput = document.getElementById('qty');
      const plus = document.getElementById('qty-plus');
      const minus = document.getElementById('qty-minus');
      const subtotalEl = document.getElementById('order-subtotal');
      const shippingEl = document.getElementById('order-shipping');
      const totalEl = document.getElementById('order-total');
      const form = document.getElementById('order-form');
      const errEl = document.getElementById('order-error');
      const submitBtn = document.getElementById('submit-order');

      const unitPrice = {{ $offer }};

      function updateTotals() {
        if (!qtyInput || !form) return;
        const qty = parseInt(qtyInput.value) || 1;
        const zone = form.querySelector('input[name="shipping_zone"]:checked');
        const ship = zone ? parseFloat(zone.dataset.shipping) : 70;
        const sub = qty * unitPrice;
        const tot = sub + ship;

        if (subtotalEl) subtotalEl.innerText = '৳ ' + Math.round(sub).toLocaleString();
        if (shippingEl) shippingEl.innerText = '৳ ' + Math.round(ship).toLocaleString();
        if (totalEl) totalEl.innerText = '৳ ' + Math.round(tot).toLocaleString();
      }

      if (plus) plus.addEventListener('click', () => { qtyInput.value = (parseInt(qtyInput.value)||1) + 1; updateTotals(); });
      if (minus) minus.addEventListener('click', () => { qtyInput.value = Math.max(1, (parseInt(qtyInput.value)||1) - 1); updateTotals(); });
      if (qtyInput) qtyInput.addEventListener('input', updateTotals);
      if (form) form.querySelectorAll('input[name="shipping_zone"]').forEach(r => r.addEventListener('change', updateTotals));

      if (form) {
        form.addEventListener('submit', function(e) {
          e.preventDefault();
          if (errEl) { errEl.classList.add('hidden'); errEl.innerText = ''; }
          submitBtn.disabled = true;
          const origText = submitBtn.innerHTML;
          submitBtn.innerHTML = 'অর্ডার প্রসেস হচ্ছে...';

          const fd = new FormData(form);
          fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: fd
          })
          .then(async res => {
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'অর্ডার করতে সমস্যা হয়েছে।');
            if (data.redirect_url) window.location.href = data.redirect_url;
          })
          .catch(err => {
            if (errEl) { errEl.innerText = err.message; errEl.classList.remove('hidden'); }
            submitBtn.disabled = false;
            submitBtn.innerHTML = origText;
          });
        });
      }
    })();
  </script>
  <script src="{{ asset('landing/chilora/js/shell.js') }}?v=v4" defer></script>
  <script src="{{ asset('landing/chilora/js/main.js') }}?v=v4" defer></script>
</body>
</html>
