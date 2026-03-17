<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Check je inbox') }} - {{ __('JudoToernooi') }}</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-md text-center">
        <div class="mb-6">
            <div class="text-6xl mb-4">&#9993;</div>
            <h1 class="text-2xl font-bold text-gray-800">{{ __('Check je inbox') }}</h1>
        </div>

        <p class="text-gray-600 mb-2">
            @if($type === 'register')
                {{ __('We hebben een activatielink gestuurd naar') }}
            @else
                {{ __('We hebben een reset link gestuurd naar') }}
            @endif
        </p>

        <p class="text-blue-600 font-semibold mb-4">{{ $email }}</p>

        <p class="text-sm text-gray-500 mb-6">
            {{ __('De link is 15 minuten geldig en kan maar een keer gebruikt worden.') }}
        </p>

        <a href="mailto:" class="inline-block w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors mb-4">
            {{ __('Open e-mailapp') }}
        </a>

        <a href="{{ route('login') }}" class="text-sm text-blue-600 hover:text-blue-800">
            {{ __('Terug naar inloggen') }}
        </a>
    </div>
</body>
</html>
