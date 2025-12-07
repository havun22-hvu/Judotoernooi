@extends('layouts.app')

@section('title', 'Poules')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Poules ({{ $poules->count() }})</h1>
    <form action="{{ route('toernooi.poule.genereer', $toernooi) }}" method="POST">
        @csrf
        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
            Herindelen
        </button>
    </form>
</div>

@php
    $problematischePoules = $poules->filter(fn($p) => $p->judokas_count < 3);
@endphp

@if($problematischePoules->count() > 0)
<div class="bg-red-50 border border-red-300 rounded-lg p-4 mb-6">
    <h3 class="font-bold text-red-800 mb-2">Problematische poules ({{ $problematischePoules->count() }})</h3>
    <p class="text-red-700 text-sm mb-3">Deze poules hebben minder dan 3 judoka's:</p>
    <div class="flex flex-wrap gap-2">
        @foreach($problematischePoules as $p)
        <a href="{{ route('toernooi.poule.show', [$toernooi, $p]) }}"
           class="inline-flex items-center px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm hover:bg-red-200">
            {{ $p->titel }} ({{ $p->judokas_count }})
        </a>
        @endforeach
    </div>
</div>
@endif

<!-- Per leeftijdsklasse -->
@forelse($poulesPerKlasse as $leeftijdsklasse => $klassePoules)
<div class="mb-8" x-data="{ open: true }">
    <button @click="open = !open" class="w-full flex justify-between items-center bg-blue-800 text-white px-4 py-3 rounded-t-lg hover:bg-blue-700">
        <span class="text-lg font-bold">{{ $leeftijdsklasse }} ({{ $klassePoules->count() }} poules, {{ $klassePoules->sum('judokas_count') }} judoka's)</span>
        <svg :class="{ 'rotate-180': open }" class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div x-show="open" x-collapse class="bg-gray-50 rounded-b-lg shadow p-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($klassePoules as $poule)
            <div class="bg-white rounded-lg shadow {{ $poule->judokas_count < 3 ? 'border-2 border-red-300' : '' }}">
                <!-- Poule header -->
                <div class="px-4 py-3 border-b {{ $poule->judokas_count < 3 ? 'bg-red-50' : 'bg-gray-50' }}">
                    <div class="flex justify-between items-center">
                        <a href="{{ route('toernooi.poule.show', [$toernooi, $poule]) }}" class="font-bold text-blue-600 hover:text-blue-800">
                            {{ $poule->titel }}
                        </a>
                        @if($poule->judokas_count < 3)
                        <span class="text-red-600 text-sm">{{ $poule->judokas_count }} judoka's</span>
                        @endif
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        @if($poule->blok)
                        Blok {{ $poule->blok->nummer }}
                        @endif
                        @if($poule->mat)
                        | Mat {{ $poule->mat->nummer }}
                        @endif
                        | {{ $poule->aantal_wedstrijden }} wedstrijden
                    </div>
                </div>

                <!-- Judoka's in poule -->
                <div class="divide-y divide-gray-100">
                    @foreach($poule->judokas as $judoka)
                    <div class="px-4 py-2 hover:bg-gray-50 text-sm">
                        <div class="flex justify-between items-start">
                            <div>
                                <a href="{{ route('toernooi.judoka.show', [$toernooi, $judoka]) }}" class="font-medium text-gray-800 hover:text-blue-600">
                                    {{ $judoka->naam }}
                                </a>
                                <div class="text-xs text-gray-500">{{ $judoka->club?->naam ?? '-' }}</div>
                            </div>
                            <div class="text-right text-xs text-gray-500">
                                <div>{{ $judoka->gewichtsklasse }}</div>
                                <div>{{ ucfirst($judoka->band) }}</div>
                            </div>
                        </div>
                    </div>
                    @endforeach

                    @if($poule->judokas->isEmpty())
                    <div class="px-4 py-3 text-gray-400 text-sm italic">Geen judoka's</div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@empty
<div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
    Nog geen poules. Genereer eerst de poule-indeling.
</div>
@endforelse
@endsection
