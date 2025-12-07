@extends('layouts.app')

@section('title', 'Judoka\'s')

@section('content')
@php
    $incompleteJudokas = $judokas->filter(fn($j) => !$j->club_id || !$j->band || !$j->geboortejaar || !$j->gewichtsklasse);
@endphp

<div class="flex justify-between items-center mb-4">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Judoka's ({{ $judokas->count() }})</h1>
        @if($incompleteJudokas->count() > 0)
        <p class="text-red-600 text-sm mt-1">{{ $incompleteJudokas->count() }} judoka's met ontbrekende gegevens</p>
        @endif
    </div>
    <div class="flex space-x-2">
        <form action="{{ route('toernooi.judoka.valideer', $toernooi) }}" method="POST" class="inline">
            @csrf
            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                Valideren
            </button>
        </form>
        <a href="{{ route('toernooi.judoka.import', $toernooi) }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            Importeren
        </a>
    </div>
</div>

<!-- Rapportage per leeftijdsklasse -->
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <h3 class="font-bold text-gray-700 mb-3">Overzicht per leeftijdsklasse</h3>
    <div class="flex flex-wrap gap-3">
        @foreach($judokasPerKlasse as $klasse => $klasseJudokas)
        <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-2">
            <span class="font-medium text-blue-800">{{ $klasse }}</span>
            <span class="ml-2 bg-blue-600 text-white px-2 py-0.5 rounded-full text-sm">{{ $klasseJudokas->count() }}</span>
        </div>
        @endforeach
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

<!-- Judoka tabel -->
@if($judokas->count() > 0)
<div class="bg-white rounded-lg shadow overflow-hidden" x-data="judokaTable()">
    <!-- Zoekbalk -->
    <div class="px-4 py-3 bg-gray-50 border-b">
        <div class="flex gap-2">
            <div class="relative flex-1">
                <input type="text"
                       x-model="zoekterm"
                       placeholder="Filter op naam, club, gewicht, band..."
                       class="w-full border rounded-lg px-4 py-2 pl-10 focus:border-blue-500 focus:outline-none">
                <svg class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <div class="flex items-center gap-1 text-sm">
                <span class="text-gray-500">Fuzzy:</span>
                <template x-for="level in [0,1,2,3]" :key="level">
                    <button @click="fuzzyLevel = level"
                            :class="fuzzyLevel === level ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
                            class="w-7 h-7 rounded font-medium" x-text="level"></button>
                </template>
            </div>
            <span x-show="zoekterm" class="flex items-center text-sm text-gray-500" x-text="filteredJudokas.length + ' resultaten'"></span>
        </div>
    </div>
    <table class="min-w-full">
        <thead class="bg-blue-800 text-white sticky top-0 z-10">
            <tr>
                <th @click="sort('naam')" class="px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                    <span class="flex items-center gap-1">Naam <template x-if="sortKey === 'naam'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                </th>
                <th @click="sort('leeftijdsklasse')" class="px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                    <span class="flex items-center gap-1">Leeftijdsklasse <template x-if="sortKey === 'leeftijdsklasse'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                </th>
                <th @click="sort('gewichtsklasse')" class="px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                    <span class="flex items-center gap-1">Gewichtsklasse <template x-if="sortKey === 'gewichtsklasse'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                </th>
                <th @click="sort('geslacht')" class="px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                    <span class="flex items-center gap-1">Geslacht <template x-if="sortKey === 'geslacht'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                </th>
                <th @click="sort('band')" class="px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                    <span class="flex items-center gap-1">Band <template x-if="sortKey === 'band'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                </th>
                <th @click="sort('club')" class="px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                    <span class="flex items-center gap-1">Club <template x-if="sortKey === 'club'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                </th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <template x-for="judoka in sortedJudokas" :key="judoka.id">
                <tr :class="judoka.incompleet ? 'bg-red-50 hover:bg-red-100' : 'hover:bg-gray-50'">
                    <td class="px-4 py-2">
                        <a :href="judoka.url" class="text-blue-600 hover:text-blue-800 font-medium" x-text="judoka.naam"></a>
                        <span x-show="judoka.incompleet" class="ml-2 text-red-600 text-xs">⚠</span>
                    </td>
                    <td class="px-4 py-2 text-sm text-gray-600" x-text="judoka.leeftijdsklasse"></td>
                    <td class="px-4 py-2 text-sm" :class="!judoka.gewichtsklasse ? 'text-red-600' : ''" x-text="judoka.gewichtsklasse || '-'"></td>
                    <td class="px-4 py-2 text-sm" x-text="judoka.geslacht"></td>
                    <td class="px-4 py-2 text-sm" :class="!judoka.band ? 'text-red-600' : ''" x-text="judoka.band || '-'"></td>
                    <td class="px-4 py-2 text-sm" :class="!judoka.club ? 'text-red-600' : ''" x-text="judoka.club || '-'"></td>
                </tr>
            </template>
        </tbody>
    </table>
</div>

