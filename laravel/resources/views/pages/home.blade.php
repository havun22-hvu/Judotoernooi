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
<body class="bg-white min-h-screen">
    <!-- Header -->
    <header class="absolute top-0 left-0 right-0 z-10">
        <div class="max-w-7xl mx-auto px-4 py-6 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <img src="/icon-512x512.png" alt="Logo" class="w-8 h-8 rounded-full">
                <span class="text-white font-bold text-xl">{{ __('JudoToernooi') }}</span>
            </div>
            <div class="flex items-center gap-4">
                {{-- Taalkiezer --}}
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" @click.away="open = false" class="flex items-center text-blue-200 hover:text-white text-sm focus:outline-none">
                        @include('partials.flag-icon', ['lang' => app()->getLocale()])
                        <svg class="ml-1 w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open" x-transition class="absolute right-0 mt-2 w-40 bg-white rounded-lg shadow-lg py-1 z-50">
                        <form action="{{ route('locale.switch', 'nl') }}" method="POST">
                            @csrf
                            <button type="submit" class="flex items-center gap-2 w-full px-4 py-2 text-gray-700 hover:bg-gray-100 {{ app()->getLocale() === 'nl' ? 'font-bold' : '' }}">
                                @include('partials.flag-icon', ['lang' => 'nl']) Nederlands
                            </button>
                        </form>
                        <form action="{{ route('locale.switch', 'en') }}" method="POST">
                            @csrf
                            <button type="submit" class="flex items-center gap-2 w-full px-4 py-2 text-gray-700 hover:bg-gray-100 {{ app()->getLocale() === 'en' ? 'font-bold' : '' }}">
                                @include('partials.flag-icon', ['lang' => 'en']) English
                            </button>
                        </form>
                    </div>
                </div>
                <a href="{{ route('login') }}" class="text-white hover:text-blue-200 transition">
                    {{ __('Inloggen') }}
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-blue-900 via-blue-800 to-blue-700 relative overflow-hidden">
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-20 left-10 w-72 h-72 bg-white rounded-full blur-3xl"></div>
            <div class="absolute bottom-20 right-10 w-96 h-96 bg-blue-400 rounded-full blur-3xl"></div>
        </div>
        <div class="max-w-4xl mx-auto px-4 pt-32 pb-16 text-center relative z-1">
                <p class="text-blue-300 font-medium mb-3 text-sm uppercase tracking-wider">{{ __('Toernooi Management Software') }}</p>
                <h1 class="text-4xl md:text-5xl font-bold text-white mb-6 leading-tight">
                    {{ __('Uw judotoernooi in minuten, niet in weken') }}
                </h1>
                <p class="text-lg text-blue-100 mb-10 leading-relaxed max-w-2xl mx-auto">
                    {{ __('Waar u vroeger dagen bezig was met Excel-lijsten, papieren weegformulieren en handgeschreven poules, regelt JudoToernooi alles digitaal. Van inschrijving tot medaille-uitreiking.') }}
                </p>

                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}"
                       class="bg-white text-blue-800 px-8 py-4 rounded-lg font-bold text-lg hover:bg-blue-50 transition shadow-lg text-center">
                        {{ __('Gratis Account Aanmaken') }}
                    </a>
                    <a href="{{ route('login') }}"
                       class="bg-transparent border-2 border-white text-white px-8 py-4 rounded-lg font-semibold text-lg hover:bg-white/10 transition text-center">
                        {{ __('Inloggen') }}
                    </a>
                </div>
        </div>

        <!-- Scroll indicator -->
        <div class="text-center pb-6 relative z-1">
            <p class="text-blue-300 text-sm mb-2">{{ __('Ontdek alle mogelijkheden') }}</p>
            <svg class="w-6 h-6 mx-auto text-blue-300 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
            </svg>
        </div>
    </section>

    <!-- USP Balk -->
    <section class="bg-blue-700 py-6">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                <div class="flex flex-col items-center gap-2">
                    <span class="text-2xl">&#9889;</span>
                    <span class="text-white font-semibold text-sm">{{ __('Setup in minuten') }}</span>
                </div>
                <div class="flex flex-col items-center gap-2">
                    <span class="text-2xl">&#127919;</span>
                    <span class="text-white font-semibold text-sm">{{ __('Alles-in-een platform') }}</span>
                </div>
                <div class="flex flex-col items-center gap-2">
                    <span class="text-2xl">&#128241;</span>
                    <span class="text-white font-semibold text-sm">{{ __('Werkt op elk apparaat') }}</span>
                </div>
                <div class="flex flex-col items-center gap-2">
                    <span class="text-2xl">&#128274;</span>
                    <span class="text-white font-semibold text-sm">{{ __('Veilig & betrouwbaar') }}</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Grid -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    {{ __('Alles voor uw toernooi') }}
                </h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    {{ __('Geen losse spreadsheets, geen papieren lijsten. Een compleet platform dat alles regelt.') }}
                </p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Feature 1: Poule-indeling -->
                <div class="bg-white rounded-2xl p-8 shadow-sm hover:shadow-lg transition-shadow border border-gray-100">
                    <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mb-5">
                        <span class="text-2xl">&#129355;</span>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">{{ __('Slimme Poule-indeling') }}</h3>
                    <p class="text-gray-600 leading-relaxed">
                        {{ __('Automatische indeling op basis van leeftijd, gewicht en band. Eerlijke poules met een druk op de knop. Handmatig aanpassen kan altijd.') }}
                    </p>
                </div>

                <!-- Feature 2: Digitale Weging -->
                <div class="bg-white rounded-2xl p-8 shadow-sm hover:shadow-lg transition-shadow border border-gray-100">
                    <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center mb-5">
                        <span class="text-2xl">&#9878;&#65039;</span>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">{{ __('Digitale Weging') }}</h3>
                    <p class="text-gray-600 leading-relaxed">
                        {{ __('Scan de QR-code, voer het gewicht in, klaar. Real-time overzicht van wie gewogen is en wie nog moet. Geen papieren weeglijsten meer.') }}
                    </p>
                </div>

                <!-- Feature 3: Wedstrijdschema's -->
                <div class="bg-white rounded-2xl p-8 shadow-sm hover:shadow-lg transition-shadow border border-gray-100">
                    <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center mb-5">
                        <span class="text-2xl">&#128203;</span>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">{{ __('Live Wedstrijdschema\'s') }}</h3>
                    <p class="text-gray-600 leading-relaxed">
                        {{ __('Overzichtelijke mat-interface met kleurcodes. Juryleden zien direct wie er aan de beurt is. Ouders kunnen live meekijken.') }}
                    </p>
                </div>

                <!-- Feature 4: Wimpelcompetitie -->
                <div class="bg-white rounded-2xl p-8 shadow-sm hover:shadow-lg transition-shadow border border-gray-100">
                    <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center mb-5">
                        <span class="text-2xl">&#127942;</span>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">{{ __('Wimpelcompetitie') }}</h3>
                    <p class="text-gray-600 leading-relaxed">
                        {{ __('Organiseer teamtoernooien en wimpelcompetities. Clubscores worden automatisch berekend. Perfect voor regionale competities.') }}
                    </p>
                </div>

                <!-- Feature 5: Club & Judoka Database -->
                <div class="bg-white rounded-2xl p-8 shadow-sm hover:shadow-lg transition-shadow border border-gray-100">
                    <div class="w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center mb-5">
                        <span class="text-2xl">&#128100;</span>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">{{ __('Club & Judoka Database') }}</h3>
                    <p class="text-gray-600 leading-relaxed">
                        {{ __('Uw clubs en judoka\'s worden bewaard tussen toernooien. Geen dubbel werk: importeer vanuit uw bestaande administratie of hergebruik eerdere inschrijvingen.') }}
                    </p>
                </div>

                <!-- Feature 6: Veiligheid & Backup -->
                <div class="bg-white rounded-2xl p-8 shadow-sm hover:shadow-lg transition-shadow border border-gray-100">
                    <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center mb-5">
                        <span class="text-2xl">&#128737;&#65039;</span>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">{{ __('Veiligheid & Backup') }}</h3>
                    <p class="text-gray-600 leading-relaxed">
                        {{ __('Automatische backups elke minuut tijdens de wedstrijddag. Noodpakket downloaden voor offline gebruik. Alles printen als backup.') }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Hoe werkt het? -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    {{ __('In 3 stappen klaar') }}
                </h2>
                <p class="text-lg text-gray-600">
                    {{ __('Geen technische kennis nodig. Als u kunt klikken, kunt u een toernooi organiseren.') }}
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-12">
                <div class="text-center">
                    <div class="w-16 h-16 bg-blue-800 text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-6">1</div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">{{ __('Maak een toernooi aan') }}</h3>
                    <p class="text-gray-600">
                        {{ __('Kies uw instellingen: gewichtsklassen, wedstrijdsysteem, aantal matten. Gebruik een template of begin vanaf nul.') }}
                    </p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 bg-blue-800 text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-6">2</div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">{{ __('Importeer deelnemers') }}</h3>
                    <p class="text-gray-600">
                        {{ __('Upload een Excel-bestand of laat coaches zelf inschrijven via het Coach Portaal. Judoka\'s worden automatisch ingedeeld.') }}
                    </p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 bg-blue-800 text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-6">3</div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">{{ __('Start de wedstrijddag') }}</h3>
                    <p class="text-gray-600">
                        {{ __('Weeg de judoka\'s, activeer de matten en laat het systeem de rest doen. Live uitslagen voor ouders en coaches.') }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Screenshot sectie -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    {{ __('Gebouwd voor de wedstrijddag') }}
                </h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    {{ __('Ontworpen door judoka\'s, voor judoka\'s. Elke functie is getest op echte toernooien.') }}
                </p>
            </div>

            <div class="grid md:grid-cols-2 gap-8">
                <!-- Screenshot: Poule-overzicht -->
                <div class="rounded-2xl overflow-hidden shadow-lg border border-gray-200">
                    <img src="/images/Poule-overzicht.png" alt="{{ __('Poule-overzicht') }}" class="w-full h-auto" loading="lazy">
                    <div class="bg-white px-4 py-3 text-center">
                        <p class="font-medium text-gray-700 text-sm">{{ __('Poule-overzicht met automatische indeling') }}</p>
                    </div>
                </div>
                <!-- Screenshot: Zaaloverzicht -->
                <div class="rounded-2xl overflow-hidden shadow-lg border border-gray-200">
                    <img src="/images/Zaaloverzicht.png" alt="{{ __('Zaaloverzicht') }}" class="w-full h-auto" loading="lazy">
                    <div class="bg-white px-4 py-3 text-center">
                        <p class="font-medium text-gray-700 text-sm">{{ __('Zaaloverzicht met matten en blokken') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Meer features -->
    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-10">
                <h2 class="text-2xl font-bold text-gray-900 mb-2">
                    {{ __('En nog veel meer...') }}
                </h2>
            </div>
            <div class="flex flex-wrap justify-center gap-3">
                @foreach([
                    __('Eliminatie-brackets'),
                    __('Coach Portaal'),
                    __('Digitale weegkaarten'),
                    __('Multi-mat ondersteuning'),
                    __('Installeerbaar als app (PWA)'),
                    __('Meertalig (NL/EN)'),
                    __('Coach Kaarten & QR'),
                    __('Zaaloverzicht'),
                    __('Blokplanning'),
                    __('Excel import/export'),
                    __('Automatische herclassificatie'),
                    __('Spreker-interface'),
                    __('Noodpakket (offline)'),
                    __('Poules printen'),
                    __('Medailles & prijzen'),
                ] as $feature)
                    <span class="bg-blue-50 text-blue-800 px-4 py-2 rounded-full text-sm font-medium border border-blue-100">
                        {{ $feature }}
                    </span>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Coach info -->
    <section class="py-12 bg-gray-50">
        <div class="max-w-3xl mx-auto px-4 text-center">
            <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                <span class="text-3xl mb-4 block">&#129358;</span>
                <h3 class="text-xl font-bold text-gray-900 mb-3">{{ __('Bent u coach?') }}</h3>
                <p class="text-gray-600">
                    {{ __('Heeft u een link ontvangen van een toernooi-organisator? Gebruik die link om uw judoka\'s in te schrijven via het Coach Portaal. Geen account nodig.') }}
                </p>
            </div>
        </div>
    </section>

    <!-- CTA onderaan -->
    <section class="py-20 bg-gradient-to-br from-blue-900 via-blue-800 to-blue-700 text-center">
        <div class="max-w-3xl mx-auto px-4">
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">
                {{ __('Klaar om uw toernooi te organiseren?') }}
            </h2>
            <p class="text-xl text-blue-200 mb-8">
                {{ __('Maak gratis een account aan en ontdek hoe makkelijk het kan.') }}
            </p>
            <a href="{{ route('register') }}"
               class="inline-block bg-white text-blue-800 px-10 py-4 rounded-lg font-semibold text-lg hover:bg-blue-50 transition shadow-lg">
                {{ __('Gratis Account Aanmaken') }}
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 py-8">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex flex-wrap justify-center items-center gap-x-3 gap-y-1 text-xs text-gray-400 mb-2">
                <a href="{{ route('legal.terms') }}" class="hover:text-white">{{ __('Voorwaarden') }}</a>
                <span class="text-gray-600">&bull;</span>
                <a href="{{ route('legal.privacy') }}" class="hover:text-white">{{ __('Privacy') }}</a>
                <span class="text-gray-600">&bull;</span>
                <a href="{{ route('legal.cookies') }}" class="hover:text-white">{{ __('Cookies') }}</a>
                <span class="text-gray-600">&bull;</span>
                <a href="mailto:havun22@gmail.com" class="hover:text-white">{{ __('Contact') }}</a>
            </div>
            <div class="text-center text-xs text-gray-500">
                &copy; {{ date('Y') }} Havun
                <span class="mx-1">&bull;</span>
                KvK 98516000
                <span class="mx-1">&bull;</span>
                BTW-vrij (KOR)
            </div>
        </div>
    </footer>
</body>
</html>
