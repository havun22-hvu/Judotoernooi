@extends('layouts.app')

@section('title', 'Toernooi Afsluiten')

@section('content')
@php
    $organisator = auth('organisator')->user();
    $magAfsluiten = $organisator && ($organisator->isSitebeheerder() || $organisator->toernooien->contains($toernooi));
    $heeftWedstrijden = $statistieken['totaal_wedstrijden'] > 0;
    $isGespeeld = $statistieken['gespeelde_wedstrijden'] > 0;
@endphp

<div class="max-w-6xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Toernooi Afsluiten</h1>
        <a href="{{ route('toernooi.show', $toernooi->routeParams()) }}" class="text-blue-600 hover:underline">
            ← Terug naar overzicht
        </a>
    </div>

    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
        {{ session('error') }}
    </div>
    @endif

    @if(!$isGespeeld && !$toernooi->isAfgesloten())
    <!-- INTRO: Toernooi nog niet gespeeld -->
    <div class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-lg p-8 mb-6 shadow-lg">
        <div class="flex items-start gap-6">
            <div class="text-6xl">🏅</div>
            <div>
                <h2 class="text-2xl font-bold mb-3">Welkom bij Toernooi Afsluiten</h2>
                <p class="opacity-90 mb-4">
                    Na afloop van het toernooi kun je hier alles afronden en een compleet overzicht krijgen van de resultaten.
                </p>
                <div class="bg-white/20 rounded-lg p-4 mb-4">
                    <h3 class="font-bold mb-2">Wat je hier straks ziet:</h3>
                    <ul class="space-y-1 text-sm opacity-90">
                        <li>📊 Complete statistieken van alle wedstrijden</li>
                        <li>🏆 Club klassement (absoluut en relatief)</li>
                        <li>👥 Overzicht deelnemers per leeftijdsklasse</li>
                        <li>📥 Export naar CSV en PDF</li>
                        <li>🔒 Mogelijkheid om toernooi definitief af te sluiten</li>
                    </ul>
                </div>
                <div class="bg-yellow-400/30 rounded-lg p-3 text-sm">
                    <strong>💡 Tip:</strong> Kom hier terug nadat alle wedstrijden zijn gespeeld om het toernooi af te ronden.
                </div>
            </div>
        </div>
    </div>

    <!-- Huidige status -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="font-bold text-lg mb-4">Huidige Status</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-2xl font-bold text-blue-600">{{ $statistieken['totaal_judokas'] }}</div>
                <div class="text-sm text-gray-600">Judoka's</div>
            </div>
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-2xl font-bold text-green-600">{{ $statistieken['totaal_clubs'] }}</div>
                <div class="text-sm text-gray-600">Clubs</div>
            </div>
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-2xl font-bold text-purple-600">{{ $statistieken['totaal_poules'] + $statistieken['totaal_eliminaties'] }}</div>
                <div class="text-sm text-gray-600">Poules</div>
            </div>
            <div class="text-center p-3 {{ $heeftWedstrijden ? 'bg-green-50' : 'bg-orange-50' }} rounded-lg">
                <div class="text-2xl font-bold {{ $heeftWedstrijden ? 'text-green-600' : 'text-orange-600' }}">{{ $statistieken['totaal_wedstrijden'] }}</div>
                <div class="text-sm text-gray-600">Wedstrijden</div>
            </div>
        </div>

        @if(!$heeftWedstrijden)
        <div class="mt-4 bg-orange-50 border border-orange-200 rounded-lg p-4 text-orange-800">
            <strong>⏳ Nog geen wedstrijden gegenereerd</strong>
            <p class="text-sm mt-1">Genereer eerst de poule-indeling en wedstrijden voordat het toernooi gespeeld kan worden.</p>
        </div>
        @else
        <div class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4 text-blue-800">
            <strong>📋 Wedstrijden klaar</strong>
            <p class="text-sm mt-1">Er zijn {{ $statistieken['totaal_wedstrijden'] }} wedstrijden gegenereerd. Start met spelen via de Mat Interface!</p>
        </div>
        @endif
    </div>
    @else

    @if($toernooi->isAfgesloten())
    <!-- Afgesloten banner -->
    <div class="bg-green-100 border-2 border-green-400 rounded-lg p-6 mb-6 text-center">
        <div class="text-5xl mb-2">🏆</div>
        <h2 class="text-2xl font-bold text-green-800 mb-2">Toernooi Afgesloten</h2>
        <p class="text-green-700">
            Afgesloten op: {{ $toernooi->afgesloten_at->format('d-m-Y H:i') }}
        </p>
        @if($toernooi->herinnering_datum)
        <p class="text-green-600 text-sm mt-2">
            Herinnering voor volgend jaar gepland: {{ $toernooi->herinnering_datum->format('d-m-Y') }}
        </p>
        @endif

        @if($magAfsluiten)
        <form action="{{ route('toernooi.heropenen', $toernooi->routeParams()) }}" method="POST" class="mt-4">
            @csrf
            <button type="submit"
                    onclick="return confirm('Weet je zeker dat je het toernooi wilt heropenen?')"
                    class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded font-medium">
                Toernooi Heropenen
            </button>
        </form>
        @endif
    </div>
    @endif

    <!-- Bedankbericht -->
    <div class="bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg p-6 mb-6 shadow-lg">
        <div class="flex items-start gap-4">
            <div class="text-4xl">🙏</div>
            <div>
                <h2 class="text-xl font-bold mb-2">Bedankt voor het gebruik van JudoToernooi!</h2>
                <p class="opacity-90">
                    We hopen dat het toernooi naar wens is verlopen. Hieronder vind je een overzicht van alle statistieken en resultaten.
                </p>
                <p class="opacity-75 text-sm mt-2">
                    Feedback? Laat het ons weten via
                    <a href="https://github.com/havun22-hvu/judotoernooi/issues" target="_blank" class="underline hover:no-underline">GitHub</a>
                    of stuur een e-mail.
                </p>
            </div>
        </div>
    </div>

    <!-- Statistieken Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-3xl font-bold text-blue-600">{{ $statistieken['totaal_judokas'] }}</div>
            <div class="text-gray-600 text-sm">Judoka's</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-3xl font-bold text-green-600">{{ $statistieken['totaal_clubs'] }}</div>
            <div class="text-gray-600 text-sm">Clubs</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-3xl font-bold text-purple-600">{{ $statistieken['totaal_poules'] + $statistieken['totaal_eliminaties'] }}</div>
            <div class="text-gray-600 text-sm">Poules</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-3xl font-bold text-orange-600">{{ $statistieken['totaal_wedstrijden'] }}</div>
            <div class="text-gray-600 text-sm">Wedstrijden</div>
        </div>
    </div>

    <!-- Gedetailleerde statistieken -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <!-- Toernooi info -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="bg-gray-800 text-white px-4 py-3">
                <h3 class="font-bold">Toernooi Details</h3>
            </div>
            <div class="p-4">
                <table class="w-full text-sm">
                    <tr class="border-b">
                        <td class="py-2 text-gray-600">Naam</td>
                        <td class="py-2 font-medium text-right">{{ $toernooi->naam }}</td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-2 text-gray-600">Datum</td>
                        <td class="py-2 font-medium text-right">{{ $toernooi->datum->format('d-m-Y') }}</td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-2 text-gray-600">Organisatie</td>
                        <td class="py-2 font-medium text-right">{{ $toernooi->organisatie ?? '-' }}</td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-2 text-gray-600">Locatie</td>
                        <td class="py-2 font-medium text-right">{{ $toernooi->locatie ?? '-' }}</td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-2 text-gray-600">Blokken</td>
                        <td class="py-2 font-medium text-right">{{ $statistieken['aantal_blokken'] }}</td>
                    </tr>
                    <tr>
                        <td class="py-2 text-gray-600">Matten</td>
                        <td class="py-2 font-medium text-right">{{ $statistieken['aantal_matten'] }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Deelnemers breakdown -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="bg-gray-800 text-white px-4 py-3">
                <h3 class="font-bold">Deelnemers</h3>
            </div>
            <div class="p-4">
                <div class="flex gap-4 mb-4">
                    <div class="flex-1 bg-blue-50 rounded-lg p-3 text-center">
                        <div class="text-2xl font-bold text-blue-600">{{ $statistieken['jongens'] }}</div>
                        <div class="text-xs text-gray-600">Jongens</div>
                    </div>
                    <div class="flex-1 bg-pink-50 rounded-lg p-3 text-center">
                        <div class="text-2xl font-bold text-pink-600">{{ $statistieken['meisjes'] }}</div>
                        <div class="text-xs text-gray-600">Meisjes</div>
                    </div>
                </div>

                <h4 class="font-medium text-gray-700 mb-2">Per leeftijdsklasse:</h4>
                <div class="space-y-1 max-h-40 overflow-y-auto">
                    @foreach($statistieken['per_leeftijdsklasse'] as $klasse => $aantal)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">{{ $klasse }}</span>
                        <span class="font-medium">{{ $aantal }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Wedstrijden voortgang -->
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="bg-gray-800 text-white px-4 py-3">
            <h3 class="font-bold">Wedstrijden Voortgang</h3>
        </div>
        <div class="p-4">
            <div class="flex items-center gap-4">
                <div class="flex-1">
                    <div class="bg-gray-200 rounded-full h-4 overflow-hidden">
                        <div class="bg-green-500 h-full transition-all duration-500"
                             style="width: {{ $statistieken['voltooiings_percentage'] }}%"></div>
                    </div>
                </div>
                <div class="text-right">
                    <span class="text-2xl font-bold text-green-600">{{ $statistieken['voltooiings_percentage'] }}%</span>
                    <div class="text-xs text-gray-500">
                        {{ $statistieken['gespeelde_wedstrijden'] }} / {{ $statistieken['totaal_wedstrijden'] }} wedstrijden
                    </div>
                </div>
            </div>

            @if($statistieken['voltooiings_percentage'] < 100)
            <div class="mt-3 bg-yellow-50 border border-yellow-200 rounded p-3 text-sm text-yellow-800">
                <strong>Let op:</strong> Niet alle wedstrijden zijn voltooid. Sluit het toernooi pas af als alle wedstrijden gespeeld zijn.
            </div>
            @endif
        </div>
    </div>

    <!-- Club Rankings -->
    <div class="mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Club Klassement</h2>
        @include('pages.resultaten._club-ranking', ['clubRanking' => $clubRanking])
    </div>

    <!-- Export opties -->
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="bg-gray-800 text-white px-4 py-3">
            <h3 class="font-bold">Exporteren</h3>
        </div>
        <div class="p-4">
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('publiek.export-uitslagen', $toernooi) }}"
                   class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded flex items-center gap-2">
                    <span>📊</span> Uitslagen CSV
                </a>
                <button onclick="window.print()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded flex items-center gap-2">
                    <span>🖨️</span> Printen / PDF
                </button>
            </div>
            <p class="text-sm text-gray-500 mt-3">
                Tip: Gebruik de printfunctie van je browser om deze pagina als PDF op te slaan.
            </p>
        </div>
    </div>

    <!-- Afsluiten actie -->
    @if(!$toernooi->isAfgesloten() && $magAfsluiten)
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="bg-red-600 text-white px-4 py-3">
            <h3 class="font-bold">Toernooi Definitief Afsluiten</h3>
        </div>
        <div class="p-4">
            <p class="text-gray-700 mb-4">
                Door het toernooi af te sluiten wordt het in alleen-lezen modus gezet.
                Je ontvangt automatisch een herinnering 3 maanden voor de datum van volgend jaar.
            </p>
            <form action="{{ route('toernooi.afsluiten.bevestig', $toernooi->routeParams()) }}" method="POST">
                @csrf
                <button type="submit"
                        onclick="return confirm('Weet je zeker dat je dit toernooi wilt afsluiten?\n\nJe kunt het later nog heropenen indien nodig.')"
                        class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-bold flex items-center gap-2">
                    <span>🔒</span> Toernooi Afsluiten
                </button>
            </form>
        </div>
    </div>
    @elseif(!$toernooi->isAfgesloten() && !$magAfsluiten)
    <div class="bg-gray-100 border border-gray-300 rounded-lg p-4 text-gray-600 text-center">
        <span class="text-2xl">🔐</span>
        <p class="mt-2">Alleen de organisator of sitebeheerder kan dit toernooi afsluiten.</p>
    </div>
    @endif

    @endif {{-- Einde van @if(!$isGespeeld) @else --}}
</div>

<style>
@media print {
    /* Hide non-printable elements */
    nav, button, a[href], form, .no-print {
        display: none !important;
    }

    /* Full width for print */
    .max-w-6xl {
        max-width: 100% !important;
    }

    /* Avoid page breaks inside elements */
    .avoid-break {
        break-inside: avoid;
    }
}
</style>
@endsection
