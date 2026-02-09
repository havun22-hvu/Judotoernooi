<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>JudoToernooi - {{ __('Professioneel Toernooi Management') }}</title>
    <meta name="description" content="{{ __('Organiseer uw judotoernooi professioneel met JudoToernooi. Van inschrijving tot eindstand, alles in een platform.') }}">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    @vite(["resources/css/app.css", "resources/js/app.js"])
</head>
<body class="bg-gradient-to-b from-blue-900 via-blue-800 to-blue-900 min-h-screen">
    <!-- Header -->
    <header class="absolute top-0 left-0 right-0 z-10">
        <div class="max-w-7xl mx-auto px-4 py-6 flex justify-between items-center">
            <div class="text-white font-bold text-xl">JudoToernooi</div>
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
    <main class="flex flex-col items-center justify-center min-h-screen px-4 text-center">
        <!-- Logo -->
        <div class="mb-8">
            <img src="/icon-512x512.png" alt="JudoToernooi Logo" class="w-32 h-32 rounded-full shadow-2xl mx-auto">
        </div>

        <!-- Title -->
        <h1 class="text-4xl md:text-6xl font-bold text-white mb-4">
            JudoToernooi
        </h1>
        <p class="text-xl md:text-2xl text-blue-200 mb-12 max-w-2xl">
            {{ __('Professioneel toernooi management voor judoclubs. Van inschrijving tot eindstand, alles in een platform.') }}
        </p>

        <!-- CTA Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 mb-16">
            <a href="{{ route('login') }}"
               class="bg-white text-blue-800 px-8 py-4 rounded-lg font-semibold text-lg hover:bg-blue-50 transition shadow-lg">
                {{ __('Inloggen als Organisator') }}
            </a>
            <a href="{{ route('register') }}"
               class="bg-transparent border-2 border-white text-white px-8 py-4 rounded-lg font-semibold text-lg hover:bg-white/10 transition">
                {{ __('Account Aanmaken') }}
            </a>
        </div>

        <!-- Features -->
        <div class="grid md:grid-cols-3 gap-8 max-w-5xl text-left">
            <div class="bg-white/10 backdrop-blur rounded-xl p-6">
                <div class="text-3xl mb-3">🥋</div>
                <h3 class="text-white font-semibold text-lg mb-2">{{ __('Poule Indeling') }}</h3>
                <p class="text-blue-200 text-sm">
                    {{ __('Automatische indeling op basis van leeftijd, gewicht en band. Eerlijke poules met een druk op de knop.') }}
                </p>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-xl p-6">
                <div class="text-3xl mb-3">⚖️</div>
                <h3 class="text-white font-semibold text-lg mb-2">{{ __('Digitale Weging') }}</h3>
                <p class="text-blue-200 text-sm">
                    {{ __('QR-codes voor snelle weging. Real-time overzicht van wie gewogen is en wie nog moet.') }}
                </p>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-xl p-6">
                <div class="text-3xl mb-3">📊</div>
                <h3 class="text-white font-semibold text-lg mb-2">{{ __('Live Uitslagen') }}</h3>
                <p class="text-blue-200 text-sm">
                    {{ __('Mat-interface voor juryleden. Ouders kunnen live meekijken wanneer hun kind aan de beurt is.') }}
                </p>
            </div>
        </div>

        <!-- Coach Info -->
        <div class="mt-16 text-center">
            <p class="text-blue-300 text-sm">
                {{ __('Bent u coach en heeft u een link ontvangen? Gebruik de link uit de uitnodiging om uw judoka\'s in te schrijven.') }}
            </p>
        </div>
    </main>

    <!-- Footer -->
    <footer class="absolute bottom-0 left-0 right-0 py-4">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex flex-wrap justify-center items-center gap-x-3 gap-y-1 text-xs text-blue-300 mb-1">
                <a href="{{ route('legal.terms') }}" class="hover:text-white">Voorwaarden</a>
                <span class="text-blue-500">•</span>
                <a href="{{ route('legal.privacy') }}" class="hover:text-white">Privacy</a>
                <span class="text-blue-500">•</span>
                <a href="{{ route('legal.cookies') }}" class="hover:text-white">Cookies</a>
                <span class="text-blue-500">•</span>
                <a href="{{ route('legal.disclaimer') }}#contact" class="hover:text-white">Contact</a>
            </div>
            <div class="text-center text-xs text-blue-400">
                &copy; {{ date('Y') }} Havun
                <span class="mx-1">•</span>
                KvK 98516000
                <span class="mx-1">•</span>
                BTW-vrij (KOR)
            </div>
        </div>
    </footer>
</body>
</html>
