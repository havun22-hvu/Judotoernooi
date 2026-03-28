<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('JudoToernooi') }} - {{ __('Professioneel Toernooi Management') }}</title>
    <x-seo
        :title="__('JudoToernooi') . ' - ' . __('Professioneel Toernooi Management')"
        :description="__('Organiseer uw judotoernooi professioneel met JudoToernooi. Van inschrijving tot eindstand, alles in een platform.')"
    />
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    @vite(["resources/css/app.css", "resources/js/app.js"])

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "Havun",
        "url": "{{ config('app.url') }}",
        "logo": "{{ config('app.url') }}/icon-512x512.png",
        "contactPoint": {
            "@type": "ContactPoint",
            "email": "havun22@gmail.com",
            "contactType": "customer service",
            "availableLanguage": ["Dutch", "English"]
        }
    }
    </script>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "JudoToernooi",
        "url": "{{ config('app.url') }}",
        "inLanguage": ["nl", "en"],
        "publisher": {
            "@type": "Organization",
            "name": "Havun"
        }
    }
    </script>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "name": "JudoToernooi",
        "applicationCategory": "SportsApplication",
        "applicationSubCategory": "Tournament Management",
        "operatingSystem": "Web",
        "description": "{{ __('Organiseer uw judotoernooi professioneel met JudoToernooi. Van inschrijving tot eindstand, alles in een platform.') }}",
        "url": "{{ config('app.url') }}",
        "inLanguage": ["nl", "en"],
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "EUR"
        },
        "author": {
            "@type": "Organization",
            "name": "Havun"
        },
        "featureList": "{{ __('Poule indeling, Digitale weging, Live uitslagen, Eliminatie brackets, Coach portal, Real-time synchronisatie') }}"
    }
    </script>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [
            {
                "@type": "Question",
                "name": "{{ __('Wat is JudoToernooi?') }}",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "{{ __('JudoToernooi is een online platform waarmee judoclubs hun toernooien professioneel kunnen organiseren. Van inschrijving en weging tot poule-indeling en live uitslagen - alles in een systeem.') }}"
                }
            },
            {
                "@type": "Question",
                "name": "{{ __('Hoe werkt de poule-indeling?') }}",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "{{ __('De poule-indeling gebeurt automatisch op basis van leeftijd, gewicht en band. Het systeem maakt eerlijke poules met een druk op de knop, rekening houdend met alle judoregels.') }}"
                }
            },
            {
                "@type": "Question",
                "name": "{{ __('Kunnen ouders live meekijken?') }}",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "{{ __('Ja! Via de publieke pagina kunnen ouders en toeschouwers live meekijken wanneer hun kind aan de beurt is. Ze ontvangen notificaties en kunnen hun favoriete judoka volgen.') }}"
                }
            }
        ]
    }
    </script>
