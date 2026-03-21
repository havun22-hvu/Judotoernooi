<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Betaling Succesvol') }} - {{ $club->naam }}</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-xl mx-auto py-8 px-4">
        <div class="bg-white rounded-lg shadow p-8 text-center">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>

            <h1 class="text-2xl font-bold text-gray-800 mb-2">{{ __('Betaling Succesvol!') }}</h1>
            <p class="text-gray-600 mb-6">
                {{ __('De inschrijving voor :club is definitief.', ['club' => $club->naam]) }}
            </p>

            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <p class="text-green-800">
                    {{ __('Je judoka\'s zijn nu ingeschreven voor :toernooi.', ['toernooi' => $toernooi->naam]) }}
                </p>
            </div>

            <a href="{{ route('coach.portal.judokas', ['organisator' => $organisator, 'toernooi' => $toernooiSlug, 'code' => $code]) }}"
               class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-lg">
                {{ __('Terug naar overzicht') }}
            </a>
        </div>
    </div>
</body>
</html>
