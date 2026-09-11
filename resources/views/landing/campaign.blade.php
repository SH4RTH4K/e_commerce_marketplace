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
  <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com" data-cfasync="false"></script>
  <script data-cfasync="false">
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              50:  '#eff6ff',
              100: '#dbeafe',
              500: '#3b82f6',
              600: '#2563eb',
              700: '#1d4ed8',
              800: '#1e40af',
              900: '#1e3a8a',
            },
            accent: {
              orange: '#ff6b00',
              amber:  '#f59e0b',
              green:  '#10b981',
              emerald:'#059669',
            }
          },
          fontFamily: {
            sans: ['Hind Siliguri', 'Plus Jakarta Sans', 'sans-serif'],
            display: ['Hind Siliguri', 'sans-serif'],
          }
        }
      }
    };
  </script>
  <style>
    body {
      font-family: 'Hind Siliguri', 'Plus Jakarta Sans', sans-serif;
      background-color: #f8fafc;
      color: #0f172a;
      scroll-behavior: smooth;
    }

    .cross-price {
      position: relative;
      display: inline-block;
      padding: 0 4px;
    }
    .cross-price::before,
    .cross-price::after {
      content: '';
      position: absolute;
      width: 100%;
      height: 2.5px;
      background-color: #ef4444;
      top: 50%;
      left: 0;
      transform-origin: center;
    }
    .cross-price::before {
      transform: rotate(16deg);
    }
    .cross-price::after {
      transform: rotate(-16deg);
    }

    .underline-draw {
      position: relative;
      display: inline-block;
    }
    .underline-draw::after {
      content: '';
      position: absolute;
      left: 0;
      bottom: -4px;
      width: 100%;
      height: 4px;
      background: #f59e0b;
      border-radius: 2px;
    }

    .pulse-glow {
      animation: pulse-shadow 2s infinite;
    }
    @keyframes pulse-shadow {
      0% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6);
      }
      70% {
        box-shadow: 0 0 0 15px rgba(16, 185, 129, 0);
      }
      100% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
      }
    }

    .countdown-card {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.25);
      border-radius: 12px;
      padding: 8px 12px;
      text-align: center;
      min-width: 65px;
    }

    @media (max-width: 640px) {
      .countdown-card {
        min-width: 52px;
        padding: 6px 8px;
      }
    }

    .radio-card input:checked + div {
      border-color: #ff6b00;
      background-color: #fff7ed;
      box-shadow: 0 4px 12px rgba(255, 107, 0, 0.15);
    }
  </style>
