<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>{{ $c['meta_title'] ?? $page->title }}</title>
  <meta name="description" content="{{ $c['meta_description'] ?? '' }}">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700;800&family=Inter:wght@500;600;700;800&display=swap" rel="stylesheet">
  @vite('resources/css/landing.css')
  <style>
    :root { color-scheme: light; }
    html { scroll-behavior: smooth; }
    body { margin: 0; font-family: "Hind Siliguri", "Inter", system-ui, sans-serif; background: #eefdff; color: #082038; }
    .wrap { width: min(1180px, calc(100% - 28px)); margin-inline: auto; }
    .money { font-family: "Inter", system-ui, sans-serif; letter-spacing: 0; }
    .hero-shell { background: linear-gradient(135deg, #e8fbff 0%, #f7ffff 48%, #eaf7ff 100%); border-bottom: 1px solid #c6eef6; }
    .glass { background: rgba(255, 255, 255, .82); border: 1px solid rgba(11, 104, 128, .12); box-shadow: 0 24px 70px rgba(8, 32, 56, .12); backdrop-filter: blur(14px); }
    .deep-card { background: linear-gradient(135deg, #071b3a, #04284a 55%, #064663); color: #fff; box-shadow: 0 18px 46px rgba(4, 40, 74, .24); }
    .offer-button { background: linear-gradient(135deg, #ff6a00, #f63b00); box-shadow: 0 18px 38px rgba(246, 59, 0, .28); }
    .field { width: 100%; border: 1px solid #d8e3eb; border-radius: 12px; padding: 12px 14px; background: #fff; outline: none; transition: border-color .18s, box-shadow .18s; }
    .field:focus { border-color: #ff5a1f; box-shadow: 0 0 0 4px rgba(255, 90, 31, .14); }
    .thumb[aria-current="true"] { border-color: #ff5a1f; box-shadow: 0 0 0 3px rgba(255, 90, 31, .16); }
    .radio-card:has(input:checked) { border-color: #ff5a1f; background: #fff7f2; }
    @media (max-width: 767px) {
      .wrap { width: min(100% - 22px, 1180px); }
      body { padding-bottom: 76px; }
    }
  </style>
</head>
<body>
@php
  $show = fn (string $section) => $page->sectionVisible($section);
  $productOffer = (float) ($product->sale_price ?: $product->regular_price ?: $product->price);
  $productRegular = (float) ($product->regular_price ?: $productOffer);
  $configuredOffer = isset($c['offer_price']) ? (float) $c['offer_price'] : 0.0;
  $configuredRegular = isset($c['regular_price']) ? (float) $c['regular_price'] : 0.0;
  $offer = $configuredOffer > 0 ? $configuredOffer : $productOffer;
  $regular = $configuredRegular > 0 ? $configuredRegular : $productRegular;
  $discount = $regular > $offer && $regular > 0 ? (int) round((($regular - $offer) / $regular) * 100) : 0;
  $phone = $c['phone'] ?? '+8801700-000000';
  $tel = preg_replace('/\D+/', '', $phone);
  $wa = preg_replace('/\D+/', '', $c['whatsapp'] ?? $tel);
  $heroImg = $page->mediaUrl($c['hero_image'] ?? '') ?: $product->imageUrl();
  $gallery = array_values(array_filter([$heroImg]));
  foreach (($product->images ?? collect()) as $image) {
      $url = method_exists($image, 'url') ? $image->url() : '';
      if ($url && ! in_array($url, $gallery, true)) {
          $gallery[] = $url;
      }
  }
  $gallery = array_slice($gallery, 0, 6);
  $title = trim((string) ($c['hero_headline'] ?? '')) ?: $product->name;
  $subtitle = trim((string) ($c['hero_subtitle'] ?? ''));
  $details = trim((string) ($c['details_text'] ?? ''));
  if ($details === '') {
      $details = trim(strip_tags((string) ($product->description ?: $product->short_description)));
  }
  $benefits = $c['benefits'] ?? [];
  $reviews = $c['testimonials'] ?? [];
  $shippingInside = (float) ($c['shipping_inside'] ?? setting('shipping_inside_dhaka', 70));
  $shippingOutside = (float) ($c['shipping_outside'] ?? setting('shipping_outside_dhaka', 130));
  $orderAction = route('landing.order', $page->slug);
@endphp

<div class="bg-[#062e5f] text-white">
  <div class="wrap flex flex-col gap-2 py-3 text-sm font-bold sm:flex-row sm:items-center sm:justify-between">
    <span>Cash on delivery available</span>
    <div class="flex flex-wrap items-center gap-3 text-white/90">
      <span>{{ $c['hero_badge'] ?? 'Limited time offer' }}</span>
      <span class="hidden h-1 w-1 rounded-full bg-white/45 sm:block"></span>
      <a class="hover:text-white" href="tel:{{ $tel }}">{{ $phone }}</a>
    </div>
  </div>
</div>

@if($show('hero'))
<header class="hero-shell">
  <div class="wrap py-6 lg:py-10">
    <div class="grid gap-5 lg:grid-cols-[1.05fr_.95fr] lg:items-stretch">
      <section class="glass rounded-[28px] p-5 sm:p-7 lg:p-9">
        <div class="grid gap-6 lg:grid-cols-[.95fr_1.05fr] lg:items-center">
          <div>
            <span class="inline-flex items-center rounded-full bg-yellow-100 px-3 py-1.5 text-xs font-extrabold text-yellow-800">Special product offer</span>
            <h1 class="mt-4 text-3xl font-extrabold leading-tight text-[#06142e] sm:text-5xl">{{ $title }}</h1>
            @if($subtitle)
              <p class="mt-4 text-lg leading-8 text-slate-600">{{ $subtitle }}</p>
            @endif

            <div class="mt-5 flex flex-wrap items-center gap-3">
              <div class="rounded-2xl bg-[#083a76] px-5 py-4 text-white">
                <span class="block text-xs font-bold text-cyan-100">Offer price</span>
                <strong class="money text-3xl font-extrabold">&#2547;{{ number_format($offer, 0) }}</strong>
              </div>
              @if($regular > $offer)
                <div class="rounded-2xl bg-white px-5 py-4">
                  <span class="block text-xs font-bold text-slate-500">Regular</span>
                  <strong class="money text-xl font-extrabold text-slate-500 line-through">&#2547;{{ number_format($regular, 0) }}</strong>
                </div>
              @endif
              @if($discount > 0)
                <span class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-extrabold text-white">Save {{ $discount }}%</span>
              @endif
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-2">
              <a href="#order" class="offer-button rounded-2xl px-5 py-4 text-center text-lg font-extrabold text-white">{{ $c['cta_text'] ?? 'Order Now' }}</a>
              <a href="{{ $wa ? 'https://wa.me/'.$wa : 'tel:'.$tel }}" class="rounded-2xl border border-[#bcdde9] bg-white px-5 py-4 text-center text-lg font-extrabold text-[#06142e]">Phone call</a>
            </div>
          </div>

          <div>
            <div class="relative overflow-hidden rounded-[26px] bg-[#fff4c7] p-4 shadow-2xl shadow-cyan-900/10">
              <div class="absolute right-4 top-4 z-10 rounded-full bg-[#05c489] px-3 py-1.5 text-sm font-extrabold text-white">{{ $discount > 0 ? '-'.$discount.'%' : 'HOT' }}</div>
              <img id="mainProductImage" src="{{ $gallery[0] ?? $heroImg }}" alt="{{ $product->name }}" class="h-[300px] w-full rounded-[20px] object-cover sm:h-[390px]">
            </div>
            @if(count($gallery) > 1)
              <div class="mt-4 grid grid-cols-5 gap-2">
                @foreach($gallery as $i => $image)
                  <button type="button" class="thumb overflow-hidden rounded-xl border-2 border-transparent bg-white p-1" aria-current="{{ $i === 0 ? 'true' : 'false' }}" data-image="{{ $image }}">
                    <img src="{{ $image }}" alt="{{ $product->name }} thumbnail {{ $i + 1 }}" class="h-14 w-full rounded-lg object-cover sm:h-16">
                  </button>
                @endforeach
              </div>
            @endif
          </div>
        </div>

        <div class="mt-6 grid gap-3 text-sm font-extrabold text-[#0d3552] sm:grid-cols-4">
          <div class="rounded-2xl bg-white px-4 py-3">Fast delivery</div>
          <div class="rounded-2xl bg-white px-4 py-3">COD payment</div>
          <div class="rounded-2xl bg-white px-4 py-3">Special offer</div>
          <div class="rounded-2xl bg-white px-4 py-3">Phone support</div>
        </div>
      </section>

      <aside class="grid gap-5">
        <div class="deep-card rounded-[28px] p-5 sm:p-6">
          <p class="text-sm font-bold text-cyan-100">Offer ends soon</p>
          <div class="mt-4 grid grid-cols-3 gap-3 text-center">
            <div class="rounded-2xl bg-white/10 p-4"><strong class="money block text-3xl">HH</strong><span class="text-xs text-cyan-100">Hours</span></div>
            <div class="rounded-2xl bg-white/10 p-4"><strong class="money block text-3xl">MM</strong><span class="text-xs text-cyan-100">Minutes</span></div>
            <div class="rounded-2xl bg-white/10 p-4"><strong class="money block text-3xl">SS</strong><span class="text-xs text-cyan-100">Seconds</span></div>
          </div>
          <div class="mt-4 rounded-2xl bg-yellow-400 px-5 py-4 text-[#06142e]">
            <span class="block text-xs font-extrabold">Today only</span>
            <strong class="money text-3xl font-extrabold">&#2547;{{ number_format($offer, 0) }}</strong>
          </div>
        </div>

        @if($show('benefits') && count($benefits))
          <div class="rounded-[28px] bg-white p-5 shadow-xl shadow-cyan-900/10">
            <h2 class="text-center text-xl font-extrabold text-[#06142e]">{{ $c['benefits_heading'] ?? 'Features' }}</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
              @foreach(array_slice($benefits, 0, 4) as $benefit)
                <article class="rounded-2xl border border-cyan-100 bg-cyan-50/50 p-4">
                  <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-full bg-white text-sm font-black text-orange-600">{{ strtoupper(substr((string) ($benefit['icon'] ?? 'ok'), 0, 1)) }}</div>
                  <h3 class="text-sm font-extrabold text-[#06142e]">{{ $benefit['title'] ?? '' }}</h3>
                  <p class="mt-1 text-xs leading-5 text-slate-600">{{ $benefit['body'] ?? '' }}</p>
                </article>
              @endforeach
            </div>
          </div>
        @endif
      </aside>
    </div>
  </div>
</header>
@endif

<main>
  <section class="wrap grid gap-6 py-8 lg:grid-cols-[.92fr_1.08fr] lg:items-start">
    <div class="space-y-6">
      @if($show('details'))
        <article class="rounded-[28px] bg-white p-6 shadow-xl shadow-cyan-900/10">
          <span class="text-sm font-extrabold uppercase text-orange-600">Product details</span>
          <h2 class="mt-2 text-2xl font-extrabold text-[#06142e]">{{ $c['details_heading'] ?? 'Product details' }}</h2>
          <div class="mt-4 rounded-2xl bg-slate-50 p-5 text-base leading-8 text-slate-700">
            @if($details)
              {!! nl2br(e($details)) !!}
            @else
              Product information will be updated soon.
            @endif
          </div>
        </article>
      @endif

      @if($show('reviews') && count($reviews))
        <article class="rounded-[28px] bg-white p-6 shadow-xl shadow-cyan-900/10">
          <h2 class="text-2xl font-extrabold text-[#06142e]">{{ $c['reviews_heading'] ?? 'Customer reviews' }}</h2>
          <div class="mt-5 grid gap-4 md:grid-cols-2">
            @foreach($reviews as $review)
              <div class="rounded-2xl border border-slate-100 bg-slate-50 p-5">
                <div class="money text-orange-500">{{ str_repeat('★', max(1, min(5, (int) ($review['rating'] ?? 5)))) }}</div>
                <p class="mt-3 leading-7 text-slate-700">{{ $review['text'] ?? '' }}</p>
                <p class="mt-4 font-extrabold text-[#06142e]">{{ $review['name'] ?? 'Verified Customer' }}</p>
              </div>
            @endforeach
          </div>
        </article>
      @endif
    </div>

    @if($show('order'))
      <section id="order" class="rounded-[28px] bg-white p-5 shadow-2xl shadow-cyan-900/10 lg:sticky lg:top-5 sm:p-6">
        <div class="mb-5 rounded-2xl bg-[#06142e] p-5 text-white">
          <div class="flex items-center gap-4">
            <img src="{{ $gallery[0] ?? $heroImg }}" alt="{{ $product->name }}" class="h-20 w-20 rounded-xl object-cover">
            <div>
              <p class="font-extrabold">{{ $product->name }}</p>
              <p class="money mt-1 text-2xl font-extrabold text-yellow-300">&#2547;{{ number_format($offer, 0) }}</p>
            </div>
          </div>
          <p class="mt-4 text-sm leading-6 text-white/70">{{ $c['order_subtext'] ?? 'Fill in your delivery information. We will call to confirm your order.' }}</p>
        </div>

        <form action="{{ $orderAction }}" method="POST" class="text-slate-900">
          @csrf
          <input type="text" name="website_url" class="hidden" tabindex="-1" autocomplete="off">

          @if($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>
          @endif

          <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
              <span class="mb-1 block text-sm font-bold">Name</span>
              <input class="field" name="customer_name" value="{{ old('customer_name') }}" required>
            </label>
            <label class="block">
              <span class="mb-1 block text-sm font-bold">Mobile number</span>
              <input class="field" name="customer_phone" value="{{ old('customer_phone') }}" inputmode="tel" placeholder="01712345678" pattern="^(?:\+?88)?01[3-9][0-9]{8}$" title="Enter a valid Bangladesh mobile number" required>
            </label>
            <label class="block sm:col-span-2">
              <span class="mb-1 block text-sm font-bold">Delivery address</span>
              <textarea class="field min-h-[96px]" name="shipping_address" required>{{ old('shipping_address') }}</textarea>
            </label>
            <label class="block">
              <span class="mb-1 block text-sm font-bold">City</span>
              <input class="field" name="city" value="{{ old('city') }}">
            </label>
            <label class="block">
              <span class="mb-1 block text-sm font-bold">Quantity</span>
              <input id="qtyInput" class="field" type="number" name="qty" value="{{ old('qty', 1) }}" min="1" max="99">
            </label>
          </div>

          <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <label class="radio-card cursor-pointer rounded-xl border border-slate-200 p-4 text-sm font-bold">
              <input type="radio" name="shipping_zone" value="inside_dhaka" class="mr-2" data-shipping="{{ $shippingInside }}" {{ old('shipping_zone', 'inside_dhaka') === 'inside_dhaka' ? 'checked' : '' }}>
              Inside Dhaka
            </label>
            <label class="radio-card cursor-pointer rounded-xl border border-slate-200 p-4 text-sm font-bold">
              <input type="radio" name="shipping_zone" value="outside_dhaka" class="mr-2" data-shipping="{{ $shippingOutside }}" {{ old('shipping_zone') === 'outside_dhaka' ? 'checked' : '' }}>
              Outside Dhaka
            </label>
          </div>

          <label class="mt-4 block">
            <span class="mb-1 block text-sm font-bold">Note</span>
            <textarea class="field min-h-[76px]" name="order_notes">{{ old('order_notes') }}</textarea>
          </label>

          <div class="mt-5 rounded-2xl bg-slate-100 p-4 text-sm font-bold">
            <div class="flex justify-between"><span>Product</span><span id="productSubtotal" class="money">&#2547;{{ number_format($offer, 0) }}</span></div>
            <div class="mt-2 flex justify-between"><span>Shipping</span><span id="shippingFee" class="money">&#2547;{{ number_format($shippingInside, 0) }}</span></div>
            <div class="mt-3 flex justify-between border-t border-slate-300 pt-3 text-lg"><span>Total</span><span id="grandTotal" class="money">&#2547;{{ number_format($offer + $shippingInside, 0) }}</span></div>
          </div>

          <button class="offer-button mt-5 w-full rounded-2xl px-5 py-4 text-lg font-extrabold text-white" type="submit">{{ $c['order_btn_text'] ?? 'Confirm Order' }}</button>
          <p class="mt-3 text-center text-xs font-semibold text-slate-500">{{ $c['guarantee_text'] ?? 'Cash on delivery available' }}</p>
        </form>
      </section>
    @endif
  </section>
</main>

<footer class="bg-[#062e5f] py-6 text-center text-sm text-white/75">
  <div class="wrap">{{ $c['footer_text'] ?? 'All rights reserved.' }}</div>
</footer>

<div class="fixed inset-x-0 bottom-0 z-40 border-t border-cyan-100 bg-white/95 p-3 shadow-2xl backdrop-blur md:hidden">
  <a href="#order" class="offer-button block rounded-2xl px-4 py-3 text-center font-extrabold text-white">{{ $c['cta_text'] ?? 'Order Now' }} - &#2547;{{ number_format($offer, 0) }}</a>
</div>

<script>
  document.querySelectorAll('.thumb').forEach((btn) => {
    btn.addEventListener('click', () => {
      const image = btn.getAttribute('data-image');
      const main = document.getElementById('mainProductImage');
      if (image && main) main.src = image;
      document.querySelectorAll('.thumb').forEach((item) => item.setAttribute('aria-current', 'false'));
      btn.setAttribute('aria-current', 'true');
    });
  });

  const unitPrice = {{ json_encode($offer) }};
  const formatMoney = (value) => '৳' + new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(value);
  const updateTotals = () => {
    const qty = Math.max(1, parseInt(document.getElementById('qtyInput')?.value || '1', 10));
    const shipping = parseFloat(document.querySelector('input[name="shipping_zone"]:checked')?.dataset.shipping || '0');
    const subtotal = unitPrice * qty;
    document.getElementById('productSubtotal').textContent = formatMoney(subtotal);
    document.getElementById('shippingFee').textContent = formatMoney(shipping);
    document.getElementById('grandTotal').textContent = formatMoney(subtotal + shipping);
  };
  document.getElementById('qtyInput')?.addEventListener('input', updateTotals);
  document.querySelectorAll('input[name="shipping_zone"]').forEach((input) => input.addEventListener('change', updateTotals));
  updateTotals();
</script>
</body>
</html>
