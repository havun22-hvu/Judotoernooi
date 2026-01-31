<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NOOD OVERSCHAKELING - JudoToernooi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-red-900 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-lg w-full text-center">
        <!-- Warning Icon -->
        <div class="text-8xl mb-6">⚠️</div>

        <h1 class="text-4xl font-bold text-white mb-4">NOOD OVERSCHAKELING</h1>

        <p class="text-red-200 text-lg mb-8">
            Gebruik dit alleen als de Primary server is gecrasht en niet meer reageert.
        </p>

        <!-- Current Status -->
        <div class="bg-red-800 rounded-lg p-4 mb-8 text-left">
            <div class="text-red-300 text-sm mb-2">Huidige situatie:</div>
            <div class="text-white">
                <div class="text-2xl font-bold mb-2">{{ config('local-server.device_name') ?: gethostname() }}</div>
                <strong>Huidige rol:</strong> {{ strtoupper(config('local-server.role')) ?? 'Niet geconfigureerd' }}<br>
                <strong>Huidige IP:</strong> <span class="font-mono">{{ config('local-server.ip') ?? 'Niet ingesteld' }}</span>
            </div>
        </div>

        <!-- Big Red Button -->
        <form method="POST" action="{{ route('local.emergency-failover.execute') }}" id="failoverForm">
            @csrf
            <button type="submit"
                    onclick="return confirm('WEET JE ZEKER dat de Primary server niet meer werkt?\n\nDeze actie maakt DEZE laptop de nieuwe Primary server.')"
                    class="w-full py-8 bg-red-600 hover:bg-red-500 text-white text-2xl font-bold rounded-xl shadow-lg transform hover:scale-105 transition-all border-4 border-red-400">
                🔴 ACTIVEER ALS PRIMARY
            </button>
        </form>

        <!-- Instructions -->
        <div class="mt-8 bg-red-800 rounded-lg p-6 text-left">
            <h2 class="text-white font-bold mb-3">Na het klikken:</h2>
            <ol class="text-red-200 space-y-2 list-decimal list-inside">
                <li>Deze laptop wordt Primary server</li>
                <li>Open de <strong>Deco app</strong> op je telefoon</li>
                <li>Ga naar <strong>Apparaten</strong> → zoek: <strong class="text-white text-lg">{{ config('local-server.device_name') ?: gethostname() }}</strong></li>
                <li>Tik op het apparaat → <strong>IP Reserveren</strong></li>
                <li>Wijzig IP naar: <strong class="text-white text-lg font-mono">{{ config('local-server.primary_ip') }}</strong></li>
                <li>Sla op en wacht 10 seconden</li>
                <li>Tablets werken automatisch weer!</li>
            </ol>
        </div>

        <!-- Visual Guide -->
        <div class="mt-4 bg-red-800 rounded-lg p-4 text-left">
            <div class="text-red-300 text-sm mb-2">Samenvatting:</div>
            <div class="text-white font-mono text-center text-lg">
                {{ config('local-server.device_name') ?: gethostname() }} → IP {{ config('local-server.primary_ip') }}
            </div>
        </div>

        <!-- Back Button -->
        <a href="{{ route('local.dashboard') }}"
           class="inline-block mt-6 px-6 py-3 bg-gray-700 text-white rounded-lg hover:bg-gray-600">
            ← Terug naar Dashboard
        </a>
    </div>
</body>
</html>
