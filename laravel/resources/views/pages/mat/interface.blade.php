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
    <title>Mat Interface - {{ $toernooi->naam }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { overscroll-behavior: none; }
        input[type="number"] { -moz-appearance: textfield; appearance: textfield; }
        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Standalone Header (device-bound PWA) -->
    <header class="bg-blue-800 text-white px-4 py-3 flex items-center justify-between shadow-lg sticky top-0 z-50">
        <div>
            <h1 class="text-lg font-bold">🥋 Mat Interface</h1>
            <p class="text-blue-200 text-sm">{{ $toernooi->naam }}</p>
        </div>
        <div class="text-2xl font-mono" id="clock"></div>
    </header>

    <main class="p-3">
        @include('pages.mat.partials._content')
    </main>

    @include('partials.pwa-mobile', ['pwaApp' => 'mat'])
</body>
</html>
