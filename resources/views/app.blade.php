<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    @php
        $defaultMetaTitle = trim((string) setting('default_meta_title', '')) ?: site_name();
        $defaultMetaDescription = trim((string) setting('default_meta_description', ''));
        $defaultMetaKeywords = trim((string) setting('default_meta_keywords', ''));
        $pageSeo = array_replace($page['props']['seoDefaults'] ?? [], array_filter($page['props']['seo'] ?? [], fn ($value) => $value !== null && $value !== ''));
        $pageMetaTitle = trim((string) ($pageSeo['title'] ?? '')) ?: $defaultMetaTitle;
        $pageMetaDescription = trim(strip_tags((string) (($pageSeo['description'] ?? '') ?: $defaultMetaDescription)));
        $pageMetaKeywords = trim((string) (($pageSeo['keywords'] ?? '') ?: $defaultMetaKeywords));
        $pageCanonical = $pageSeo['canonical'] ?? url()->current();
        $pageMetaImage = $pageSeo['image'] ?? null;
        $pageComponent = $page['component'] ?? '';
        $pageProps = $page['props'] ?? [];
        $keywordConsistencyEnabled = ! request()->is('admin*') && (string) setting('seo_keyword_consistency_enabled', '1') === '1';
        $seoKeywordPhrases = seo_keyword_list(trim((string) setting('seo_target_keywords', '')) ?: $pageMetaKeywords);
        if ($keywordConsistencyEnabled && $seoKeywordPhrases === [] && $pageComponent === 'Storefront/Home') {
            $homeKeywordNames = [];
            foreach (($pageProps['featuredCategories'] ?? []) as $category) {
                if (is_array($category) && ! empty($category['name'])) {
                    $homeKeywordNames[] = $category['name'];
                }
            }
            foreach (($pageProps['templateTwoCategorySections'] ?? []) as $section) {
                if (is_array($section) && ! empty($section['name'])) {
                    $homeKeywordNames[] = $section['name'];
                }
            }
            $seoKeywordPhrases = seo_keyword_list(implode(', ', array_slice(array_unique($homeKeywordNames), 0, 8)));
        }
        if ($keywordConsistencyEnabled && $seoKeywordPhrases !== []) {
            $pageMetaTitle = seo_append_missing_keywords($pageMetaTitle, $seoKeywordPhrases, 180);
            $pageMetaDescription = seo_description_with_keywords($pageMetaDescription ?: site_name(), $seoKeywordPhrases, 400);
            $pageMetaKeywords = seo_append_missing_keywords($pageMetaKeywords, $seoKeywordPhrases, 500, ', ');
        }
        $fallbackH1Enabled = ! app(\App\Services\StorefrontSeoService::class)->isPrivate(request()) && (string) setting('seo_fallback_h1_enabled', '1') === '1';
        $fallbackH1 = trim((string) setting('seo_h1_heading', ''))
            ?: trim((string) ($pageSeo['h1'] ?? ''))
            ?: $pageMetaTitle
            ?: site_name();
        if ($keywordConsistencyEnabled && $seoKeywordPhrases !== []) {
            $fallbackH1 = seo_append_missing_keywords($fallbackH1, $seoKeywordPhrases, 180);
        }
        $fallbackProducts = collect($pageProps['products']['data'] ?? [])
            ->merge($pageProps['flashProducts'] ?? [])
            ->merge($pageProps['trending'] ?? [])
            ->merge($pageProps['newArrivals'] ?? [])
            ->merge($pageProps['bestSellers'] ?? []);
        foreach (($pageProps['templateTwoCategorySections'] ?? []) as $section) {
            $fallbackProducts = $fallbackProducts->merge($section['products'] ?? []);
        }
        $fallbackProducts = $fallbackProducts
            ->filter(fn ($product) => is_array($product) && ! empty($product['name']) && ! empty($product['url_key']))
            ->unique('url_key')
            ->take(24);
    @endphp
    <title data-inertia="">{{ $pageMetaTitle }}</title>
    <script>document.documentElement.classList.add('js')</script>
    <style>.js .seo-crawler-fallback { display: none; }</style>
    <meta name="site-name" content="{{ site_name() }}" />
    <meta name="seo-default-title" content="{{ $pageMetaTitle }}" />
    {{-- Social crawlers read this HTML without running the storefront JavaScript. --}}
    @if($pageMetaDescription)<meta data-inertia="description" name="description" content="{{ $pageMetaDescription }}" />@endif
    @if($pageMetaKeywords)<meta data-inertia="keywords" name="keywords" content="{{ $pageMetaKeywords }}" />@endif
    <meta property="og:site_name" content="{{ site_name() }}" />
    <meta data-inertia="og:type" property="og:type" content="{{ $pageSeo['type'] ?? 'website' }}" />
    <meta data-inertia="og:title" property="og:title" content="{{ $pageMetaTitle }}" />
    @if($pageMetaDescription)<meta data-inertia="og:description" property="og:description" content="{{ $pageMetaDescription }}" />@endif
    <meta data-inertia="og:url" property="og:url" content="{{ $pageCanonical }}" />
    @if($pageMetaImage)<meta data-inertia="og:image" property="og:image" content="{{ $pageMetaImage }}" />@endif
    <meta data-inertia="twitter:card" name="twitter:card" content="{{ $pageMetaImage ? 'summary_large_image' : 'summary' }}" />
    <meta data-inertia="twitter:title" name="twitter:title" content="{{ $pageMetaTitle }}" />
    @if($pageMetaDescription)<meta data-inertia="twitter:description" name="twitter:description" content="{{ $pageMetaDescription }}" />@endif
    @if($pageMetaImage)<meta data-inertia="twitter:image" name="twitter:image" content="{{ $pageMetaImage }}" />@endif
    @if(!empty($pageSeo['robots']))<meta data-inertia="robots" name="robots" content="{{ $pageSeo['robots'] }}" />@endif
    <link data-inertia="canonical" rel="canonical" href="{{ $pageCanonical }}" />
    @if(!empty($page['props']['structuredData']))
        <script data-inertia="structured-data" type="application/ld+json">{!! json_encode($page['props']['structuredData'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
    <link rel="icon" href="{{ favicon_url() }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter+Tight:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    @if (! request()->is('admin*'))
        {{-- Load storefront styling before React mounts to prevent a flash of unstyled content. --}}
        <link rel="preload" as="style" href="{{ asset('theme/css/storefront-typography.css') }}">
        <link rel="stylesheet" href="{{ asset('theme/css/storefront-typography.css') }}">
        @if (setting('storefront_template', 'template-2') === 'template-1')
            <link rel="preload" as="style" href="{{ asset('theme/css/template-1-storefront.css') }}">
            <link rel="stylesheet" href="{{ asset('theme/css/template-1-storefront.css') }}">
        @endif
    @endif
    @include('partials.tracking-head')
    @viteReactRefresh
    @vite(['resources/js/app.jsx'])
    @inertiaHead
</head>
<body class="antialiased">
    @include('partials.tracking-body')
    @if($fallbackH1Enabled && $fallbackH1 !== '')
        {{-- Render meaningful content for visitors and crawlers without JavaScript. --}}
        <main class="seo-crawler-fallback" data-seo-crawler-fallback>
            <h1 data-seo-fallback-h1>{{ $pageProps['product']['name'] ?? $pageProps['activeCategory']['name'] ?? $pageProps['heading'] ?? $fallbackH1 }}</h1>
            @if($pageMetaDescription)<p>{{ $pageMetaDescription }}</p>@endif
            <nav aria-label="Additional store links"><a href="{{ route('about') }}">About TAQI LIFE</a> · <a href="{{ route('blog') }}">Shopping guides</a></nav>
            <nav aria-label="Store links"><a href="{{ route('home') }}">Home</a> · <a href="{{ route('shop') }}">Shop</a> · <a href="{{ route('contact') }}">Contact</a></nav>
            @if(request()->is('/'))
                <section aria-labelledby="shopping-guide-title">
                    <h2 id="shopping-guide-title">Online shopping in Bangladesh with TAQI LIFE</h2>
                    <p>TAQI LIFE is an online shopping destination for everyday fashion, useful gadgets, home essentials, gifts, and products for children. Explore the store by category or visit the full shop to compare items that fit your needs. Each collection is arranged to help you move from browsing to choosing without losing sight of the details that matter.</p>
                    <p>Start with a category, then use the product listings to discover current choices. Open a product page to review its photos, price, available options, and description before adding it to your cart. Product availability and offers can change, so the product page is the best place to check the latest information for the item you want.</p>
                    <p>Before checkout, review the items and quantities in your cart and provide accurate delivery information. Delivery charges are shown as part of the order process. If an item needs special delivery arrangements, contact TAQI LIFE before placing the order so the team can confirm the available option.</p>
                    <p>Need help after choosing a product? Use order tracking to follow an order, read the shipping and return information before you buy, or contact TAQI LIFE with a product or order question. The shopping guides also share practical ideas for choosing products, comparing useful features, planning thoughtful gifts, and getting more value from your online shopping every day.</p>
                    <nav aria-label="Shopping help"><a href="{{ route('shop') }}">Browse all products</a> · <a href="{{ route('track') }}">Track an order</a> · <a href="{{ route('shipping') }}">Shipping information</a> · <a href="{{ route('refund') }}">Return and refund policy</a> · <a href="{{ route('contact') }}">Contact TAQI LIFE</a></nav>
                </section>
            @endif
            @foreach(($pageProps['filterCategories'] ?? $pageProps['featuredCategories'] ?? []) as $category)
                <a href="{{ route('shop.category', $category['slug']) }}">{{ $category['name'] }}</a>
            @endforeach
            @if($fallbackProducts->isNotEmpty())
                <ul>@foreach($fallbackProducts as $product)
                    <li><a href="{{ route('product.show', $product['url_key']) }}">{{ $product['name'] }}</a></li>
                @endforeach</ul>
            @endif
            @if(isset($pageProps['products']['data']))
                <ul>@foreach($pageProps['products']['data'] as $product)
                    <li><a href="{{ route('product.show', $product['url_key'] ?? $product['slug']) }}">{{ $product['name'] }}</a></li>
                @endforeach</ul>
                @if(!empty($pageProps['products']['prev_page_url']))<a href="{{ $pageProps['products']['prev_page_url'] }}">Previous page</a>@endif
                @if(!empty($pageProps['products']['next_page_url']))<a href="{{ $pageProps['products']['next_page_url'] }}">Next page</a>@endif
            @endif
        </main>
    @endif
    @inertia
</body>
</html>
