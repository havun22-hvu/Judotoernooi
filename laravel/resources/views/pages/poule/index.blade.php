@extends('layouts.app')

@section('title', 'Poules')

@section('content')
<div class="flex justify-between items-center mb-8">
    <h1 class="text-3xl font-bold text-gray-800">Poules ({{ $poules->total() }})</h1>
    <form action="{{ route('toernooi.poule.genereer', $toernooi) }}" method="POST">
        @csrf
        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
            🎯 Herindelen
        </button>
    </form>
</div>

@php
    $problematischePoules = $poules->filter(fn($p) => $p->judokas_count < 3);
@endphp

@if($problematischePoules->count() > 0)
<div class="bg-red-50 border border-red-300 rounded-lg p-4 mb-6">
    <h3 class="font-bold text-red-800 mb-2">⚠️ Problematische poules ({{ $problematischePoules->count() }})</h3>
    <p class="text-red-700 text-sm mb-3">Deze poules hebben minder dan 3 judoka's en moeten worden aangepast:</p>
    <div class="flex flex-wrap gap-2">
        @foreach($problematischePoules as $p)
        <a href="{{ route('toernooi.poule.show', [$toernooi, $p]) }}"
           class="inline-flex items-center px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm hover:bg-red-200">
            {{ $p->titel }} ({{ $p->judokas_count }} judoka's)
        </a>
        @endforeach
    </div>
</div>
@endif

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nr</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Titel</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Blok</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mat</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Judoka's</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Wedstrijden</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @forelse($poules as $poule)
            <tr class="hover:bg-gray-50 {{ $poule->judokas_count < 3 ? 'bg-red-50' : '' }}">
                <td class="px-4 py-3 font-medium">
                    @if($poule->judokas_count < 3)
                    <span class="text-red-600">⚠️</span>
                    @endif
                    {{ $poule->nummer }}
                </td>
                <td class="px-4 py-3">
                    <a href="{{ route('toernooi.poule.show', [$toernooi, $poule]) }}" class="text-blue-600 hover:text-blue-800">
                        {{ $poule->titel }}
                    </a>
                </td>
                <td class="px-4 py-3">{{ $poule->blok?->nummer ?? '-' }}</td>
                <td class="px-4 py-3">{{ $poule->mat?->nummer ?? '-' }}</td>
                <td class="px-4 py-3">{{ $poule->judokas_count }}</td>
                <td class="px-4 py-3">{{ $poule->aantal_wedstrijden }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                    Nog geen poules. Genereer eerst de poule-indeling.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $poules->links() }}
</div>
@endsection
