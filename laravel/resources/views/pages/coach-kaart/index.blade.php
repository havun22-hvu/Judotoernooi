@extends('layouts.app')

@section('title', 'Coach Kaarten')

@section('content')
<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Coach Kaarten</h1>
        <p class="text-gray-600 mt-1">Toegangsbewijzen voor begeleiders tot de Dojo (1 per {{ $toernooi->judokas_per_coach ?? 5 }} judoka's)</p>
    </div>
    <form action="{{ route('toernooi.coach-kaart.genereer', $toernooi) }}" method="POST">
        @csrf
        <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Kaarten Genereren/Bijwerken
        </button>
    </form>
</div>

@php
    $totaalKaarten = $clubs->sum(fn($c) => $c->coachKaarten->count());
    $gescand = $clubs->sum(fn($c) => $c->coachKaarten->where('is_gescand', true)->count());
@endphp

<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <p class="text-3xl font-bold text-purple-600">{{ $totaalKaarten }}</p>
        <p class="text-gray-600">Totaal kaarten</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <p class="text-3xl font-bold text-green-600">{{ $gescand }}</p>
        <p class="text-gray-600">Gescand (aanwezig)</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <p class="text-3xl font-bold text-gray-600">{{ $totaalKaarten - $gescand }}</p>
        <p class="text-gray-600">Nog niet gescand</p>
    </div>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Club</th>
                <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700">Judoka's</th>
                <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700">Kaarten</th>
                <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700">Gescand</th>
                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Links</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($clubs as $club)
            @php
                $kaarten = $club->coachKaarten;
                $benodigdAantal = $club->berekenAantalCoachKaarten($toernooi);
            @endphp
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                    <div class="font-medium text-gray-900">{{ $club->naam }}</div>
                    @if($club->plaats)
                    <div class="text-sm text-gray-500">{{ $club->plaats }}</div>
                    @endif
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="text-lg font-semibold text-blue-600">{{ $club->judokas_count }}</span>
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="text-lg font-semibold {{ $kaarten->count() >= $benodigdAantal ? 'text-green-600' : 'text-orange-600' }}">
                        {{ $kaarten->count() }} / {{ $benodigdAantal }}
                    </span>
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="text-lg font-semibold text-purple-600">{{ $kaarten->where('is_gescand', true)->count() }}</span>
                </td>
                <td class="px-4 py-3">
                    <div class="flex flex-wrap gap-1">
                        @foreach($kaarten as $index => $kaart)
                        <a href="{{ route('coach-kaart.show', $kaart->qr_code) }}"
                           target="_blank"
                           class="inline-flex items-center px-2 py-1 rounded text-xs {{ $kaart->is_gescand ? 'bg-green-100 text-green-700' : 'bg-purple-100 text-purple-700 hover:bg-purple-200' }}">
                            Kaart {{ $index + 1 }}
                            @if($kaart->is_gescand)
                            <svg class="w-3 h-3 ml-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            @endif
                        </a>
                        @endforeach
                        @if($kaarten->isEmpty())
                        <span class="text-gray-400 italic text-sm">Nog geen kaarten</span>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                    Geen clubs met judoka's gevonden
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
