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
    @endphp
    <title inertia>{{ $defaultMetaTitle }}</title>
    <meta name="site-name" content="{{ site_name() }}" />
    <meta name="seo-default-title" content="{{ $defaultMetaTitle }}" />
    @if($defaultMetaDescription)<meta name="description" content="{{ $defaultMetaDescription }}" />@endif
    @if($defaultMetaKeywords)<meta name="keywords" content="{{ $defaultMetaKeywords }}" />@endif
    <meta property="og:site_name" content="{{ site_name() }}" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="{{ $defaultMetaTitle }}" />
    @if($defaultMetaDescription)<meta property="og:description" content="{{ $defaultMetaDescription }}" />@endif
    <meta name="twitter:card" content="summary_large_image" />
    <link rel="canonical" href="{{ url()->current() }}" />
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
