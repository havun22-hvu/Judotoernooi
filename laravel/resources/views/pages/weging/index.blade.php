@extends('layouts.app')

@section('title', __('Weeglijst'))

@section('content')
@php
    $blokGesloten = $blokGesloten ?? [];
    $aanwezig = $judokas->where('aanwezigheid', 'aanwezig')->count();
    // Only count afwezig if their blok is closed
    $afwezig = $judokas->where('aanwezigheid', 'afwezig')->filter(function($j) use ($blokGesloten) {
        $blokNr = $j->poules->first()?->blok?->nummer;
        return $blokNr && ($blokGesloten[$blokNr] ?? false);
    })->count();
    $nietGewogen = $judokas->whereNull('gewicht_gewogen')->count();
@endphp

<div class="flex justify-between items-center mb-4">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">{{ __('Weeglijst') }} ({{ $judokas->count() }})</h1>
        <p class="text-sm text-gray-600 mt-1">
            <span class="text-green-600">{{ $aanwezig }} {{ __('aanwezig') }}</span> ·
            <span class="text-red-600">{{ $afwezig }} {{ __('afwezig') }}</span> ·
            <span class="text-orange-600">{{ $nietGewogen }} {{ __('niet gewogen') }}</span>
        </p>
    </div>
    <a href="{{ route('toernooi.weging.interface', $toernooi->routeParams()) }}" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded">
        ⚖️ {{ __('Weging Interface') }}
    </a>
</div>

{{-- Waarschuwing: poules niet in zaaloverzicht --}}
@if($zaaloverzichtWaarschuwing ?? false)
    <div class="bg-yellow-50 border border-yellow-300 rounded-lg p-4 mb-4">
        <h3 class="font-bold text-yellow-800 mb-1">{{ __('Poules staan nog niet in het zaaloverzicht') }}</h3>
        <p class="text-yellow-700 text-sm">
            {{ __('De poules zijn nog niet aan matten toegewezen. Ga naar') }} <strong>{{ __('Blokken') }}</strong> → <strong>{{ __('Verdeel over matten') }}</strong>
            {{ __('zodat de weeglijst, weegkaarten en het zaaloverzicht correct werken.') }}
        </p>
    </div>
@endif

