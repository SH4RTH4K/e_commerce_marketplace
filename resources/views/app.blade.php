<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    @include('partials.mobile-meta')
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
        $fallbackHomeH1 = $fallbackH1;
        if ($pageComponent === 'Storefront/Home' && mb_strlen($fallbackHomeH1) < 20) {
            $fallbackHomeH1 = site_name() . ': Online shopping in Bangladesh';
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
    {{-- Keep the server-rendered fallback visible until the React storefront has mounted. --}}
    <style nonce="{{ csp_nonce() }}">
        .js .seo-crawler-fallback { display: none; }
        /* Keep the server-rendered storefront useful and readable while the React bundle loads. */
        .seo-crawler-fallback {
            box-sizing: border-box;
            max-width: 760px;
            margin: 32px auto;
            padding: 28px;
            border: 1px solid #e6e9ee;
            border-radius: 16px;
            background: #fff;
            color: #1e2430;
            font: 16px/1.65 Arial, Helvetica, sans-serif;
        }
        .seo-crawler-fallback h1 { margin: 0 0 12px; font-size: clamp(22px, 4vw, 32px); line-height: 1.25; }
        .seo-crawler-fallback p { color: #687282; }
        .seo-crawler-fallback a { color: #e84f19; }
        @media (max-width: 800px) { .seo-crawler-fallback { margin: 16px; padding: 20px; } }
    </style>
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
    @if($pageMetaImage)
        <meta data-inertia="og:image" property="og:image" content="{{ $pageMetaImage }}" />
        <meta data-inertia="og:image:secure_url" property="og:image:secure_url" content="{{ $pageMetaImage }}" />
        <meta data-inertia="og:image:alt" property="og:image:alt" content="{{ $pageProps['product']['name'] ?? site_name() }}" />
    @endif
    <meta data-inertia="twitter:card" name="twitter:card" content="{{ $pageMetaImage ? 'summary_large_image' : 'summary' }}" />
    <meta data-inertia="twitter:title" name="twitter:title" content="{{ $pageMetaTitle }}" />
    @if($pageMetaDescription)<meta data-inertia="twitter:description" name="twitter:description" content="{{ $pageMetaDescription }}" />@endif
    @if($pageMetaImage)<meta data-inertia="twitter:image" name="twitter:image" content="{{ $pageMetaImage }}" />@endif
    @if(!empty($pageSeo['robots']))<meta data-inertia="robots" name="robots" content="{{ $pageSeo['robots'] }}" />@endif
    <link data-inertia="canonical" rel="canonical" href="{{ $pageCanonical }}" />
    @if(!empty($page['props']['structuredData']))
        <script data-inertia="structured-data" nonce="{{ csp_nonce() }}" type="application/ld+json">{!! json_encode($page['props']['structuredData'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
    <link rel="icon" href="{{ favicon_url() }}" />
    <link rel="apple-touch-icon" href="{{ favicon_url() }}" />
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
    @include('partials.meta-pixel-consent')
    @if($fallbackH1Enabled && $fallbackH1 !== '')
        {{-- Render meaningful content for visitors and crawlers without JavaScript. --}}
        <main class="seo-crawler-fallback" data-seo-crawler-fallback>
            <p style="margin:0 0 1.25rem"><img src="{{ seo_image_url() }}" alt="{{ site_name() }}" width="180" height="64" style="display:block;max-width:180px;height:64px;object-fit:contain;object-position:left center" /></p>
            <h1 data-seo-fallback-h1>{{ $pageProps['product']['name'] ?? $pageProps['activeCategory']['name'] ?? $pageProps['guide']['title'] ?? ($pageComponent === 'Storefront/Home' ? $fallbackHomeH1 : ($pageProps['heading'] ?? $fallbackH1)) }}</h1>
            @if($pageMetaDescription)<p>{{ $pageMetaDescription }}</p>@endif
            @if($pageComponent === 'Storefront/Product' && !empty($pageProps['product']['availability_label']))
                <p>{{ $pageProps['product']['availability_label'] }}</p>
                @if(!empty($pageProps['product']['allow_backorder']) && (int) ($pageProps['product']['available_stock_quantity'] ?? 0) === 0)
                    <p>We source this item after receiving your order. We will confirm availability and delivery timing before dispatch.</p>
                @endif
            @endif
            @if($pageComponent === 'Storefront/BlogDetail' && !empty($pageProps['guide']))
                @foreach($pageProps['guide']['sections'] as $section)
                    <section><h2>{{ $section['heading'] }}</h2><p>{{ $section['text'] }}</p></section>
                @endforeach
                @if(!empty($pageProps['guideCategory']))
                    <a href="{{ $pageProps['guideCategory']['url'] }}">Browse {{ $pageProps['guideCategory']['name'] }}</a>
                @endif
                @foreach($pageProps['guideProducts'] ?? [] as $guideProduct)
                    <p><a href="{{ $guideProduct['url'] }}">{{ $guideProduct['name'] }}</a> &mdash; {{ $guideProduct['availability_label'] ?? 'In Stock' }}</p>
                @endforeach
            @endif
            <nav aria-label="Additional store links"><a href="{{ route('about') }}">About TAQI LIFE</a> · <a href="{{ route('blog') }}">Shopping guides</a></nav>
            <nav aria-label="Store links"><a href="{{ route('home') }}">Homepage</a> · <a href="{{ route('shop') }}">Browse the shop</a> · <a href="{{ route('contact') }}">Contact support</a></nav>
            @php
                $socialLinks = [
                    'Facebook' => trim((string) setting('facebook_url', '')),
                    'Instagram' => trim((string) setting('instagram_url', '')),
                    'YouTube' => trim((string) setting('youtube_url', '')),
                    'X' => trim((string) setting('twitter_url', '')),
                ];
            @endphp
            @if(collect($socialLinks)->filter()->isNotEmpty())
                <nav aria-label="Social links">Follow TAQI LIFE: @foreach($socialLinks as $label => $url) @if($url)<a href="{{ $url }}" rel="noopener">{{ $label }}</a>@endif @endforeach</nav>
            @endif
            @if(request()->is('/'))
                <section aria-labelledby="shopping-guide-title">
                    <h2 id="shopping-guide-title">Online shopping in Bangladesh with TAQI LIFE</h2>
                    <p>TAQI LIFE is an online shopping destination for everyday fashion, useful gadgets, home essentials, gifts, and products for children. Explore the store by category or visit the full shop to compare items that fit your needs. Each collection is arranged to help you move from browsing to choosing without losing sight of the details that matter.</p>
                    <p>Start with a category, then use the product listings to discover current choices. Open a product page to review its photos, price, available options, and description before adding it to your cart. Product availability and offers can change, so the product page is the best place to check the latest information for the item you want.</p>
                    <p>Before checkout, review the items and quantities in your cart and provide accurate delivery information. Delivery charges are shown as part of the order process. If an item needs special delivery arrangements, contact TAQI LIFE before placing the order so the team can confirm the available option.</p>
                    <p>Need help after choosing a product? Use order tracking to follow an order, read the shipping and return information before you buy, or contact TAQI LIFE with a product or order question. The shopping guides also share practical ideas for choosing products, comparing useful features, planning thoughtful gifts, and getting more value from your online shopping every day.</p>
                    <h2>Shop useful products for everyday life</h2>
                    <p>TAQI LIFE brings together practical collections for different parts of your day. Browse men's and women's fashion for clothing and traditional styles, or explore watches and accessories for personal use and thoughtful gifts. Our gadgets and electronics collection includes useful devices and accessories for work, study, travel, entertainment, and home convenience. Home and lifestyle products help you organize rooms, prepare meals, care for your family, and handle everyday tasks more comfortably.</p>
                    <p>Parents can explore the kids collection for age-appropriate items, while the customize and gift collection makes it easier to find something personal for a birthday, celebration, festival, or family occasion. New products and seasonal offers are added as they become available. Use category pages to narrow your choices, then compare product photos, descriptions, specifications, prices, available sizes, and current stock before making a decision.</p>
                    <h2>Product details you can review before ordering</h2>
                    <p>Every product page is designed to answer the questions customers usually have before ordering. Check the product name, brand, SKU, photographs, customer price, discount information, and available quantity. Products with variations show the available choices so you can select the correct size, colour, style, or model. If an item is out of stock, the page clearly shows its availability so you can choose another product or return later when the stock is updated.</p>
                    <p>Supplier products and locally added products are reviewed in the same storefront catalog. Prices, stock levels, and product information may change when a supplier updates its catalog, so the latest product page should be used as the final reference. If an important detail is unclear, contact the TAQI LIFE team before placing your order and include the product name or SKU so we can help quickly.</p>
                    <h2>Delivery, payment, and customer support</h2>
                    <p>TAQI LIFE serves customers across Bangladesh. During checkout, enter a complete delivery address and an active phone number so the delivery team can reach you. Review the order summary, item quantities, delivery charge, and total amount before confirming. Cash on delivery and other enabled payment options are shown during checkout according to the current store settings and delivery area.</p>
                    <p>After ordering, use the tracking page to check progress and keep your order information available for reference. If you need to ask about delivery, product availability, a return, or a replacement, contact customer support with your order number. Read the shipping and return information for the current process and time limits. Our goal is to make browsing, comparing, ordering, and getting help straightforward from the first visit through delivery.</p>
                    <p>When comparing products, consider the features that matter for your own needs rather than choosing only by appearance. Read the full description, check measurements and materials where they are provided, and review the available options before adding an item to your cart. A clear product choice helps reduce returns and makes delivery smoother. You can save interesting products to your wishlist, continue browsing related categories, or return to the shop to compare alternatives. TAQI LIFE updates its catalog regularly, so visit again for new arrivals, useful seasonal products, and current offers selected for customers in Bangladesh.</p>
                    <nav aria-label="Shopping help"><a href="{{ route('shop') }}">Browse all products</a> · <a href="{{ route('track') }}">Track an order</a> · <a href="{{ route('shipping') }}">Shipping information</a> · <a href="{{ route('refund') }}">Return and refund policy</a> · <a href="{{ route('contact') }}">Contact TAQI LIFE</a></nav>
                </section>
            @endif
            @foreach(collect($pageProps['filterCategories'] ?? $pageProps['featuredCategories'] ?? [])->unique('slug') as $category)
                <a href="{{ route('shop.category', $category['slug']) }}">{{ $category['name'] }}</a>
            @endforeach
            @if($fallbackProducts->isNotEmpty())
                <ul>@foreach($fallbackProducts as $product)
                    <li><a href="{{ route('product.show', $product['url_key']) }}">{{ $product['name'] }}</a></li>
                @endforeach</ul>
            @endif
            @if(isset($pageProps['products']['data']) && $fallbackProducts->isEmpty())
                <ul>@foreach($pageProps['products']['data'] as $product)
                    <li><a href="{{ route('product.show', $product['url_key'] ?? $product['slug']) }}">{{ $product['name'] }}</a></li>
                @endforeach</ul>
            @endif
            @if(!empty($pageProps['products']['prev_page_url']))<a href="{{ $pageProps['products']['prev_page_url'] }}">Previous page</a>@endif
            @if(!empty($pageProps['products']['next_page_url']))<a href="{{ $pageProps['products']['next_page_url'] }}">Next page</a>@endif
        </main>
    @endif
    @php
        $__inertiaSsrResponse = app(\Inertia\Ssr\SsrState::class)->setPage($page)->dispatch();
    @endphp
    @if($__inertiaSsrResponse)
        {!! $__inertiaSsrResponse->body !!}
    @else
        <script data-page="app" nonce="{{ csp_nonce() }}" type="application/json">{!! json_encode($page, JSON_HEX_TAG) !!}</script><div id="app"></div>
    @endif
</body>
</html>
