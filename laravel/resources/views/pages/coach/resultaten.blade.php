<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $club->naam }} - Resultaten</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            .avoid-break { page-break-inside: avoid; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-4xl mx-auto py-8 px-4">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow p-6 mb-6 no-print">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">{{ $club->naam }}</h1>
                    <p class="text-gray-600">{{ $toernooi->naam }} - {{ $toernooi->datum->format('d F Y') }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="window.print()" class="bg-blue-600 text-white px-3 py-2 rounded hover:bg-blue-700 text-sm">
                        Printen
                    </button>
                    <form action="{{ route('coach.portal.logout', ['organisator' => $organisator, 'toernooi' => $toernooiSlug, 'code' => $code]) }}" method="POST">
                        @csrf
                        <button type="submit" class="text-gray-600 hover:text-gray-800">Uitloggen</button>
                    </form>
                </div>
            </div>

            <!-- Navigation tabs -->
            <div class="mt-4 flex space-x-4 border-t pt-4 overflow-x-auto">
                <a href="{{ route('coach.portal.judokas', ['organisator' => $organisator, 'toernooi' => $toernooiSlug, 'code' => $code]) }}"
                   class="text-gray-600 hover:text-gray-800 px-3 py-1 whitespace-nowrap">
                    Judoka's
                </a>
                <a href="{{ route('coach.portal.coachkaarten', ['organisator' => $organisator, 'toernooi' => $toernooiSlug, 'code' => $code]) }}"
                   class="text-gray-600 hover:text-gray-800 px-3 py-1 whitespace-nowrap">
                    Coach Kaarten
                </a>
                <a href="{{ route('coach.portal.weegkaarten', ['organisator' => $organisator, 'toernooi' => $toernooiSlug, 'code' => $code]) }}"
                   class="text-gray-600 hover:text-gray-800 px-3 py-1 whitespace-nowrap">
                    Weegkaarten
                </a>
                <a href="{{ route('coach.portal.resultaten', ['organisator' => $organisator, 'toernooi' => $toernooiSlug, 'code' => $code]) }}"
                   class="text-blue-600 font-medium border-b-2 border-blue-600 px-3 py-1 whitespace-nowrap">
                    Resultaten
                </a>
            </div>
        </div>

        <!-- Print Header -->
        <div class="hidden print:block mb-6 text-center border-b-2 border-gray-300 pb-4">
            <h1 class="text-2xl font-bold">{{ $club->naam }}</h1>
            <p class="text-gray-600">{{ $toernooi->naam }} - {{ $toernooi->datum->format('d F Y') }}</p>
        </div>

        <!-- Club Summary -->
        <div class="bg-white rounded-lg shadow p-6 mb-6 avoid-break">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Overzicht {{ $club->naam }}</h2>

            <!-- Medal Count -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
                    <div class="text-3xl">🥇</div>
                    <div class="text-2xl font-bold text-yellow-700">{{ $medailles['goud'] }}</div>
                    <div class="text-sm text-yellow-600">Goud</div>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">
                    <div class="text-3xl">🥈</div>
                    <div class="text-2xl font-bold text-gray-600">{{ $medailles['zilver'] }}</div>
                    <div class="text-sm text-gray-500">Zilver</div>
                </div>
                <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 text-center">
                    <div class="text-3xl">🥉</div>
                    <div class="text-2xl font-bold text-orange-700">{{ $medailles['brons'] }}</div>
                    <div class="text-sm text-orange-600">Brons</div>
                </div>
            </div>

            <!-- Club Ranking Position -->
            @if($clubPositieAbsoluut)
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="text-sm text-blue-600 mb-1">Club Klassement</div>
                <div class="text-2xl font-bold text-blue-800">
                    #{{ $clubPositieAbsoluut }}
                    <span class="text-sm font-normal text-blue-600">van {{ count($clubRanking['absoluut']) }} clubs</span>
                </div>
                <div class="text-xs text-blue-500 mt-1">Gesorteerd op gemiddelde WP/JP per judoka</div>
            </div>
            @endif
        </div>

        <!-- Individual Results -->
        <div class="bg-white rounded-lg shadow overflow-hidden mb-6 avoid-break">
            <div class="bg-purple-600 text-white px-4 py-3">
                <h2 class="font-bold">Resultaten per Judoka</h2>
            </div>

            @if(count($resultaten) > 0)
            <div class="divide-y">
                @foreach($resultaten as $r)
                <div class="px-4 py-3 flex justify-between items-center
                    @if($r['plaats'] === 1) bg-yellow-50
                    @elseif($r['plaats'] === 2) bg-gray-50
                    @elseif($r['plaats'] === 3) bg-orange-50
                    @endif">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-1">
                            @if($r['plaats'] === 1)
                                <span class="text-2xl">🥇</span>
                            @elseif($r['plaats'] === 2)
                                <span class="text-2xl">🥈</span>
                            @elseif($r['plaats'] === 3)
                                <span class="text-2xl">🥉</span>
                            @else
                                <span class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold bg-gray-200 text-gray-600">{{ $r['plaats'] }}</span>
                            @endif
                        </div>
                        <div>
                            <span class="font-medium text-gray-800">{{ $r['judoka']->naam }}</span>
                            <span class="text-gray-500 text-sm block">
                                {{ $r['leeftijdsklasse'] }}
                                @if($r['gewichtsklasse'] && $r['gewichtsklasse'] !== 'onbekend')
                                    / {{ $r['gewichtsklasse'] }}
                                @endif
                            </span>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="font-bold text-blue-600">{{ $r['wp'] }}</span>
                        <span class="text-gray-400 text-sm">WP</span>
                        <span class="text-gray-600 ml-1">{{ $r['jp'] }}</span>
                        <span class="text-gray-400 text-sm">JP</span>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="p-8 text-center text-gray-500">
                <div class="text-4xl mb-2">🏆</div>
                <p>Nog geen resultaten beschikbaar.</p>
                <p class="text-sm mt-1">Resultaten verschijnen hier zodra poules zijn afgerond.</p>
            </div>
            @endif
        </div>

        <!-- Club Ranking Tables -->
        <div x-data="{ showRanking: false }" class="no-print">
            <button @click="showRanking = !showRanking"
                    class="w-full bg-white rounded-lg shadow p-4 text-left flex justify-between items-center hover:bg-gray-50">
                <span class="font-bold text-gray-800">Totaal Club Ranking</span>
                <svg class="w-5 h-5 text-gray-500 transition-transform" :class="showRanking ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>

            <div x-show="showRanking" x-collapse class="mt-4">
                @include('pages.resultaten._club-ranking', ['clubRanking' => $clubRanking])
            </div>
        </div>
    </div>
</body>
</html>
