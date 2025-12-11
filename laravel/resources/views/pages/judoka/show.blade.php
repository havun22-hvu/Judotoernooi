@extends('layouts.app')

@section('title', $judoka->naam)

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">{{ $judoka->naam }}</h1>
                <p class="text-gray-600">{{ $judoka->club?->naam ?? 'Geen club' }}</p>
            </div>
            <div class="text-right">
                @if($judoka->aanwezigheid === 'aanwezig')
                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full">Aanwezig</span>
                @elseif($judoka->aanwezigheid === 'afwezig')
                <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full">Afwezig</span>
                @else
                <span class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full">Onbekend</span>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Gegevens</h2>
            <table class="w-full">
                <tbody>
                    <tr>
                        <td class="text-gray-600 py-1.5">Geboortejaar</td>
                        <td class="font-medium text-right py-1.5">{{ $judoka->geboortejaar }}</td>
                    </tr>
                    <tr>
                        <td class="text-gray-600 py-1.5">Leeftijd</td>
                        <td class="font-medium text-right py-1.5">{{ $judoka->leeftijd }} jaar</td>
                    </tr>
                    <tr>
                        <td class="text-gray-600 py-1.5">Geslacht</td>
                        <td class="font-medium text-right py-1.5">{{ $judoka->geslacht === 'M' ? 'Man' : 'Vrouw' }}</td>
                    </tr>
                    <tr>
                        <td class="text-gray-600 py-1.5">Band</td>
                        <td class="font-medium text-right py-1.5">{{ ucfirst($judoka->band) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Classificatie</h2>
            <table class="w-full">
                <tbody>
                    <tr>
                        <td class="text-gray-600 py-1.5">Leeftijdsklasse</td>
                        <td class="font-medium text-right py-1.5">{{ $judoka->leeftijdsklasse }}</td>
                    </tr>
                    <tr>
                        <td class="text-gray-600 py-1.5">Gewichtsklasse</td>
                        <td class="font-medium text-right py-1.5">{{ $judoka->gewichtsklasse }} kg</td>
                    </tr>
                    <tr>
                        <td class="text-gray-600 py-1.5">Opgegeven gewicht</td>
                        <td class="font-medium text-right py-1.5">{{ $judoka->gewicht ? $judoka->gewicht . ' kg' : '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-gray-600 py-1.5">Gewogen gewicht</td>
                        <td class="font-medium text-right py-1.5">{{ $judoka->gewicht_gewogen ? $judoka->gewicht_gewogen . ' kg' : '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-gray-600 py-1.5">Judoka Code</td>
                        <td class="font-mono text-right py-1.5">{{ $judoka->judoka_code }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    @if($judoka->poules->count() > 0)
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-bold mb-4">Poules</h2>
        @foreach($judoka->poules as $poule)
        <div class="border rounded p-4 mb-2">
            <div class="font-medium">{{ $poule->titel }}</div>
            <div class="text-gray-600 text-sm">
                Blok {{ $poule->blok?->nummer ?? '?' }} - Mat {{ $poule->mat?->nummer ?? '?' }}
            </div>
        </div>
        @endforeach
    </div>
    @endif

    <div class="flex justify-between">
        <a href="{{ route('toernooi.judoka.index', $toernooi) }}" class="text-blue-600 hover:text-blue-800">
            &larr; Terug naar lijst
        </a>
        <a href="{{ route('toernooi.judoka.edit', [$toernooi, $judoka]) }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
            Bewerken
        </a>
    </div>
</div>
@endsection