</head>
<body class="antialiased pb-20 sm:pb-0">

  @php
    $show = fn($sec) => $page->sectionVisible($sec);
    $phone = $c['phone'] ?? setting('contact_phone', '01923443872');
    $whatsapp = $c['whatsapp'] ?? setting('whatsapp_number', '8801923443872');
    $regularPrice = (float) ($c['regular_price'] ?? 1650);
    $offerPrice = (float) ($c['offer_price'] ?? 699);
    $packages = $c['package_options'] ?? [
      ['name' => '১ সেট (৫টি মাস্টারপিস বই)', 'price' => $offerPrice, 'qty' => 1, 'badge' => 'জনপ্রিয় অফার'],
      ['name' => '২ সেট (১০টি বই - স্পেশাল গিফট সহ)', 'price' => 1299, 'qty' => 2, 'badge' => 'সেরা সেভিংস']
    ];
    $shippingInside = (float) ($c['shipping_inside'] ?? setting('shipping_inside_dhaka', 70));
    $shippingOutside = (float) ($c['shipping_outside'] ?? setting('shipping_outside_dhaka', 130));
  @endphp

  {{-- 1. Top Urgent Bar & Countdown Timer --}}
  @if($show('top_timer'))
  <header style="background: {{ $c['top_bg_gradient'] ?? 'radial-gradient(at center center, #1877F2 28%, #0a4898 79%)' }}" class="text-white py-3 sm:py-4 px-4 shadow-md sticky top-0 z-40">
    <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-center justify-between gap-3">
      <div class="text-center md:text-left">
        <h1 class="text-base sm:text-lg md:text-xl font-extrabold tracking-wide text-white leading-snug">
          {{ $c['top_timer_headline'] ?? 'সফলতার রোডম্যাপ ৫টি মাস্টারপিস বই পাচ্ছেন মাত্র ৬৯৯ টাকায়।' }}
          <span class="text-amber-300 block sm:inline font-bold text-sm sm:text-base mt-0.5 sm:mt-0">
            {{ $c['top_timer_highlight'] ?? 'সময় শেষ হলে অফার আর আসবে না — অর্ডার করুন এখনই!' }}
          </span>
        </h1>
      </div>

      {{-- Countdown Box --}}
      <div class="flex items-center gap-2" id="countdown-timer">
        <div class="countdown-card">
          <div class="text-xl sm:text-2xl font-black text-amber-300" id="timer-days">00</div>
          <div class="text-[10px] sm:text-xs font-semibold uppercase tracking-wider text-white/90">দিন</div>
        </div>
        <div class="countdown-card">
          <div class="text-xl sm:text-2xl font-black text-amber-300" id="timer-hours">13</div>
          <div class="text-[10px] sm:text-xs font-semibold uppercase tracking-wider text-white/90">ঘণ্টা</div>
        </div>
        <div class="countdown-card">
          <div class="text-xl sm:text-2xl font-black text-amber-300" id="timer-mins">59</div>
          <div class="text-[10px] sm:text-xs font-semibold uppercase tracking-wider text-white/90">মিনিট</div>
        </div>
        <div class="countdown-card">
          <div class="text-xl sm:text-2xl font-black text-amber-300" id="timer-secs">48</div>
          <div class="text-[10px] sm:text-xs font-semibold uppercase tracking-wider text-white/90">সেকেন্ড</div>
        </div>
      </div>
    </div>
  </header>
  @endif

  <main class="max-w-4xl mx-auto px-4 py-6 sm:py-10 space-y-8 sm:space-y-12">

    {{-- 2. Hero Section: Headline, Video/Image & Price Banner --}}
    @if($show('hero'))
    <section class="text-center space-y-6">
      
      {{-- Animated Outline Headline --}}
      <div class="border-2 border-dashed border-emerald-600 bg-emerald-50/50 rounded-2xl p-4 sm:p-6 shadow-sm">
        <h2 class="text-xl sm:text-3xl md:text-4xl font-extrabold text-slate-900 leading-snug sm:leading-tight">
          {{ $c['hero_headline'] ?? 'সফলতার রোডম্যাপ ৫টি মাস্টারপিস বই পাচ্ছেন মাত্র ৬৯৯ টাকায়।' }}
        </h2>
        @if(!empty($c['hero_subtitle']))
          <p class="text-sm sm:text-base text-slate-600 mt-2 font-medium max-w-2xl mx-auto">
            {{ $c['hero_subtitle'] }}
          </p>
        @endif
      </div>

      {{-- Media: Video Embed or Hero Image --}}
      <div class="rounded-2xl overflow-hidden shadow-xl border border-slate-200 bg-white">
        @php
          $videoUrl = trim((string) ($c['hero_video_url'] ?? ''));
          if ($videoUrl !== '') {
            if (str_contains($videoUrl, 'watch?v=')) {
              $videoUrl = str_replace('watch?v=', 'embed/', $videoUrl);
            } elseif (str_contains($videoUrl, 'youtu.be/')) {
              $videoUrl = str_replace('youtu.be/', 'www.youtube.com/embed/', $videoUrl);
            }
          }
        @endphp
        @if(!empty($videoUrl))
          <div class="relative w-full aspect-video bg-black">
            <iframe src="{{ $videoUrl }}?autoplay=0&rel=0" class="absolute inset-0 w-full h-full border-0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
          </div>
        @elseif(!empty($c['hero_image']))
          <img src="{{ $page->mediaUrl($c['hero_image']) }}" alt="{{ $c['hero_headline'] ?? 'Campaign Image' }}" class="w-full h-auto object-cover max-h-[520px]" />
        @else
          <img src="https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=1000&q=80" alt="Hero Banner" class="w-full h-auto object-cover max-h-[520px]" />
        @endif
      </div>

      {{-- Price Banner Card --}}
      <div class="bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 text-white rounded-2xl p-6 sm:p-8 shadow-xl text-center space-y-4 border border-blue-900/50">
        
        <div class="inline-block bg-orange-500 text-white font-bold text-xs sm:text-sm px-4 py-1 rounded-full uppercase tracking-wider shadow-sm">
          {{ $c['offer_badge'] ?? 'সীমিত সময়ের বিশেষ অফার' }}
        </div>

        <div class="space-y-1">
          <div class="text-base sm:text-xl font-medium text-slate-300">
            রেগুলার মূল্য: <span class="cross-price text-red-400 font-bold text-lg sm:text-2xl">৳{{ number_format($regularPrice, 0) }}</span> টাকা
          </div>
          <div class="text-3xl sm:text-5xl md:text-6xl font-black text-amber-400">
            অফার মূল্য: <span class="underline-draw text-white">৳{{ number_format($offerPrice, 0) }}</span> <span class="text-2xl sm:text-4xl text-amber-400 font-bold">টাকা</span>
          </div>
        </div>

        <div class="pt-3">
          <a href="#order-form-section" class="inline-flex items-center justify-center gap-3 w-full sm:w-auto px-8 py-4 bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white font-black text-lg sm:text-xl rounded-xl shadow-lg transform active:scale-95 transition-all pulse-glow">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            <span>{{ $c['cta_text'] ?? 'অর্ডার করতে চাই — ক্যাশ অন ডেলিভারি' }}</span>
          </a>
        </div>
      </div>
    </section>
    @endif

    {{-- 3. Urgency / Warning Box --}}
    @if($show('notice') && (!empty($c['notice_heading']) || !empty($c['notice_text'])))
    <section class="border-3 border-dashed border-red-500 bg-white rounded-2xl overflow-hidden shadow-md">
      <div class="bg-slate-900 text-white font-bold text-base sm:text-lg py-3 px-5 text-center flex items-center justify-center gap-2">
        <span class="text-red-400 text-xl">⚠️</span>
        <span>{{ $c['notice_heading'] ?? 'সতর্কতা / বিশেষ দ্রষ্টব্য' }}</span>
      </div>
      <div class="p-5 sm:p-6 text-slate-800 text-sm sm:text-base leading-relaxed whitespace-pre-line font-medium bg-amber-50/40">
        {{ $c['notice_text'] ?? "১. পণ্য হাতে পেয়ে চেক করে সম্পূর্ণ মূল্য পরিশোধ করবেন।\n২. অগ্রিম ১ টাকাও দেওয়া লাগবে না।\n৩. ডেলিভারি ম্যানের সামনে প্যাকেট খুলে চেক করার সুযোগ রয়েছে।" }}
      </div>
    </section>
    @endif

    {{-- 4. Product Items / Book Breakdown --}}
    @if($show('features') && !empty($c['features']))
    <section class="space-y-6">
      <div class="text-center space-y-2">
        <h3 class="text-xl sm:text-3xl font-extrabold text-slate-900">
          {{ $c['features_heading'] ?? '#বুক লিস্ট — যে ৫টি মাস্টারপিস বই পাচ্ছেন' }}
        </h3>
        @if(!empty($c['features_intro']))
          <p class="text-sm sm:text-base text-slate-600">{{ $c['features_intro'] }}</p>
        @endif
      </div>

      <div class="space-y-4">
        @foreach($c['features'] as $index => $item)
        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 shadow-sm flex flex-col sm:flex-row items-center gap-4 hover:border-emerald-300 transition-all">
          @if(!empty($item['image']))
            <img src="{{ $page->mediaUrl($item['image']) }}" alt="{{ $item['title'] ?? '' }}" class="w-20 h-24 sm:w-24 sm:h-28 object-cover rounded-xl shadow shrink-0 border border-slate-100" />
          @endif
          <div class="flex-1 text-center sm:text-left space-y-1">
            <h4 class="font-extrabold text-base sm:text-lg text-slate-900">{{ $item['title'] ?? '' }}</h4>
            <p class="text-sm text-slate-600 leading-relaxed font-medium">{{ $item['body'] ?? '' }}</p>
          </div>
          <div class="shrink-0">
            <span class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 font-bold flex items-center justify-center text-sm">
              ✓
            </span>
          </div>
        </div>
        @endforeach
      </div>

      <div class="text-center pt-2">
        <a href="#order-form-section" class="inline-flex items-center justify-center gap-2 px-6 py-3.5 bg-slate-900 hover:bg-black text-white font-bold text-base rounded-xl shadow transition-all">
          <span>অর্ডার করতে ক্লিক করুন</span>
          <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
        </a>
      </div>
    </section>
    @endif

    {{-- 5. Benefits / Why Choose Us --}}
    @if($show('why') && !empty($c['benefits']))
    <section class="space-y-6">
      <div class="text-center">
        <h3 class="text-xl sm:text-3xl font-extrabold text-slate-900">
          {{ $c['why_heading'] ?? 'কেন এই বইগুলো আপনার পড়া উচিত?' }}
        </h3>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @foreach($c['benefits'] as $b)
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex items-start gap-4">
          <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 text-xl font-bold">
            ✨
          </div>
          <div class="space-y-1">
            <h4 class="font-extrabold text-base text-slate-900">{{ $b['title'] ?? '' }}</h4>
            <p class="text-sm text-slate-600 leading-relaxed font-medium">{{ $b['body'] ?? '' }}</p>
          </div>
        </div>
        @endforeach
      </div>
    </section>
    @endif

    {{-- 6. Customer Reviews & Proof --}}
    @if($show('reviews') && !empty($c['testimonials']))
    <section class="space-y-6">
      <div class="text-center">
        <h3 class="text-xl sm:text-3xl font-extrabold text-slate-900">
          {{ $c['reviews_heading'] ?? 'আমাদের সম্মানিত পাঠকদের প্রতিক্রিয়া' }}
        </h3>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @foreach($c['testimonials'] as $rev)
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex flex-col justify-between space-y-4">
          <div class="space-y-3">
            <div class="flex text-amber-400 text-sm">★★★★★</div>
            <p class="text-sm text-slate-700 italic font-medium leading-relaxed">{{ $rev['text'] ?? '' }}</p>
          </div>
          <div class="flex items-center gap-3 pt-3 border-t border-slate-100">
            @if(!empty($rev['image']))
              <img src="{{ $page->mediaUrl($rev['image']) }}" alt="{{ $rev['name'] ?? 'Reviewer' }}" class="w-10 h-10 rounded-full object-cover border border-slate-200" />
            @else
              <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center text-sm">
                {{ mb_substr($rev['name'] ?? 'U', 0, 1) }}
              </div>
            @endif
            <div>
              <h5 class="font-bold text-sm text-slate-900">{{ $rev['name'] ?? 'সন্তুষ্ট গ্রাহক' }}</h5>
              <span class="text-[11px] text-emerald-600 font-medium">✓ ভেরিফাইড ক্রেতা</span>
            </div>
          </div>
        </div>
        @endforeach
      </div>
    </section>
    @endif

    {{-- 7. Direct 1-Page Cash on Delivery Checkout Block --}}
    @if($show('order'))
    <section id="order-form-section" class="bg-white rounded-3xl p-5 sm:p-8 border-2 border-emerald-500 shadow-2xl space-y-6">
      
      <div class="text-center space-y-2 pb-4 border-b border-slate-100">
        <span class="bg-emerald-100 text-emerald-800 text-xs font-extrabold px-3 py-1 rounded-full uppercase tracking-wider">
          ক্যাশ অন ডেলিভারি
        </span>
        <h3 class="text-xl sm:text-3xl font-extrabold text-slate-900">
          {{ $c['order_heading'] ?? 'অর্ডার করতে নিচের ফর্মটি সঠিকভাবে পূরণ করুন' }}
        </h3>
        <p class="text-sm text-slate-600 font-medium">
          {{ $c['order_subtext'] ?? 'ক্যাশ অন ডেলিভারি — পণ্য হাতে পেয়ে চেক করে মূল্য পরিশোধ করুন।' }}
        </p>
      </div>

      <form id="landing-order-form" method="POST" action="{{ route('landing.order', $page->slug) }}" class="space-y-6">
        @csrf

        {{-- Anti-Bot Honeypots (Invisible to humans, traps bots) --}}
        <div style="display:none !important;" aria-hidden="true">
          <input type="text" name="b_trap" value="" tabindex="-1" autocomplete="off" />
          <input type="text" name="website_url" value="" tabindex="-1" autocomplete="off" />
        </div>

        {{-- Package Selector --}}
        @if(!empty($packages) && count($packages) > 0)
        <div class="space-y-3">
          <label class="block text-sm font-extrabold text-slate-800">প্যাকেজ নির্বাচন করুন:</label>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($packages as $idx => $pkg)
            <label class="radio-card cursor-pointer relative block">
              <input type="radio" name="package_index" value="{{ $idx }}" {{ $idx === 0 ? 'checked' : '' }} class="peer sr-only" data-price="{{ (float)$pkg['price'] }}" data-qty="{{ (int)($pkg['qty'] ?? 1) }}" />
              <div class="p-4 rounded-2xl border-2 border-slate-200 hover:border-orange-300 transition-all flex items-center justify-between">
                <div class="space-y-0.5">
                  <div class="font-bold text-sm sm:text-base text-slate-900">{{ $pkg['name'] }}</div>
                  @if(!empty($pkg['badge']))
                    <span class="inline-block text-[11px] font-bold text-orange-600 bg-orange-100 px-2 py-0.5 rounded-full">{{ $pkg['badge'] }}</span>
                  @endif
                </div>
                <div class="text-right">
                  <span class="text-base sm:text-lg font-black text-emerald-600">৳{{ number_format((float)$pkg['price'], 0) }}</span>
                </div>
              </div>
            </label>
            @endforeach
          </div>
        </div>
        @endif

        {{-- Customer Info Inputs --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="sm:col-span-2">
            <label class="block text-sm font-bold text-slate-700 mb-1.5">আপনার নাম <span class="text-red-500">*</span></label>
            <input type="text" id="cust-name" name="customer_name" required placeholder="সম্পূর্ণ নাম লিখুন (যেমন: তানভীর আহমেদ)" class="w-full px-4 py-3 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none bg-slate-50/50" />
            <div id="name-feedback" class="text-xs font-semibold mt-1 hidden text-red-500"></div>
          </div>

          <div class="sm:col-span-2">
            <label class="block text-sm font-bold text-slate-700 mb-1.5">মোবাইল নাম্বার <span class="text-red-500">*</span></label>
            <div class="relative">
              <input type="tel" id="cust-phone" name="customer_phone" required placeholder="১১ ডিজিটের মোবাইল নম্বর (যেমন: 01712345678)" maxlength="14" class="w-full px-4 py-3 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none bg-slate-50/50 font-medium" />
              <div id="phone-valid-icon" class="absolute right-3.5 top-3.5 hidden">
                <span class="text-emerald-500 font-bold text-base">✓</span>
              </div>
            </div>
            <div id="phone-feedback" class="text-xs font-semibold mt-1 hidden"></div>
          </div>

          <div class="sm:col-span-2">
            <label class="block text-sm font-bold text-slate-700 mb-1.5">সম্পূর্ণ ডেলিভারি ঠিকানা <span class="text-red-500">*</span></label>
            <textarea id="cust-addr" name="shipping_address" required rows="2" placeholder="রোড, বাড়ি নম্বর, এলাকা, থানা ও জেলার নাম লিখুন" class="w-full px-4 py-3 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none bg-slate-50/50"></textarea>
            <div id="addr-feedback" class="text-xs font-semibold mt-1 hidden text-red-500"></div>
          </div>

          {{-- Delivery Zone Selection --}}
          <div class="sm:col-span-2 space-y-2">
            <label class="block text-sm font-bold text-slate-700">ডেলিভারি এরিয়া নির্বাচন করুন <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <label class="radio-card cursor-pointer block">
                <input type="radio" name="shipping_zone" value="inside_dhaka" checked class="peer sr-only" data-shipping="{{ $shippingInside }}" />
                <div class="p-3.5 rounded-xl border-2 border-slate-200 hover:border-emerald-400 transition-all flex items-center justify-between">
                  <div class="font-bold text-sm text-slate-800">🏢 ঢাকার ভিতরে</div>
                  <div class="font-black text-emerald-600">৳{{ number_format($shippingInside, 0) }}</div>
                </div>
              </label>

              <label class="radio-card cursor-pointer block">
                <input type="radio" name="shipping_zone" value="outside_dhaka" class="peer sr-only" data-shipping="{{ $shippingOutside }}" />
                <div class="p-3.5 rounded-xl border-2 border-slate-200 hover:border-emerald-400 transition-all flex items-center justify-between">
                  <div class="font-bold text-sm text-slate-800">🚚 ঢাকার বাইরে</div>
                  <div class="font-black text-emerald-600">৳{{ number_format($shippingOutside, 0) }}</div>
                </div>
              </label>
            </div>
          </div>
        </div>

        {{-- Live Calculation Order Summary --}}
        <div class="bg-slate-900 text-white rounded-2xl p-5 space-y-3 shadow-inner">
          <div class="flex justify-between items-center text-sm text-slate-300">
            <span>প্রোডাক্ট মূল্য:</span>
            <span id="summary-subtotal" class="font-bold text-white">৳{{ number_format($offerPrice, 0) }}</span>
          </div>
          <div class="flex justify-between items-center text-sm text-slate-300">
            <span>ডেলিভারি চার্জ:</span>
            <span id="summary-shipping" class="font-bold text-white">৳{{ number_format($shippingInside, 0) }}</span>
          </div>
          <div class="flex justify-between items-center text-lg sm:text-xl font-extrabold border-t border-slate-800 pt-3 text-amber-400">
            <span>সর্বমোট পরিশোধযোগ্য:</span>
            <span id="summary-total" class="text-2xl font-black">৳{{ number_format($offerPrice + $shippingInside, 0) }}</span>
          </div>
        </div>

        {{-- Submit Button --}}
        <div class="space-y-3">
          <button type="submit" id="btn-submit-order" class="w-full py-4 px-6 bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white font-extrabold text-lg sm:text-xl rounded-2xl shadow-xl transform active:scale-98 transition-all flex items-center justify-center gap-3 cursor-pointer">
            <span>{{ $c['order_btn_text'] ?? 'অর্ডার কনফার্ম করুন (ক্যাশ অন ডেলিভারি)' }}</span>
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
          </button>

          <div class="text-center">
            <span class="text-xs sm:text-sm text-slate-500 font-medium">
              {{ $c['guarantee_text'] ?? '🔒 ১০০% ক্যাশ অন ডেলিভারি ও মানিব্যাক গ্যারান্টি' }}
            </span>
          </div>
        </div>

        <div id="form-error-msg" class="hidden p-4 bg-red-50 text-red-700 text-sm font-semibold rounded-2xl text-center border border-red-200"></div>
      </form>
    </section>
    @endif

  </main>

  {{-- Footer --}}
  <footer class="bg-slate-900 text-slate-400 text-xs py-8 border-t border-slate-800 text-center space-y-2">
    <div class="max-w-4xl mx-auto px-4">
      <p>{{ $c['footer_copyright'] ?? 'সকল স্বত্ব সংরক্ষিত © ২০২৬ | আপনার বিশ্বস্ত অনলাইন বুক মার্কেটপ্লেস' }}</p>
      @if(!empty($phone))
        <p>হেল্পলাইন: <a href="tel:{{ $phone }}" class="text-emerald-400 font-bold hover:underline">{{ $phone }}</a></p>
      @endif
    </div>
  </footer>

  {{-- Mobile Sticky Bottom Bar --}}
  <div class="sm:hidden fixed bottom-0 inset-x-0 bg-white/95 backdrop-blur-md border-t border-slate-200 p-3 z-50 flex items-center justify-between gap-3 shadow-2xl">
    <div class="space-y-0.5">
      <div class="text-[11px] text-slate-500 font-bold">অফার প্রাইস:</div>
      <div class="text-lg font-black text-emerald-600">৳{{ number_format($offerPrice, 0) }}</div>
    </div>
    <a href="#order-form-section" class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-center rounded-xl text-sm shadow-md active:scale-95 transition-all">
      অর্ডার করুন এখনই
    </a>
  </div>

  {{-- Floating WhatsApp button --}}
  @if(!empty($whatsapp))
  <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="fixed bottom-20 sm:bottom-6 right-5 bg-green-500 hover:bg-green-600 text-white p-3.5 rounded-full shadow-2xl z-50 flex items-center justify-center transform hover:scale-110 transition-transform" aria-label="WhatsApp">
    <svg class="w-7 h-7" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
  </a>
  @endif

  <script>
    // Live Countdown Timer
    (function initTimer() {
      const countdownHours = {{ (int) ($c['countdown_hours'] ?? 14) }};
      let endTime = localStorage.getItem('lp_timer_end_{{ $page->id }}');
      if (!endTime) {
        endTime = Date.now() + (countdownHours * 60 * 60 * 1000);
        localStorage.setItem('lp_timer_end_{{ $page->id }}', endTime);
      } else {
        endTime = parseInt(endTime, 10);
      }

      function update() {
        const remaining = Math.max(0, endTime - Date.now());
        const days = Math.floor(remaining / (1000 * 60 * 60 * 24));
        const hours = Math.floor((remaining % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const mins = Math.floor((remaining % (1000 * 60 * 60)) / (1000 * 60));
        const secs = Math.floor((remaining % (1000 * 60)) / 1000);

        const pad = (n) => String(n).padStart(2, '0');
        const daysEl = document.getElementById('timer-days');
        const hoursEl = document.getElementById('timer-hours');
        const minsEl = document.getElementById('timer-mins');
        const secsEl = document.getElementById('timer-secs');

        if (daysEl) daysEl.innerText = pad(days);
        if (hoursEl) hoursEl.innerText = pad(hours);
        if (minsEl) minsEl.innerText = pad(mins);
        if (secsEl) secsEl.innerText = pad(secs);
      }

      update();
      setInterval(update, 1000);
    })();

    // Client-side BD Phone & Fake Order Guard
    function validateBdPhone(phone) {
      const clean = phone.replace(/^(\+?88)/, '').replace(/[\s\-]/g, '');
      if (!clean) return { valid: false, message: '' };

      if (clean.length < 3) return { valid: false, message: '' };

      // Valid prefixes: 013, 014, 015, 016, 017, 018, 019
      const validPrefixes = ['013', '014', '015', '016', '017', '018', '019'];
      const prefix = clean.substring(0, 3);
      if (!validPrefixes.includes(prefix)) {
        return { valid: false, message: '❌ সঠিক বাংলাদেশী মোবাইল নম্বর দিন (013, 014, 015, 016, 017, 018, 019)' };
      }

      if (clean.length < 11) {
        return { valid: false, message: `⚠️ আরও ${11 - clean.length} টি ডিজিট বাকি (মোট ১১ ডিজিট)` };
      }

      if (clean.length > 11) {
        return { valid: false, message: '❌ নম্বরটি ১১ ডিজিটের বেশি হতে পারে না' };
      }

      // Check obvious fake repeating patterns: e.g. 01711111111, 01700000000
      const last8 = clean.substring(3);
      if (/^(.)\1{7}$/.test(last8)) {
        return { valid: false, message: '❌ এই নম্বরটি ভুয়া মনে হচ্ছে। সঠিক নম্বর দিন।' };
      }

      // Sequential ascending 12345678 or descending
      if (last8 === '12345678' || last8 === '23456789' || last8 === '87654321' || last8 === '00000000') {
        return { valid: false, message: '❌ অনুগ্রহ করে আপনার সক্রিয় ফোন নম্বর দিন।' };
      }

      return { valid: true, message: '✓ সঠিক মোবাইল নম্বর', normalized: clean };
    }

    // Live Calculations & AJAX Checkout with Fake Order Guard
    (function initOrderCalc() {
      const form = document.getElementById('landing-order-form');
      if (!form) return;

      const subtotalEl = document.getElementById('summary-subtotal');
      const shippingEl = document.getElementById('summary-shipping');
      const totalEl = document.getElementById('summary-total');
      const errorMsg = document.getElementById('form-error-msg');
      const submitBtn = document.getElementById('btn-submit-order');

      const phoneInput = document.getElementById('cust-phone');
      const phoneFeedback = document.getElementById('phone-feedback');
      const phoneValidIcon = document.getElementById('phone-valid-icon');

      const nameInput = document.getElementById('cust-name');
      const nameFeedback = document.getElementById('name-feedback');
      const addrInput = document.getElementById('cust-addr');
      const addrFeedback = document.getElementById('addr-feedback');

      // Live Phone Validation on Typing
      if (phoneInput && phoneFeedback) {
        phoneInput.addEventListener('input', function() {
          const res = validateBdPhone(this.value);
          if (!this.value.trim()) {
            phoneFeedback.classList.add('hidden');
            phoneFeedback.innerText = '';
            phoneValidIcon?.classList.add('hidden');
            phoneInput.classList.remove('border-emerald-500', 'border-red-500');
            return;
          }

          phoneFeedback.classList.remove('hidden');
          phoneFeedback.innerText = res.message;

          if (res.valid) {
            phoneFeedback.className = 'text-xs font-bold mt-1 text-emerald-600';
            phoneValidIcon?.classList.remove('hidden');
            phoneInput.classList.remove('border-red-500');
            phoneInput.classList.add('border-emerald-500');
          } else {
            phoneFeedback.className = 'text-xs font-semibold mt-1 text-red-500';
            phoneValidIcon?.classList.add('hidden');
            phoneInput.classList.remove('border-emerald-500');
            phoneInput.classList.add('border-red-500');
          }
        });
      }

      function recalculate() {
        const selectedPkg = form.querySelector('input[name="package_index"]:checked');
        const selectedZone = form.querySelector('input[name="shipping_zone"]:checked');

        const subtotal = selectedPkg ? parseFloat(selectedPkg.dataset.price) : {{ $offerPrice }};
        const shipping = selectedZone ? parseFloat(selectedZone.dataset.shipping) : {{ $shippingInside }};
        const total = subtotal + shipping;

        if (subtotalEl) subtotalEl.innerText = '৳' + Math.round(subtotal).toLocaleString();
        if (shippingEl) shippingEl.innerText = '৳' + Math.round(shipping).toLocaleString();
        if (totalEl) totalEl.innerText = '৳' + Math.round(total).toLocaleString();
      }

      form.querySelectorAll('input[name="package_index"], input[name="shipping_zone"]').forEach(r => {
        r.addEventListener('change', recalculate);
      });

      // AJAX Order Submission with Client-Side Fake Guard
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        if (errorMsg) { errorMsg.classList.add('hidden'); errorMsg.innerText = ''; }

        // Client-side quick checks
        const nameVal = nameInput ? nameInput.value.trim() : '';
        const phoneVal = phoneInput ? phoneInput.value.trim() : '';
        const addrVal = addrInput ? addrInput.value.trim() : '';

        if (nameVal.length < 3 || /^(test|fake|asdf|qwerty|12345)$/i.test(nameVal)) {
          if (nameFeedback) {
            nameFeedback.innerText = 'অনুগ্রহ করে আপনার সঠিক সম্পূর্ণ নাম লিখুন।';
            nameFeedback.classList.remove('hidden');
          }
          nameInput?.focus();
          return;
        } else {
          nameFeedback?.classList.add('hidden');
        }

        const phoneRes = validateBdPhone(phoneVal);
        if (!phoneRes.valid) {
          if (phoneFeedback) {
            phoneFeedback.innerText = phoneRes.message || 'অনুগ্রহ করে একটি সঠিক বাংলাদেশী ফোন নম্বর দিন (01X-XXXXXXXX)।';
            phoneFeedback.className = 'text-xs font-bold mt-1 text-red-500';
            phoneFeedback.classList.remove('hidden');
          }
          phoneInput?.focus();
          return;
        }

        if (addrVal.length < 5 || /^(test|fake|asdf|12345)$/i.test(addrVal)) {
          if (addrFeedback) {
            addrFeedback.innerText = 'অনুগ্রহ করে আপনার সঠিক ডেলিভারি ঠিকানা লিখুন (বাড়ি/রোড/এলাকা)।';
            addrFeedback.classList.remove('hidden');
          }
          addrInput?.focus();
          return;
        } else {
          addrFeedback?.classList.add('hidden');
        }

        submitBtn.disabled = true;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = `
          <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          অর্ডার প্রসেস হচ্ছে...
        `;

        const formData = new FormData(form);

        fetch(form.action, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
          },
          body: formData,
        })
        .then(async res => {
          const data = await res.json();
          if (!res.ok) {
            throw new Error(data.message || 'অর্ডার করতে সমস্যা হয়েছে।');
          }
          if (data.redirect_url) {
            window.location.href = data.redirect_url;
          }
        })
        .catch(err => {
          if (errorMsg) {
            errorMsg.innerText = err.message || 'অর্ডার করতে সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।';
            errorMsg.classList.remove('hidden');
          }
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalText;
        });
      });
    })();
  </script>
</body>
</html>
