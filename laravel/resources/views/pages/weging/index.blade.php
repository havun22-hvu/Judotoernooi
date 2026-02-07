@extends('layouts.app')

@section('title', 'Weeglijst')

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
        <h1 class="text-3xl font-bold text-gray-800">Weeglijst ({{ $judokas->count() }})</h1>
        <p class="text-sm text-gray-600 mt-1">
            <span class="text-green-600">{{ $aanwezig }} aanwezig</span> ·
            <span class="text-red-600">{{ $afwezig }} afwezig</span> ·
            <span class="text-orange-600">{{ $nietGewogen }} niet gewogen</span>
        </p>
    </div>
    <a href="{{ route('toernooi.weging.interface', $toernooi->routeParams()) }}" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded">
        ⚖️ Weging Interface
    </a>
</div>

@if($judokas->count() > 0)
<div class="bg-white rounded-lg shadow overflow-hidden" x-data="weeglijstTable()">
    <!-- Zoekbalk -->
    <div class="px-4 py-3 bg-gray-50 border-b">
        <div class="flex gap-2 items-center flex-wrap">
            <div class="relative flex-1 min-w-[200px]">
                <input type="text"
                       x-model="zoekterm"
                       placeholder="Zoek op naam, club, gewicht..."
                       class="w-full border rounded-lg px-4 py-2 pl-10 focus:border-blue-500 focus:outline-none">
                <svg class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <button @click="filterStatus = filterStatus === 'aanwezig' ? null : 'aanwezig'"
                    :class="filterStatus === 'aanwezig' ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
                    class="px-3 py-2 rounded text-sm font-medium whitespace-nowrap">
                Aanwezig ({{ $aanwezig }})
            </button>
            <button @click="filterStatus = filterStatus === 'afwezig' ? null : 'afwezig'"
                    :class="filterStatus === 'afwezig' ? 'bg-red-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
                    class="px-3 py-2 rounded text-sm font-medium whitespace-nowrap">
                Afwezig ({{ $afwezig }})
            </button>
            <button @click="filterStatus = filterStatus === 'niet_gewogen' ? null : 'niet_gewogen'"
                    :class="filterStatus === 'niet_gewogen' ? 'bg-orange-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
                    class="px-3 py-2 rounded text-sm font-medium whitespace-nowrap">
                Niet gewogen ({{ $nietGewogen }})
            </button>
            <div x-show="zoekterm || filterStatus" class="bg-blue-100 border border-blue-300 rounded-lg px-3 py-2 flex items-center gap-2">
                <span class="text-blue-800 font-bold" x-text="filteredJudokas.length"></span>
                <span class="text-blue-700 text-sm">resultaten</span>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-blue-800 text-white sticky top-0 z-10">
                <tr>
                    <th @click="sort('naam')" class="px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                        <span class="flex items-center gap-1">Naam <template x-if="sortKey === 'naam'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                    </th>
                    <th @click="sort('club')" class="px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                        <span class="flex items-center gap-1">Club <template x-if="sortKey === 'club'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                    </th>
                    <th @click="sort('leeftijdsklasse')" class="px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                        <span class="flex items-center gap-1">Categorie <template x-if="sortKey === 'leeftijdsklasse'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                    </th>
                    <th @click="sort('gewichtsklasse')" class="px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                        <span class="flex items-center gap-1">Klasse <template x-if="sortKey === 'gewichtsklasse'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                    </th>
                    <th @click="sort('gewicht')" class="px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                        <span class="flex items-center gap-1">Ingeschreven <template x-if="sortKey === 'gewicht'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                    </th>
                    <th @click="sort('gewicht_gewogen')" class="px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                        <span class="flex items-center gap-1">Gewogen <template x-if="sortKey === 'gewicht_gewogen'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                    </th>
                    <th @click="sort('status')" class="px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                        <span class="flex items-center gap-1">Status <template x-if="sortKey === 'status'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <template x-for="j in sortedJudokas" :key="j.id">
                    <tr :class="j.overpouler ? 'bg-orange-50 hover:bg-orange-100' : 'hover:bg-gray-50'">
                        <td class="px-4 py-2 font-medium" x-text="j.naam"></td>
                        <td class="px-4 py-2 text-gray-600" x-text="j.club || '-'"></td>
                        <td class="px-4 py-2 text-sm" x-text="j.leeftijdsklasse"></td>
                        <td class="px-4 py-2 text-sm" x-text="j.gewichtsklasse ? j.gewichtsklasse + ' kg' : '-'"></td>
                        <td class="px-4 py-2 text-sm" x-text="j.gewicht ? j.gewicht + ' kg' : '-'"></td>
                        <td class="px-4 py-2">
                            <template x-if="j.gewicht_gewogen">
                                <span class="font-bold" :class="j.overpouler ? 'text-orange-600' : ''" x-text="j.gewicht_gewogen + ' kg'"></span>
                            </template>
                            <template x-if="!j.gewicht_gewogen">
                                <span class="text-gray-400">-</span>
                            </template>
                        </td>
                        <td class="px-4 py-2">
                            <template x-if="j.status === 'aanwezig'">
                                <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">Aanwezig</span>
                            </template>
                            <template x-if="j.status === 'afwezig'">
                                <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full">Afwezig</span>
                            </template>
                            <template x-if="!j.status || (j.status !== 'aanwezig' && j.status !== 'afwezig')">
                                <span class="px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded-full">-</span>
                            </template>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</div>

<script>
function weeglijstTable() {
    return {
        sortKey: null,
        sortAsc: true,
        zoekterm: '',
        filterStatus: null,
        judokas: [
            @foreach($judokas as $judoka)
            @php
                $blokNr = $judoka->poules->first()?->blok?->nummer;
                $blokIsClosed = $blokNr && ($blokGesloten[$blokNr] ?? false);
                // Only show 'afwezig' status if blok is closed
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

            // Filter on status
            if (this.filterStatus === 'aanwezig') {
                result = result.filter(j => j.status === 'aanwezig');
            } else if (this.filterStatus === 'afwezig') {
                result = result.filter(j => j.status === 'afwezig');
            } else if (this.filterStatus === 'niet_gewogen') {
                result = result.filter(j => !j.gewicht_gewogen);
            }

            // Filter on search term
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
        }
    };
}
</script>
@else
<div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
    Geen judoka's gevonden
</div>
@endif
@endsection
