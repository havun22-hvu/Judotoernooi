@extends('layouts.app')

@section('title', 'Judoka\'s')

@section('content')
<div class="flex justify-between items-center mb-8">
    <h1 class="text-3xl font-bold text-gray-800">Judoka's ({{ $judokas->total() }})</h1>
    <div class="flex space-x-2">
        <form action="{{ route('toernooi.judoka.valideer', $toernooi) }}" method="POST" class="inline">
            @csrf
            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                ✓ Valideren
            </button>
        </form>
        <a href="{{ route('toernooi.judoka.import', $toernooi) }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            📥 Importeren
        </a>
    </div>
</div>

@if(session('validatie_fouten'))
<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
    <h3 class="font-bold text-yellow-800 mb-2">Ontbrekende gegevens:</h3>
    <ul class="list-disc list-inside text-yellow-700 text-sm">
        @foreach(session('validatie_fouten') as $fout)
        <li>{{ $fout }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Club</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Leeftijdsklasse</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gewicht</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Band</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @forelse($judokas as $judoka)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                    <a href="{{ route('toernooi.judoka.show', [$toernooi, $judoka]) }}" class="text-blue-600 hover:text-blue-800 font-medium">
                        {{ $judoka->naam }}
                    </a>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $judoka->club?->naam ?? '-' }}</td>
                <td class="px-4 py-3">{{ $judoka->leeftijdsklasse }}</td>
                <td class="px-4 py-3">{{ $judoka->gewichtsklasse }} kg</td>
                <td class="px-4 py-3">{{ ucfirst($judoka->band) }}</td>
                <td class="px-4 py-3">
                    @if($judoka->aanwezigheid === 'aanwezig')
                    <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">Aanwezig</span>
                    @elseif($judoka->aanwezigheid === 'afwezig')
                    <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full">Afwezig</span>
                    @else
                    <span class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full">Onbekend</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                    Nog geen judoka's. <a href="{{ route('toernooi.judoka.import', $toernooi) }}" class="text-blue-600">Importeer deelnemers</a>.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $judokas->links() }}
</div>
@endsection
