<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Afrekenen - {{ $club->naam }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-2xl mx-auto py-8 px-4">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Afrekenen</h1>
                    <p class="text-gray-600">{{ $club->naam }} - {{ $toernooi->naam }}</p>
                </div>
                <a href="{{ route('coach.portal.judokas', ['organisator' => $organisator, 'toernooi' => $toernooiSlug, 'code' => $code]) }}"
                   class="text-gray-600 hover:text-gray-800">
                    &larr; Terug
                </a>
            </div>
        </div>

        @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-6">
            {{ session('error') }}
        </div>
        @endif

        <!-- Already paid -->
        @if($reedsBetaald->count() > 0)
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <h3 class="font-medium text-green-800 mb-2">Al betaald ({{ $reedsBetaald->count() }} judoka's)</h3>
            <ul class="text-sm text-green-700 space-y-1">
                @foreach($reedsBetaald as $judoka)
                <li>{{ $judoka->naam }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <!-- Ready for payment -->
        @if($klaarVoorBetaling->count() > 0)
        <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h2 class="text-xl font-bold text-gray-800">Te betalen ({{ $klaarVoorBetaling->count() }} judoka's)</h2>
            </div>
            <div class="divide-y">
                @foreach($klaarVoorBetaling as $judoka)
                <div class="p-4 flex justify-between items-center">
                    <div>
                        <p class="font-medium text-gray-800">{{ $judoka->naam }}</p>
                        <p class="text-sm text-gray-600">
                            {{ $judoka->leeftijdsklasse ?? '' }}
                            @if($judoka->gewichtsklasse && $judoka->gewichtsklasse !== 'Variabel')
                            - {{ $judoka->gewichtsklasse }} kg
                            @elseif($judoka->gewicht)
                            - {{ $judoka->gewicht }} kg
                            @endif
                        </p>
                    </div>
                    <span class="text-gray-600">&euro;{{ number_format($inschrijfgeld, 2, ',', '.') }}</span>
                </div>
                @endforeach
            </div>
            <div class="px-6 py-4 bg-gray-50 border-t">
                <div class="flex justify-between items-center text-lg font-bold">
                    <span>Totaal:</span>
                    <span>&euro;{{ number_format($totaalBedrag, 2, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <!-- Payment button -->
        <form action="{{ route('coach.portal.betalen', ['organisator' => $organisator, 'toernooi' => $toernooiSlug, 'code' => $code]) }}" method="POST">
            @csrf
            <button type="submit"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-4 px-6 rounded-lg flex items-center justify-center gap-3 text-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                </svg>
                Betaal met iDEAL
            </button>
        </form>

        <p class="text-center text-sm text-gray-500 mt-4">
            Je wordt doorgestuurd naar iDEAL om de betaling te voltooien.
        </p>
        @else
        <div class="bg-white rounded-lg shadow p-8 text-center">
            <p class="text-gray-600 mb-4">Geen judoka's om af te rekenen.</p>
            <a href="{{ route('coach.portal.judokas', ['organisator' => $organisator, 'toernooi' => $toernooiSlug, 'code' => $code]) }}"
               class="text-blue-600 hover:text-blue-800">
                Terug naar judoka's
            </a>
        </div>
        @endif
    </div>
</body>
</html>
