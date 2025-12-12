<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>JudoToernooi - Professioneel Toernooi Management</title>
    <meta name="description" content="Organiseer uw judotoernooi professioneel met JudoToernooi. Van inschrijving tot eindstand, alles in een platform.">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-b from-blue-900 via-blue-800 to-blue-900 min-h-screen">
    <!-- Header -->
    <header class="absolute top-0 left-0 right-0 z-10">
        <div class="max-w-7xl mx-auto px-4 py-6 flex justify-between items-center">
            <div class="text-white font-bold text-xl">JudoToernooi</div>
            <a href="{{ route('organisator.login') }}" class="text-white hover:text-blue-200 transition">
                Inloggen
            </a>
        </div>
    </header>

    <!-- Hero Section -->
    <main class="flex flex-col items-center justify-center min-h-screen px-4 text-center">
        <!-- Logo -->
        <div class="mb-8">
            <div class="w-32 h-32 bg-white rounded-full flex items-center justify-center shadow-2xl mx-auto">
                <svg class="w-20 h-20 text-blue-800" viewBox="0 0 100 100" fill="currentColor">
                    <!-- Simplified judo icon -->
                    <circle cx="50" cy="25" r="12" />
                    <path d="M30 45 Q50 35 70 45 L65 85 Q50 90 35 85 Z" />
                    <path d="M25 50 L10 65 M75 50 L90 65" stroke="currentColor" stroke-width="6" fill="none" stroke-linecap="round"/>
                </svg>
            </div>
        </div>

        <!-- Title -->
        <h1 class="text-4xl md:text-6xl font-bold text-white mb-4">
            JudoToernooi
        </h1>
        <p class="text-xl md:text-2xl text-blue-200 mb-12 max-w-2xl">
            Professioneel toernooi management voor judoclubs. Van inschrijving tot eindstand, alles in een platform.
        </p>

        <!-- CTA Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 mb-16">
            <a href="{{ route('organisator.login') }}"
               class="bg-white text-blue-800 px-8 py-4 rounded-lg font-semibold text-lg hover:bg-blue-50 transition shadow-lg">
                Inloggen als Organisator
            </a>
            <a href="{{ route('organisator.register') }}"
               class="bg-transparent border-2 border-white text-white px-8 py-4 rounded-lg font-semibold text-lg hover:bg-white/10 transition">
                Account Aanmaken
            </a>
        </div>

        <!-- Features -->
        <div class="grid md:grid-cols-3 gap-8 max-w-5xl text-left">
            <div class="bg-white/10 backdrop-blur rounded-xl p-6">
                <div class="text-3xl mb-3">🥋</div>
                <h3 class="text-white font-semibold text-lg mb-2">Poule Indeling</h3>
                <p class="text-blue-200 text-sm">
                    Automatische indeling op basis van leeftijd, gewicht en band. Eerlijke poules met een druk op de knop.
                </p>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-xl p-6">
                <div class="text-3xl mb-3">⚖️</div>
                <h3 class="text-white font-semibold text-lg mb-2">Digitale Weging</h3>
                <p class="text-blue-200 text-sm">
                    QR-codes voor snelle weging. Real-time overzicht van wie gewogen is en wie nog moet.
                </p>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-xl p-6">
                <div class="text-3xl mb-3">📊</div>
                <h3 class="text-white font-semibold text-lg mb-2">Live Uitslagen</h3>
                <p class="text-blue-200 text-sm">
                    Mat-interface voor juryleden. Ouders kunnen live meekijken wanneer hun kind aan de beurt is.
                </p>
            </div>
        </div>

        <!-- Coach Info -->
        <div class="mt-16 text-center">
            <p class="text-blue-300 text-sm">
                Bent u coach en heeft u een link ontvangen? Gebruik de link uit de uitnodiging om uw judoka's in te schrijven.
            </p>
        </div>
    </main>

    <!-- Footer -->
    <footer class="absolute bottom-0 left-0 right-0 py-6">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p class="text-blue-300 text-sm">
                &copy; {{ date('Y') }} Havun - JudoToernooi Management Systeem
            </p>
        </div>
    </footer>
</body>
</html>
