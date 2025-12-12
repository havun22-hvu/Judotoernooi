@extends('layouts.app')

@section('title', 'Spreker Interface')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold text-gray-800">📢 Spreker Interface</h1>
    <div class="text-sm text-gray-600">
        Auto-refresh elke 10 seconden
    </div>
</div>

{{-- Uitslagen om af te roepen --}}
@if($klarePoules->isNotEmpty())
<div class="mb-8">
    <h2 class="text-2xl font-bold text-purple-800 mb-4">🏆 Uitslagen om af te roepen</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($klarePoules as $poule)
        <div class="bg-white rounded-lg shadow-lg overflow-hidden border-2 border-purple-500">
            <div class="bg-purple-600 text-white px-4 py-3">
                <div class="font-bold text-lg">{{ $poule->leeftijdsklasse }} - {{ $poule->gewichtsklasse }} - Poule {{ $poule->nummer }}</div>
                <div class="text-purple-200 text-sm">Mat {{ $poule->mat?->nummer ?? '?' }}</div>
            </div>
            <div class="p-4">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-gray-500 text-xs">
                            <th class="text-left py-1">Naam (Club)</th>
                            <th class="text-center w-12">WP</th>
                            <th class="text-center w-12">JP</th>
                            <th class="text-center w-12">Plaats</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($poule->standings as $index => $standing)
                        @php $plaats = $index + 1; @endphp
                        <tr class="{{ !$loop->last ? 'border-b' : '' }}">
                            <td class="py-2">
                                <span class="font-medium">{{ $standing['judoka']->naam }}</span>
                                <span class="text-gray-500">({{ $standing['judoka']->club?->naam ?? '-' }})</span>
                            </td>
                            <td class="text-center font-bold">{{ $standing['wp'] }}</td>
                            <td class="text-center">{{ $standing['jp'] }}</td>
                            <td class="text-center font-bold text-lg
                                {{ $plaats === 1 ? 'text-yellow-500' : ($plaats === 2 ? 'text-gray-400' : ($plaats === 3 ? 'text-amber-600' : 'text-gray-600')) }}">
                                {{ $plaats === 1 ? '🥇' : ($plaats === 2 ? '🥈' : ($plaats === 3 ? '🥉' : $plaats)) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="bg-gray-100 px-4 py-2 text-xs text-gray-500">
                Klaar: {{ $poule->spreker_klaar->format('H:i') }}
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- Mat overzicht --}}
<h2 class="text-xl font-bold text-gray-800 mb-4">Mat Overzicht</h2>
<div class="grid grid-cols-1 md:grid-cols-{{ $toernooi->aantal_matten > 4 ? 4 : $toernooi->aantal_matten }} gap-6">
    @for($mat = 1; $mat <= $toernooi->aantal_matten; $mat++)
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="bg-blue-800 text-white px-4 py-3">
            <h2 class="text-xl font-bold text-center">Mat {{ $mat }}</h2>
        </div>

        <div class="p-4" id="mat-{{ $mat }}-content">
            @php
                $matPoules = collect();
                foreach($overzicht as $blok) {
                    if (isset($blok['matten'][$mat])) {
                        foreach($blok['matten'][$mat]['poules'] as $poule) {
                            $matPoules->push([
                                'blok' => $blok['nummer'],
                                'titel' => $poule['titel'],
                                'judokas' => $poule['judokas'],
                            ]);
                        }
                    }
                }
            @endphp

            @forelse($matPoules as $poule)
            <div class="border-b py-3 last:border-0">
                <div class="text-xs text-gray-500 mb-1">Blok {{ $poule['blok'] }}</div>
                <div class="font-bold text-lg">{{ $poule['titel'] }}</div>
                <div class="text-sm text-gray-600">{{ $poule['judokas'] }} deelnemers</div>
            </div>
            @empty
            <div class="text-gray-400 text-center py-8">
                Geen poules gepland
            </div>
            @endforelse
        </div>
    </div>
    @endfor
</div>

<div class="mt-8 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
    <h3 class="font-bold text-yellow-800 mb-2">Tips voor de spreker:</h3>
    <ul class="text-sm text-yellow-700 space-y-1">
        <li>• Roep judoka's 2-3 wedstrijden van tevoren op</li>
        <li>• Noem de mat, poule naam en namen van de judoka's</li>
        <li>• Herhaal de oproep na 1 minuut indien nodig</li>
    </ul>
</div>

<script>
    // Auto-refresh elke 10 seconden
    setTimeout(function() {
        location.reload();
    }, 10000);
</script>
@endsection
