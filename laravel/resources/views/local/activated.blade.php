<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lokale Server Actief</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-green-50 min-h-screen flex items-center justify-center">
    <div class="container mx-auto px-4 max-w-lg text-center">
        <!-- Success Icon -->
        <div class="mb-8">
            <div class="w-24 h-24 bg-green-500 rounded-full mx-auto flex items-center justify-center">
                <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
        </div>

        <!-- Title -->
        <h1 class="text-4xl font-bold text-green-800 mb-4">Lokale Server Actief!</h1>

        <!-- IP Address -->
        <div class="bg-white rounded-2xl shadow-lg p-8 mb-8">
            <p class="text-gray-600 mb-2">Apparaten kunnen verbinden via:</p>
            <div class="text-3xl font-mono font-bold text-blue-600 bg-blue-50 rounded-xl p-4">
                http://{{ $ip }}:8000
            </div>
        </div>

        <!-- Instructions -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8 text-left">
            <h2 class="font-bold text-lg mb-4">Volgende stappen:</h2>
            <ol class="space-y-3">
                <li class="flex items-start gap-3">
                    <span class="bg-blue-500 text-white rounded-full w-6 h-6 flex items-center justify-center flex-shrink-0 text-sm font-bold">1</span>
                    <span>Open de Deco app op je telefoon</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="bg-blue-500 text-white rounded-full w-6 h-6 flex items-center justify-center flex-shrink-0 text-sm font-bold">2</span>
                    <span>Ga naar "Meer" → "Wi-Fi instellingen"</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="bg-blue-500 text-white rounded-full w-6 h-6 flex items-center justify-center flex-shrink-0 text-sm font-bold">3</span>
                    <span>Wijzig het IP-adres naar: <strong>{{ $ip }}</strong></span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="bg-blue-500 text-white rounded-full w-6 h-6 flex items-center justify-center flex-shrink-0 text-sm font-bold">4</span>
                    <span>Herstart de tablets/telefoons</span>
                </li>
            </ol>
        </div>

        <!-- Warning -->
        <div class="bg-yellow-100 border-l-4 border-yellow-500 p-4 rounded-r-xl text-left mb-8">
            <p class="text-yellow-800">
                <strong>Let op:</strong> Wijzigingen worden lokaal opgeslagen en automatisch gesynchroniseerd naar de cloud zodra er internet is.
            </p>
        </div>

        <!-- Actions -->
        <div class="space-y-4">
            <a href="{{ route('local.simple') }}"
               class="block w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-6 rounded-xl">
                Terug naar Synchronisatie
            </a>
            <a href="{{ route('local.dashboard') }}"
               class="block w-full bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-4 px-6 rounded-xl">
                Naar Dashboard
            </a>
        </div>
    </div>
</body>
</html>
