<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instellingen - JudoToernooi</title>
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
                    <span class="bg-purple-100 text-purple-800 text-xs font-medium px-2 py-1 rounded">Sitebeheerder</span>
                    @endif
                    <form action="{{ route('logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="text-gray-600 hover:text-gray-800">Uitloggen</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('organisator.dashboard', $organisator) }}" class="text-blue-600 hover:text-blue-800">&larr; Terug naar dashboard</a>
        </div>

        @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            {{ session('success') }}
        </div>
        @endif

        <h1 class="text-2xl font-bold text-gray-900 mb-6">Instellingen</h1>

        <form action="{{ route('organisator.instellingen.update', $organisator) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Live Verversing</h2>
                <p class="text-gray-600 text-sm mb-4">
                    Deze instelling bepaalt hoe vaak de publieke apps (Publiek PWA, Spreker) automatisch verversen.
                    Dit geldt voor al je toernooien.
                </p>

                <div class="mb-4">
                    <label for="live_refresh_interval" class="block text-gray-700 font-medium mb-2">
                        Verversingsinterval
                    </label>
                    <select name="live_refresh_interval" id="live_refresh_interval" class="w-full md:w-64 border rounded px-3 py-2">
                        <option value="" {{ $organisator->live_refresh_interval === null ? 'selected' : '' }}>
                            Automatisch (adaptief)
                        </option>
                        <option value="5" {{ $organisator->live_refresh_interval == 5 ? 'selected' : '' }}>
                            5 seconden (snel, meer dataverkeer)
                        </option>
                        <option value="10" {{ $organisator->live_refresh_interval == 10 ? 'selected' : '' }}>
                            10 seconden
                        </option>
                        <option value="15" {{ $organisator->live_refresh_interval == 15 ? 'selected' : '' }}>
                            15 seconden (aanbevolen)
                        </option>
                        <option value="30" {{ $organisator->live_refresh_interval == 30 ? 'selected' : '' }}>
                            30 seconden
                        </option>
                        <option value="60" {{ $organisator->live_refresh_interval == 60 ? 'selected' : '' }}>
                            60 seconden (minder dataverkeer)
                        </option>
                    </select>
                    <p class="text-gray-500 text-sm mt-2">
                        <strong>Automatisch</strong> = snel bij activiteit, langzaam bij pauze.
                    </p>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-6 py-2 rounded">
                    Opslaan
                </button>
            </div>
        </form>
    </main>
</body>
</html>
