<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Instellingen') }} - JudoToernooi</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="{{ route('organisator.dashboard', $organisator) }}" class="text-xl font-bold text-gray-800">JudoToernooi</a>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">{{ $organisator->naam }}</span>
                    @if($organisator->isSitebeheerder())
                    <span class="bg-purple-100 text-purple-800 text-xs font-medium px-2 py-1 rounded">{{ __('Sitebeheerder') }}</span>
                    @endif
                    <form action="{{ route('logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="text-gray-600 hover:text-gray-800">{{ __('Uitloggen') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('organisator.dashboard', $organisator) }}" class="text-blue-600 hover:text-blue-800">&larr; {{ __('Terug naar dashboard') }}</a>
        </div>

        @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            {{ session('success') }}
        </div>
        @endif

        <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ __('Instellingen') }}</h1>

        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">{{ __('Live Verversing') }}</h2>
            <p class="text-gray-600 text-sm mb-4">
                {{ __('De publieke apps (Publiek PWA, Spreker) worden realtime bijgewerkt via de Chat Server (Reverb).') }}
                {{ __('Zorg dat de Chat Server actief is bij de toernooi-instellingen.') }}
            </p>
            <div class="flex items-center gap-2 text-sm text-green-700 bg-green-50 rounded p-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <span>{{ __('Realtime updates via WebSocket — geen polling meer nodig.') }}</span>
            </div>
        </div>
    </main>
</body>
</html>