@if($judokas->count() > 0)
<div class="bg-white rounded-lg shadow overflow-hidden" x-data="weeglijstTable">
    <!-- Zoekbalk -->
    <div class="px-4 py-3 bg-gray-50 border-b">
        <div class="flex gap-2 items-center flex-wrap">
            <div class="relative flex-1 min-w-[200px]">
                <input type="text"
                       x-model="zoekterm"
                       placeholder="{{ __('Zoek op naam, club, gewicht...') }}"
                       class="w-full border rounded-lg px-4 py-2 pl-10 focus:border-blue-500 focus:outline-none">
                <svg class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <button @click="toggleFilter('aanwezig')"
                    :class="filterButtonClass('aanwezig', 'bg-green-600')"
                    class="px-3 py-2 rounded text-sm font-medium whitespace-nowrap">
                {{ __('Aanwezig') }} ({{ $aanwezig }})
            </button>
            <button @click="toggleFilter('afwezig')"
                    :class="filterButtonClass('afwezig', 'bg-red-600')"
                    class="px-3 py-2 rounded text-sm font-medium whitespace-nowrap">
                {{ __('Afwezig') }} ({{ $afwezig }})
            </button>
            <button @click="toggleFilter('niet_gewogen')"
                    :class="filterButtonClass('niet_gewogen', 'bg-orange-600')"
                    class="px-3 py-2 rounded text-sm font-medium whitespace-nowrap">
                {{ __('Niet gewogen') }} ({{ $nietGewogen }})
            </button>
            <div x-show="hasFilters" class="bg-blue-100 border border-blue-300 rounded-lg px-3 py-2 flex items-center gap-2">
                <span class="text-blue-800 font-bold" x-text="filteredJudokas.length"></span>
                <span class="text-blue-700 text-sm">{{ __('resultaten') }}</span>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-blue-800 text-white sticky top-0 z-10">
                <tr>
                    <th @click="sort('naam')" class="px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                        <span class="flex items-center gap-1">{{ __('Naam') }} <template x-if="isSorting('naam')"><span x-text="sortIcon"></span></template></span>
                    </th>
                    <th @click="sort('club')" class="px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                        <span class="flex items-center gap-1">{{ __('Club') }} <template x-if="isSorting('club')"><span x-text="sortIcon"></span></template></span>
                    </th>
                    <th @click="sort('leeftijdsklasse')" class="px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                        <span class="flex items-center gap-1">{{ __('Categorie') }} <template x-if="isSorting('leeftijdsklasse')"><span x-text="sortIcon"></span></template></span>
                    </th>
                    <th @click="sort('gewichtsklasse')" class="px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                        <span class="flex items-center gap-1">{{ __('Klasse') }} <template x-if="isSorting('gewichtsklasse')"><span x-text="sortIcon"></span></template></span>
                    </th>
                    <th @click="sort('gewicht')" class="px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                        <span class="flex items-center gap-1">{{ __('Ingeschreven') }} <template x-if="isSorting('gewicht')"><span x-text="sortIcon"></span></template></span>
                    </th>
                    <th @click="sort('gewicht_gewogen')" class="px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                        <span class="flex items-center gap-1">{{ __('Gewogen') }} <template x-if="isSorting('gewicht_gewogen')"><span x-text="sortIcon"></span></template></span>
                    </th>
                    <th @click="sort('status')" class="px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                        <span class="flex items-center gap-1">{{ __('Status') }} <template x-if="isSorting('status')"><span x-text="sortIcon"></span></template></span>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <template x-for="j in sortedJudokas" :key="j.id">
                    <tr :class="rowClass(j)">
                        <td class="px-4 py-2 font-medium" x-text="j.naam"></td>
                        <td class="px-4 py-2 text-gray-600" x-text="clubOrDash(j)"></td>
                        <td class="px-4 py-2 text-sm" x-text="j.leeftijdsklasse"></td>
                        <td class="px-4 py-2 text-sm" x-text="gewichtsklasseLabel(j)"></td>
                        <td class="px-4 py-2 text-sm" x-text="gewichtLabel(j)"></td>
                        <td class="px-4 py-2">
                            <template x-if="j.gewicht_gewogen">
                                <span class="font-bold" :class="gewogenClass(j)" x-text="gewogenLabel(j)"></span>
                            </template>
                            <template x-if="nietGewogen(j)">
                                <span class="text-gray-400">-</span>
                            </template>
                        </td>
                        <td class="px-4 py-2">
                            <template x-if="statusAanwezig(j)">
                                <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">{{ __('Aanwezig') }}</span>
                            </template>
                            <template x-if="statusAfwezig(j)">
                                <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full">{{ __('Afwezig') }}</span>
                            </template>
                            <template x-if="statusOnbekend(j)">
                                <span class="px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded-full">-</span>
                            </template>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</div>