<script>
function judokaTable() {
    // Levenshtein distance for fuzzy matching
    function levenshtein(a, b) {
        if (a.length === 0) return b.length;
        if (b.length === 0) return a.length;
        const matrix = [];
        for (let i = 0; i <= b.length; i++) matrix[i] = [i];
        for (let j = 0; j <= a.length; j++) matrix[0][j] = j;
        for (let i = 1; i <= b.length; i++) {
            for (let j = 1; j <= a.length; j++) {
                matrix[i][j] = b[i-1] === a[j-1]
                    ? matrix[i-1][j-1]
                    : Math.min(matrix[i-1][j-1] + 1, matrix[i][j-1] + 1, matrix[i-1][j] + 1);
            }
        }
        return matrix[b.length][a.length];
    }

    // Check if term fuzzy-matches any word in text
    function fuzzyMatch(term, text, maxDist) {
        if (maxDist === 0) return text.includes(term);
        const words = text.split(/\s+/);
        for (const word of words) {
            if (word.includes(term)) return true;
            if (term.length >= 3 && levenshtein(term, word.substring(0, term.length + maxDist)) <= maxDist) return true;
        }
        // Also check substring match with tolerance
        for (let i = 0; i <= text.length - term.length + maxDist; i++) {
            const sub = text.substring(i, i + term.length);
            if (levenshtein(term, sub) <= maxDist) return true;
        }
        return false;
    }

    return {
        sortKey: null,
        sortAsc: true,
        zoekterm: '',
        fuzzyLevel: 0,
        judokas: [
            @foreach($judokas as $judoka)
            {
                id: {{ $judoka->id }},
                naam: @json($judoka->naam),
                leeftijdsklasse: @json($judoka->leeftijdsklasse),
                leeftijdsklasseOrder: {{ $leeftijdsklasseVolgorde[$judoka->leeftijdsklasse] ?? 99 }},
                gewichtsklasse: @json($judoka->gewichtsklasse),
                gewichtsklasseNum: {{ preg_match('/([+-]?)(\d+)/', $judoka->gewichtsklasse ?? '', $m) ? ((int)($m[2] ?? 999) + (($m[1] ?? '') === '+' ? 1000 : 0)) : 999 }},
                geslacht: '{{ $judoka->geslacht == "M" ? "Jongen" : "Meisje" }}',
                band: @json($judoka->band ? ucfirst($judoka->band) : null),
                bandOrder: {{ array_search(strtolower($judoka->band ?? ''), ['wit', 'geel', 'oranje', 'groen', 'blauw', 'bruin', 'zwart']) !== false ? array_search(strtolower($judoka->band ?? ''), ['wit', 'geel', 'oranje', 'groen', 'blauw', 'bruin', 'zwart']) : 99 }},
                club: @json($judoka->club?->naam),
                incompleet: {{ (!$judoka->club_id || !$judoka->band || !$judoka->geboortejaar || !$judoka->gewichtsklasse) ? 'true' : 'false' }},
                url: '{{ route("toernooi.judoka.show", [$toernooi, $judoka]) }}'
            },
            @endforeach
        ],

        get filteredJudokas() {
            if (!this.zoekterm) return this.judokas;
            const terms = this.zoekterm.toLowerCase().split(/\s+/).filter(t => t.length > 0);
            const maxDist = this.fuzzyLevel;
            return this.judokas.filter(j => {
                const searchText = [
                    j.naam, j.club, j.leeftijdsklasse, j.gewichtsklasse, j.geslacht, j.band
                ].filter(Boolean).join(' ').toLowerCase();
                return terms.every(term => fuzzyMatch(term, searchText, maxDist));
            });
        },

        get sortedJudokas() {
            const list = this.filteredJudokas;
            if (!this.sortKey) return list;
            return [...list].sort((a, b) => {
                let aVal, bVal;
                if (this.sortKey === 'leeftijdsklasse') {
                    aVal = a.leeftijdsklasseOrder;
                    bVal = b.leeftijdsklasseOrder;
                } else if (this.sortKey === 'gewichtsklasse') {
                    aVal = a.gewichtsklasseNum;
                    bVal = b.gewichtsklasseNum;
                } else if (this.sortKey === 'band') {
                    aVal = a.bandOrder;
                    bVal = b.bandOrder;
                } else if (this.sortKey === 'club') {
                    aVal = (a.club || 'zzz').toLowerCase();
                    bVal = (b.club || 'zzz').toLowerCase();
                } else {
                    aVal = (a[this.sortKey] || '').toString().toLowerCase();
                    bVal = (b[this.sortKey] || '').toString().toLowerCase();
                }
                if (aVal < bVal) return this.sortAsc ? -1 : 1;
                if (aVal > bVal) return this.sortAsc ? 1 : -1;
                return 0;
            });
        },

        sort(key) {
            if (this.sortKey === key) {
                this.sortAsc = !this.sortAsc;
            } else {
                this.sortKey = key;
                this.sortAsc = true;
            }
        }
    }
}
</script>
@else
<div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
    Nog geen judoka's. <a href="{{ route('toernooi.judoka.import', $toernooi) }}" class="text-blue-600">Importeer deelnemers</a>.
</div>
@endif
@endsection
