@extends('layouts.app')

@section('title', 'Blok ' . $blok->nummer)

@section('content')
<div class="flex justify-between items-center mb-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Blok {{ $blok->nummer }}</h1>
        @if($blok->weging_gesloten)
        <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full">Weging gesloten</span>
        @endif
    </div>
    <div class="space-x-2">
        @if(!$blok->weging_gesloten)
        <form action="{{ route('toernooi.blok.sluit-weging', [$toernooi, $blok]) }}" method="POST" class="inline">
            @csrf
            <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white font-bold py-2 px-4 rounded"
                    onclick="return confirm('Weging sluiten?')">
                Sluit Weging
            </button>
        </form>
        @endif
        <a href="{{ route('toernooi.blok.zaaloverzicht', $toernooi) }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-block">
            Naar Zaaloverzicht
        </a>
    </div>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mat</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Poule</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Judoka's</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Wedstrijden</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @forelse($blok->poules->sortBy('mat.nummer') as $poule)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium">Mat {{ $poule->mat?->nummer }}</td>
                <td class="px-4 py-3">
                    <a href="{{ route('toernooi.poule.show', [$toernooi, $poule]) }}" class="text-blue-600 hover:text-blue-800">
                        {{ $poule->titel }}
                    </a>
                </td>
                <td class="px-4 py-3">{{ $poule->judokas->count() }}</td>
                <td class="px-4 py-3">{{ $poule->aantal_wedstrijden }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                    Geen poules in dit blok
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">
    <a href="{{ route('toernooi.blok.index', $toernooi) }}" class="text-blue-600 hover:text-blue-800">
        ← Terug naar blokken
    </a>
</div>
@endsection
