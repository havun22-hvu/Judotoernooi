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

<!-- Toast notification -->
<div id="toast" class="fixed top-4 right-4 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300 z-50">
    <span id="toast-message"></span>
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
                        <span x-text="judoka.gewichtsklasse"></span>
                    </div>
                </div>
            </a>
        </template>
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

<!-- Judoka tabel -->
@if($judokas->count() > 0)
<div class="bg-white rounded-lg shadow overflow-hidden" x-data="judokaTable()">
    <div class="px-4 py-2 bg-gray-100 border-b text-sm text-gray-600">
        <span class="font-medium">Tip:</span> Klik op een cel om te bewerken. Druk Enter om op te slaan, Escape om te annuleren.
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
                <th @click="sort('gewicht')" class="px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                    <span class="flex items-center gap-1">Gewicht <template x-if="sortKey === 'gewicht'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase">Acties</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <template x-for="judoka in sortedJudokas" :key="judoka.id">
                <tr :class="judoka.incompleet ? 'bg-red-50 hover:bg-red-100' : 'hover:bg-gray-50'">
                    <!-- Naam (editable) -->
                    <td class="px-4 py-2">
                        <template x-if="editing.id === judoka.id && editing.field === 'naam'">
                            <input type="text" x-model="editing.value"
                                   @keydown.enter="saveEdit(judoka)"
                                   @keydown.escape="cancelEdit()"
                                   @blur="saveEdit(judoka)"
                                   x-ref="editInput"
                                   class="w-full px-2 py-1 border rounded focus:outline-none focus:border-blue-500">
                        </template>
                        <template x-if="!(editing.id === judoka.id && editing.field === 'naam')">
                            <span @click="startEdit(judoka, 'naam', judoka.naam)"
                                  class="cursor-pointer hover:bg-blue-100 px-1 rounded text-blue-600 font-medium"
                                  x-text="judoka.naam"></span>
                        </template>
                        <span x-show="judoka.incompleet" class="ml-2 text-red-600 text-xs">⚠</span>
                    </td>

                    <!-- Leeftijdsklasse (read-only, calculated) -->
                    <td class="px-4 py-2 text-sm text-gray-600" x-text="judoka.leeftijdsklasse"></td>

                    <!-- Gewichtsklasse (editable) -->
                    <td class="px-4 py-2">
                        <template x-if="editing.id === judoka.id && editing.field === 'gewichtsklasse'">
                            <input type="text" x-model="editing.value"
                                   @keydown.enter="saveEdit(judoka)"
                                   @keydown.escape="cancelEdit()"
                                   @blur="saveEdit(judoka)"
                                   placeholder="-40"
                                   class="w-20 px-2 py-1 border rounded focus:outline-none focus:border-blue-500">
                        </template>
                        <template x-if="!(editing.id === judoka.id && editing.field === 'gewichtsklasse')">
                            <span @click="startEdit(judoka, 'gewichtsklasse', judoka.gewichtsklasse)"
                                  class="cursor-pointer hover:bg-blue-100 px-1 rounded"
                                  :class="!judoka.gewichtsklasse ? 'text-red-600 font-medium' : ''"
                                  x-text="judoka.gewichtsklasse || '-'"></span>
                        </template>
                    </td>

                    <!-- Geslacht (editable dropdown) -->
                    <td class="px-4 py-2">
                        <template x-if="editing.id === judoka.id && editing.field === 'geslacht'">
                            <select x-model="editing.value"
                                    @change="saveEdit(judoka)"
                                    @keydown.escape="cancelEdit()"
                                    class="px-2 py-1 border rounded focus:outline-none focus:border-blue-500">
                                <option value="M">Jongen</option>
                                <option value="V">Meisje</option>
                            </select>
                        </template>
                        <template x-if="!(editing.id === judoka.id && editing.field === 'geslacht')">
                            <span @click="startEdit(judoka, 'geslacht', judoka.geslachtCode)"
                                  class="cursor-pointer hover:bg-blue-100 px-1 rounded"
                                  x-text="judoka.geslacht"></span>
                        </template>
                    </td>

                    <!-- Band (editable dropdown) -->
                    <td class="px-4 py-2">
                        <template x-if="editing.id === judoka.id && editing.field === 'band'">
                            <select x-model="editing.value"
                                    @change="saveEdit(judoka)"
                                    @keydown.escape="cancelEdit()"
                                    class="px-2 py-1 border rounded focus:outline-none focus:border-blue-500">
                                <option value="">-</option>
                                <option value="wit">Wit</option>
                                <option value="geel">Geel</option>
                                <option value="oranje">Oranje</option>
                                <option value="groen">Groen</option>
                                <option value="blauw">Blauw</option>
                                <option value="bruin">Bruin</option>
                                <option value="zwart">Zwart</option>
                            </select>
                        </template>
                        <template x-if="!(editing.id === judoka.id && editing.field === 'band')">
                            <span @click="startEdit(judoka, 'band', judoka.bandRaw)"
                                  class="cursor-pointer hover:bg-blue-100 px-1 rounded"
                                  :class="!judoka.band ? 'text-red-600 font-medium' : ''"
                                  x-text="judoka.band || '-'"></span>
                        </template>
                    </td>

                    <!-- Gewicht (editable) -->
                    <td class="px-4 py-2">
                        <template x-if="editing.id === judoka.id && editing.field === 'gewicht'">
                            <input type="number" step="0.1" x-model="editing.value"
                                   @keydown.enter="saveEdit(judoka)"
                                   @keydown.escape="cancelEdit()"
                                   @blur="saveEdit(judoka)"
                                   placeholder="35.5"
                                   class="w-20 px-2 py-1 border rounded focus:outline-none focus:border-blue-500">
                        </template>
                        <template x-if="!(editing.id === judoka.id && editing.field === 'gewicht')">
                            <span @click="startEdit(judoka, 'gewicht', judoka.gewicht)"
                                  class="cursor-pointer hover:bg-blue-100 px-1 rounded text-gray-500"
                                  x-text="judoka.gewicht ? judoka.gewicht + ' kg' : '-'"></span>
                        </template>
                    </td>

                    <!-- Acties -->
                    <td class="px-4 py-2">
                        <a :href="judoka.url" class="text-blue-600 hover:text-blue-800 text-sm">Details</a>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>