<script @nonce>
document.addEventListener('alpine:init', () => {
    Alpine.data('weeglijstTable', () => ({
        sortKey: null,
        sortAsc: true,
        zoekterm: '',
        filterStatus: null,
        judokas: [
            @foreach($judokas as $judoka)
            @php
                $blokNr = $judoka->poules->first()?->blok?->nummer;
                $blokIsClosed = $blokNr && ($blokGesloten[$blokNr] ?? false);
                $effectiveStatus = ($judoka->aanwezigheid === 'afwezig' && !$blokIsClosed) ? null : $judoka->aanwezigheid;
            @endphp
            {
                id: {{ $judoka->id }},
                naam: @json($judoka->naam),
                club: @json($judoka->club?->naam),
                leeftijdsklasse: @json($judoka->leeftijdsklasse),
                leeftijdsklasseOrder: {{ $judoka->sort_categorie ?? 99 }},
                gewichtsklasse: @json($judoka->gewichtsklasse),
                gewicht: {{ $judoka->gewicht ?? 'null' }},
                gewicht_gewogen: {{ $judoka->gewicht_gewogen ?? 'null' }},
                status: @json($effectiveStatus),
                overpouler: {{ ($judoka->isVasteGewichtsklasse() && !$judoka->isGewichtBinnenKlasse()) ? 'true' : 'false' }}
            },
            @endforeach
        ],

        // --- CSP-safe getters/helpers ---
        toggleFilter(status) { this.filterStatus = this.filterStatus === status ? null : status; },
        filterButtonClass(status, activeBg) {
            return this.filterStatus === status
                ? `${activeBg} text-white`
                : 'bg-gray-200 text-gray-700 hover:bg-gray-300';
        },
        get hasFilters() { return !!(this.zoekterm || this.filterStatus); },
        get sortIcon() { return this.sortAsc ? '▲' : '▼'; },
        isSorting(key) { return this.sortKey === key; },
        rowClass(j) { return j.overpouler ? 'bg-orange-50 hover:bg-orange-100' : 'hover:bg-gray-50'; },
        clubOrDash(j) { return j.club || '-'; },
        gewichtsklasseLabel(j) { return j.gewichtsklasse ? `${j.gewichtsklasse} kg` : '-'; },
        gewichtLabel(j) { return j.gewicht ? `${j.gewicht} kg` : '-'; },
        gewogenClass(j) { return j.overpouler ? 'text-orange-600' : ''; },
        gewogenLabel(j) { return `${j.gewicht_gewogen} kg`; },
        nietGewogen(j) { return !j.gewicht_gewogen; },
        statusAanwezig(j) { return j.status === 'aanwezig'; },
        statusAfwezig(j) { return j.status === 'afwezig'; },
        statusOnbekend(j) { return !j.status || (j.status !== 'aanwezig' && j.status !== 'afwezig'); },

        sort(key) {
            if (this.sortKey === key) {
                this.sortAsc = !this.sortAsc;
            } else {
                this.sortKey = key;
                this.sortAsc = true;
            }
        },

        get filteredJudokas() {
            let result = this.judokas;
            if (this.filterStatus === 'aanwezig') {
                result = result.filter(j => j.status === 'aanwezig');
            } else if (this.filterStatus === 'afwezig') {
                result = result.filter(j => j.status === 'afwezig');
            } else if (this.filterStatus === 'niet_gewogen') {
                result = result.filter(j => !j.gewicht_gewogen);
            }
            if (this.zoekterm) {
                const term = this.zoekterm.toLowerCase();
                result = result.filter(j => {
                    const searchText = [
                        j.naam, j.club, j.leeftijdsklasse, j.gewichtsklasse,
                        j.gewicht ? j.gewicht + 'kg' : '',
                        j.gewicht_gewogen ? j.gewicht_gewogen + 'kg' : ''
                    ].filter(Boolean).join(' ').toLowerCase();
                    return searchText.includes(term);
                });
            }
            return result;
        },

        get sortedJudokas() {
            const list = this.filteredJudokas;
            if (!this.sortKey) return list;
            return [...list].sort((a, b) => {
                let aVal, bVal;
                if (this.sortKey === 'naam' || this.sortKey === 'club') {
                    aVal = (a[this.sortKey] || '').toLowerCase();
                    bVal = (b[this.sortKey] || '').toLowerCase();
                } else if (this.sortKey === 'leeftijdsklasse') {
                    aVal = a.leeftijdsklasseOrder;
                    bVal = b.leeftijdsklasseOrder;
                } else if (this.sortKey === 'gewichtsklasse') {
                    aVal = parseFloat((a.gewichtsklasse || '999').replace(/[^0-9.-]/g, '')) || 999;
                    bVal = parseFloat((b.gewichtsklasse || '999').replace(/[^0-9.-]/g, '')) || 999;
                } else if (this.sortKey === 'gewicht' || this.sortKey === 'gewicht_gewogen') {
                    aVal = a[this.sortKey] ?? 999;
                    bVal = b[this.sortKey] ?? 999;
                } else if (this.sortKey === 'status') {
                    const order = {'aanwezig': 0, 'afwezig': 1, null: 2, '': 2};
                    aVal = order[a.status] ?? 2;
                    bVal = order[b.status] ?? 2;
                } else {
                    aVal = a[this.sortKey];
                    bVal = b[this.sortKey];
                }
                if (aVal < bVal) return this.sortAsc ? -1 : 1;
                if (aVal > bVal) return this.sortAsc ? 1 : -1;
                return 0;
            });
        },
    }));
});
</script>
@else
<div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
    {{ __("Geen judoka's gevonden") }}
</div>
@endif
@endsection
