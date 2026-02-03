<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#1e40af">
    <link rel="manifest" href="/manifest-spreker.json">
    <link rel="icon" type="image/png" sizes="192x192" href="/icon-192x192.png">
    <link rel="apple-touch-icon" href="/icon-192x192.png">
    <title>Spreker Interface - {{ $toernooi->naam }}</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <style>
        body { overscroll-behavior: none; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Standalone Header (device-bound PWA) -->
    <header class="bg-blue-800 text-white px-4 py-3 flex items-center justify-between shadow-lg sticky top-0 z-50">
        <div>
            <h1 class="text-lg font-bold">📢 Spreker Interface</h1>
            <p class="text-blue-200 text-sm">{{ $toernooi->naam }}</p>
        </div>
        <div class="text-2xl font-mono" id="clock"></div>
    </header>

    <main class="p-3">
        @include('pages.spreker.partials._content')
    </main>

    @include('partials.pwa-mobile', ['pwaApp' => 'spreker'])

    {{-- Chat Widget --}}
    @include('partials.chat-widget', [
        'chatType' => 'spreker',
        'chatId' => $toegang->id ?? null,
        'toernooiId' => $toernooi->id,
        'chatApiBase' => route('toernooi.chat.index', $toernooi->routeParams()),
    ])

    {{-- Real-time mat updates via Reverb --}}
    @if(config('broadcasting.default') === 'reverb')
        @include('partials.mat-updates-listener', ['toernooi' => $toernooi, 'matId' => null])
        <script>
            // Auto-refresh when poule is marked as complete
            window.addEventListener('mat-poule-klaar', function(e) {
                console.log('Spreker: Poule klaar ontvangen', e.detail);
                // Reload page to show new completed poule
                location.reload();
            });
        </script>
    @endif
</body>
</html>
