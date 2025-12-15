@extends('layouts.app')

@section('title', 'Spreker Interface')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold text-gray-800">📢 Spreker Interface</h1>
    <div class="text-sm text-gray-600">
        Auto-refresh elke 10 seconden
    </div>
</div>

@if($klarePoules->isEmpty())
<div class="bg-white rounded-lg shadow p-12 text-center">
    <div class="text-6xl mb-4">🎙️</div>
    <h2 class="text-2xl font-bold text-gray-600 mb-2">Wachten op uitslagen...</h2>
    <p class="text-gray-500">Afgeronde poules verschijnen hier automatisch</p>
</div>
@else
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @foreach($klarePoules as $poule)
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <!-- Header -->
        <div class="bg-purple-700 text-white px-4 py-3">
            <div class="font-bold text-lg">Poule {{ $poule->nummer }} - {{ $poule->leeftijdsklasse }} {{ $poule->gewichtsklasse }}</div>
            <div class="text-purple-200 text-sm">Blok {{ $poule->blok?->nummer ?? '?' }} - Mat {{ $poule->mat?->nummer ?? '?' }}</div>
        </div>

        <!-- Resultaten tabel -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-gray-200 border-b-2 border-gray-400">
                        <th class="px-3 py-2 text-left font-bold text-gray-700">Naam</th>
                        <th class="px-2 py-2 text-center font-bold text-gray-700 w-12">WP</th>
                        <th class="px-2 py-2 text-center font-bold text-gray-700 w-12">JP</th>
                        <th class="px-2 py-2 text-center font-bold text-gray-700 w-12">#</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($poule->standings as $index => $standing)
                    @php $plaats = $index + 1; @endphp
                    <tr class="border-b last:border-0">
                        <td class="px-3 py-2">
                            <span class="font-bold">{{ $standing['judoka']->naam }}</span>
                            <span class="text-gray-500 text-xs">({{ $standing['judoka']->club?->naam ?? '-' }})</span>
                        </td>
                        <td class="px-2 py-2 text-center font-bold bg-blue-50 text-blue-800">{{ $standing['wp'] }}</td>
                        <td class="px-2 py-2 text-center bg-blue-50 text-blue-800">{{ $standing['jp'] }}</td>
                        <td class="px-2 py-2 text-center font-bold text-lg
                            @if($plaats === 1) bg-yellow-400 text-yellow-900
                            @elseif($plaats === 2) bg-gray-300 text-gray-800
                            @elseif($plaats === 3) bg-orange-300 text-orange-900
                            @else bg-yellow-50
                            @endif">
                            {{ $plaats }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Footer met tijdstip -->
        <div class="bg-gray-100 px-4 py-2 text-xs text-gray-500">
            Klaar: {{ $poule->spreker_klaar->format('H:i') }}
        </div>
    </div>
    @endforeach
</div>
@endif

<script>
    // Auto-refresh elke 10 seconden
    setTimeout(function() {
        location.reload();
    }, 10000);
</script>
@endsection
