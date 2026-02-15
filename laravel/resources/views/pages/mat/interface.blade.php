<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#1e40af">
    <link rel="manifest" href="/manifest-mat.json">
    <link rel="icon" type="image/png" sizes="192x192" href="/icon-192x192.png">
    <link rel="apple-touch-icon" href="/icon-192x192.png">
    <title>{{ __('Mat Interface') }} - {{ $toernooi->naam }}</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
    <style>
        body { overscroll-behavior: none; }
        input[type="number"] { -moz-appearance: textfield; appearance: textfield; }
        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

        .sortable-bracket-ghost { opacity: 0.4; background: #dbeafe !important; }
        .sortable-bracket-chosen { opacity: 0.3; }
        .sortable-drop-highlight { outline: 3px solid #a855f7; outline-offset: -1px; background: #f3e8ff !important; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Standalone Header (device-bound PWA) -->
    <header class="bg-blue-800 text-white px-4 py-3 flex items-center justify-between shadow-lg sticky top-0 z-50">
        <div>
            <h1 class="text-lg font-bold">🥋 Mat {{ $matNummer ?? '' }} Interface</h1>
            <p class="text-blue-200 text-sm">{{ $toernooi->naam }}</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="text-2xl font-mono" id="clock"></div>
            <div x-data="{ menuOpen: false, showHelp: false }" class="relative">
                <button @click="menuOpen = !menuOpen" class="bg-white/20 hover:bg-white/30 text-white p-2 rounded-full">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                {{-- Dropdown menu --}}
                <div x-show="menuOpen" @click.away="menuOpen = false" x-transition
                     class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl py-1 z-50 text-sm">
                    <button type="button" @click="menuOpen = false; document.getElementById('mat-interface') && Alpine.$data(document.getElementById('mat-interface')).refreshAll()"
                            class="w-full text-left px-4 py-2.5 text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        {{ __('Ververs gegevens') }}
                    </button>
                    <hr class="my-1">
                    <button type="button" @click="menuOpen = false; showHelp = true"
                            class="w-full text-left px-4 py-2.5 text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ __('Help') }}
                    </button>
                    <button type="button" @click="menuOpen = false; document.getElementById('pwa-settings-modal').classList.remove('hidden')"
                            class="w-full text-left px-4 py-2.5 text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ __('Over') }}
                    </button>
                </div>

                {{-- Help modal --}}
                <div x-show="showHelp" x-cloak @click.self="showHelp = false"
                     class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
                    <div class="bg-white rounded-lg shadow-xl w-full max-w-md overflow-hidden">
                        <div class="bg-blue-800 text-white px-5 py-3">
                            <h2 class="text-lg font-bold">{{ __('Help - Mat Interface') }}</h2>
                        </div>
                        <div class="px-5 py-4 space-y-3 text-sm text-gray-700 max-h-[60vh] overflow-y-auto">
                            <div>
                                <h3 class="font-bold text-gray-900 mb-1">{{ __('Beurtaanduiding') }}</h3>
                                <p><strong>{{ __('Dubbelklik') }}</strong> {{ __('op een wedstrijd om de beurt in te stellen:') }}</p>
                                <ul class="ml-4 mt-1 space-y-0.5 list-disc">
                                    <li><span class="inline-block w-3 h-3 rounded bg-green-500"></span> {{ __('Actief — wordt nu gevochten') }}</li>
                                    <li><span class="inline-block w-3 h-3 rounded bg-yellow-400"></span> {{ __('Staat klaar — volgende wedstrijd') }}</li>
                                    <li><span class="inline-block w-3 h-3 rounded bg-blue-400"></span> {{ __('Gereed maken — voorbereiding') }}</li>
                                </ul>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 mb-1">{{ __('Winnaar doorschuiven') }}</h3>
                                <p>{{ __('Sleep een judoka naar het volgende ronde-slot. Dit bevestigt de winnaar en schuift door.') }}</p>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 mb-1">{{ __('Seeding') }}</h3>
                                <p>{{ __('Zolang er geen wedstrijd gespeeld is kun je judokas naar andere slots slepen om de indeling aan te passen.') }}</p>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 mb-1">{{ __('Navigatie') }}</h3>
                                <p>{{ __('Gebruik de pijltjes boven de bracket om door de rondes te navigeren als het schema breder is dan het scherm.') }}</p>
                            </div>
                        </div>
                        <div class="px-5 py-3 bg-gray-50 text-right">
                            <button @click="showHelp = false" class="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">{{ __('Sluiten') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="p-3 max-w-5xl mx-auto">
        @include('pages.mat.partials._content')
    </main>

    <!-- Pusher for Reverb WebSocket -->
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>

    <!-- SortableJS for drag & drop in bracket -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

    @include('partials.mat-updates-listener', [
        'toernooi' => $toernooi,
        'matId' => $matNummer ?? null
    ])

    @include('partials.pwa-mobile', ['pwaApp' => 'mat'])

    {{-- Chat Widget --}}
    @include('partials.chat-widget', [
        'chatType' => 'mat',
        'chatId' => $matNummer ?? null,
        'toernooiId' => $toernooi->id,
        'chatApiBase' => route('toernooi.chat.index', $toernooi->routeParams()),
    ])
</body>
</html>
