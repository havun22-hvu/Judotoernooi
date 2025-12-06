@extends('layouts.app')

@section('title', $poule->titel)

@section('content')
<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800">{{ $poule->titel }}</h1>
    <p class="text-gray-600">Blok {{ $poule->blok?->nummer ?? '?' }} - Mat {{ $poule->mat?->nummer ?? '?' }}</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">Judoka's ({{ $poule->judokas->count() }})</h2>
        <table class="min-w-full">
            <thead>
                <tr class="border-b">
                    <th class="text-left py-2">Naam</th>
                    <th class="text-left py-2">Club</th>
                    <th class="text-left py-2">Band</th>
                </tr>
            </thead>
            <tbody>
                @foreach($poule->judokas as $judoka)
                <tr class="border-b">
                    <td class="py-2">
                        <a href="{{ route('toernooi.judoka.show', [$toernooi, $judoka]) }}" class="text-blue-600 hover:text-blue-800">
                            {{ $judoka->naam }}
                        </a>
                    </td>
                    <td class="py-2 text-gray-600">{{ $judoka->club?->naam ?? '-' }}</td>
                    <td class="py-2">{{ ucfirst($judoka->band) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Stand</h2>
        </div>
        <table class="min-w-full">
            <thead>
                <tr class="border-b">
                    <th class="text-left py-2">#</th>
                    <th class="text-left py-2">Naam</th>
                    <th class="text-center py-2">W</th>
                    <th class="text-center py-2">V</th>
                    <th class="text-center py-2">G</th>
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
                    <td class="py-2 text-center">{{ $s['gelijk'] }}</td>
                    <td class="py-2 text-center font-bold">{{ $s['punten'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="mt-6">
    <a href="{{ route('toernooi.poule.index', $toernooi) }}" class="text-blue-600 hover:text-blue-800">
        ← Terug naar poules
    </a>
</div>
@endsection
