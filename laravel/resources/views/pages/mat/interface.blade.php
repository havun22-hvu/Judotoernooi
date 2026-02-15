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
            <button onclick="document.getElementById('pwa-settings-modal').classList.remove('hidden')"
                    class="bg-white/20 hover:bg-white/30 text-white p-2 rounded-full"
                    title="{{ __('Instellingen') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
            </button>
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
