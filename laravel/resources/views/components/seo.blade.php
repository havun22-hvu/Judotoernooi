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
    $defaultLocale = config('app.locale', 'nl');
    $currentLocale = app()->getLocale();
    $currentPath = request()->getPathInfo();

    // Host-genormaliseerde basis-URL zonder query (consolideert www/non-www naar config('app.url')).
    $baseUrl = $appUrl . $currentPath;

    // Eén indexeerbare URL per taal: default-taal = param-loos, overige talen = ?locale=xx.
    // Hierdoor zijn canonical en hreflang self-referentieel en consistent (geen conflict meer).
    $localeUrl = fn (string $locale) => $locale === $defaultLocale ? $baseUrl : $baseUrl . '?locale=' . $locale;

    $canonicalUrl = $canonical ?? $localeUrl($currentLocale);
    $ogImageUrl = $ogImage ? (str_starts_with($ogImage, 'http') ? $ogImage : $appUrl . $ogImage) : $appUrl . '/icon-512x512.png';
@endphp

@if($description)
<meta name="description" content="{{ $description }}">
@endif

@if($noindex)
<meta name="robots" content="noindex, nofollow">
@endif

<link rel="canonical" href="{{ $canonicalUrl }}">

@unless($noindex)
{{-- hreflang tags — wijzen naar self-canonical, indexeerbare URLs per taal --}}
@foreach(config('app.available_locales', ['nl', 'en']) as $locale)
<link rel="alternate" hreflang="{{ $locale }}" href="{{ $localeUrl($locale) }}">
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ $baseUrl }}">
@endunless

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
<meta name="twitter:card" content="summary_large_image">
@if($title)
<meta name="twitter:title" content="{{ $title }}">
@endif
@if($description)
<meta name="twitter:description" content="{{ $description }}">
@endif
<meta name="twitter:image" content="{{ $ogImageUrl }}">

