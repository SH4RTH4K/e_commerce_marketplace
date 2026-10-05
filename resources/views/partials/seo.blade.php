@php
    $siteName     = site_name();
    $pageTitle    = trim($rawTitle ?? '') !== ''
        ? $rawTitle
        : (trim($title ?? '') !== '' ? ($title . ' — ' . $siteName) : setting('default_meta_title', $siteName));
    $desc         = $metaDescription ?? setting('default_meta_description', $siteName);
    $keywords     = $metaKeywords ?? setting('default_meta_keywords');
    $canonicalUrl = $canonical ?? url()->current();
    $image        = $ogImage ?? null;
    $keywordConsistencyEnabled = (string) setting('seo_keyword_consistency_enabled', '1') === '1';
    $seoKeywordPhrases = $keywordConsistencyEnabled ? seo_keyword_list(trim((string) setting('seo_target_keywords', '')) ?: $keywords) : [];
    if ($seoKeywordPhrases !== []) {
        $pageTitle = seo_append_missing_keywords($pageTitle, $seoKeywordPhrases, 180);
        $desc = seo_description_with_keywords($desc ?: $siteName, $seoKeywordPhrases, 400);
        $keywords = seo_append_missing_keywords($keywords, $seoKeywordPhrases, 500, ', ');
    }
    $descShort    = \Illuminate\Support\Str::limit(strip_tags((string) $desc), 155); // 155 = Google recommended max
    $descLong     = \Illuminate\Support\Str::limit(strip_tags((string) $desc), 300);
@endphp
<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ $descShort }}">
<meta name="author" content="{{ $siteName }}">
@if($keywords)<meta name="keywords" content="{{ $keywords }}">@endif
<link rel="canonical" href="{{ $canonicalUrl }}">
<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">

<meta property="og:type" content="{{ $ogType ?? 'website' }}">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $descLong }}">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:site_name" content="{{ $siteName }}">
@if($image)<meta property="og:image" content="{{ $image }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">@endif

<meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $pageTitle }}">
<meta name="twitter:description" content="{{ $descShort }}">
@if($image)<meta name="twitter:image" content="{{ $image }}">@endif

@isset($jsonLd)
<script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endisset

