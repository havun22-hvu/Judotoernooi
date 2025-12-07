@extends('layouts.app')

@section('title', 'Judoka\'s')

@section('content')
<div class="flex justify-between items-center mb-4">
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

<!-- Zoekbalk -->
<div class="mb-6" x-data="judokaZoek()">
    <div class="relative">
        <input type="text"
               x-model="zoekterm"
               @input.debounce.200ms="zoek()"
               placeholder="Zoek op naam of club..."
               class="w-full border-2 rounded-lg px-4 py-3 pl-10 focus:border-blue-500 focus:outline-none">
        <svg class="absolute left-3 top-3.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <span x-show="loading" class="absolute right-3 top-3.5 text-gray-400">
            <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        </span>
    </div>

    <!-- Zoekresultaten -->
    <div x-show="zoekterm.length >= 2 && resultaten.length > 0" x-cloak
         class="mt-2 bg-white rounded-lg shadow-lg border max-h-96 overflow-y-auto">
        <template x-for="judoka in resultaten" :key="judoka.id">
            <a :href="'{{ route('toernooi.judoka.index', $toernooi) }}/' + judoka.id"
               class="block px-4 py-3 hover:bg-blue-50 border-b last:border-0">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="font-medium text-gray-800" x-text="judoka.naam"></span>
                        <span class="text-gray-500 text-sm ml-2" x-text="judoka.club || '-'"></span>
                    </div>
                    <div class="text-sm text-gray-500">
                        <span x-text="judoka.leeftijdsklasse"></span> |
                        <span x-text="judoka.gewichtsklasse"></span> |
                        <span x-text="judoka.band"></span>
                    </div>
                </div>
            </a>
        </template>
    </div>

    <div x-show="zoekterm.length >= 2 && resultaten.length === 0 && !loading" x-cloak
         class="mt-2 bg-white rounded-lg shadow border px-4 py-3 text-gray-500">
        Geen resultaten gevonden
    </div>
</div>

<script>
function judokaZoek() {
    return {
        zoekterm: '',
        resultaten: [],
        loading: false,
        async zoek() {
            if (this.zoekterm.length < 2) {
                this.resultaten = [];
                return;
            }
            this.loading = true;
            try {
                const response = await fetch('{{ route('toernooi.judoka.zoek', $toernooi) }}?q=' + encodeURIComponent(this.zoekterm));
                this.resultaten = await response.json();
            } catch (e) {
                this.resultaten = [];
            }
            this.loading = false;
        }
    }
}
</script>

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
                <td class="px-4 py-3">{{ $judoka->gewichtsklasse }}</td>
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
