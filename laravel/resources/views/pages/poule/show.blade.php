@extends('layouts.app')

@section('title', $poule->titel)

@section('content')
<div class="mb-6">
    <div class="flex justify-between items-start">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ $poule->titel }}</h1>
            <p class="text-gray-600">Blok {{ $poule->blok?->nummer ?? '?' }} - Mat {{ $poule->mat?->nummer ?? '?' }}</p>
        </div>
        <a href="{{ route('toernooi.poule.index', $toernooi) }}" class="text-blue-600 hover:text-blue-800">
            ← Terug naar poules
        </a>
    </div>
</div>

@if($poule->judokas->count() < 3)
<div class="bg-red-50 border border-red-300 rounded-lg p-4 mb-6">
    <h3 class="font-bold text-red-800">⚠️ Problematische poule</h3>
    <p class="text-red-700 text-sm">Deze poule heeft minder dan 3 judoka's. Voeg judoka's toe of voeg samen met een andere poule.</p>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Judoka's in deze poule -->
    <div class="lg:col-span-2 bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">Judoka's ({{ $poule->judokas->count() }})</h2>
        <table class="min-w-full">
            <thead>
                <tr class="border-b">
                    <th class="text-left py-2">Naam</th>
                    <th class="text-left py-2">Club</th>
                    <th class="text-left py-2">Band</th>
                    <th class="text-left py-2">Acties</th>
                </tr>
            </thead>
            <tbody>
                @foreach($poule->judokas as $judoka)
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-2">
                        <a href="{{ route('toernooi.judoka.show', [$toernooi, $judoka]) }}" class="text-blue-600 hover:text-blue-800">
                            {{ $judoka->naam }}
                        </a>
                    </td>
                    <td class="py-2 text-gray-600">{{ $judoka->club?->naam ?? '-' }}</td>
                    <td class="py-2">{{ ucfirst($judoka->band) }}</td>
                    <td class="py-2">
                        <div class="flex items-center space-x-2" x-data="{ showMove: false }">
                            <button @click="showMove = !showMove" class="text-blue-600 hover:text-blue-800 text-sm">
                                Verplaats
                            </button>
                            <form action="{{ route('toernooi.poule.verwijder-judoka', [$toernooi, $poule, $judoka]) }}"
                                  method="POST" class="inline"
                                  onsubmit="return confirm('Weet je zeker dat je deze judoka uit de poule wilt verwijderen?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm">Verwijder</button>
                            </form>

                            <!-- Verplaats dropdown -->
                            <div x-show="showMove" x-cloak class="absolute mt-20 bg-white border rounded shadow-lg p-2 z-10">
                                <form action="{{ route('toernooi.poule.verplaats-judoka', [$toernooi, $poule]) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="judoka_id" value="{{ $judoka->id }}">
                                    <select name="naar_poule_id" class="text-sm border rounded px-2 py-1" onchange="this.form.submit()">
                                        <option value="">Kies poule...</option>
                                        @foreach($compatibelePoules as $cp)
                                        <option value="{{ $cp->id }}">{{ $cp->titel }} ({{ $cp->judokas_count }})</option>
                                        @endforeach
                                    </select>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Acties panel -->
    <div class="space-y-6">
        <!-- Stand -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Stand</h2>
            <table class="min-w-full">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-2">#</th>
                        <th class="text-left py-2">Naam</th>
                        <th class="text-center py-2">W</th>
                        <th class="text-center py-2">V</th>
                        <th class="text-center py-2">Ptn</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stand as $s)
                    <tr class="border-b">
                        <td class="py-2 font-bold">{{ $s['positie'] ?? '-' }}</td>
                        <td class="py-2">{{ $s['naam'] }}</td>
                        <td class="py-2 text-center">{{ $s['gewonnen'] }}</td>
                        <td class="py-2 text-center">{{ $s['verloren'] }}</td>
                        <td class="py-2 text-center font-bold">{{ $s['punten'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Samenvoegen -->
        @if($compatibelePoules->count() > 0)
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Poule samenvoegen</h2>
            <p class="text-sm text-gray-600 mb-3">Voeg alle judoka's van een andere poule toe aan deze poule:</p>
            <form action="{{ route('toernooi.poule.samenvoegen', [$toernooi, $poule]) }}" method="POST"
                  onsubmit="return confirm('Let op: de andere poule wordt verwijderd. Doorgaan?')">
                @csrf
                <select name="andere_poule_id" class="w-full border rounded px-3 py-2 mb-3">
                    <option value="">Kies poule om samen te voegen...</option>
                    @foreach($compatibelePoules as $cp)
                    <option value="{{ $cp->id }}">{{ $cp->titel }} ({{ $cp->judokas_count }} judoka's)</option>
                    @endforeach
                </select>
                <button type="submit" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold py-2 px-4 rounded">
                    Samenvoegen
                </button>
            </form>
        </div>
        @endif

        <!-- Wedstrijden -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Wedstrijden</h2>
            @if($poule->wedstrijden->count() > 0)
            <p class="text-sm text-gray-600 mb-3">{{ $poule->wedstrijden->count() }} wedstrijden gepland</p>
            <a href="{{ route('toernooi.poule.wedstrijdschema', [$toernooi, $poule]) }}"
               class="block text-center bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Bekijk wedstrijdschema
            </a>
            @else
            <p class="text-sm text-gray-600 mb-3">Nog geen wedstrijden gegenereerd</p>
            <form action="{{ route('toernooi.poule.genereer-wedstrijden', [$toernooi, $poule]) }}" method="POST">
                @csrf
                <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    Genereer wedstrijden
                </button>
            </form>
            @endif
        </div>
    </div>
</div>
@endsection
