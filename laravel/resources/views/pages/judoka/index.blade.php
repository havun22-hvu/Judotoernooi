@extends('layouts.app')

@section('title', 'Judoka\'s')

@section('content')
@php
    $incompleteJudokas = $judokas->filter(fn($j) => $j->is_onvolledig || !$j->club_id || !$j->band || !$j->geboortejaar || !$j->gewicht);
    $nietGecategoriseerdAantal = $toernooi->countNietGecategoriseerd();
@endphp

<!-- INFO: Judoka's die niet in een categorie passen (alleen via portal te zien) -->
@if($nietInCategorie->count() > 0)
<div class="mb-4 p-4 bg-orange-50 border border-orange-300 rounded-lg" x-data="{ open: false }">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="text-2xl">🚫</span>
            <div>
                <p class="font-bold text-orange-800">{{ $nietInCategorie->count() }} judoka('s) niet in deelnemerslijst</p>
                <p class="text-sm text-orange-700">Te oud/jong voor dit toernooi. Alleen zichtbaar in het club portal.</p>
            </div>
        </div>
        <button @click="open = !open" class="px-4 py-2 bg-orange-500 text-white rounded hover:bg-orange-600 text-sm font-medium">
            <span x-text="open ? 'Verbergen' : 'Tonen'"></span>
        </button>
    </div>
    <div x-show="open" x-collapse class="mt-4 border-t border-orange-200 pt-3">
        <table class="w-full text-sm">
            <thead class="text-orange-800">
                <tr>
                    <th class="text-left py-1">Naam</th>
                    <th class="text-left py-1">Geb.jaar</th>
                    <th class="text-left py-1">Club</th>
                    <th class="text-left py-1">Reden</th>
                    <th class="text-right py-1">Acties</th>
                </tr>
            </thead>
            <tbody class="text-orange-700">
                @foreach($nietInCategorie as $judoka)
                <tr class="border-t border-orange-100">
                    <td class="py-1">{{ $judoka->naam }}</td>
                    <td class="py-1">{{ $judoka->geboortejaar }}</td>
                    <td class="py-1">{{ $judoka->club?->naam ?? '-' }}</td>
                    <td class="py-1 text-red-600">{{ $judoka->import_warnings ?: 'Onbekende categorie' }}</td>
                    <td class="py-1 text-right whitespace-nowrap">
                        <a href="{{ route('toernooi.judoka.show', $toernooi->routeParamsWith(['judoka' => $judoka])) }}"
                           class="text-blue-600 hover:text-blue-800 text-xs mr-2">
                            Bekijken
                        </a>
                        <form action="{{ route('toernooi.judoka.destroy', $toernooi->routeParamsWith(['judoka' => $judoka])) }}" method="POST" class="inline"
                              onsubmit="return confirm('Weet je zeker dat je {{ $judoka->naam }} wilt verwijderen?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800 text-xs">
                                Verwijderen
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<!-- WAARSCHUWING: Niet-gecategoriseerde judoka's (oude stijl - voor backward compatibility) -->
@if($nietGecategoriseerdAantal > 0 && $nietInCategorie->count() === 0)
<div id="niet-gecategoriseerd-alert"
     class="mb-4 p-4 bg-red-100 border-2 border-red-500 rounded-lg animate-error-blink"
     x-data="{ show: true }"
     x-show="show"
     x-init="setTimeout(() => $el.classList.remove('animate-error-blink'), 10000)">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="text-2xl">⚠️</span>
            <div>
                <p class="font-bold text-red-800">{{ $nietGecategoriseerdAantal }} judoka('s) niet gecategoriseerd!</p>
                <p class="text-sm text-red-700">Geen categorie past bij deze judoka('s). Pas de categorie-instellingen aan.</p>
            </div>
        </div>
        <a href="{{ route('toernooi.edit', $toernooi->routeParams()) }}?tab=toernooi#categorieen"
           class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 text-sm font-medium">
            Naar Instellingen
        </a>
    </div>
</div>
@endif

<div class="flex justify-between items-center mb-4">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Judoka's ({{ $judokas->count() }})</h1>
        @if($incompleteJudokas->count() > 0)
        <p class="text-red-600 text-sm mt-1">{{ $incompleteJudokas->count() }} judoka's met ontbrekende gegevens</p>
        @endif
    </div>
    <div class="flex space-x-2">
        <a href="{{ route('toernooi.judoka.import', $toernooi->routeParams()) }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            Importeren
        </a>
        <button onclick="document.getElementById('addJudokaModal').classList.remove('hidden')"
                class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
            + Judoka toevoegen
        </button>
        <form action="{{ route('toernooi.judoka.valideer', $toernooi->routeParams()) }}" method="POST" class="inline">
            @csrf
            <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Valideren
            </button>
        </form>
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

@if(session('correctie_mails'))
<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
    <p class="text-blue-800">{{ session('correctie_mails') }}</p>
</div>
@endif

@if(session('import_fouten'))
<div class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-6" x-data="{ open: false }">
    <div class="flex items-center justify-between">
        <h3 class="font-bold text-orange-800">
            <span class="cursor-pointer hover:underline" @click="open = !open">
                {{ count(session('import_fouten')) }} import fouten
                <span class="text-sm font-normal ml-2">(klik voor details)</span>
            </span>
        </h3>
        <button @click="open = !open" class="text-orange-600 hover:text-orange-800">
            <svg x-show="!open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
            <svg x-show="open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
            </svg>
        </button>
    </div>
    <div x-show="open" x-collapse class="mt-3">
        <ul class="list-disc list-inside text-orange-700 text-sm max-h-64 overflow-y-auto">
            @foreach(session('import_fouten') as $fout)
            <li>{{ $fout }}</li>
            @endforeach
        </ul>
    </div>
</div>
@endif

@if($importWarningsPerClub->count() > 0)
<div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6" x-data="{ open: false }">
    <div class="flex items-center justify-between">
        <h3 class="font-bold text-red-800">
            <span class="cursor-pointer hover:underline" @click="open = !open">
                ⚠️ {{ $importWarningsPerClub->flatten()->count() }} judoka's met import waarschuwingen
                <span class="text-sm font-normal ml-2">(klik voor details per club)</span>
            </span>
        </h3>
        <button @click="open = !open" class="text-red-600 hover:text-red-800">
            <svg x-show="!open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
            <svg x-show="open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
            </svg>
        </button>
    </div>
    <div x-show="open" x-collapse class="mt-3 space-y-4">
        @foreach($importWarningsPerClub as $clubNaam => $clubJudokas)
        @php $club = $clubJudokas->first()->club; @endphp
        <div class="bg-white rounded p-3 border border-red-100">
            <div class="flex justify-between items-start mb-2">
                <h4 class="font-semibold text-red-900">{{ $clubNaam }} ({{ $clubJudokas->count() }})</h4>
                @if($club)
                <div class="text-xs text-gray-600 text-right">
                    @if($club->email)
                    <a href="mailto:{{ $club->email }}?subject=Judoka%20gegevens%20aanvullen%20-%20{{ urlencode($toernooi->naam) }}" class="text-blue-600 hover:underline">{{ $club->email }}</a>
                    @endif
                    @if($club->telefoon)
                    <span class="ml-2">{{ $club->telefoon }}</span>
                    @endif
                </div>
                @endif
            </div>
            <ul class="text-sm text-red-700 space-y-1">
                @foreach($clubJudokas as $judoka)
                <li class="flex justify-between">
                    <a href="{{ route('toernooi.judoka.show', $toernooi->routeParamsWith(['judoka' => $judoka])) }}" class="hover:underline hover:text-red-900">{{ $judoka->naam }}</a>
                    <span class="text-red-500 text-xs">{{ $judoka->import_warnings }}</span>
                </li>
                @endforeach
            </ul>
        </div>
        @endforeach
    </div>
</div>
@endif

<!-- Judoka tabel -->
@if($judokas->count() > 0)
<div class="bg-white rounded-lg shadow overflow-hidden" x-data="judokaTable()">
    <!-- Zoekbalk -->
    <div class="px-4 py-3 bg-gray-50 border-b">
        <div class="flex gap-2 items-center">
            <div class="relative flex-1">
                <input type="text"
                       x-model="zoekterm"
                       placeholder="Filter: naam, club, -45kg, 20-30kg, +55kg..."
                       class="w-full border rounded-lg px-4 py-2 pl-10 focus:border-blue-500 focus:outline-none">
                <svg class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <button @click="fuzzyLevel = fuzzyLevel ? 0 : 1"
                    :class="fuzzyLevel ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
                    class="px-3 py-2 rounded text-sm font-medium whitespace-nowrap">
                Fuzzy <span x-text="fuzzyLevel ? 'aan' : 'uit'"></span>
            </button>
            <button @click="toonOnvolledig = !toonOnvolledig"
                    :class="toonOnvolledig ? 'bg-red-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
                    class="px-3 py-2 rounded text-sm font-medium whitespace-nowrap">
                Onvolledig ({{ $incompleteJudokas->count() }})
            </button>
            <div x-show="zoekterm" class="bg-green-100 border border-green-300 rounded-lg px-4 py-2 flex items-center gap-2">
                <span class="text-green-800 font-bold text-lg" x-text="filteredJudokas.length"></span>
                <span class="text-green-700 text-sm">resultaten</span>
            </div>
        </div>
    </div>
    <table class="w-full table-fixed">
        <thead class="bg-blue-800 text-white sticky top-0 z-10">
            <tr>
                <th @click="sort('naam')" class="w-[18%] px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                    <span class="flex items-center gap-1">Naam <template x-if="sortKey === 'naam'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                </th>
                <th @click="sort('leeftijdsklasse')" class="w-[12%] px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                    <span class="flex items-center gap-1">Categorie <template x-if="sortKey === 'leeftijdsklasse'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                </th>
                <th @click="sort('gewicht')" class="w-[10%] px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                    <span class="flex items-center gap-1">Gewicht <template x-if="sortKey === 'gewicht'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                </th>
                <th @click="sort('geboortejaar')" class="w-[8%] px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                    <span class="flex items-center gap-1">Geb.jaar <template x-if="sortKey === 'geboortejaar'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                </th>
                <th @click="sort('geslacht')" class="w-[8%] px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                    <span class="flex items-center gap-1">M/V <template x-if="sortKey === 'geslacht'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                </th>
                <th @click="sort('band')" class="w-[10%] px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                    <span class="flex items-center gap-1">Band <template x-if="sortKey === 'band'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                </th>
                <th @click="sort('club')" class="w-[28%] px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                    <span class="flex items-center gap-1">Club <template x-if="sortKey === 'club'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                </th>
                <th class="w-[6%] px-4 py-3 text-left text-xs font-medium uppercase">Acties</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <template x-for="judoka in sortedJudokas" :key="judoka.id">
                <tr :class="judoka.incompleet ? 'bg-red-50 hover:bg-red-100' : 'hover:bg-gray-50'">
                    <td class="px-4 py-2 truncate">
                        <a :href="judoka.url + (toonOnvolledig ? '?filter=onvolledig' : '')" class="text-blue-600 hover:text-blue-800 font-medium" x-text="judoka.naam"></a>
                        <span x-show="judoka.incompleet" class="ml-1 text-red-600 text-xs">⚠</span>
                    </td>
                    <td class="px-4 py-2 text-sm text-gray-600 truncate" x-text="judoka.leeftijdsklasse"></td>
                    <td class="px-4 py-2 text-sm" :class="!judoka.gewicht ? 'text-red-600' : ''" x-text="judoka.gewicht ? judoka.gewicht + ' kg' : '-'"></td>
                    <td class="px-4 py-2 text-sm" :class="!judoka.geboortejaar ? 'text-red-600' : ''" x-text="judoka.geboortejaar || '-'"></td>
                    <td class="px-4 py-2 text-sm" x-text="judoka.geslacht === 'Jongen' ? 'M' : 'V'"></td>
                    <td class="px-4 py-2 text-sm truncate" :class="!judoka.band ? 'text-red-600' : ''" x-text="judoka.band || '-'"></td>
                    <td class="px-4 py-2 text-sm truncate" :class="!judoka.club ? 'text-red-600' : ''" x-text="judoka.club || '-'"></td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <a :href="judoka.editUrl + (toonOnvolledig ? '?filter=onvolledig' : '')" class="text-blue-600 hover:text-blue-800 mr-2" title="Bewerken">✏️</a>
                        <form :action="judoka.deleteUrl" method="POST" class="inline" @submit.prevent="if(confirm('Weet je zeker dat je ' + judoka.naam + ' wilt verwijderen?')) $el.submit()">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800 font-bold text-lg" title="Verwijderen">×</button>
                        </form>
                    </td>
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
        toonOnvolledig: sessionStorage.getItem('toonOnvolledig') === 'true' || new URLSearchParams(window.location.search).get('filter') === 'onvolledig',

        init() {
            // Clear sessionStorage after reading - it's only for back navigation
            sessionStorage.removeItem('toonOnvolledig');
        },

        judokas: [
            @foreach($judokas as $judoka)
            {
                id: {{ $judoka->id }},
                naam: @json($judoka->naam),
                leeftijdsklasse: @json($judoka->leeftijdsklasse),
                leeftijdsklasseOrder: {{ $judoka->sort_categorie ?? 99 }},
                gewicht: {{ $judoka->gewicht ?? 'null' }},
                geboortejaar: {{ $judoka->geboortejaar ?? 'null' }},
                geslacht: '{{ $judoka->geslacht == "M" ? "Jongen" : "Meisje" }}',
                band: @json($judoka->band ? ucfirst(\App\Enums\Band::stripKyu($judoka->band)) : null),
                bandOrder: {{ array_search(strtolower($judoka->band ?? ''), ['wit', 'geel', 'oranje', 'groen', 'blauw', 'bruin', 'zwart']) !== false ? array_search(strtolower($judoka->band ?? ''), ['wit', 'geel', 'oranje', 'groen', 'blauw', 'bruin', 'zwart']) : 99 }},
                club: @json($judoka->club?->naam),
                incompleet: {{ ($judoka->is_onvolledig || !$judoka->club_id || !$judoka->band || !$judoka->geboortejaar || !$judoka->gewicht) ? 'true' : 'false' }},
                url: '{{ route("toernooi.judoka.show", $toernooi->routeParamsWith(["judoka" => $judoka])) }}',
                editUrl: '{{ route("toernooi.judoka.edit", $toernooi->routeParamsWith(["judoka" => $judoka])) }}',
                deleteUrl: '{{ route("toernooi.judoka.destroy", $toernooi->routeParamsWith(["judoka" => $judoka])) }}'
            },
            @endforeach
        ],

        get filteredJudokas() {
            let result = this.judokas;

            // Filter on incomplete if enabled
            if (this.toonOnvolledig) {
                result = result.filter(j => j.incompleet);
            }

            // Filter on search term (with weight filter support)
            if (this.zoekterm) {
                const terms = this.zoekterm.toLowerCase().split(/\s+/).filter(t => t.length > 0);
                const maxDist = this.fuzzyLevel;

                // Check for weight filter patterns and separate them
                const gewichtFilters = [];
                const tekstTerms = [];

                terms.forEach(term => {
                    // Pattern: -45, +55, 20-30
                    if (/^[+-]?\d+(\.\d+)?$/.test(term) || /^\d+(\.\d+)?-\d+(\.\d+)?$/.test(term)) {
                        gewichtFilters.push(term);
                    } else {
                        tekstTerms.push(term);
                    }
                });

                // Apply weight filters
                gewichtFilters.forEach(filter => {
                    let minGewicht = null;
                    let maxGewicht = null;

                    if (filter.startsWith('-')) {
                        // -45 = onder 45 kg
                        maxGewicht = parseFloat(filter.substring(1));
                    } else if (filter.startsWith('+')) {
                        // +55 = boven 55 kg
                        minGewicht = parseFloat(filter.substring(1));
                    } else if (filter.includes('-')) {
                        // 20-30 = tussen 20 en 30 kg
                        const parts = filter.split('-');
                        minGewicht = parseFloat(parts[0]);
                        maxGewicht = parseFloat(parts[1]);
                    }

                    if (minGewicht !== null || maxGewicht !== null) {
                        result = result.filter(j => {
                            if (!j.gewicht) return false;
                            if (minGewicht !== null && j.gewicht < minGewicht) return false;
                            if (maxGewicht !== null && j.gewicht > maxGewicht) return false;
                            return true;
                        });
                    }
                });

                // Apply text search on remaining terms
                if (tekstTerms.length > 0) {
                    result = result.filter(j => {
                        const searchText = [
                            j.naam, j.club, j.leeftijdsklasse, j.gewicht ? j.gewicht + 'kg' : '', j.geboortejaar, j.geslacht, j.band
                        ].filter(Boolean).join(' ').toLowerCase();
                        return tekstTerms.every(term => fuzzyMatch(term, searchText, maxDist));
                    });
                }
            }

            return result;
        },

        get sortedJudokas() {
            const list = this.filteredJudokas;
            if (!this.sortKey) return list;
            return [...list].sort((a, b) => {
                let aVal, bVal;
                if (this.sortKey === 'leeftijdsklasse') {
                    aVal = a.leeftijdsklasseOrder;
                    bVal = b.leeftijdsklasseOrder;
                } else if (this.sortKey === 'gewicht') {
                    aVal = a.gewicht ?? 999;
                    bVal = b.gewicht ?? 999;
                } else if (this.sortKey === 'geboortejaar') {
                    aVal = a.geboortejaar ?? 0;
                    bVal = b.geboortejaar ?? 0;
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
    Nog geen judoka's. <a href="{{ route('toernooi.judoka.import', $toernooi->routeParams()) }}" class="text-blue-600">Importeer deelnemers</a>.
</div>
@endif

<!-- Add Judoka Modal -->
<div id="addJudokaModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-xl shadow-lg rounded-lg bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">Judoka toevoegen</h3>
            <button onclick="document.getElementById('addJudokaModal').classList.add('hidden')"
                    class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>

        <form action="{{ route('toernooi.judoka.store', $toernooi->routeParams()) }}" method="POST">
            @csrf
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 font-medium mb-1">Naam *</label>
                        <input type="text" name="naam" required
                               class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-1">Club</label>
                        <select name="club_id" class="w-full border rounded px-3 py-2">
                            <option value="">-- Geen club --</option>
                            @foreach($toernooi->clubs()->orderBy('naam')->get() as $club)
                            <option value="{{ $club->id }}">{{ $club->naam }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-gray-700 font-medium mb-1">Geboortejaar</label>
                        <input type="number" name="geboortejaar" min="1990" max="{{ date('Y') }}"
                               class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-1">Geslacht</label>
                        <select name="geslacht" class="w-full border rounded px-3 py-2">
                            <option value="">--</option>
                            <option value="M">Man</option>
                            <option value="V">Vrouw</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-1">Band</label>
                        <select name="band" class="w-full border rounded px-3 py-2">
                            <option value="">--</option>
                            @foreach(['wit', 'geel', 'oranje', 'groen', 'blauw', 'bruin', 'zwart'] as $band)
                            <option value="{{ $band }}">{{ ucfirst($band) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 font-medium mb-1">Gewicht (kg)</label>
                        <input type="number" name="gewicht" step="0.1" min="10" max="200"
                               class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-1">Telefoon</label>
                        <input type="tel" name="telefoon"
                               class="w-full border rounded px-3 py-2">
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <button type="button"
                        onclick="document.getElementById('addJudokaModal').classList.add('hidden')"
                        class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                    Annuleren
                </button>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    Toevoegen
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
