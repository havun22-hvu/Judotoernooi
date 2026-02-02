<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Er is iets misgegaan</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-lg p-8 max-w-md text-center">
        <div class="text-6xl mb-4">⚠️</div>
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Er is iets misgegaan</h1>

        <p class="text-gray-600 mb-6">
            {{ $message ?? 'De pagina kon niet worden geladen.' }}
        </p>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <p class="text-blue-800 font-medium">Wat kun je doen?</p>
            <ul class="text-blue-700 text-sm mt-2 text-left list-disc list-inside">
                <li>Probeer de pagina te vernieuwen</li>
                <li>Wacht een moment en probeer het opnieuw</li>
                <li>Vraag om een nieuwe link bij de jurytafel</li>
            </ul>
        </div>

        <button onclick="location.reload()" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-700 transition">
            🔄 Pagina vernieuwen
        </button>
    </div>
</body>
</html>
