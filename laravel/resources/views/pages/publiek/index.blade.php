<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $toernooi->naam }} - Live</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .favorite-star { cursor: pointer; transition: all 0.2s; }
        .favorite-star:hover { transform: scale(1.2); }
        .favorite-star.active { color: #f59e0b; }
        .band-wit { background: #d4d4d4; color: #404040; }
        .band-geel { background: #ca8a04; color: white; }
        .band-oranje { background: #c2410c; color: white; }
        .band-groen { background: #15803d; color: white; }
        .band-blauw { background: #1d4ed8; color: white; }
        .band-bruin { background: #5c2d0e; color: white; }
        .band-zwart { background: #0a0a0a; color: white; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col" x-data="publiekApp()" x-init="init()">
    <!-- Header -->
    <header class="bg-blue-600 text-white shadow-lg sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-4 py-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold">{{ $toernooi->naam }}</h1>
                    <p class="text-blue-200">{{ $toernooi->datum->format('d F Y') }}</p>
                </div>
                <div class="flex items-center gap-4">
                    <span class="bg-blue-500 px-3 py-1 rounded-full text-sm">
                        {{ $totaalJudokas }} deelnemers
                    </span>
                    @if($poulesGegenereerd)
                    <span class="bg-green-500 px-3 py-1 rounded-full text-sm animate-pulse">
                        LIVE
                    </span>
                    @endif
                </div>
            </div>

            <!-- Search bar -->
            <div class="mt-4 relative">
                <input type="text"
                       x-model="zoekterm"
                       @input.debounce.300ms="zoekJudokas()"
                       placeholder="Zoek judoka of club..."
                       class="w-full px-4 py-2 rounded-lg text-gray-800 focus:ring-2 focus:ring-blue-300 focus:outline-none">
                <div x-show="zoekLoading" class="absolute right-3 top-1/2 -translate-y-1/2">
                    <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <!-- Geen resultaten -->
                <div x-show="heeftGezocht && zoekResultaten.length === 0 && !zoekLoading" x-cloak
                     class="absolute top-full left-0 right-0 bg-white rounded-b-lg shadow-lg mt-1 p-4 text-center text-gray-500 z-50">
                    Geen judoka's gevonden voor "<span x-text="zoekterm"></span>"
                </div>
                <!-- Resultaten -->
                <div x-show="zoekResultaten.length > 0 && !zoekLoading" x-cloak
                     class="absolute top-full left-0 right-0 bg-white rounded-b-lg shadow-lg mt-1 max-h-64 overflow-y-auto z-50">
                    <template x-for="judoka in zoekResultaten" :key="judoka.id">
                        <div @click="toggleFavoriet(judoka.id); zoekterm = ''; zoekResultaten = []"
                             class="px-4 py-3 hover:bg-blue-50 cursor-pointer border-b flex justify-between items-center">
                            <div>
                                <span class="font-medium text-gray-800" x-text="judoka.naam"></span>
                                <span class="text-gray-400">(</span><span class="w-3 h-3 inline-block rounded-full" :class="'band-' + judoka.band"></span><span class="text-gray-400">)</span>
                                <span class="text-gray-500 text-sm" x-text="' - ' + (judoka.club || 'Geen club')"></span>
                                <span class="text-xs text-gray-400 block" x-text="judoka.leeftijdsklasse + ' / ' + judoka.gewichtsklasse + ' kg'"></span>
                            </div>
                            <span class="favorite-star text-2xl" :class="isFavoriet(judoka.id) ? 'active' : 'text-gray-300'">&#9733;</span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex border-b border-blue-500 overflow-x-auto">
                <button @click="activeTab = 'info'"
                        :class="activeTab === 'info' ? 'bg-white text-blue-600' : 'text-blue-200 hover:text-white'"
                        class="px-4 md:px-6 py-3 font-medium rounded-t-lg transition whitespace-nowrap">
                    Info
                </button>
                <button @click="activeTab = 'deelnemers'"
                        :class="activeTab === 'deelnemers' ? 'bg-white text-blue-600' : 'text-blue-200 hover:text-white'"
                        class="px-4 md:px-6 py-3 font-medium rounded-t-lg transition whitespace-nowrap">
                    Deelnemers
                </button>
                <button @click="activeTab = 'favorieten'; loadFavorieten()"
                        :class="activeTab === 'favorieten' ? 'bg-white text-blue-600' : 'text-blue-200 hover:text-white'"
                        class="px-4 md:px-6 py-3 font-medium rounded-t-lg transition relative whitespace-nowrap">
                    Mijn Favorieten
                    <span x-show="favorieten.length > 0"
                          class="absolute -top-1 -right-1 bg-yellow-400 text-yellow-900 text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center"
                          x-text="favorieten.length"></span>
                </button>
                @if($poulesGegenereerd)
                <button @click="activeTab = 'live'"
                        :class="activeTab === 'live' ? 'bg-white text-blue-600' : 'text-blue-200 hover:text-white'"
                        class="px-4 md:px-6 py-3 font-medium rounded-t-lg transition whitespace-nowrap">
                    Live Matten
                </button>
                @endif
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-6 flex-grow">
        <!-- Info Tab -->
        <div x-show="activeTab === 'info'" x-cloak>
            @php
                $paginaBlokken = $toernooi->pagina_content['blokken'] ?? [];
                $heeftCustomContent = !empty($paginaBlokken);
            @endphp

            @if($heeftCustomContent)
                {{-- Custom Pagina Builder Content --}}
                <div class="space-y-6">
                    @foreach(collect($paginaBlokken)->sortBy('order') as $blok)
                        @switch($blok['type'])
                            @case('header')
                                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                                    <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white p-8">
                                        @if(!empty($blok['data']['logo']))
                                            <img src="{{ asset('storage/' . $blok['data']['logo']) }}" alt="Logo" class="h-24 mb-4 object-contain">
                                        @endif
                                        @if(!empty($blok['data']['titel']))
                                            <h2 class="text-3xl font-bold mb-2">{{ $blok['data']['titel'] }}</h2>
                                        @endif
                                        @if(!empty($blok['data']['subtitel']))
                                            <p class="text-blue-200 text-lg">{{ $blok['data']['subtitel'] }}</p>
                                        @endif
                                    </div>
                                </div>
                                @break

                            @case('tekst')
                                <div class="bg-white rounded-lg shadow-lg p-6">
                                    <div class="prose max-w-none">
                                        {!! $blok['data']['html'] ?? '' !!}
                                    </div>
                                </div>
                                @break

                            @case('afbeelding')
                                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                                    @if(!empty($blok['data']['src']))
                                        <img src="{{ asset('storage/' . $blok['data']['src']) }}" alt="{{ $blok['data']['alt'] ?? '' }}" class="w-full object-cover max-h-96">
                                    @endif
                                    @if(!empty($blok['data']['caption']))
                                        <div class="p-4 text-center text-gray-600 text-sm">{{ $blok['data']['caption'] }}</div>
                                    @endif
                                </div>
                                @break

                            @case('sponsors')
                                @if(!empty($blok['data']['sponsors']))
                                    <div class="bg-white rounded-lg shadow-lg p-6">
                                        <h3 class="text-lg font-bold text-gray-800 mb-4 text-center">Sponsors</h3>
                                        <div class="flex flex-wrap justify-center items-center gap-8">
                                            @foreach($blok['data']['sponsors'] as $sponsor)
                                                @if(!empty($sponsor['logo']))
                                                    @if(!empty($sponsor['url']))
                                                        <a href="{{ $sponsor['url'] }}" target="_blank" rel="noopener" class="hover:opacity-80 transition">
                                                            <img src="{{ asset('storage/' . $sponsor['logo']) }}" alt="{{ $sponsor['naam'] ?? 'Sponsor' }}" class="h-16 object-contain">
                                                        </a>
                                                    @else
                                                        <img src="{{ asset('storage/' . $sponsor['logo']) }}" alt="{{ $sponsor['naam'] ?? 'Sponsor' }}" class="h-16 object-contain">
                                                    @endif
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                @break

                            @case('video')
                                @if(!empty($blok['data']['url']))
                                    @php
                                        $videoUrl = $blok['data']['url'];
                                        $embedUrl = '';
                                        if (preg_match('/youtube\.com\/watch\?v=([^&]+)/', $videoUrl, $matches)) {
                                            $embedUrl = 'https://www.youtube.com/embed/' . $matches[1];
                                        } elseif (preg_match('/youtu\.be\/([^?]+)/', $videoUrl, $matches)) {
                                            $embedUrl = 'https://www.youtube.com/embed/' . $matches[1];
                                        } elseif (preg_match('/vimeo\.com\/(\d+)/', $videoUrl, $matches)) {
                                            $embedUrl = 'https://player.vimeo.com/video/' . $matches[1];
                                        }
                                    @endphp
                                    @if($embedUrl)
                                        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                                            @if(!empty($blok['data']['titel']))
                                                <div class="p-4 border-b">
                                                    <h3 class="font-bold text-gray-800">{{ $blok['data']['titel'] }}</h3>
                                                </div>
                                            @endif
                                            <div class="aspect-video">
                                                <iframe src="{{ $embedUrl }}" class="w-full h-full" frameborder="0" allowfullscreen></iframe>
                                            </div>
                                        </div>
                                    @endif
                                @endif
                                @break

                            @case('info_kaart')
                                {{-- Auto-filled tournament info --}}
                                <div class="bg-white rounded-lg shadow-lg p-6">
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
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
                                    @if($blokken->count() > 0)
                                    <div class="border-t pt-6">
                                        <h3 class="text-lg font-bold text-gray-800 mb-4">Tijdschema</h3>
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-{{ min($blokken->count(), 4) }} gap-4">
                                            @foreach($blokken as $tijdblok)
                                            <div class="bg-gray-50 rounded-lg p-4 border">
                                                <div class="font-bold text-blue-600 mb-2">Blok {{ $tijdblok->nummer }}</div>
                                                @if($tijdblok->weging_start && $tijdblok->weging_einde)
                                                <div class="flex justify-between text-sm mb-1">
                                                    <span class="text-gray-600">Weging:</span>
                                                    <span class="font-medium">{{ \Carbon\Carbon::parse($tijdblok->weging_start)->format('H:i') }} - {{ \Carbon\Carbon::parse($tijdblok->weging_einde)->format('H:i') }}</span>
                                                </div>
                                                @endif
                                                @if($tijdblok->starttijd)
                                                <div class="flex justify-between text-sm">
                                                    <span class="text-gray-600">Start wedstrijden:</span>
                                                    <span class="font-medium text-green-600">{{ \Carbon\Carbon::parse($tijdblok->starttijd)->format('H:i') }}</span>
                                                </div>
                                                @endif
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif
                                </div>
                                @break
                        @endswitch
                    @endforeach
                </div>
            @else
                {{-- Default Info Content (fallback) --}}
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <!-- Hero section -->
                    <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white p-8">
                        <h2 class="text-3xl font-bold mb-2">{{ $toernooi->naam }}</h2>
                        @if($toernooi->organisatie)
                        <p class="text-blue-200">Georganiseerd door {{ $toernooi->organisatie }}</p>
                        @endif
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
                                    <div class="font-bold text-blue-600 mb-2">Blok {{ $blok->nummer }}</div>
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
                                    <p class="text-sm text-orange-600">Nog {{ $toernooi->inschrijving_deadline->diffInDays(now()) }} dagen om in te schrijven</p>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- Live Matten Tab -->
        @if($poulesGegenereerd && count($matten) > 0)
        <div x-show="activeTab === 'live'" x-cloak>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($matten as $mat)
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-4 py-3">
                        <div class="flex justify-between items-center">
                            <span class="text-2xl font-bold">Mat {{ $mat->nummer }}</span>
                            @if($mat->huidigePoule)
                            <span class="bg-green-500 px-2 py-1 rounded text-xs animate-pulse">BEZIG</span>
                            @else
                            <span class="bg-gray-500 px-2 py-1 rounded text-xs">WACHT</span>
                            @endif
                        </div>
                    </div>
                    @if($mat->huidigePoule)
                    <div class="p-4">
                        <div class="font-medium text-gray-800 mb-2">
                            #{{ $mat->huidigePoule->nummer }} {{ $mat->huidigePoule->leeftijdsklasse }} / {{ $mat->huidigePoule->gewichtsklasse }} kg
                        </div>
                        <div class="space-y-1">
                            @foreach($mat->huidigePoule->judokas->take(6) as $judoka)
                            <div class="flex items-center gap-1 text-sm text-gray-600">
                                <span>{{ $judoka->naam }}</span>
                                <span class="text-gray-400">(</span><span class="w-2.5 h-2.5 inline-block rounded-full band-{{ $judoka->band }}"></span><span class="text-gray-400">)</span>
                            </div>
                            @endforeach
                            @if($mat->huidigePoule->judokas->count() > 6)
                            <div class="text-xs text-gray-400">+{{ $mat->huidigePoule->judokas->count() - 6 }} meer</div>
                            @endif
                        </div>
                    </div>
                    @else
                    <div class="p-4 text-center text-gray-500">
                        <p>Wacht op volgende poule</p>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>

            <p class="text-center text-gray-500 text-sm mt-4">
                Pagina wordt automatisch ververst
            </p>
        </div>
        @endif

        <!-- Deelnemers Tab -->
        <div x-show="activeTab === 'deelnemers'" x-cloak>
            @forelse($categorien as $leeftijdsklasse => $gewichtsklassen)
            <div class="mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-3 flex items-center gap-2">
                    <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded">{{ $leeftijdsklasse }}</span>
                    <span class="text-gray-400 text-sm font-normal">{{ $gewichtsklassen->flatten()->count() }} judoka's</span>
                </h2>

                <div class="flex flex-wrap gap-2">
                    @foreach($gewichtsklassen as $gewichtsklasse => $judokas)
                    <div class="bg-white rounded-lg shadow overflow-hidden" x-data="{ open: false }">
                        <button @click="open = !open"
                                class="px-3 py-2 flex items-center gap-2 hover:bg-gray-50 transition">
                            <span class="font-medium text-gray-700">{{ $gewichtsklasse }} kg</span>
                            <span class="text-xs text-gray-500">{{ $judokas->count() }}</span>
                            <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div x-show="open" x-collapse class="border-t">
                            @foreach($judokas as $judoka)
                            <div class="px-4 py-2 flex justify-between items-center hover:bg-gray-50 border-b last:border-b-0">
                                <div>
                                    <span class="text-gray-800">{{ $judoka->naam }}</span>
                                    <span class="text-gray-400">(</span><span class="w-3 h-3 inline-block rounded-full band-{{ $judoka->band }}"></span><span class="text-gray-400">)</span>
                                    <span class="text-xs text-gray-500 block">{{ $judoka->club?->naam }}</span>
                                </div>
                                <button @click="toggleFavoriet({{ $judoka->id }})"
                                        class="favorite-star text-xl"
                                        :class="isFavoriet({{ $judoka->id }}) ? 'active' : 'text-gray-300'">
                                    &#9733;
                                </button>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
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

                    <!-- No poules yet -->
                    <template x-if="!loadingPoules && favorietenPoules.length === 0 && !poulesGegenereerd">
                        <div class="text-center py-12 text-gray-500">
                            <p class="text-xl">Poules nog niet beschikbaar</p>
                            <p class="text-sm mt-2">De poule-indeling wordt gemaakt zodra het toernooi begint.</p>
                            <p class="text-sm mt-4">Je favorieten:</p>
                            <div class="flex flex-wrap justify-center gap-2 mt-2">
                                <template x-for="id in favorieten" :key="id">
                                    <span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm flex items-center gap-1">
                                        <span x-text="getFavorietNaam(id)"></span>
                                        <button @click="toggleFavoriet(id)" class="text-yellow-600 hover:text-red-600">&times;</button>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Poules -->
                    <div x-show="!loadingPoules && favorietenPoules.length > 0" class="space-y-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-bold text-gray-800">Poules van mijn favorieten</h2>
                            <button @click="loadFavorieten()" class="text-blue-600 hover:text-blue-800 text-sm flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Ververs
                            </button>
                        </div>

                        <template x-for="poule in favorietenPoules" :key="poule.id">
                            <div class="bg-white rounded-lg shadow overflow-hidden">
                                <div class="bg-blue-600 text-white px-4 py-3">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <span class="font-bold">#<span x-text="poule.nummer"></span></span>
                                            <span x-text="poule.leeftijdsklasse + ' / ' + poule.gewichtsklasse + ' kg'"></span>
                                        </div>
                                        <div class="text-blue-200 text-sm">
                                            <span x-show="poule.mat">Mat <span x-text="poule.mat"></span></span>
                                            <span x-show="poule.blok"> - Blok <span x-text="poule.blok"></span></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="divide-y">
                                    <template x-for="(judoka, index) in poule.judokas" :key="judoka.id">
                                        <div class="px-4 py-3 flex justify-between items-center"
                                             :class="{
                                                 'bg-yellow-50 border-l-4 border-yellow-400': judoka.is_favoriet,
                                                 'opacity-50 line-through': judoka.is_doorgestreept
                                             }">
                                            <div class="flex items-center gap-3">
                                                <span class="w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center text-sm font-medium"
                                                      x-text="judoka.eindpositie || (index + 1)"></span>
                                                <div>
                                                    <span class="font-medium" :class="judoka.is_favoriet ? 'text-yellow-800' : 'text-gray-800'" x-text="judoka.naam"></span>
                                                    <span class="text-sm text-gray-500 block" x-text="judoka.club"></span>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <span class="text-sm text-gray-600" x-text="judoka.gewicht + ' kg'"></span>
                                                <span class="w-4 h-4 rounded-full" :class="'band-' + judoka.band"></span>
                                                <span x-show="judoka.punten > 0" class="bg-green-100 text-green-800 px-2 py-0.5 rounded text-sm font-medium" x-text="judoka.punten + ' pt'"></span>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-gray-400 text-center py-4 mt-auto shrink-0">
        <p>{{ $toernooi->naam }} - Publiek overzicht</p>
    </footer>

    <script>
        const STORAGE_KEY = 'judotoernooi_favorieten_{{ $toernooi->id }}';
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

                init() {
                    // Load favorites from localStorage
                    const stored = localStorage.getItem(STORAGE_KEY);
                    if (stored) {
                        try {
                            this.favorieten = JSON.parse(stored);
                        } catch (e) {
                            this.favorieten = [];
                        }
                    }

                    // Auto-refresh page every 60 seconds if on live tab
                    if (poulesGegenereerd) {
                        setInterval(() => {
                            if (this.activeTab === 'live') {
                                window.location.reload();
                            }
                            if (this.activeTab === 'favorieten' && this.favorieten.length > 0) {
                                this.loadFavorieten();
                            }
                        }, 60000);
                    }
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

                async loadFavorieten() {
                    if (this.favorieten.length === 0) {
                        this.favorietenPoules = [];
                        return;
                    }

                    this.loadingPoules = true;

                    try {
                        const response = await fetch('/publiek/{{ $toernooi->slug }}/favorieten', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({ judoka_ids: this.favorieten }),
                        });

                        const data = await response.json();
                        this.favorietenPoules = data.poules || [];
                    } catch (error) {
                        console.error('Error loading poules:', error);
                    }

                    this.loadingPoules = false;
                },

                async zoekJudokas() {
                    if (this.zoekterm.length < 2) {
                        this.zoekResultaten = [];
                        this.zoekLoading = false;
                        this.heeftGezocht = false;
                        return;
                    }

                    this.zoekLoading = true;
                    const url = `/publiek/{{ $toernooi->slug }}/zoeken?q=${encodeURIComponent(this.zoekterm)}`;

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
    </script>
</body>
</html>
