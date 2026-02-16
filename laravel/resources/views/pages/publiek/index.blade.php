<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="📺 LIVE {{ $toernooi->naam }}">
    <title>📺 LIVE - {{ $toernooi->naam }}</title>
    <link rel="manifest" href="{{ route('publiek.manifest', $toernooi->routeParams()) }}">
    <link rel="apple-touch-icon" href="/icon-192x192.png">
    @vite(["resources/css/app.css", "resources/js/app.js"])
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        /* Touch-friendly star buttons */
        .favorite-star { cursor: pointer; transition: all 0.2s; min-width: 44px; min-height: 44px; display: flex; align-items: center; justify-content: center; }
        .favorite-star:hover { transform: scale(1.1); }
        .favorite-star.active { color: #f59e0b; }
        /* Belt colors */
        .band-wit { background: #d4d4d4; color: #404040; }
        .band-geel { background: #FACC15; color: #000; }
        .band-oranje { background: #c2410c; color: white; }
        .band-groen { background: #15803d; color: white; }
        .band-blauw { background: #1d4ed8; color: white; }
        .band-bruin { background: #8B4513; color: white; }
        .band-zwart { background: #0a0a0a; color: white; }
        /* Prevent zoom on input focus (iOS) */
        input[type="text"] { font-size: 16px; }
        /* Smooth scrolling */
        html { scroll-behavior: smooth; }
        /* Better touch targets */
        @media (max-width: 640px) {
            .touch-target { min-height: 48px; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col" x-data="publiekApp()" x-init="init()">
    <!-- Splash Screen -->
    <div id="splashScreen" class="fixed inset-0 z-[100] bg-blue-600 flex flex-col items-center justify-center transition-opacity duration-500">
        <img src="/icon-512x512.png" alt="Logo" class="w-32 h-32 mb-6 animate-pulse">
        <h1 class="text-white text-2xl font-bold text-center px-4">{{ $toernooi->naam }}</h1>
        <p class="text-blue-200 mt-2">{{ $toernooi->datum->format('d F Y') }}</p>
    </div>

    <!-- Header -->
    <header class="bg-blue-600 text-white shadow-lg sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-4 py-3">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-baseline gap-2 flex-wrap min-w-0">
                    <h1 class="text-xl font-bold truncate">{{ $toernooi->naam }}</h1>
                    <span class="text-blue-200 text-sm whitespace-nowrap">{{ $toernooi->datum->format('d M Y') }}</span>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <span class="bg-blue-500 px-2 py-1 rounded-full text-xs sm:text-sm whitespace-nowrap">
                        {{ $totaalJudokas }} <span class="hidden sm:inline">{{ __('deelnemers') }}</span>
                    </span>
                    @if($poulesGegenereerd)
                    <button @click="forceRefresh(); debugTapCount++"
                            @dblclick.prevent="debugMode = !debugMode"
                            class="px-2 py-1 rounded-full text-xs sm:text-sm font-bold transition-colors cursor-pointer"
                            :class="isConnected ? 'bg-green-500 hover:bg-green-600' : 'bg-orange-500 hover:bg-orange-600 animate-pulse'"
                            :title="isConnected ? 'WebSocket verbonden - klik om te verversen' : 'Polling modus - klik om te verversen'">
                        <span x-show="!isRefreshing" x-text="isConnected ? 'LIVE' : 'POLL'"></span>
                        <span x-show="isRefreshing" class="inline-block animate-spin">⟳</span>
                    </button>
                    @endif
                </div>
            </div>

        </div>

        <!-- Tabs -->
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex border-b border-blue-500 overflow-x-auto">
                <button @click="activeTab = 'info'"
                        :class="activeTab === 'info' ? 'bg-white text-blue-600' : 'text-blue-200 hover:text-white'"
                        class="px-3 sm:px-6 py-3 font-medium rounded-t-lg transition whitespace-nowrap text-sm sm:text-base">
                    Info
                </button>
                <button @click="activeTab = 'deelnemers'"
                        :class="activeTab === 'deelnemers' ? 'bg-white text-blue-600' : 'text-blue-200 hover:text-white'"
                        class="px-3 sm:px-6 py-3 font-medium rounded-t-lg transition whitespace-nowrap text-sm sm:text-base">
                    Deelnemers
                </button>
                <button @click="activeTab = 'favorieten'; loadFavorieten()"
                        :class="activeTab === 'favorieten' ? 'bg-white text-blue-600' : 'text-blue-200 hover:text-white'"
                        class="px-3 sm:px-6 py-3 font-medium rounded-t-lg transition relative whitespace-nowrap text-sm sm:text-base">
                    <span class="hidden sm:inline">Mijn </span>Favorieten
                    <span x-show="favorieten.length > 0"
                          class="absolute -top-1 -right-1 bg-yellow-400 text-yellow-900 text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center"
                          x-text="favorieten.length"></span>
                </button>
                @if($poulesGegenereerd)
                <button @click="activeTab = 'live'"
                        :class="activeTab === 'live' ? 'bg-white text-blue-600' : 'text-blue-200 hover:text-white'"
                        class="px-3 sm:px-6 py-3 font-medium rounded-t-lg transition whitespace-nowrap text-sm sm:text-base">
                    <span class="hidden sm:inline">{{ __('Live') }} </span>{{ __('Matten') }}
                </button>
                @endif
                @if(count($uitslagen) > 0)
                <button @click="activeTab = 'uitslagen'"
                        :class="activeTab === 'uitslagen' ? 'bg-white text-blue-600' : 'text-blue-200 hover:text-white'"
                        class="px-3 sm:px-6 py-3 font-medium rounded-t-lg transition whitespace-nowrap text-sm sm:text-base">
                    🏆 Uitslagen
                </button>
                @endif
            </div>
        </div>
    </header>

    <!-- Debug Panel (double-click on LIVE/POLL button to toggle) -->
    <div x-show="debugMode" x-cloak class="bg-gray-900 text-green-400 text-xs font-mono p-2 border-b border-gray-700">
        <div class="max-w-6xl mx-auto flex flex-wrap gap-4">
            <span>Reverb: <span :class="isConnected ? 'text-green-400' : 'text-red-400'" x-text="isConnected ? 'Connected' : 'Polling (15s)'"></span></span>
            <span>WS msgs: <span x-text="wsMessageCount"></span></span>
            <button @click="debugMode = false" class="text-gray-500 hover:text-white ml-auto">[x]</button>
        </div>
    </div>

    <main class="max-w-6xl mx-auto px-2 sm:px-4 py-4 sm:py-6 flex-grow">
        <!-- Info Tab -->
        <div x-show="activeTab === 'info'" x-cloak>
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <!-- Hero section with logo + judoschool -->
                    <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white p-8">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="bg-white/20 rounded-lg p-3">
                                <svg class="w-10 h-10 text-white" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14.5v-9l7 4.5-7 4.5z" opacity="0.3"/>
                                    <path d="M5.5 7.5c.83 0 1.5-.67 1.5-1.5S6.33 4.5 5.5 4.5 4 5.17 4 6s.67 1.5 1.5 1.5zm9 0c.83 0 1.5-.67 1.5-1.5s-.67-1.5-1.5-1.5-1.5.67-1.5 1.5.67 1.5 1.5 1.5zM5 9.5L2 12v4h2v4h3v-4h1l4-4-2-2-3 3V9.5zm14 0l-3 3.5v1l-4 4h1v4h3v-4h2v-4l-3-4.5z"/>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-3xl font-bold">{{ $toernooi->naam }}</h2>
                                @if($toernooi->organisator && $toernooi->organisator->organisatie_naam)
                                    <p class="text-blue-200 text-lg">{{ $toernooi->organisator->organisatie_naam }}</p>
                                @elseif($toernooi->organisatie)
                                    <p class="text-blue-200 text-lg">{{ $toernooi->organisatie }}</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="p-6">
                        <!-- Key info -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                            <div class="flex items-start gap-3">
                                <div class="bg-blue-100 p-3 rounded-lg">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-sm">Datum</p>
                                    <p class="font-medium text-gray-800">{{ $toernooi->datum->format('l d F Y') }}</p>
                                </div>
                            </div>

                            @if($toernooi->locatie)
                            <div class="flex items-start gap-3">
                                <div class="bg-green-100 p-3 rounded-lg">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-sm">Locatie</p>
                                    <p class="font-medium text-gray-800">{{ $toernooi->locatie }}</p>
                                </div>
                            </div>
                            @endif

                            <div class="flex items-start gap-3">
                                <div class="bg-yellow-100 p-3 rounded-lg">
                                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-sm">Deelnemers</p>
                                    <p class="font-medium text-gray-800">{{ $totaalJudokas }} aangemeld</p>
                                </div>
                            </div>
                        </div>

                        <!-- Blokken / Tijdschema -->
                        @if($blokken->count() > 0)
                        <div class="border-t pt-6">
                            <h3 class="text-lg font-bold text-gray-800 mb-4">Tijdschema</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-{{ min($blokken->count(), 4) }} gap-4">
                                @foreach($blokken as $blok)
                                <div class="bg-gray-50 rounded-lg p-4 border">
                                    <div class="font-bold text-blue-600 mb-2">{{ __('Blok') }} {{ $blok->nummer }}</div>
                                    @if($blok->weging_start && $blok->weging_einde)
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-600">Weging:</span>
                                        <span class="font-medium">{{ \Carbon\Carbon::parse($blok->weging_start)->format('H:i') }} - {{ \Carbon\Carbon::parse($blok->weging_einde)->format('H:i') }}</span>
                                    </div>
                                    @endif
                                    @if($blok->starttijd)
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Start wedstrijden:</span>
                                        <span class="font-medium text-green-600">{{ \Carbon\Carbon::parse($blok->starttijd)->format('H:i') }}</span>
                                    </div>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <!-- Deadline -->
                        @if($toernooi->inschrijving_deadline && $toernooi->inschrijving_deadline->isFuture())
                        <div class="border-t pt-6 mt-6">
                            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 flex items-center gap-3">
                                <svg class="w-6 h-6 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div>
                                    <p class="font-medium text-orange-800">Inschrijving sluit op {{ $toernooi->inschrijving_deadline->format('d F Y') }}</p>
                                    <p class="text-sm text-orange-600">Nog {{ floor(now()->floatDiffInDays($toernooi->inschrijving_deadline)) }} dagen om in te schrijven</p>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

            <!-- QR Code voor delen -->
            <div class="bg-white rounded-lg shadow-lg p-6 mt-6">
                <div class="flex flex-col sm:flex-row items-center gap-6">
                    <div class="flex-shrink-0">
                        {!! QrCode::size(160)->generate(url()->current()) !!}
                    </div>
                    <div class="text-center sm:text-left">
                        <h3 class="text-lg font-bold text-gray-800 mb-2">📱 Deel met anderen</h3>
                        <p class="text-gray-600 text-sm">Laat mensen om je heen deze QR code scannen zodat zij ook live kunnen meekijken. Ze krijgen dezelfde app op hun telefoon.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Matten Tab -->
        @if($poulesGegenereerd)
        <div x-show="activeTab === 'live'" x-cloak>
            <!-- Loading state -->
            <template x-if="liveMatten.length === 0 && liveLoading">
                <div class="text-center py-8">
                    <div class="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full mx-auto mb-4"></div>
                    <p class="text-gray-500">Laden...</p>
                </div>
            </template>

            <!-- No matten -->
            <template x-if="liveMatten.length === 0 && !liveLoading">
                <div class="text-center py-8 text-gray-500">
                    <div class="text-4xl mb-2">⏳</div>
                    <p>Geen actieve matten</p>
                </div>
            </template>

            <!-- Matten grid -->
            <div x-show="liveMatten.length > 0" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <template x-for="mat in liveMatten" :key="mat.id">
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <!-- Mat Header -->
                        <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-4 py-3">
                            <div class="flex justify-between items-center">
                                <span class="text-2xl font-bold" x-text="'{{ __('Mat') }} ' + mat.nummer"></span>
                            </div>
                            <div x-show="mat.poule_titel" class="text-blue-200 text-sm mt-1" x-text="mat.poule_titel"></div>
                        </div>

                        <!-- Groen: Speelt nu -->
                        <template x-if="mat.groen">
                            <div class="bg-green-100 border-l-4 border-green-500 px-4 py-3">
                                <div class="flex items-center gap-2 text-green-800 font-bold text-sm mb-2">
                                    <span class="text-lg">🥋</span> SPEELT NU
                                </div>
                                <div x-show="mat.groen.poule_titel !== mat.poule_titel" class="text-green-700 text-xs mb-2" x-text="mat.groen.poule_titel"></div>
                                <div class="flex items-center justify-between text-sm">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="w-4 h-4 rounded-full bg-white border-2 border-gray-400"></span>
                                            <span class="font-medium" x-text="mat.groen.wit?.naam || '?'"></span>
                                        </div>
                                        <div class="text-gray-500 text-xs ml-6" x-text="mat.groen.wit?.club || ''"></div>
                                    </div>
                                    <span class="text-gray-400 font-bold mx-3">vs</span>
                                    <div class="flex-1 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <span class="font-medium" x-text="mat.groen.blauw?.naam || '?'"></span>
                                            <span class="w-4 h-4 rounded-full bg-blue-500"></span>
                                        </div>
                                        <div class="text-gray-500 text-xs mr-6" x-text="mat.groen.blauw?.club || ''"></div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Geel: Klaar staan -->
                        <template x-if="mat.geel">
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 px-4 py-3">
                                <div class="flex items-center gap-2 text-yellow-800 font-bold text-sm mb-2">
                                    <span class="text-lg">⏳</span> KLAAR STAAN
                                </div>
                                <div x-show="mat.geel.poule_titel !== mat.poule_titel" class="text-yellow-700 text-xs mb-2" x-text="mat.geel.poule_titel"></div>
                                <div class="flex items-center justify-between text-sm">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="w-4 h-4 rounded-full bg-white border-2 border-gray-400"></span>
                                            <span class="font-medium" x-text="mat.geel.wit?.naam || '?'"></span>
                                        </div>
                                        <div class="text-gray-500 text-xs ml-6" x-text="mat.geel.wit?.club || ''"></div>
                                    </div>
                                    <span class="text-gray-400 font-bold mx-3">vs</span>
                                    <div class="flex-1 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <span class="font-medium" x-text="mat.geel.blauw?.naam || '?'"></span>
                                            <span class="w-4 h-4 rounded-full bg-blue-500"></span>
                                        </div>
                                        <div class="text-gray-500 text-xs mr-6" x-text="mat.geel.blauw?.club || ''"></div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Blauw: Gereed maken -->
                        <template x-if="mat.blauw">
                            <div class="bg-blue-50 border-l-4 border-blue-400 px-4 py-3">
                                <div class="flex items-center gap-2 text-blue-800 font-bold text-sm mb-2">
                                    <span class="text-lg">📋</span> GEREED MAKEN
                                </div>
                                <div x-show="mat.blauw.poule_titel !== mat.poule_titel" class="text-blue-700 text-xs mb-2" x-text="mat.blauw.poule_titel"></div>
                                <div class="flex items-center justify-between text-sm">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="w-4 h-4 rounded-full bg-white border-2 border-gray-400"></span>
                                            <span class="font-medium" x-text="mat.blauw.wit?.naam || '?'"></span>
                                        </div>
                                        <div class="text-gray-500 text-xs ml-6" x-text="mat.blauw.wit?.club || ''"></div>
                                    </div>
                                    <span class="text-gray-400 font-bold mx-3">vs</span>
                                    <div class="flex-1 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <span class="font-medium" x-text="mat.blauw.blauw?.naam || '?'"></span>
                                            <span class="w-4 h-4 rounded-full bg-blue-500"></span>
                                        </div>
                                        <div class="text-gray-500 text-xs mr-6" x-text="mat.blauw.blauw?.club || ''"></div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Geen wedstrijden -->
                        <template x-if="!mat.groen && !mat.geel && !mat.blauw">
                            <div class="p-8 text-center text-gray-500">
                                <div class="text-4xl mb-2">⏳</div>
                                <p>Wacht op volgende wedstrijd</p>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <div class="text-center mt-4">
                <button @click="refreshLive()"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium inline-flex items-center gap-2 active:scale-95 transition"
                        :disabled="liveLoading">
                    <svg x-show="!liveLoading" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <svg x-show="liveLoading" x-cloak class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-text="liveLoading ? 'Laden...' : 'Ververs'"></span>
                </button>
                <p class="text-gray-400 text-xs mt-2">Vernieuwd automatisch elke 15 seconden</p>
            </div>
        </div>
        @endif

        <!-- Deelnemers Tab -->
        <div x-show="activeTab === 'deelnemers'" x-cloak>
            <!-- Search bar - alleen in deelnemers tab -->
            <div class="mb-4 relative" @click.outside="if(zoekResultaten.length > 0 || heeftGezocht) { zoekResultaten = []; zoekterm = ''; heeftGezocht = false; }">
                <input type="text"
                       x-model="zoekterm"
                       @input.debounce.300ms="zoekJudokas()"
                       placeholder="🔍 Zoek judoka of club..."
                       class="w-full px-4 py-3 pr-10 rounded-lg border border-gray-300 text-gray-800 focus:ring-2 focus:ring-blue-300 focus:outline-none text-base">
                <!-- Clear button -->
                <button x-show="zoekterm.length > 0 && !zoekLoading"
                        @click="zoekterm = ''; zoekResultaten = []; heeftGezocht = false;"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 p-1"
                        type="button">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
                <!-- Loading spinner -->
                <div x-show="zoekLoading" class="absolute right-3 top-1/2 -translate-y-1/2">
                    <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <!-- Geen resultaten -->
                <div x-show="heeftGezocht && zoekResultaten.length === 0 && !zoekLoading && zoekterm.length >= 2" x-cloak
                     class="absolute top-full left-0 right-0 bg-white rounded-b-lg shadow-lg mt-1 p-4 text-center text-gray-500 z-50 border border-gray-200">
                    Geen judoka's gevonden voor "<span x-text="zoekterm"></span>"
                </div>
                <!-- Resultaten -->
                <div x-show="zoekResultaten.length > 0 && !zoekLoading" x-cloak
                     class="absolute top-full left-0 right-0 bg-white rounded-b-lg shadow-lg mt-1 max-h-64 overflow-y-auto z-50 border border-gray-200">
                    <template x-for="judoka in zoekResultaten" :key="judoka.id">
                        <div class="px-4 py-3 hover:bg-blue-50 border-b flex justify-between items-center">
                            <div>
                                <span class="font-medium text-gray-800" x-text="judoka.naam"></span>
                                <span class="text-gray-400 text-sm" x-text="'(' + (judoka.leeftijd || '-') + 'j, ' + (judoka.gewicht || '-') + 'kg)'"></span>
                                <span class="text-gray-500 text-sm" x-text="' - ' + (judoka.club || 'Geen club')"></span>
                                <span class="text-xs text-gray-400 block" x-text="judoka.leeftijdsklasse || '-'"></span>
                            </div>
                            <button @click="toggleFavoriet(judoka.id)"
                                    class="favorite-star text-2xl"
                                    :class="isFavoriet(judoka.id) ? 'active' : 'text-gray-300'">&#9733;</button>
                        </div>
                    </template>
                </div>
            </div>

            @forelse($categorien as $leeftijdsklasse => $gewichtsklassen)
            @php
                $leeftijdId = Str::slug($leeftijdsklasse);
                // Check of dit een dynamische categorie is (alleen "Alle" key)
                $isDynamisch = $gewichtsklassen->count() === 1 && $gewichtsklassen->has('Alle');
                $alleJudokas = $gewichtsklassen->flatten();
            @endphp
            <div class="mb-6" x-data="{ openGewicht: null }">
                <h2 class="text-xl font-bold text-gray-800 mb-3 flex items-center gap-2">
                    <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded">{{ $leeftijdsklasse }}</span>
                    <span class="text-gray-400 text-sm font-normal">{{ $alleJudokas->count() }} {{ __("judoka's") }}</span>
                </h2>

                @if($isDynamisch)
                {{-- Dynamische categorie: toon direct alle judoka's zonder knoppen --}}
                @php $dynamischJudokas = $gewichtsklassen->get('Alle', collect()); @endphp
                <div class="bg-white rounded-lg shadow overflow-hidden w-full sm:max-w-md">
                    <div class="divide-y">
                        @foreach($dynamischJudokas as $judoka)
                        @php
                            $judokaPoule = $judoka->poules->first();
                            $judokaBlok = $judokaPoule?->blok;
                            $judokaMat = $judokaPoule?->mat;
                            $gewicht = $judoka->gewicht_gewogen ?? $judoka->gewicht;
                        @endphp
                        <div class="px-4 py-3 flex justify-between items-center hover:bg-gray-50 active:bg-gray-100">
                            <div class="flex-1 min-w-0">
                                <span class="text-gray-800">{{ $judoka->naam }}</span>
                                <span class="text-gray-400">({{ $judoka->leeftijd }}j, {{ $gewicht ? $gewicht . 'kg' : '-' }})</span>
                                <span class="text-xs text-gray-500 block truncate">{{ $judoka->club?->naam }}</span>
                                @if($toernooi->voorbereiding_klaar_op && $judokaBlok)
                                <div class="text-xs text-blue-600 mt-1">
                                    <span class="font-medium">{{ __('Blok') }} {{ $judokaBlok->nummer }}</span>
                                    @if($judokaMat) | {{ __('Mat') }} {{ $judokaMat->nummer }} @endif
                                </div>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                @if($toernooi->weegkaarten_publiek && $judoka->qr_code)
                                <a href="{{ route('weegkaart.show', $judoka->qr_code) }}" target="_blank"
                                   class="text-blue-500 hover:text-blue-700" title="Weegkaart">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15A2.25 2.25 0 002.25 6.75v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z"/>
                                    </svg>
                                </a>
                                @endif
                                <button @click="toggleFavoriet({{ $judoka->id }})"
                                        class="favorite-star text-2xl"
                                        :class="isFavoriet({{ $judoka->id }}) ? 'active' : 'text-gray-300'">&#9733;</button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @else
                {{-- Vaste gewichtsklassen: knoppen per klasse --}}
                <div class="flex flex-wrap gap-2 mb-3">
                    @foreach($gewichtsklassen as $gewichtsklasse => $judokas)
                    @php $gewichtId = str_replace(['-', '+'], ['min', 'plus'], $gewichtsklasse); @endphp
                    <button @click="openGewicht = openGewicht === '{{ $gewichtId }}' ? null : '{{ $gewichtId }}'"
                            class="px-3 py-2.5 sm:py-2 rounded-lg shadow transition flex items-center gap-2 active:scale-95"
                            :class="openGewicht === '{{ $gewichtId }}' ? 'bg-blue-600 text-white' : 'bg-white hover:bg-gray-50 text-gray-700'">
                        <span class="font-medium text-sm sm:text-base">{{ $gewichtsklasse }}</span>
                        <span class="text-xs bg-opacity-20 px-1.5 py-0.5 rounded" :class="openGewicht === '{{ $gewichtId }}' ? 'text-blue-200 bg-blue-400' : 'text-gray-500 bg-gray-200'">{{ $judokas->count() }}</span>
                    </button>
                    @endforeach
                </div>

                {{-- Gewichtsklassen panels --}}
                @foreach($gewichtsklassen as $gewichtsklasse => $judokas)
                @php $gewichtId = str_replace(['-', '+'], ['min', 'plus'], $gewichtsklasse); @endphp
                <div x-show="openGewicht === '{{ $gewichtId }}'" x-collapse x-cloak
                     class="bg-white rounded-lg shadow overflow-hidden w-full sm:max-w-md mb-3">
                    <div class="bg-gray-50 px-4 py-2 border-b flex justify-between items-center">
                        <span class="font-medium text-gray-700">{{ $gewichtsklasse }} - {{ $judokas->count() }} {{ __("judoka's") }}</span>
                        <button @click="openGewicht = null" class="text-gray-400 hover:text-gray-600">&times;</button>
                    </div>
                    <div class="divide-y">
                        @foreach($judokas as $judoka)
                        @php
                            $judokaPoule = $judoka->poules->first();
                            $judokaBlok = $judokaPoule?->blok;
                            $judokaMat = $judokaPoule?->mat;
                            $gewicht = $judoka->gewicht_gewogen ?? $judoka->gewicht;
                        @endphp
                        <div class="px-4 py-3 flex justify-between items-center hover:bg-gray-50 active:bg-gray-100">
                            <div class="flex-1 min-w-0">
                                <span class="text-gray-800">{{ $judoka->naam }}</span>
                                <span class="text-gray-400">({{ $judoka->leeftijd }}j{{ $gewicht ? ', ' . $gewicht . 'kg' : '' }})</span>
                                <span class="text-xs text-gray-500 block truncate">{{ $judoka->club?->naam }}</span>
                                @if($toernooi->voorbereiding_klaar_op && $judokaBlok)
                                <div class="text-xs text-blue-600 mt-1">
                                    <span class="font-medium">{{ __('Blok') }} {{ $judokaBlok->nummer }}</span>
                                    @if($judokaMat) | {{ __('Mat') }} {{ $judokaMat->nummer }} @endif
                                </div>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                @if($toernooi->weegkaarten_publiek && $judoka->qr_code)
                                <a href="{{ route('weegkaart.show', $judoka->qr_code) }}" target="_blank"
                                   class="text-blue-500 hover:text-blue-700" title="Weegkaart">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15A2.25 2.25 0 002.25 6.75v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z"/>
                                    </svg>
                                </a>
                                @endif
                                <button @click="toggleFavoriet({{ $judoka->id }})"
                                        class="favorite-star text-2xl"
                                        :class="isFavoriet({{ $judoka->id }}) ? 'active' : 'text-gray-300'">&#9733;</button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
                @endif {{-- einde isDynamisch --}}
            </div>
            @empty
            <div class="text-center py-12 text-gray-500">
                <p class="text-xl">Nog geen deelnemers aangemeld</p>
                <p class="text-sm mt-2">De deelnemerslijst wordt bijgewerkt zodra judoka's zich aanmelden.</p>
            </div>
            @endforelse
        </div>

        <!-- Favorieten Tab -->
        <div x-show="activeTab === 'favorieten'" x-cloak>
            <!-- Notificatie banner -->
            <div x-show="favorieten.length > 0 && !notificatiesAan" class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-2xl">🔔</span>
                        <div>
                            <p class="font-medium text-blue-800 text-sm">Meldingen aanzetten?</p>
                            <p class="text-blue-600 text-xs">Krijg een melding als je favoriet moet klaar staan</p>
                        </div>
                    </div>
                    <button @click="vraagNotificatiePermissie()"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium">
                        Aanzetten
                    </button>
                </div>
            </div>
            <div x-show="favorieten.length > 0 && notificatiesAan" class="bg-green-50 border border-green-200 rounded-lg p-2 mb-4">
                <div class="flex items-center gap-2 text-green-700 text-sm">
                    <span>🔔</span>
                    <span>Meldingen staan aan - je krijgt een signaal als je favoriet moet klaar staan</span>
                </div>
            </div>

            <template x-if="favorieten.length === 0">
                <div class="text-center py-12 text-gray-500">
                    <p class="text-xl">Geen favorieten</p>
                    <p class="text-sm mt-2">Klik op de ster bij een judoka om deze als favoriet te markeren.</p>
                    <button @click="activeTab = 'deelnemers'" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Bekijk deelnemers
                    </button>
                </div>
            </template>

            <template x-if="favorieten.length > 0">
                <div>
                    <!-- Loading -->
                    <div x-show="loadingPoules" class="text-center py-8">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
                        <p class="text-gray-500 mt-4">Poules laden...</p>
                    </div>

                    <!-- No poules yet or empty result -->
                    <div x-show="!loadingPoules && favorietenPoules.length === 0" class="text-center py-12 text-gray-500">
                        <p class="text-xl" x-text="poulesGegenereerd ? 'Geen poules gevonden' : 'Poules nog niet beschikbaar'"></p>
                        <p class="text-sm mt-2" x-text="poulesGegenereerd ? 'De poules voor je favorieten worden geladen...' : 'De poule-indeling wordt gemaakt zodra het toernooi begint.'"></p>
                        <p class="text-sm mt-4">Je favorieten:</p>
                        <div class="flex flex-wrap justify-center gap-2 mt-2">
                            <template x-for="id in favorieten" :key="id">
                                <span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm flex items-center gap-1">
                                    <span x-text="getFavorietNaam(id)"></span>
                                    <button @click="toggleFavoriet(id)" class="text-yellow-600 hover:text-red-600">&times;</button>
                                </span>
                            </template>
                        </div>
                        <button @click="loadFavorieten()" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Opnieuw laden
                        </button>
                    </div>

                    <!-- Poules met tabs per favoriet -->
                    <div x-show="!loadingPoules && favorietenPoules.length > 0" x-data="{ activeFavoriet: null }" x-init="$watch('favorietenPoules', () => { if(favorietenPoules.length > 0 && !activeFavoriet) activeFavoriet = getFirstFavorietId() })">
                        <!-- Alert voor favoriet die volgende is -->
                        <template x-for="poule in favorietenPoules" :key="'alert-'+poule.id">
                            <template x-if="poule.judokas.some(j => j.is_favoriet && j.is_volgende)">
                                <div class="bg-yellow-400 text-yellow-900 px-4 py-2 rounded-lg mb-3 flex items-center gap-2 animate-pulse">
                                    <span class="text-xl">⚡</span>
                                    <span class="font-bold">Maak je klaar!</span>
                                    <span x-text="poule.judokas.find(j => j.is_favoriet && j.is_volgende)?.naam + ' is bijna aan de beurt'"></span>
                                </div>
                            </template>
                        </template>

                        <!-- Alert voor favoriet die nu aan de beurt is -->
                        <template x-for="poule in favorietenPoules" :key="'now-'+poule.id">
                            <template x-if="poule.judokas.some(j => j.is_favoriet && j.is_aan_de_beurt)">
                                <div class="bg-green-500 text-white px-4 py-2 rounded-lg mb-3 flex items-center gap-2">
                                    <span class="text-xl">🥋</span>
                                    <span class="font-bold">NU!</span>
                                    <span x-text="poule.judokas.find(j => j.is_favoriet && j.is_aan_de_beurt)?.naam + ' is aan het vechten!'"></span>
                                </div>
                            </template>
                        </template>

                        <!-- Tabs voor favorieten (max 10) -->
                        <div class="flex gap-1 mb-3 overflow-x-auto pb-2">
                            <template x-for="id in favorieten.slice(0, 10)" :key="id">
                                <button @click="activeFavoriet = id"
                                        class="px-3 py-2 rounded-t-lg text-sm font-medium whitespace-nowrap transition-colors relative"
                                        :class="activeFavoriet === id ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'">
                                    <span x-text="getFavorietNaam(id).split(' ')[0]"></span>
                                    <!-- Indicator voor volgende/bezig -->
                                    <template x-if="favorietenPoules.some(p => p.judokas.some(j => j.id === id && j.is_aan_de_beurt))">
                                        <span class="absolute -top-1 -right-1 w-3 h-3 bg-green-500 rounded-full animate-pulse"></span>
                                    </template>
                                    <template x-if="favorietenPoules.some(p => p.judokas.some(j => j.id === id && j.is_volgende && !j.is_aan_de_beurt))">
                                        <span class="absolute -top-1 -right-1 w-3 h-3 bg-yellow-400 rounded-full"></span>
                                    </template>
                                </button>
                            </template>
                            <button @click="loadFavorieten()" class="px-2 py-2 text-blue-600 hover:text-blue-800" title="Ververs">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                            </button>
                        </div>

                        <!-- Actieve poule -->
                        <template x-for="poule in favorietenPoules.filter(p => p.judokas.some(j => j.id === activeFavoriet))" :key="poule.id">
                            <div class="bg-white rounded-lg shadow overflow-hidden">
                                <div class="bg-blue-600 text-white px-4 py-2">
                                    <div class="flex justify-between items-center">
                                        <div class="text-sm">
                                            <span class="font-bold">P<span x-text="poule.nummer"></span></span>
                                            <span x-text="kortLeeftijd(poule.leeftijdsklasse) + (poule.gewichtsklasse && poule.gewichtsklasse !== 'onbekend' ? ' / ' + poule.gewichtsklasse : '')"></span>
                                            <span x-show="poule.mat" class="text-blue-200 ml-1">{{ __('Mat') }} <span x-text="poule.mat"></span></span>
                                        </div>
                                        <div x-show="poule.blok" class="text-blue-200 text-xs">
                                            {{ __('Blok') }} <span x-text="poule.blok"></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="divide-y">
                                    <template x-for="(judoka, index) in sortJudokas(poule.judokas)" :key="judoka.id">
                                        <div class="px-3 py-2 flex justify-between items-center"
                                             :class="{
                                                 'bg-green-100 border-l-4 border-green-500': judoka.id === activeFavoriet && judoka.is_aan_de_beurt,
                                                 'bg-yellow-100 border-l-4 border-yellow-400': judoka.id === activeFavoriet && judoka.is_volgende && !judoka.is_aan_de_beurt,
                                                 'bg-green-50 border-l-4 border-green-300': judoka.id === activeFavoriet && !judoka.is_volgende && !judoka.is_aan_de_beurt,
                                                 'bg-green-50': judoka.is_aan_de_beurt && judoka.id !== activeFavoriet,
                                                 'bg-yellow-50': judoka.is_volgende && !judoka.is_aan_de_beurt && judoka.id !== activeFavoriet
                                             }">
                                            <div class="flex items-center gap-2">
                                                <span class="w-6 h-6 rounded-full flex items-center justify-center text-sm font-bold"
                                                      :class="judoka.is_aan_de_beurt ? 'bg-green-500 text-white' : (judoka.is_volgende ? 'bg-yellow-400 text-yellow-900' : (index === 0 ? 'bg-yellow-400 text-yellow-900' : (index === 1 ? 'bg-gray-300' : (index === 2 ? 'bg-orange-300' : 'bg-gray-200'))))"
                                                      x-text="judoka.is_aan_de_beurt ? '🥋' : (judoka.is_volgende ? '⏳' : (judoka.eindpositie || (index + 1)))"></span>
                                                <div>
                                                    <span class="font-medium text-sm" :class="judoka.id === activeFavoriet ? 'text-green-800' : 'text-gray-800'" x-text="judoka.naam"></span>
                                                    <span class="text-xs text-gray-500 block" x-text="judoka.club"></span>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2 text-xs">
                                                <span x-show="judoka.is_aan_de_beurt" class="bg-green-500 text-white px-1.5 py-0.5 rounded font-bold">NU</span>
                                                <span x-show="judoka.is_volgende && !judoka.is_aan_de_beurt" class="bg-yellow-400 text-yellow-900 px-1.5 py-0.5 rounded font-bold">KLAAR</span>
                                                <span x-show="judoka.leeftijd" class="text-gray-400" x-text="judoka.leeftijd + 'j'"></span>
                                                <span class="text-gray-500" x-text="judoka.gewicht ? judoka.gewicht + 'kg' : ''"></span>
                                                <span x-show="judoka.band_kleur" class="w-3 h-3 rounded-full border border-gray-300" :style="'background-color: ' + judoka.band_kleur"></span>
                                                <span x-show="judoka.wp > 0" class="bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded font-medium" x-text="judoka.wp + 'WP ' + judoka.jp + 'JP'"></span>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <!-- Geen poule voor deze favoriet -->
                        <template x-if="activeFavoriet && !favorietenPoules.some(p => p.judokas.some(j => j.id === activeFavoriet))">
                            <div class="bg-gray-100 rounded-lg p-4 text-center text-gray-500 text-sm">
                                Nog geen poule voor deze judoka
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        <!-- Uitslagen Tab -->
        @if(count($uitslagen) > 0)
        <div x-show="activeTab === 'uitslagen'" x-cloak>
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800">🏆 Uitslagen</h2>
                <a href="{{ route('publiek.export-uitslagen', $toernooi->routeParams()) }}"
                   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2">
                    📥 Download CSV
                </a>
            </div>

            @foreach($uitslagen as $leeftijdsklasse => $poules)
            <div class="mb-6">
                <h3 class="text-lg font-bold text-gray-700 mb-3 flex items-center gap-2">
                    <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded">{{ $leeftijdsklasse }}</span>
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($poules as $poule)
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="{{ $poule->is_punten_competitie ? 'bg-teal-600' : 'bg-purple-600' }} text-white px-4 py-2">
                            @if($poule->gewichtsklasse && $poule->gewichtsklasse !== 'onbekend')
                            <span class="font-bold">{{ $poule->gewichtsklasse }}</span> -
                            @endif
                            <span class="{{ $poule->is_punten_competitie ? 'text-teal-200' : 'text-purple-200' }} text-sm">
                                {{ $poule->is_punten_competitie ? 'Puntencompetitie' : 'Poule' }} {{ $poule->nummer }}
                            </span>
                        </div>
                        <div class="divide-y">
                            @foreach($poule->standings as $index => $standing)
                            @php $plaats = $index + 1; @endphp
                            <div class="px-4 py-2 flex justify-between items-center
                                @if(!$poule->is_punten_competitie)
                                    @if($plaats === 1) bg-yellow-50
                                    @elseif($plaats === 2) bg-gray-50
                                    @elseif($plaats === 3) bg-orange-50
                                    @endif
                                @endif">
                                <div class="flex items-center gap-3">
                                    @if($poule->is_punten_competitie)
                                    <span class="w-8 h-8 rounded-full flex items-center justify-center bg-teal-100 text-teal-700 text-sm font-bold">
                                        {{ $standing['gewonnen'] }}
                                    </span>
                                    @else
                                    <span class="w-8 h-8 rounded-full flex items-center justify-center text-lg font-bold
                                        @if($plaats === 1) bg-yellow-400 text-yellow-900
                                        @elseif($plaats === 2) bg-gray-300 text-gray-800
                                        @elseif($plaats === 3) bg-orange-300 text-orange-900
                                        @else bg-gray-200 text-gray-600
                                        @endif">
                                        {{ $plaats }}
                                    </span>
                                    @endif
                                    <div>
                                        <span class="font-medium text-gray-800">{{ $standing['judoka']->naam }}</span>
                                        <span class="text-gray-500 text-xs block">{{ $standing['judoka']->club?->naam ?? '-' }}</span>
                                    </div>
                                </div>
                                <div class="text-right text-sm">
                                    @if($poule->is_punten_competitie)
                                    <span class="font-bold text-teal-600">{{ $standing['gewonnen'] }}</span>
                                    <span class="text-gray-400">gewonnen</span>
                                    @else
                                    <span class="font-bold text-blue-600">{{ $standing['wp'] }}</span>
                                    <span class="text-gray-400">WP</span>
                                    <span class="text-gray-600 ml-1">{{ $standing['jp'] }}</span>
                                    <span class="text-gray-400">JP</span>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-gray-400 text-center py-4 mt-auto shrink-0">
        <p>{{ $toernooi->naam }} - Publiek overzicht</p>
    </footer>

    <!-- PWA Install Banner -->
    <div id="installBanner" class="fixed bottom-0 left-0 right-0 bg-blue-600 text-white p-4 shadow-lg transform translate-y-full transition-transform duration-300 z-50" style="display: none;">
        <div class="max-w-xl mx-auto flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="bg-white rounded-lg p-2">
                    <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                    </svg>
                </div>
                <div>
                    <p class="font-bold text-sm sm:text-base">Installeer de app!</p>
                    <p class="text-blue-200 text-xs sm:text-sm">Snelle toegang op je startscherm</p>
                </div>
            </div>
            <div class="flex gap-2">
                <button id="installBtn" class="bg-white text-blue-600 px-4 py-2 rounded-lg font-bold text-sm hover:bg-blue-50 active:scale-95 transition">
                    Installeer
                </button>
                <button id="closeBanner" class="text-blue-200 hover:text-white p-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- iOS Install Instructions -->
    <div id="iosInstall" class="fixed bottom-0 left-0 right-0 bg-gray-800 text-white p-4 shadow-lg transform translate-y-full transition-transform duration-300 z-50" style="display: none;">
        <div class="max-w-xl mx-auto">
            <div class="flex justify-between items-start mb-3">
                <p class="font-bold">Installeer op iPhone/iPad</p>
                <button id="closeIos" class="text-gray-400 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="flex items-center gap-4 text-sm">
                <div class="flex items-center gap-2">
                    <span class="bg-gray-700 rounded px-2 py-1">1.</span>
                    <span>Tik op</span>
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M16 5l-1.42 1.42-1.59-1.59V16h-1.98V4.83L9.42 6.42 8 5l4-4 4 4zm4 5v11c0 1.1-.9 2-2 2H6c-1.11 0-2-.9-2-2V10c0-1.11.89-2 2-2h3v2H6v11h12V10h-3V8h3c1.1 0 2 .89 2 2z"/></svg>
                </div>
                <div class="flex items-center gap-2">
                    <span class="bg-gray-700 rounded px-2 py-1">2.</span>
                    <span>Zet op beginscherm</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        const STORAGE_KEY = 'judotoernooi_favorieten_{{ $toernooi->id }}';
        const NOTIFIED_KEY = 'judotoernooi_notified_{{ $toernooi->id }}';
        const poulesGegenereerd = {{ $poulesGegenereerd ? 'true' : 'false' }};

        // Judoka names cache for display
        const judokaNamen = @json($categorien->flatten(2)->pluck('naam', 'id'));

        function publiekApp() {
            return {
                activeTab: 'info',
                favorieten: [],
                favorietenPoules: [],
                loadingPoules: false,
                zoekterm: '',
                zoekResultaten: [],
                zoekLoading: false,
                heeftGezocht: false,
                poulesGegenereerd: poulesGegenereerd,
                liveLoading: false,
                liveMatten: [],
                notificatiesAan: false,
                notifiedState: {}, // Track welke notificaties al verstuurd zijn
                isConnected: false, // WebSocket verbinding status
                isRefreshing: false, // Bezig met forceren refresh
                wsMessageCount: 0, // Count WebSocket messages for debug
                pollTimer: null, // Polling fallback timer
                debugMode: false, // Toggle with double-click on LIVE button
                debugTapCount: 0, // For tracking taps

                init() {
                    // Load notification state
                    try {
                        const notified = localStorage.getItem(NOTIFIED_KEY);
                        if (notified) this.notifiedState = JSON.parse(notified);
                    } catch (e) {
                        this.notifiedState = {};
                    }

                    // Check notification permission
                    if ('Notification' in window) {
                        this.notificatiesAan = Notification.permission === 'granted';
                    }
                    // Load favorites from localStorage first
                    const stored = localStorage.getItem(STORAGE_KEY);
                    if (stored) {
                        try {
                            let loadedFavorieten = JSON.parse(stored);
                            // Filter: alleen judoka's die in dit toernooi bestaan
                            const geldigeFavorieten = loadedFavorieten.filter(id => judokaNamen[id] !== undefined);
                            // Als er ongeldige favorieten waren, update localStorage
                            if (geldigeFavorieten.length !== loadedFavorieten.length) {
                                localStorage.setItem(STORAGE_KEY, JSON.stringify(geldigeFavorieten));
                            }
                            this.favorieten = geldigeFavorieten;
                        } catch (e) {
                            this.favorieten = [];
                        }
                    }

                    // Restore active tab from sessionStorage (for refresh)
                    const savedTab = sessionStorage.getItem('publiek_active_tab_{{ $toernooi->id }}');
                    if (savedTab && ['info', 'deelnemers', 'favorieten', 'live', 'uitslagen'].includes(savedTab)) {
                        this.activeTab = savedTab;

                        // If restoring to favorieten tab, load the data
                        if (savedTab === 'favorieten' && this.favorieten.length > 0) {
                            this.loadFavorieten();
                        }
                    }

                    // Save active tab when it changes
                    this.$watch('activeTab', (tab) => {
                        sessionStorage.setItem('publiek_active_tab_{{ $toernooi->id }}', tab);
                    });

                    // Initial load of matten
                    if (poulesGegenereerd) {
                        this.loadMatten();
                    }

                    // Real-time updates via Reverb, polling fallback als Reverb uitvalt
                    this.setupRealtimeListeners();
                    this.startPollingFallback();
                },

                // Polling fallback: alleen actief als Reverb niet verbonden is
                startPollingFallback() {
                    if (!poulesGegenereerd) return;
                    setInterval(() => {
                        if (this.isConnected) return; // Reverb werkt, skip polling
                        if (this.activeTab === 'favorieten' && this.favorieten.length > 0) {
                            this.loadFavorieten();
                        }
                        if (this.activeTab === 'live') {
                            this.loadMatten();
                        }
                    }, 15000);
                },

                // Force refresh - herlaad alles en herconnect WebSocket
                async forceRefresh() {
                    this.isRefreshing = true;

                    // Reload all data
                    await Promise.all([
                        this.loadMatten(),
                        this.favorieten.length > 0 ? this.loadFavorieten() : Promise.resolve()
                    ]);

                    // Check connection by seeing if we got data
                    this.isConnected = this.liveMatten.length >= 0; // API werkt = verbonden

                    this.isRefreshing = false;
                },

                // Setup real-time mat update listeners
                setupRealtimeListeners() {
                    // Track connection status via custom events from mat-updates-listener
                    window.addEventListener('reverb-connected', () => {
                        console.log('Publiek: Reverb verbonden');
                        this.isConnected = true;
                    });

                    window.addEventListener('reverb-disconnected', () => {
                        console.log('Publiek: Reverb verbinding verbroken');
                        this.isConnected = false;
                    });

                    // Score update - reload matten + favorieten
                    window.addEventListener('mat-score-update', (e) => {
                        this.isConnected = true;
                        this.wsMessageCount++;
                        this.loadMatten();
                        if (this.favorieten.length > 0) this.loadFavorieten();
                    });

                    // Beurt update (groen/geel/blauw)
                    window.addEventListener('mat-beurt-update', (e) => {
                        this.isConnected = true;
                        this.wsMessageCount++;
                        this.loadMatten();
                        if (this.favorieten.length > 0) this.loadFavorieten();
                    });

                    // Poule klaar - reload everything
                    window.addEventListener('mat-poule-klaar', (e) => {
                        this.isConnected = true;
                        this.wsMessageCount++;
                        this.loadMatten();
                        if (this.favorieten.length > 0) this.loadFavorieten();
                    });

                    // Start met check - als Reverb geladen is zijn we verbonden
                    setTimeout(() => {
                        if (window.Pusher) {
                            this.isConnected = true;
                        }
                    }, 2000);
                },

                isFavoriet(id) {
                    return this.favorieten.includes(id);
                },

                toggleFavoriet(id) {
                    if (this.isFavoriet(id)) {
                        this.favorieten = this.favorieten.filter(f => f !== id);
                    } else {
                        this.favorieten.push(id);
                    }
                    localStorage.setItem(STORAGE_KEY, JSON.stringify(this.favorieten));

                    // Reload poules if on favorieten tab
                    if (this.activeTab === 'favorieten') {
                        this.loadFavorieten();
                    }
                },

                getFavorietNaam(id) {
                    return judokaNamen[id] || 'Judoka #' + id;
                },

                getFirstFavorietId() {
                    return this.favorieten.length > 0 ? this.favorieten[0] : null;
                },

                async loadFavorieten() {
                    if (this.favorieten.length === 0) {
                        this.favorietenPoules = [];
                        this.loadingPoules = false;
                        return;
                    }

                    this.loadingPoules = true;

                    try {
                        const response = await fetch('{{ route('publiek.favorieten', $toernooi->routeParams()) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ judoka_ids: this.favorieten }),
                        });

                        if (!response.ok) {
                            throw new Error('Response: ' + response.status);
                        }

                        const data = await response.json();
                        const oldPoules = this.favorietenPoules;
                        this.favorietenPoules = data.poules || [];

                        // Check for status changes and send notifications
                        this.checkAndNotify(oldPoules, this.favorietenPoules);
                    } catch (error) {
                        console.error('Error loading poules:', error);
                        this.favorietenPoules = [];
                    } finally {
                        this.loadingPoules = false;
                    }
                },

                // Vraag notificatie permissie
                async vraagNotificatiePermissie() {
                    if (!('Notification' in window)) {
                        alert('Je browser ondersteunt geen notificaties');
                        return;
                    }

                    const permission = await Notification.requestPermission();
                    this.notificatiesAan = permission === 'granted';

                    if (permission === 'granted') {
                        this.speelGeluid('klaar');
                        new Notification('Notificaties aan! 🔔', {
                            body: 'Je krijgt nu een melding als je favoriet moet klaar staan of aan de beurt is.',
                            icon: '/icon-192x192.png',
                            tag: 'test'
                        });
                    }
                },

                // Check status veranderingen en stuur notificaties
                checkAndNotify(oldPoules, newPoules) {
                    if (!this.notificatiesAan) return;

                    newPoules.forEach(poule => {
                        poule.judokas.forEach(judoka => {
                            if (!judoka.is_favoriet) return;

                            const key = `${judoka.id}`;
                            const oldState = this.notifiedState[key] || {};

                            // Check "aan de beurt" (groen) - hoogste prioriteit
                            if (judoka.is_aan_de_beurt && !oldState.aanDeBeurt) {
                                this.stuurNotificatie(judoka.naam, 'aanDeBeurt', poule.mat_label);
                                this.notifiedState[key] = { ...oldState, aanDeBeurt: true, volgende: true };
                            }
                            // Check "volgende" (geel) - klaar staan
                            else if (judoka.is_volgende && !judoka.is_aan_de_beurt && !oldState.volgende) {
                                this.stuurNotificatie(judoka.naam, 'volgende', poule.mat_label);
                                this.notifiedState[key] = { ...oldState, volgende: true };
                            }

                            // Reset state als wedstrijd gespeeld is (niet meer aan de beurt)
                            if (!judoka.is_aan_de_beurt && !judoka.is_volgende && oldState.aanDeBeurt) {
                                this.notifiedState[key] = {};
                            }
                        });
                    });

                    // Save state
                    localStorage.setItem(NOTIFIED_KEY, JSON.stringify(this.notifiedState));
                },

                // Stuur browser notificatie
                stuurNotificatie(naam, type, mat) {
                    if (!('Notification' in window) || Notification.permission !== 'granted') return;

                    let title, body, geluid;
                    if (type === 'aanDeBeurt') {
                        title = `🥋 ${naam} is aan de beurt!`;
                        body = `Nu op ${mat || 'de mat'}`;
                        geluid = 'aanDeBeurt';
                    } else {
                        title = `⏳ ${naam} moet klaar staan!`;
                        body = `Volgende wedstrijd op ${mat || 'de mat'}`;
                        geluid = 'klaar';
                    }

                    // Browser notificatie
                    try {
                        new Notification(title, {
                            body: body,
                            icon: '/icon-192x192.png',
                            tag: `${naam}-${type}`,
                            requireInteraction: true,
                            vibrate: [200, 100, 200]
                        });
                    } catch (e) {
                        console.log('Notification error:', e);
                    }

                    // Geluid + vibratie
                    this.speelGeluid(geluid);
                    this.vibreer(type);
                },

                // Speel notificatie geluid
                speelGeluid(type) {
                    try {
                        const ctx = new (window.AudioContext || window.webkitAudioContext)();
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.connect(gain);
                        gain.connect(ctx.destination);

                        if (type === 'aanDeBeurt') {
                            // Urgenter geluid - hoger en langer
                            osc.frequency.value = 880;
                            gain.gain.value = 0.3;
                            osc.start();
                            setTimeout(() => { osc.frequency.value = 1100; }, 150);
                            setTimeout(() => { osc.frequency.value = 880; }, 300);
                            setTimeout(() => { osc.stop(); ctx.close(); }, 500);
                        } else {
                            // Zachter geluid voor klaar staan
                            osc.frequency.value = 660;
                            gain.gain.value = 0.2;
                            osc.start();
                            setTimeout(() => { osc.frequency.value = 880; }, 200);
                            setTimeout(() => { osc.stop(); ctx.close(); }, 350);
                        }
                    } catch (e) {
                        console.log('Audio error:', e);
                    }
                },

                // Vibreer telefoon
                vibreer(type) {
                    if (!('vibrate' in navigator)) return;
                    if (type === 'aanDeBeurt') {
                        navigator.vibrate([200, 100, 200, 100, 300]);
                    } else {
                        navigator.vibrate([200, 100, 200]);
                    }
                },

                // Verkort leeftijdsklasse voor compacte weergave
                kortLeeftijd(lk) {
                    // Gebruik volledige label of maak automatisch korte versie
                    return lk.length <= 10 ? lk : lk.substring(0, 8) + '..';
                },

                // Sorteer judokas: groen (speelt) eerst, dan geel (klaar), dan op punten
                sortJudokas(judokas) {
                    return [...judokas].sort((a, b) => {
                        // Groen (speelt nu) altijd eerst
                        if (a.is_aan_de_beurt && !b.is_aan_de_beurt) return -1;
                        if (!a.is_aan_de_beurt && b.is_aan_de_beurt) return 1;
                        // Geel (klaar maken) daarna
                        if (a.is_volgende && !b.is_volgende) return -1;
                        if (!a.is_volgende && b.is_volgende) return 1;
                        // Dan op WP (hoog naar laag), JP als tiebreaker
                        const wpDiff = (b.wp || 0) - (a.wp || 0);
                        if (wpDiff !== 0) return wpDiff;
                        return (b.jp || 0) - (a.jp || 0);
                    });
                },

                async refreshLive() {
                    await this.loadMatten();
                },

                async loadMatten() {
                    this.liveLoading = true;
                    try {
                        const response = await fetch('{{ route('publiek.matten', $toernooi->routeParams()) }}');
                        if (response.ok) {
                            const data = await response.json();
                            this.liveMatten = data.matten || [];
                        }
                    } catch (e) {
                        console.error('Error loading matten:', e);
                    }
                    this.liveLoading = false;
                },

                async zoekJudokas() {
                    if (this.zoekterm.length < 2) {
                        this.zoekResultaten = [];
                        this.zoekLoading = false;
                        this.heeftGezocht = false;
                        return;
                    }

                    this.zoekLoading = true;
                    const url = `{{ route('publiek.zoeken', $toernooi->routeParams()) }}?q=${encodeURIComponent(this.zoekterm)}`;

                    try {
                        const response = await fetch(url);
                        if (!response.ok) {
                            throw new Error('Response: ' + response.status);
                        }
                        const data = await response.json();
                        this.zoekResultaten = data.judokas || [];
                        this.heeftGezocht = true;
                    } catch (error) {
                        console.error('Zoekfout:', error);
                        this.zoekResultaten = [];
                    } finally {
                        this.zoekLoading = false;
                    }
                },
            }
        }

        // PWA Install Logic
        let deferredPrompt;
        const installBanner = document.getElementById('installBanner');
        const iosInstall = document.getElementById('iosInstall');
        const installBtn = document.getElementById('installBtn');
        const closeBanner = document.getElementById('closeBanner');
        const closeIos = document.getElementById('closeIos');

        // Check if already installed or dismissed
        const isInstalled = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
        const isDismissed = localStorage.getItem('pwa_install_dismissed_{{ $toernooi->id }}');

        function showBanner(banner) {
            banner.style.display = 'block';
            setTimeout(() => banner.classList.remove('translate-y-full'), 100);
        }

        function hideBanner(banner) {
            banner.classList.add('translate-y-full');
            setTimeout(() => banner.style.display = 'none', 300);
        }

        // Android/Chrome: beforeinstallprompt
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;

            if (!isInstalled && !isDismissed) {
                setTimeout(() => showBanner(installBanner), 2000);
            }
        });

        // iOS detection
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
        if (isIOS && !isInstalled && !isDismissed) {
            setTimeout(() => showBanner(iosInstall), 2000);
        }

        // Install button click
        installBtn?.addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                deferredPrompt = null;
                hideBanner(installBanner);
            }
        });

        // Close buttons
        closeBanner?.addEventListener('click', () => {
            hideBanner(installBanner);
            localStorage.setItem('pwa_install_dismissed_{{ $toernooi->id }}', 'true');
        });

        closeIos?.addEventListener('click', () => {
            hideBanner(iosInstall);
            localStorage.setItem('pwa_install_dismissed_{{ $toernooi->id }}', 'true');
        });

        // Service Worker registration
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js');
        }

        // Hide splash screen after 2 seconds
        const splash = document.getElementById('splashScreen');
        if (splash) {
            setTimeout(() => {
                splash.style.opacity = '0';
                setTimeout(() => splash.remove(), 500);
            }, 2000);
        }

    </script>

    {{-- Real-time mat updates via Reverb --}}
    @if(config('broadcasting.default') === 'reverb')
        @include('partials.mat-updates-listener', ['toernooi' => $toernooi, 'matId' => null])
    @endif
</body>
</html>
