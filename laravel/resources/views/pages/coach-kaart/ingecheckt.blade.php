@extends('layouts.app')

@section('title', 'Ingecheckte Coaches')

@section('content')
<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Ingecheckte Coaches</h1>
        <p class="text-gray-600 mt-1">Coaches die momenteel in de dojo zijn</p>
    </div>
    <a href="{{ route('toernooi.coach-kaart.index', $toernooi) }}"
       class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Terug naar overzicht
    </a>
</div>

<!-- Warning box -->
<div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded mb-6">
    <div class="flex items-start gap-3">
        <svg class="w-6 h-6 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <div>
            <p class="font-bold">Geforceerd uitchecken - alleen voor hoofdjury</p>
            <p class="text-sm mt-1">
                Gebruik deze functie alleen als een coach niet zelf heeft uitgecheckt en de kaart moet worden overgedragen.
                De uitcheck wordt gelogd als "geforceerd door hoofdjury".
            </p>
        </div>
    </div>
</div>

@if($ingecheckteKaarten->count() > 0)
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Coach</th>
                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Club</th>
                <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700">Ingecheckt om</th>
                <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700">Actie</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @foreach($ingecheckteKaarten as $kaart)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                        <!-- Photo -->
                        <div class="w-12 h-14 flex-shrink-0 bg-gray-100 rounded overflow-hidden">
                            @if($kaart->foto)
                            <img src="{{ $kaart->getFotoUrl() }}" alt="{{ $kaart->naam }}"
                                 class="w-full h-full object-cover">
                            @else
                            <div class="w-full h-full flex items-center justify-center text-gray-400">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            @endif
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">{{ $kaart->naam ?? '(naam onbekend)' }}</p>
                            <p class="text-xs text-gray-500">Kaart: {{ $kaart->qr_code }}</p>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <p class="font-medium text-gray-900">{{ $kaart->club->naam ?? 'Onbekend' }}</p>
                    @if($kaart->club?->plaats)
                    <p class="text-sm text-gray-500">{{ $kaart->club->plaats }}</p>
                    @endif
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="inline-flex items-center gap-1 text-green-600 font-medium">
                        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                        {{ $kaart->ingecheckt_op->format('H:i') }}
                    </span>
                    <p class="text-xs text-gray-500">{{ $kaart->ingecheckt_op->diffForHumans() }}</p>
                </td>
                <td class="px-4 py-3 text-center">
                    <form action="{{ route('toernooi.coach-kaart.force-checkout', [$toernooi, $kaart]) }}" method="POST"
                          onsubmit="return confirm('Weet je zeker dat je {{ $kaart->naam }} geforceerd wilt uitchecken?');">
                        @csrf
                        <button type="submit"
                                class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded text-sm">
                            Forceer uitcheck
                        </button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@else
<div class="bg-white rounded-lg shadow p-8 text-center">
    <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
    </svg>
    <p class="text-gray-500 text-lg">Geen coaches momenteel ingecheckt</p>
    <p class="text-gray-400 text-sm mt-1">Alle coaches zijn uitgecheckt of nog niet langs de dojo scanner geweest</p>
</div>
@endif
@endsection
