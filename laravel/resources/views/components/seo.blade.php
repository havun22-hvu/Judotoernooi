@props([
    'title' => null,
    'description' => null,
    'canonical' => null,
    'ogImage' => null,
    'type' => 'website',
    'noindex' => false,
])

@php
    $appUrl = rtrim(config('app.url'), '/');
    $currentPath = request()->getPathInfo();
    $canonicalUrl = $canonical ?? $appUrl . $currentPath;
    $ogImageUrl = $ogImage ? (str_starts_with($ogImage, 'http') ? $ogImage : $appUrl . $ogImage) : $appUrl . '/icon-512x512.png';
    $currentLocale = app()->getLocale();
    $alternateLocale = $currentLocale === 'nl' ? 'en' : 'nl';

    // Build hreflang URLs with locale parameter
    $currentUrl = url()->current();
    $hreflangSeparator = str_contains($currentUrl, '?') ? '&' : '?';
@endphp

@if($description)
<meta name="description" content="{{ $description }}">
@endif

@if($noindex)
<meta name="robots" content="noindex, nofollow">
@endif

<link rel="canonical" href="{{ $canonicalUrl }}">

{{-- hreflang tags --}}
<link rel="alternate" hreflang="nl" href="{{ $currentUrl . $hreflangSeparator . 'locale=nl' }}">
<link rel="alternate" hreflang="en" href="{{ $currentUrl . $hreflangSeparator . 'locale=en' }}">
<link rel="alternate" hreflang="x-default" href="{{ $currentUrl }}">

{{-- Open Graph --}}
<meta property="og:type" content="{{ $type }}">
@if($title)
<meta property="og:title" content="{{ $title }}">
@endif
@if($description)
<meta property="og:description" content="{{ $description }}">
@endif
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:image" content="{{ $ogImageUrl }}">
<meta property="og:locale" content="{{ $currentLocale === 'nl' ? 'nl_NL' : 'en_GB' }}">
<meta property="og:locale:alternate" content="{{ $currentLocale === 'nl' ? 'en_GB' : 'nl_NL' }}">
<meta property="og:site_name" content="{{ __('JudoToernooi') }}">

{{-- Twitter Card --}}
<meta name="twitter:card" content="summary">
@if($title)
<meta name="twitter:title" content="{{ $title }}">
@endif
@if($description)
<meta name="twitter:description" content="{{ $description }}">
@endif
<meta name="twitter:image" content="{{ $ogImageUrl }}">
