@extends('layouts.app')

@section('title', 'Weeglijst')

@section('content')
<div class="flex justify-between items-center mb-8">
    <h1 class="text-3xl font-bold text-gray-800">Weeglijst</h1>
    <a href="{{ route('toernooi.weging.interface', $toernooi) }}" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded">
        ⚖️ Weging Interface
    </a>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Club</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Klasse</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Blok</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gewicht</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @forelse($judokas as $judoka)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium">{{ $judoka->naam }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $judoka->club?->naam ?? '-' }}</td>
                <td class="px-4 py-3">{{ $judoka->gewichtsklasse }} kg</td>
                <td class="px-4 py-3">{{ $judoka->poules->first()?->blok?->nummer ?? '-' }}</td>
                <td class="px-4 py-3">
                    @if($judoka->gewicht_gewogen)
                    <span class="font-bold">{{ $judoka->gewicht_gewogen }} kg</span>
                    @else
                    <span class="text-gray-400">-</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if($judoka->aanwezigheid === 'aanwezig')
                    <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">Aanwezig</span>
                    @else
                    <span class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full">-</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                    Geen judoka's gevonden
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
