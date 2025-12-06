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
            <dl class="space-y-2">
                <div class="flex justify-between">
                    <dt class="text-gray-600">Geboortejaar</dt>
                    <dd class="font-medium">{{ $judoka->geboortejaar }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-600">Leeftijd</dt>
                    <dd class="font-medium">{{ $judoka->leeftijd }} jaar</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-600">Geslacht</dt>
                    <dd class="font-medium">{{ $judoka->geslacht === 'M' ? 'Man' : 'Vrouw' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-600">Band</dt>
                    <dd class="font-medium">{{ ucfirst($judoka->band) }}</dd>
                </div>
            </dl>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Classificatie</h2>
            <dl class="space-y-2">
                <div class="flex justify-between">
                    <dt class="text-gray-600">Leeftijdsklasse</dt>
                    <dd class="font-medium">{{ $judoka->leeftijdsklasse }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-600">Gewichtsklasse</dt>
                    <dd class="font-medium">{{ $judoka->gewichtsklasse }} kg</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-600">Opgegeven gewicht</dt>
                    <dd class="font-medium">{{ $judoka->gewicht ? $judoka->gewicht . ' kg' : '-' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-600">Gewogen gewicht</dt>
                    <dd class="font-medium">{{ $judoka->gewicht_gewogen ? $judoka->gewicht_gewogen . ' kg' : '-' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-600">Judoka Code</dt>
                    <dd class="font-mono">{{ $judoka->judoka_code }}</dd>
                </div>
            </dl>
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
            ← Terug naar lijst
        </a>
        <a href="{{ route('toernooi.judoka.edit', [$toernooi, $judoka]) }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
            Bewerken
        </a>
    </div>
</div>
@endsection
