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
  <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com" data-cfasync="false"></script>
  <script data-cfasync="false">
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            ink:    "#0f0c29",
            violet: "#7c3aed",
            vmid:   "#6d28d9",
            vdeep:  "#4c1d95",
            vsoft:  "#ede9fe",
            rose:   "#f43f5e",
            gold:   "#f59e0b",
            muted:  "#64748b",
          },
          fontFamily: { sans: ["Hind Siliguri", "Manrope", "sans-serif"] },
        },
      },
    };
  </script>
  <link rel="stylesheet" href="{{ asset('landing/nuraya/css/style.css') }}?v=v4" />

  {{-- Inline SVG icon helpers (reusable via <use>) --}}
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
    <symbol id="ico-tag" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>
    </symbol>
    <symbol id="ico-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
    </symbol>
    <symbol id="ico-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
    </symbol>
    <symbol id="ico-cart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
    </symbol>
    <symbol id="ico-bolt" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
    </symbol>
    <symbol id="ico-arrow-up" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/>
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
    $cta     = $c['cta_text'] ?? 'এখনই অর্ডার করুন';
  @endphp

  {{-- ══════════════════════════════════════════ HERO ══ --}}
  @if($show('hero'))
  <section class="hero">
    <div class="max-w-6xl mx-auto px-4 hero-grid">
      <div class="reveal order-2 lg:order-1">
        @if(!empty($c['hero_chip']))
          <p class="collection-chip mb-4">
            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
            {{ $c['hero_chip'] }}
          </p>
        @endif
        <h1 class="font-display text-[1.9rem] sm:text-[2.4rem] lg:text-[2.75rem] leading-[1.25] text-ink mb-3 whitespace-pre-line">{{ $c['hero_title'] ?? $page->title }}</h1>
        @if(!empty($c['hero_kicker']))
          <p class="hero-kicker mb-3">{{ $c['hero_kicker'] }}</p>
        @endif
        <p class="hero-copy">{{ $c['hero_subtitle'] ?? '' }}</p>
        <div class="hero-meta">
          <span class="price-chip">
            মূল্য: ৳{{ number_format($offer, 0) }}
            @if($regular > $offer)
              <span class="was">৳{{ number_format($regular, 0) }}</span>
            @endif
          </span>
          @if($phone)
            <a href="tel:{{ $tel }}" class="phone-chip">
              <svg class="w-4 h-4"><use href="#ico-phone"/></svg>
              {{ $phone }}
            </a>
          @endif
        </div>
        <div class="hero-actions">
          @include('landing.partials.buy-now', [
            'btnText'  => $cta.' →',
            'btnClass' => 'btn-primary',
          ])
          @if($show('order'))
            <a href="#order" class="btn-secondary">বিস্তারিত দেখুন</a>
          @endif
        </div>
      </div>
      <div class="reveal order-1 lg:order-2 hero-visual">
        <div class="hero-card">
          <div class="relative">
            <img class="fabric-ph" src="{{ $heroImg }}" alt="{{ $c['product_label'] ?? $page->title }}" width="640" height="800" loading="eager" referrerpolicy="no-referrer" onerror="this.onerror=null;this.removeAttribute('src');this.style.minHeight='280px'" />
            <span class="tag-pill top">১০০% সুতি</span>
            <span class="tag-pill bot">ক্যাশ অন ডেলিভারি</span>
          </div>
        </div>
      </div>
    </div>
  </section>

  {{-- Trust badges strip --}}
  <div class="trust-strip max-w-6xl mx-auto px-4">
    <span class="trust-badge">
      <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
      ক্যাশ অন ডেলিভারি
    </span>
    <span class="text-gray-300">|</span>
    <span class="trust-badge">
      <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      ৬ মাসের ওয়ারেন্টি
    </span>
    <span class="text-gray-300">|</span>
    <span class="trust-badge">
      <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      দ্রুত ডেলিভারি
    </span>
    <span class="text-gray-300">|</span>
    <span class="trust-badge">
      <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
      মানের নিশ্চয়তা
    </span>
  </div>
  @endif

  {{-- ══════════════════════════════════════════ OFFER ══ --}}
  @if($show('offer'))
  <section id="offer" class="offer-band">
    <div class="max-w-6xl mx-auto px-4 text-center reveal">
      @if(!empty($c['offer_badge']))
        <p class="inline-flex items-center gap-1.5 text-gold font-bold mb-3 tracking-wide text-sm">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><polygon points="10 1 12.9 7 19.5 8.1 15 13 16.2 19.8 10 16.5 3.8 19.8 5 13 .5 8.1 7.1 7"/></svg>
          {{ $c['offer_badge'] }}
        </p>
      @endif
      <h2 class="font-display text-2xl sm:text-3xl mb-2">{{ $c['offer_title'] ?? 'অফার শেষ হওয়ার আগেই অর্ডার করুন' }}</h2>
      <div class="countdown-row">
        <div class="countdown-cell"><div id="cd-h" class="text-2xl sm:text-3xl font-extrabold tabular-nums">00</div><div class="text-[10px] sm:text-xs opacity-80 font-semibold mt-1">ঘন্টা</div></div>
        <div class="countdown-cell"><div id="cd-m" class="text-2xl sm:text-3xl font-extrabold tabular-nums">00</div><div class="text-[10px] sm:text-xs opacity-80 font-semibold mt-1">মিনিট</div></div>
        <div class="countdown-cell"><div id="cd-s" class="text-2xl sm:text-3xl font-extrabold tabular-nums">00</div><div class="text-[10px] sm:text-xs opacity-80 font-semibold mt-1">সেকেন্ড</div></div>
      </div>
      <p class="offer-price-line">
        রেগুলার <span class="line-through opacity-75">৳{{ number_format($regular, 0) }}</span>
        &nbsp;·&nbsp; অফার <span class="now">৳{{ number_format($offer, 0) }}</span>
      </p>
      @include('landing.partials.buy-now', [
        'btnText'  => $cta,
        'btnClass' => 'btn-primary',
      ])
    </div>
  </section>
  @endif

  {{-- ══════════════════════════════════════════ BENEFITS ══ --}}
  @if($show('benefits'))
  <section id="benefits" class="section-pad">
    <div class="max-w-6xl mx-auto px-4">
      <div class="text-center max-w-2xl mx-auto mb-9 sm:mb-11 reveal">
        <p class="section-badge mb-3">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          কেন এই পণ্য
        </p>
        <h2 class="font-display text-2xl sm:text-3xl text-ink">{{ $c['benefits_heading'] ?? '' }}</h2>
        <div class="section-rule"></div>
      </div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
        @foreach($c['benefits'] ?? [] as $i => $b)
          <article class="benefit-card reveal">
            <div class="benefit-num">{{ $i + 1 }}</div>
            <h3 class="font-bold text-base sm:text-lg mb-3 text-ink">{{ $b['title'] ?? '' }}</h3>
            <ul>
              @foreach(preg_split("/\r\n|\n|\r/", $b['items'] ?? '') as $line)
                @if(trim($line) !== '')
                  <li>{{ trim($line) }}</li>
                @endif
              @endforeach
            </ul>
          </article>
        @endforeach
      </div>
      <div class="text-center mt-9 sm:mt-11 reveal">
        @include('landing.partials.buy-now', [
          'btnText'  => $cta,
          'btnClass' => 'btn-primary',
        ])
      </div>
    </div>
  </section>
  @endif

  {{-- ══════════════════════════════════════════ REVIEWS ══ --}}
  @if($show('reviews'))
  <section id="reviews" class="section-pad bg-white/50">
    <div class="max-w-6xl mx-auto px-4">
      <div class="text-center mb-8 reveal">
        <p class="section-badge mb-3">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          কাস্টমার রিভিউ
        </p>
        <h2 class="font-display text-2xl sm:text-3xl text-ink">{{ $c['reviews_heading'] ?? '' }}</h2>
        <div class="section-rule"></div>
      </div>
      <div class="review-rail reveal">
        @foreach($c['testimonials'] ?? [] as $t)
          <figure class="review-card">
            @if(!empty($t['image']))
              <img src="{{ $page->mediaUrl($t['image']) }}" alt="" class="fabric-ph" loading="lazy" referrerpolicy="no-referrer" onerror="this.onerror=null;this.removeAttribute('src')" />
            @else
              <div class="fabric-ph ph-block"></div>
            @endif
            <figcaption>
              <div class="stars">
                @for($s=0;$s<5;$s++)<svg viewBox="0 0 24 24"><use href="#ico-star"/></svg>@endfor
              </div>
              {{ $t['text'] ?? '' }}
            </figcaption>
          </figure>
        @endforeach
      </div>
    </div>
  </section>
  @endif

  {{-- ══════════════════════════════════════════ ORDER ══ --}}
  @if($show('order'))
  <section id="order" class="section-pad order-section">
    <div class="max-w-6xl mx-auto px-4">
      <div class="text-center mb-8 sm:mb-10 reveal">
        <p class="section-badge mb-3">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
          চেকআউট
        </p>
        <h2 class="font-display text-2xl sm:text-3xl text-ink mb-2">{{ $c['order_heading'] ?? '' }}</h2>
        <p class="text-[var(--muted)] text-sm max-w-lg mx-auto">{{ $c['order_subtext'] ?? '' }}</p>
      </div>
      <form id="order-form" method="POST" action="{{ route('landing.order', $page->slug) }}" class="order-panel reveal max-w-xl mx-auto grid gap-5 bg-white p-6 rounded-3xl border border-line shadow-xl">
        @csrf
        <div class="order-product flex items-center gap-3 p-3 bg-violet-50/50 rounded-2xl border border-violet-100">
          <img src="{{ $thumb }}" alt="" class="w-16 h-16 object-cover rounded-xl border border-line" loading="lazy" />
          <div class="flex-1 min-w-0">
            <p class="font-bold text-sm sm:text-base leading-snug text-slate-900">{{ $c['product_label'] ?? $product->name }}</p>
            <p class="text-violet-700 font-extrabold text-base mt-0.5">
              ৳{{ number_format($offer, 0) }}
              @if($regular > $offer)
                <span class="text-xs font-semibold text-slate-400 line-through ml-1">৳{{ number_format($regular, 0) }}</span>
              @endif
            </p>
          </div>
          <div class="qty-wrap flex items-center gap-1">
            <input id="nuraya-qty" name="qty" type="number" min="1" max="99" value="1" class="w-14 text-center py-1.5 border border-line rounded-lg font-bold" />
          </div>
        </div>

        <div class="space-y-3">
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">আপনার নাম <span class="text-red-500">*</span></label>
            <input type="text" name="customer_name" required placeholder="আপনার সম্পূর্ণ নাম" class="w-full px-3.5 py-2.5 rounded-xl border border-line text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none" />
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">মোবাইল নম্বর <span class="text-red-500">*</span></label>
            <input type="tel" name="customer_phone" required placeholder="১১ ডিজিটের মোবাইল নম্বর" class="w-full px-3.5 py-2.5 rounded-xl border border-line text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none" />
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">সম্পূর্ণ ঠিকানা <span class="text-red-500">*</span></label>
            <textarea name="shipping_address" required rows="2" placeholder="রোড নং, এলাকা, থানা ও জেলা" class="w-full px-3.5 py-2.5 rounded-xl border border-line text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none"></textarea>
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">ডেলিভারি এরিয়া <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-2 gap-2">
              <label class="cursor-pointer border-2 border-line rounded-xl p-2.5 flex items-center justify-between hover:border-violet-400">
                <input type="radio" name="shipping_zone" value="inside_dhaka" checked class="accent-violet-600" data-shipping="70" />
                <span class="text-xs font-bold text-slate-800 ml-1.5">ঢাকার ভিতরে (৳৭০)</span>
              </label>
              <label class="cursor-pointer border-2 border-line rounded-xl p-2.5 flex items-center justify-between hover:border-violet-400">
                <input type="radio" name="shipping_zone" value="outside_dhaka" class="accent-violet-600" data-shipping="130" />
                <span class="text-xs font-bold text-slate-800 ml-1.5">ঢাকার বাইরে (৳১৩০)</span>
              </label>
            </div>
          </div>
        </div>

        <div class="rounded-xl bg-slate-900 text-white p-4 text-xs space-y-2">
          <div class="flex justify-between items-center text-slate-300">
            <span>পণ্য মূল্য:</span>
            <span id="nuraya-subtotal" class="font-bold text-white">৳{{ number_format($offer, 0) }}</span>
          </div>
          <div class="flex justify-between items-center text-slate-300">
            <span>ডেলিভারি চার্জ:</span>
            <span id="nuraya-shipping" class="font-bold text-white">৳70</span>
          </div>
          <div class="flex justify-between items-center font-bold text-base border-t border-slate-700 pt-2 text-amber-400">
            <span>সর্বমোট পরিশোধযোগ্য:</span>
            <span id="nuraya-total" class="text-lg font-black">৳{{ number_format($offer + 70, 0) }}</span>
          </div>
        </div>

        <button type="submit" id="submit-order" class="w-full py-3.5 bg-violet-600 hover:bg-violet-700 text-white font-extrabold text-base rounded-xl shadow-lg transition-all">
          <svg class="w-4 h-4 inline-block mr-1 -mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          {{ $cta ?? 'অর্ডার কনফার্ম করুন (ক্যাশ অন ডেলিভারি)' }}
        </button>

        <div id="nuraya-order-err" class="hidden p-2.5 bg-red-50 text-red-600 text-xs font-semibold rounded-xl text-center border border-red-200"></div>
      </form>
    </div>
  </section>
  @endif

  <div id="site-footer"></div>

  @if(($show('hero') || $show('order')) && $show('order'))
  <div class="mobile-cta" aria-label="Quick order">
    <div class="price">
      <strong>৳{{ number_format($offer, 0) }}</strong>
      @if($regular > $offer)
        <span>৳{{ number_format($regular, 0) }}</span>
      @endif
    </div>
    @include('landing.partials.buy-now', [
      'btnText'  => $cta,
      'btnClass' => 'btn-primary',
      'formClass'=> 'shrink-0',
    ])
  </div>
  @endif

  @if($wa && $show('brand'))
    <a href="https://wa.me/{{ $wa }}" class="whatsapp-fab" aria-label="WhatsApp" target="_blank" rel="noopener">
      <svg viewBox="0 0 448 512" fill="currentColor" aria-hidden="true"><path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"/></svg>
    </a>
  @endif
  <button type="button" id="back-top" class="back-top" aria-label="Back to top">
    <svg class="w-5 h-5 mx-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
  </button>
  <div id="toast" class="toast" role="status"></div>

  <script>
    window.NURAYA = {
      brand:        @json($c['brand'] ?? 'Nuraya'),
      phone:        @json($phone),
      promo_text:   @json($c['promo_text'] ?? ''),
      footer_blurb: @json($c['footer_blurb'] ?? ''),
      offerEndHours: {{ (int) ($c['offer_end_hours'] ?? 23) }},
      priceMin:     {{ $offer }},
      imgBase:      @json(asset('landing/nuraya/img')),
      useStoreCheckout: true,
      hideChrome:   @json(! $show('brand')),
      buyNow: {
        url:       @json(route('cart.buy-now')),
        productId: {{ (int) $product->id }},
        csrf:      @json(csrf_token()),
        cta:       @json($cta),
      },
      sections: {
        offer:    @json($show('offer')),
        benefits: @json($show('benefits')),
        reviews:  @json($show('reviews')),
        order:    @json($show('order')),
      }
    };
  </script>
  <script>
    (function() {
      const qtyInput = document.getElementById('nuraya-qty');
      const subtotalEl = document.getElementById('nuraya-subtotal');
      const shippingEl = document.getElementById('nuraya-shipping');
      const totalEl = document.getElementById('nuraya-total');
      const form = document.getElementById('order-form');
      const errEl = document.getElementById('nuraya-order-err');
      const submitBtn = document.getElementById('submit-order');

      const unitPrice = {{ $offer }};

      function updateTotals() {
        if (!qtyInput || !form) return;
        const qty = parseInt(qtyInput.value) || 1;
        const zone = form.querySelector('input[name="shipping_zone"]:checked');
        const ship = zone ? parseFloat(zone.dataset.shipping) : 70;
        const sub = qty * unitPrice;
        const tot = sub + ship;

        if (subtotalEl) subtotalEl.innerText = '৳' + Math.round(sub).toLocaleString();
        if (shippingEl) shippingEl.innerText = '৳' + Math.round(ship).toLocaleString();
        if (totalEl) totalEl.innerText = '৳' + Math.round(tot).toLocaleString();
      }

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
  <script src="{{ asset('landing/nuraya/js/shell.js') }}?v=v4" defer></script>
  <script src="{{ asset('landing/nuraya/js/main.js') }}?v=v4" defer></script>
</body>
</html>
