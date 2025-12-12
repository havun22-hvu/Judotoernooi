<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - JudoToernooi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-bold text-gray-800">JudoToernooi</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">{{ $organisator->naam }}</span>
                    <form action="{{ route('organisator.logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="text-gray-600 hover:text-gray-800">
                            Uitloggen
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            {{ session('success') }}
        </div>
        @endif

        <div class="mb-6 flex justify-between items-center">
            <h2 class="text-2xl font-bold text-gray-800">Mijn Toernooien</h2>
            <a href="{{ route('toernooi.create') }}"
               class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition-colors">
                Nieuw Toernooi
            </a>
        </div>

        @if($toernooien->isEmpty())
        <div class="bg-white rounded-lg shadow p-8 text-center">
            <p class="text-gray-600 mb-4">Je hebt nog geen toernooien aangemaakt.</p>
            <a href="{{ route('toernooi.create') }}"
               class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition-colors inline-block">
                Maak je eerste toernooi aan
            </a>
        </div>
        @else
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach($toernooien as $toernooi)
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-2">{{ $toernooi->naam }}</h3>
                <p class="text-gray-600 mb-4">
                    {{ $toernooi->datum ? $toernooi->datum->format('d-m-Y') : 'Geen datum' }}
                </p>
                <div class="flex items-center justify-between">
                    <span class="text-sm px-2 py-1 rounded
                        @if($toernooi->pivot->rol === 'eigenaar') bg-blue-100 text-blue-800
                        @else bg-gray-100 text-gray-800 @endif">
                        {{ ucfirst($toernooi->pivot->rol) }}
                    </span>
                    <a href="{{ route('toernooi.show', $toernooi) }}"
                       class="text-blue-600 hover:text-blue-800 font-medium">
                        Beheer
                    </a>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </main>
</body>
</html>
