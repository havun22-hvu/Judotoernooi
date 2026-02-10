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
        <div class="text-2xl font-mono" id="clock"></div>
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
