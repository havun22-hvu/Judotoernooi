@extends('layouts.app')

@section('title', 'Zaaloverzicht')

@section('content')
<h1 class="text-3xl font-bold text-gray-800 mb-8">Zaaloverzicht</h1>

@foreach($overzicht as $blok)
<div class="bg-white rounded-lg shadow mb-6">
    <div class="bg-gray-800 text-white px-6 py-3 rounded-t-lg flex justify-between items-center">
        <h2 class="text-xl font-bold">Blok {{ $blok['nummer'] }}</h2>
        @if($blok['weging_gesloten'])
        <span class="px-2 py-1 text-xs bg-red-500 rounded">Weging gesloten</span>
        @endif
    </div>

    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-{{ count($blok['matten']) > 4 ? '4' : count($blok['matten']) }} gap-4">
            @foreach($blok['matten'] as $matNr => $matData)
            <div class="border rounded p-4">
                <h3 class="font-bold text-lg mb-2">Mat {{ $matNr }}</h3>
                @forelse($matData['poules'] as $poule)
                <div class="text-sm border-b py-1 last:border-0">
                    <div class="font-medium">{{ $poule['titel'] }}</div>
                    <div class="text-gray-500">{{ $poule['judokas'] }} judoka's, {{ $poule['wedstrijden'] }} wedstrijden</div>
                </div>
                @empty
                <div class="text-gray-400 text-sm">Geen poules</div>
                @endforelse
            </div>
            @endforeach
        </div>
    </div>
</div>
@endforeach
@endsection
