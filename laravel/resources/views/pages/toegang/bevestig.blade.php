<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>{{ __('Apparaat koppelen') }} — {{ $toegang->getLabel() }}</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-lg p-8 max-w-md text-center">
        <div class="text-6xl mb-4">📱</div>
        <h1 class="text-2xl font-bold text-gray-800 mb-2">{{ $toegang->getLabel() }}</h1>
        <p class="text-gray-600 mb-6">
            {{ __('Koppel dit apparaat om de interface te openen. Dit apparaat blijft daarna gekoppeld.') }}
        </p>

        <form method="POST"
              action="{{ route('toegang.koppel', ['organisator' => $organisatorSlug, 'toernooi' => $toernooiSlug, 'code' => $toegang->code]) }}">
            @csrf
            <button type="submit"
                    class="w-full bg-blue-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-700 transition">
                {{ __('Dit apparaat koppelen') }}
            </button>
        </form>

        <p class="text-gray-400 text-sm mt-4">
            {{ __('Open je deze link op het apparaat waarop je de interface wilt gebruiken?') }}
        </p>
    </div>
</body>
</html>
