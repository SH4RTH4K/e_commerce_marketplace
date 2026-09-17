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
        $pageSeo = $page['props']['seo'] ?? [];
        $pageMetaTitle = trim((string) ($pageSeo['title'] ?? '')) ?: $defaultMetaTitle;
        $pageMetaDescription = trim(strip_tags((string) (($pageSeo['description'] ?? '') ?: $defaultMetaDescription)));
        $pageMetaKeywords = trim((string) (($pageSeo['keywords'] ?? '') ?: $defaultMetaKeywords));
        $pageCanonical = $pageSeo['canonical'] ?? url()->current();
        $pageMetaImage = $pageSeo['image'] ?? null;
    @endphp
    <title data-inertia="">{{ $pageMetaTitle }}</title>
    <meta name="site-name" content="{{ site_name() }}" />
    <meta name="seo-default-title" content="{{ $defaultMetaTitle }}" />
    {{-- Social crawlers read this HTML without running the storefront JavaScript. --}}
    @if($pageMetaDescription)<meta data-inertia="description" name="description" content="{{ $pageMetaDescription }}" />@endif
    @if($pageMetaKeywords)<meta data-inertia="keywords" name="keywords" content="{{ $pageMetaKeywords }}" />@endif
    <meta property="og:site_name" content="{{ site_name() }}" />
    <meta data-inertia="og:type" property="og:type" content="{{ $pageSeo['type'] ?? 'website' }}" />
    <meta data-inertia="og:title" property="og:title" content="{{ $pageMetaTitle }}" />
    @if($pageMetaDescription)<meta data-inertia="og:description" property="og:description" content="{{ $pageMetaDescription }}" />@endif
    <meta data-inertia="og:url" property="og:url" content="{{ $pageCanonical }}" />
    @if($pageMetaImage)<meta data-inertia="og:image" property="og:image" content="{{ $pageMetaImage }}" />@endif
    <meta data-inertia="twitter:card" name="twitter:card" content="summary_large_image" />
    <meta data-inertia="twitter:title" name="twitter:title" content="{{ $pageMetaTitle }}" />
    @if($pageMetaDescription)<meta data-inertia="twitter:description" name="twitter:description" content="{{ $pageMetaDescription }}" />@endif
    @if($pageMetaImage)<meta data-inertia="twitter:image" name="twitter:image" content="{{ $pageMetaImage }}" />@endif
    @if(!empty($pageSeo['robots']))<meta data-inertia="robots" name="robots" content="{{ $pageSeo['robots'] }}" />@endif
    <link data-inertia="canonical" rel="canonical" href="{{ $pageCanonical }}" />
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
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @inertiaHead
</head>
<body class="antialiased">
    @include('partials.tracking-body')
    @inertia
</body>
</html>