</div>

<script>
function judokaTable() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const baseUrl = '{{ url("toernooi/{$toernooi->id}/judoka") }}';

    return {
        sortKey: null,
        sortAsc: true,
        editing: { id: null, field: null, value: null },
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
                geslachtCode: '{{ $judoka->geslacht }}',
                band: @json($judoka->band ? ucfirst($judoka->band) : null),
                bandRaw: @json($judoka->band),
                bandOrder: {{ array_search(strtolower($judoka->band ?? ''), ['wit', 'geel', 'oranje', 'groen', 'blauw', 'bruin', 'zwart']) !== false ? array_search(strtolower($judoka->band ?? ''), ['wit', 'geel', 'oranje', 'groen', 'blauw', 'bruin', 'zwart']) : 99 }},
                gewicht: {{ $judoka->gewicht ? number_format($judoka->gewicht, 1) : 'null' }},
                incompleet: {{ (!$judoka->club_id || !$judoka->band || !$judoka->geboortejaar || !$judoka->gewichtsklasse) ? 'true' : 'false' }},
                url: '{{ route("toernooi.judoka.show", [$toernooi, $judoka]) }}'
            },
            @endforeach
        ],

        get sortedJudokas() {
            if (!this.sortKey) return this.judokas;
            return [...this.judokas].sort((a, b) => {
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
                } else if (this.sortKey === 'gewicht') {
                    aVal = a.gewicht || 9999;
                    bVal = b.gewicht || 9999;
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
        },

        startEdit(judoka, field, value) {
            this.editing = { id: judoka.id, field, value: value || '' };
            this.$nextTick(() => {
                const input = this.$refs.editInput;
                if (input) input.focus();
            });
        },

        cancelEdit() {
            this.editing = { id: null, field: null, value: null };
        },

        async saveEdit(judoka) {
            if (this.editing.id !== judoka.id) return;

            const field = this.editing.field;
            let value = this.editing.value;

            // Skip if no change
            if (field === 'naam' && value === judoka.naam) { this.cancelEdit(); return; }
            if (field === 'gewichtsklasse' && value === judoka.gewichtsklasse) { this.cancelEdit(); return; }
            if (field === 'geslacht' && value === judoka.geslachtCode) { this.cancelEdit(); return; }
            if (field === 'band' && value === judoka.bandRaw) { this.cancelEdit(); return; }
            if (field === 'gewicht' && parseFloat(value) === judoka.gewicht) { this.cancelEdit(); return; }

            try {
                const response = await fetch(`${baseUrl}/${judoka.id}/update-api`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ [field]: value || null })
                });

                const data = await response.json();

                if (data.success) {
                    // Update local data
                    judoka.naam = data.judoka.naam;
                    judoka.leeftijdsklasse = data.judoka.leeftijdsklasse;
                    judoka.gewichtsklasse = data.judoka.gewichtsklasse;
                    judoka.geslachtCode = data.judoka.geslacht;
                    judoka.geslacht = data.judoka.geslacht === 'M' ? 'Jongen' : 'Meisje';
                    judoka.bandRaw = data.judoka.band;
                    judoka.band = data.judoka.band ? data.judoka.band.charAt(0).toUpperCase() + data.judoka.band.slice(1) : null;
                    judoka.gewicht = data.judoka.gewicht;

                    // Update sort values
                    if (data.judoka.gewichtsklasse) {
                        const match = data.judoka.gewichtsklasse.match(/([+-]?)(\d+)/);
                        judoka.gewichtsklasseNum = match ? (parseInt(match[2]) + (match[1] === '+' ? 1000 : 0)) : 999;
                    }
                    const bands = ['wit', 'geel', 'oranje', 'groen', 'blauw', 'bruin', 'zwart'];
                    judoka.bandOrder = data.judoka.band ? bands.indexOf(data.judoka.band.toLowerCase()) : 99;
                    if (judoka.bandOrder === -1) judoka.bandOrder = 99;

                    // Update incomplete status
                    judoka.incompleet = !data.judoka.band || !data.judoka.gewichtsklasse;

                    this.showToast('Opgeslagen');
                }
            } catch (error) {
                console.error('Error:', error);
                this.showToast('Fout bij opslaan', true);
            }

            this.cancelEdit();
        },

        showToast(message, isError = false) {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toast-message');
            toastMessage.textContent = message;
            toast.classList.remove('translate-x-full', 'bg-green-600', 'bg-red-600');
            toast.classList.add(isError ? 'bg-red-600' : 'bg-green-600');
            setTimeout(() => toast.classList.add('translate-x-full'), 2000);
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