</head>
<body class="bg-white min-h-screen antialiased">
    <!-- DO NOT REMOVE: Header with logo, language switcher, and login link -->
    <header class="bg-gradient-to-r from-blue-950 via-blue-900 to-blue-800 sticky top-0 z-20">
        <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <img src="/icon-512x512.png" alt="Logo" class="w-9 h-9 rounded-full shadow-sm">
                <span class="text-white font-bold text-lg tracking-tight">JudoTournament</span>
            </div>
            <div class="flex items-center gap-5">
                {{-- Taalkiezer --}}
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" @click.away="open = false" class="flex items-center text-blue-300 hover:text-white text-sm transition focus:outline-none">
                        @include('partials.flag-icon', ['lang' => app()->getLocale()])
                        <svg class="ml-1 w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open" x-transition class="absolute right-0 mt-2 w-40 bg-white rounded-lg shadow-xl py-1 z-50 border border-gray-100">
                        <form action="{{ route('locale.switch', 'nl') }}" method="POST">
                            @csrf
                            <button type="submit" class="flex items-center gap-2 w-full px-4 py-2 text-gray-700 hover:bg-gray-50 text-sm {{ app()->getLocale() === 'nl' ? 'font-bold' : '' }}">
                                @include('partials.flag-icon', ['lang' => 'nl']) Nederlands
                            </button>
                        </form>
                        <form action="{{ route('locale.switch', 'en') }}" method="POST">
                            @csrf
                            <button type="submit" class="flex items-center gap-2 w-full px-4 py-2 text-gray-700 hover:bg-gray-50 text-sm {{ app()->getLocale() === 'en' ? 'font-bold' : '' }}">
                                @include('partials.flag-icon', ['lang' => 'en']) English
                            </button>
                        </form>
                    </div>
                </div>
                <a href="{{ route('login') }}" class="text-white/80 hover:text-white text-sm font-medium transition">
                    {{ __('Inloggen') }}
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-blue-950 via-blue-900 to-blue-800 relative overflow-hidden">
        <div class="absolute inset-0">
            <div class="absolute top-10 left-1/4 w-96 h-96 bg-blue-500/10 rounded-full blur-3xl"></div>
            <div class="absolute bottom-10 right-1/4 w-80 h-80 bg-indigo-500/10 rounded-full blur-3xl"></div>
        </div>
        <div class="max-w-4xl mx-auto px-6 pt-16 md:pt-28 pb-20 md:pb-32 text-center relative z-1">
            <p class="text-blue-400 font-semibold mb-6 text-xs uppercase tracking-[0.2em]">{{ __('Toernooi Management Software') }}</p>
            <h1 class="text-4xl md:text-6xl font-extrabold text-white mb-8 leading-[1.1] tracking-tight">
                {{ __('Uw judotoernooi in minuten, niet in weken') }}
            </h1>
            <p class="text-lg md:text-xl text-blue-200 mb-12 leading-relaxed max-w-2xl mx-auto">
                {{ __("Waar u vroeger dagen bezig was met Excel-lijsten, papieren weegformulieren en handgeschreven poules en wedstrijdschema's, regelt JudoToernooi alles digitaal. Van inschrijving tot medaille-uitreiking.") }}
            </p>

            <!-- DO NOT REMOVE: CTA buttons for registration and login - primary conversion point -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}"
                   class="bg-white text-blue-900 px-8 py-4 rounded-xl font-bold text-lg hover:bg-blue-50 transition-all shadow-lg shadow-blue-950/30 text-center">
                    {{ __('Account Aanmaken') }}
                </a>
                <a href="{{ route('login') }}"
                   class="border-2 border-white/30 text-white px-8 py-4 rounded-xl font-semibold text-lg hover:bg-white/10 hover:border-white/50 transition-all text-center">
                    {{ __('Inloggen') }}
                </a>
            </div>
        </div>
    </section>

    <!-- USP Balk -->
    <section class="bg-blue-800 border-t border-blue-700/50 py-5">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                <div class="flex flex-col items-center gap-2">
                    <svg class="w-6 h-6 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span class="text-white/90 font-medium text-sm">{{ __('Setup in minuten') }}</span>
                </div>
                <div class="flex flex-col items-center gap-2">
                    <svg class="w-6 h-6 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    <span class="text-white/90 font-medium text-sm">{{ __('Alles-in-een platform') }}</span>
                </div>
                <div class="flex flex-col items-center gap-2">
                    <svg class="w-6 h-6 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <span class="text-white/90 font-medium text-sm">{{ __('PC, laptop, tablet & touchscreen') }}</span>
                </div>
                <div class="flex flex-col items-center gap-2">
                    <svg class="w-6 h-6 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <span class="text-white/90 font-medium text-sm">{{ __('Veilig & betrouwbaar') }}</span>
                </div>
            </div>
        </div>
    </section>

    <!-- DO NOT REMOVE: Features Grid - all 6 feature cards showcase core product capabilities -->
    <section class="py-24 bg-gray-50">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    {{ __('Alles voor uw toernooi') }}
                </h2>
                <p class="text-lg text-gray-500 max-w-2xl mx-auto">
                    {{ __('Geen losse spreadsheets, geen papieren lijsten. Een compleet platform dat alles regelt.') }}
                </p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Feature 1: Poule-indeling -->
                <div class="bg-white rounded-xl p-7 shadow-sm hover:shadow-md transition-shadow border border-gray-100">
                    <div class="w-11 h-11 bg-blue-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">{{ __('Slimme Poule-indeling') }}</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">
                        {{ __('Automatische indeling op basis van leeftijd, gewicht en band. Eerlijke poules met een druk op de knop. Handmatig aanpassen kan altijd.') }}
                    </p>
                </div>

                <!-- Feature 2: Digitale Weging -->
                <div class="bg-white rounded-xl p-7 shadow-sm hover:shadow-md transition-shadow border border-gray-100">
                    <div class="w-11 h-11 bg-green-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">{{ __('Digitale Weging') }}</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">
                        {{ __('Scan de QR-code, voer het gewicht in, klaar. Real-time overzicht van wie gewogen is en wie nog moet. Geen papieren weeglijsten meer.') }}
                    </p>
                </div>

                <!-- Feature 3: Wedstrijdschema's -->
                <div class="bg-white rounded-xl p-7 shadow-sm hover:shadow-md transition-shadow border border-gray-100">
                    <div class="w-11 h-11 bg-amber-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">{{ __("Live Wedstrijdschema's") }}</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">
                        {{ __("Overzichtelijke mat-interface met kleurcodes. Judoka's en coaches zien direct wie er aan de beurt zijn.") }}
                    </p>
                </div>

                <!-- Feature 4: Wimpelcompetitie -->
                <div class="bg-white rounded-xl p-7 shadow-sm hover:shadow-md transition-shadow border border-gray-100">
                    <div class="w-11 h-11 bg-purple-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">{{ __('Wimpelcompetitie') }}</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">
                        {{ __('Organiseer puntencompetities. Judokascores worden automatisch bijgehouden. Perfect voor interne oefenwedstrijden.') }}
                    </p>
                </div>

                <!-- Feature 5: Club & Judoka Database -->
                <div class="bg-white rounded-xl p-7 shadow-sm hover:shadow-md transition-shadow border border-gray-100">
                    <div class="w-11 h-11 bg-indigo-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">{{ __('Club & Judoka Database') }}</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">
                        {{ __("Uw clubs en judoka's worden bewaard tussen toernooien. Geen dubbel werk: importeer vanuit uw bestaande administratie of hergebruik eerdere inschrijvingen.") }}
                    </p>
                </div>

                <!-- Feature 6: Veiligheid & Backup -->
                <div class="bg-white rounded-xl p-7 shadow-sm hover:shadow-md transition-shadow border border-gray-100">
                    <div class="w-11 h-11 bg-red-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">{{ __('Veiligheid & Backup') }}</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">
                        {{ __('Automatische backups elke minuut tijdens de wedstrijddag. Noodpakket downloaden voor offline gebruik. Alles printen als backup.') }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Hoe werkt het? -->
    <section class="py-24 bg-white">
        <div class="max-w-5xl mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    {{ __('In 5 stappen klaar') }}
                </h2>
                <p class="text-lg text-gray-500">
                    {{ __('Geen technische kennis nodig. Als u kunt klikken, kunt u een toernooi organiseren.') }}
                </p>
            </div>

            <div class="grid md:grid-cols-5 gap-8">
                @foreach([
                    ['title' => __('Maak een toernooi aan'), 'desc' => __('Kies uw instellingen: gewichtsklassen, wedstrijdsysteem, aantal matten. Gebruik een template of begin vanaf nul.')],
                    ['title' => __('Importeer deelnemers'), 'desc' => __("Upload een Excel-bestand of laat coaches zelf inschrijven via het Coach Portaal. Judoka's worden automatisch ingedeeld in de juiste gewichtsklasse.")],
                    ['title' => __('Poule-indeling'), 'desc' => __("Automatische verdeling van judoka's over de poules, handmatig aan te passen.")],
                    ['title' => __('Blokken & Matten'), 'desc' => __("Verdeel de categorieën en poules over de blokken en de matten, handmatig aan te passen.")],
                    ['title' => __('Start de wedstrijddag'), 'desc' => __("Weeg de judoka's, overpoulen indien nodig, activeer de wedstrijdschema's en laat het systeem de rest doen.")],
                ] as $i => $step)
                <div class="text-center">
                    <div class="w-12 h-12 bg-blue-900 text-white rounded-full flex items-center justify-center text-lg font-bold mx-auto mb-4">{{ $i + 1 }}</div>
                    <h3 class="font-bold text-gray-900 mb-2">{{ $step['title'] }}</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">{{ $step['desc'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Wedstrijdagenda -->
    @if(isset($agendaToernooien) && $agendaToernooien->count() > 0)
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    {{ __('Wedstrijdagenda') }}
                </h2>
                <p class="text-lg text-gray-500">
                    {{ __('Komende toernooien — meld je club aan of bekijk live uitslagen') }}
                </p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($agendaToernooien as $t)
                @php
                    $isLive = $t->weegkaarten_gemaakt_op !== null;
                    $url = route('publiek.index', ['organisator' => $t->organisator?->slug, 'toernooi' => $t->slug]);
                @endphp
                <a href="{{ $url }}" class="block bg-white border border-gray-200 rounded-xl p-6 hover:shadow-lg hover:border-blue-300 transition-all group">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <h3 class="font-bold text-gray-900 group-hover:text-blue-600 transition-colors">{{ $t->naam }}</h3>
                            @if($t->organisator?->organisatie_naam)
                                <p class="text-sm text-gray-500">{{ $t->organisator->organisatie_naam }}</p>
                            @endif
                        </div>
                        @if($isLive)
                            <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full">LIVE</span>
                        @elseif($t->datum->isToday())
                            <span class="px-2 py-1 bg-red-100 text-red-700 text-xs font-medium rounded-full animate-pulse">{{ __('VANDAAG') }}</span>
                        @endif
                    </div>

                    <div class="space-y-2 text-sm text-gray-600">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span>{{ $t->datum->translatedFormat('l d F Y') }}</span>
                        </div>
                        @if($t->locatie)
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span>{{ $t->locatie }}</span>
                        </div>
                        @endif
                    </div>

                    <div class="mt-4 pt-3 border-t">
                        @if($isLive)
                            <span class="text-green-600 font-medium text-sm">{{ __('Bekijk live uitslagen') }} &rarr;</span>
                        @else
                            <span class="text-blue-600 font-medium text-sm">{{ __('Meer info & aanmelden') }} &rarr;</span>
                        @endif
                    </div>
                </a>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <!-- DO NOT REMOVE: Screenshot section with lightbox - shows real product screenshots -->
    <section class="py-24 bg-gray-50">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-14">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    {{ __('Gebouwd voor de wedstrijddag') }}
                </h2>
                <p class="text-lg text-gray-500 max-w-2xl mx-auto">
                    {{ __("Ontworpen door judoka's, voor judoka's. Elke functie is getest op echte toernooien.") }}
                </p>
            </div>

            <div class="grid md:grid-cols-2 gap-6" x-data="{ lightbox: null }">
                @foreach([
                    ['img' => 'Poule-overzicht.png', 'label' => __('Poule-overzicht met automatische indeling')],
                    ['img' => 'Zaaloverzicht.png', 'label' => __('Zaaloverzicht met matten en blokken')],
                    ['img' => 'Mat-interface.png', 'label' => __('Live wedstrijdschema met beurtaanduiding')],
                    ['img' => 'Eliminatie-bracket.png', 'label' => __('Eliminatie-bracket met A en B groep')],
                ] as $screenshot)
                <div class="rounded-xl overflow-hidden shadow-sm border border-gray-200 cursor-pointer hover:shadow-md transition-shadow group"
                     @click="lightbox = '/images/{{ $screenshot['img'] }}'">
                    <img src="/images/{{ $screenshot['img'] }}" alt="{{ $screenshot['label'] }}" class="w-full max-h-64 object-cover object-top group-hover:scale-[1.02] transition-transform duration-300" loading="lazy">
                    <div class="bg-white px-4 py-3 text-center">
                        <p class="font-medium text-gray-600 text-sm">{{ $screenshot['label'] }}</p>
                    </div>
                </div>
                @endforeach

                <!-- Lightbox popup -->
                <div x-show="lightbox" x-transition.opacity
                     x-data="{ zoomed: false }"
                     class="fixed inset-0 z-50 bg-black/80"
                     :class="zoomed ? 'overflow-auto cursor-zoom-out' : 'flex items-center justify-center cursor-zoom-in'"
                     @click="lightbox = null; zoomed = false"
                     @keydown.escape.window="lightbox = null; zoomed = false"
                     style="display:none">
                    <img :src="lightbox"
                         :class="zoomed ? 'max-w-none rounded-lg shadow-2xl m-4' : 'max-w-full max-h-[90vh] rounded-lg shadow-2xl'"
                         @click.stop="zoomed = !zoomed">
                    <button @click.stop="lightbox = null; zoomed = false"
                            class="fixed top-4 right-4 text-white text-4xl leading-none hover:text-gray-300 z-10">&times;</button>
                </div>
            </div>
        </div>
    </section>

    <!-- Meer features -->
    <section class="py-16 bg-white">
        <div class="max-w-5xl mx-auto px-6">
            <div class="text-center mb-10">
                <h2 class="text-2xl font-bold text-gray-900 mb-2">
                    {{ __('En nog veel meer...') }}
                </h2>
            </div>
            <div class="flex flex-wrap justify-center gap-2.5">
                @foreach([
                    __('Eliminatie-brackets'),
                    __('Coach Portaal'),
                    __('Digitale weegkaarten'),
                    __('Multi-mat ondersteuning'),
                    __('Installeerbaar als app (PWA)'),
                    __('Meertalig (NL/EN)'),
                    __('Coach Kaarten & QR Code'),
                    __('Zaaloverzicht'),
                    __('Blokplanning'),
                    __('Excel import/export'),
                    __('Herclassificatie bij afwijkend gewicht'),
                    __('Spreker-interface'),
                    __('Noodpakket (offline)'),
                    __("Poules & wedstrijdschema's printen"),
                    __('Medailles & prijzen'),
                    __('Live app voor ouders & coaches'),
                ] as $feature)
                    <span class="bg-gray-50 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium border border-gray-200">
                        {{ $feature }}
                    </span>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Coach info -->
    <section class="py-16 bg-gray-50">
        <div class="max-w-2xl mx-auto px-6 text-center">
            <div class="bg-white rounded-xl p-8 shadow-sm border border-gray-100">
                <div class="w-11 h-11 bg-orange-50 rounded-lg flex items-center justify-center mb-4 mx-auto">
                    <svg class="w-6 h-6 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">{{ __('Bent u coach?') }}</h3>
                <p class="text-gray-500 text-sm leading-relaxed">
                    {{ __("Heeft u een link ontvangen van een toernooi-organisator? Gebruik die link om uw judoka's in te schrijven via het Coach Portaal. Geen account nodig.") }}
                </p>
            </div>
        </div>
    </section>

    <!-- DO NOT REMOVE: Final CTA section - bottom-of-page conversion point with register button -->
    <section class="py-24 bg-gradient-to-br from-blue-950 via-blue-900 to-blue-800 text-center relative overflow-hidden">
        <div class="absolute inset-0">
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-blue-500/5 rounded-full blur-3xl"></div>
        </div>
        <div class="max-w-3xl mx-auto px-6 relative z-1">
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">
                {{ __('Klaar om uw toernooi te organiseren?') }}
            </h2>
            <p class="text-xl text-blue-300 mb-10">
                {{ __('Maak een account aan en ontdek hoe makkelijk het kan.') }}
            </p>
            <a href="{{ route('register') }}"
               class="inline-block bg-white text-blue-900 px-10 py-4 rounded-xl font-bold text-lg hover:bg-blue-50 transition-all shadow-lg shadow-blue-950/30">
                {{ __('Account Aanmaken') }}
            </a>
        </div>
    </section>

    <!-- DO NOT REMOVE: Footer with legal links (Voorwaarden, Privacy, Cookies, Contact) and KvK info -->
    <footer class="bg-gray-950 py-8">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-wrap justify-center items-center gap-x-4 gap-y-1 text-xs text-gray-500 mb-3">
                <a href="{{ route('legal.terms') }}" class="hover:text-gray-300 transition">{{ __('Voorwaarden') }}</a>
                <span class="text-gray-700">&bull;</span>
                <a href="{{ route('legal.privacy') }}" class="hover:text-gray-300 transition">{{ __('Privacy') }}</a>
                <span class="text-gray-700">&bull;</span>
                <a href="{{ route('legal.cookies') }}" class="hover:text-gray-300 transition">{{ __('Cookies') }}</a>
                <span class="text-gray-700">&bull;</span>
                <a href="mailto:havun22@gmail.com" class="hover:text-gray-300 transition">{{ __('Contact') }}</a>
            </div>
            <div class="text-center text-xs text-gray-600">
                &copy; {{ date('Y') }} Havun
                <span class="mx-1.5">&bull;</span>
                KvK 98516000
                <span class="mx-1.5">&bull;</span>
                BTW-vrij (KOR)
            </div>
        </div>
    </footer>
</body>
</html>
