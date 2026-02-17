@extends('layouts.app')

@section('title', 'Judoka\'s ' . ($organisator->organisatie_naam ?: $organisator->naam))

@section('content')
<div class="max-w-6xl mx-auto" x-data="stambestandPage()">
    {{-- Header --}}
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Judoka's van {{ $organisator->organisatie_naam ?: $organisator->naam }}</h1>
            <p class="text-gray-500">Stambestand - alle judoka's van jouw club</p>
        </div>
        <div class="flex items-center gap-3">
            <button @click="showImportModal = true"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-lg text-sm">
                CSV Import
            </button>
            <button @click="openForm()"
                    class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg text-sm">
                + Judoka toevoegen
            </button>
            <a href="{{ route('organisator.dashboard', $organisator) }}"
               class="text-blue-600 hover:text-blue-800">
                &larr; Terug
            </a>
        </div>
    </div>

    {{-- Flash messages (na import redirect) --}}
    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 rounded px-4 py-3 mb-4">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 rounded px-4 py-3 mb-4">
            {{ session('error') }}
        </div>
    @endif
    @if(session('import_fouten'))
        <div class="bg-yellow-50 border border-yellow-300 rounded p-3 mb-4 text-sm max-h-40 overflow-y-auto">
            <p class="font-medium text-yellow-800 mb-1">{{ count(session('import_fouten')) }} fout(en):</p>
            @foreach(session('import_fouten') as $fout)
                <p class="text-yellow-700 text-xs">{{ $fout }}</p>
            @endforeach
        </div>
    @endif

    {{-- Feedback (AJAX) --}}
    <div x-show="feedback" x-transition x-cloak
         :class="feedbackType === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'"
         class="border rounded px-4 py-3 mb-4">
        <span x-text="feedback"></span>
    </div>

    {{-- Zoek + Filter --}}
    <div class="mb-4 flex flex-wrap gap-3 items-center">
        <div class="relative flex-1 min-w-[200px]">
            <input type="text" x-model="zoek"
                   placeholder="Filter: naam, band, -45kg, +55kg, 20-30kg..."
                   class="w-full border rounded-lg px-4 py-2 pl-10 text-sm focus:border-blue-500 focus:outline-none">
            <svg class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>
        <select x-model="filter" class="border rounded px-3 py-2 text-sm">
            <option value="actief">Actief</option>
            <option value="inactief">Inactief</option>
            <option value="alle">Alle</option>
        </select>
        <div x-show="zoek" x-cloak class="bg-green-100 border border-green-300 rounded-lg px-3 py-1.5 flex items-center gap-1">
            <span class="text-green-800 font-bold" x-text="gefilterd.length"></span>
            <span class="text-green-700 text-sm">resultaten</span>
        </div>
        <span x-show="!zoek" class="text-sm text-gray-500" x-text="gefilterd.length + ' judoka\'s'"></span>
    </div>

    {{-- Toevoegen/bewerken formulier --}}
    <div x-show="showForm" x-transition x-cloak class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4" x-text="editId ? 'Judoka bewerken' : 'Judoka toevoegen'"></h3>
        <form @submit.prevent="saveJudoka()" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Naam *</label>
                <input type="text" x-model="form.naam" required
                       class="w-full border rounded px-3 py-2 text-sm" placeholder="Voornaam Achternaam">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Geboortejaar *</label>
                <input type="number" x-model="form.geboortejaar" required min="1950" max="{{ date('Y') }}"
                       class="w-full border rounded px-3 py-2 text-sm" placeholder="{{ date('Y') - 10 }}">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Geslacht *</label>
                <select x-model="form.geslacht" required class="w-full border rounded px-3 py-2 text-sm">
                    <option value="M">Man</option>
                    <option value="V">Vrouw</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Band *</label>
                <input type="text" x-model="form.band" required
                       class="w-full border rounded px-3 py-2 text-sm" placeholder="bijv. wit, geel, oranje">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Gewicht (kg)</label>
                <input type="number" x-model="form.gewicht" step="0.1" min="10" max="200"
                       class="w-full border rounded px-3 py-2 text-sm" placeholder="Optioneel">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notities</label>
                <input type="text" x-model="form.notities"
                       class="w-full border rounded px-3 py-2 text-sm" placeholder="Optioneel">
            </div>
            <div class="md:col-span-3 flex gap-3">
                <button type="submit" :disabled="saving"
                        class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-6 rounded text-sm">
                    <span x-text="saving ? 'Opslaan...' : (editId ? 'Bijwerken' : 'Toevoegen')"></span>
                </button>
                <button type="button" @click="showForm = false"
                        class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-6 rounded text-sm">
                    Annuleren
                </button>
            </div>
        </form>
    </div>

    {{-- Judoka tabel --}}
    <div class="bg-white rounded-lg shadow">
        <template x-if="judokas.length === 0">
            <div class="p-6 text-center text-gray-500">
                Nog geen judoka's in het stambestand. Voeg ze toe via het formulier of importeer een CSV.
            </div>
        </template>

        <template x-if="judokas.length > 0">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th @click="sorteer('naam')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:text-gray-700">
                                Naam
                                <span x-show="sortKolom === 'naam'" x-text="sortRichting === 'asc' ? ' &#9650;' : ' &#9660;'"></span>
                            </th>
                            <th @click="sorteer('geboortejaar')" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase cursor-pointer hover:text-gray-700">
                                Geb.jaar
                                <span x-show="sortKolom === 'geboortejaar'" x-text="sortRichting === 'asc' ? ' &#9650;' : ' &#9660;'"></span>
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Geslacht</th>
                            <th @click="sorteer('band')" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase cursor-pointer hover:text-gray-700">
                                Band
                                <span x-show="sortKolom === 'band'" x-text="sortRichting === 'asc' ? ' &#9650;' : ' &#9660;'"></span>
                            </th>
                            <th @click="sorteer('gewicht')" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase cursor-pointer hover:text-gray-700">
                                Gewicht
                                <span x-show="sortKolom === 'gewicht'" x-text="sortRichting === 'asc' ? ' &#9650;' : ' &#9660;'"></span>
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acties</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <template x-for="judoka in gefilterd" :key="judoka.id">
                            <tr class="hover:bg-gray-50" :class="{ 'opacity-50': !judoka.actief }">
                                <td class="px-4 py-3 font-medium" x-text="judoka.naam"></td>
                                <td class="px-4 py-3 text-center text-gray-600" x-text="judoka.geboortejaar"></td>
                                <td class="px-4 py-3 text-center text-gray-600" x-text="judoka.geslacht"></td>
                                <td class="px-4 py-3 text-center text-gray-600" x-text="judoka.band.charAt(0).toUpperCase() + judoka.band.slice(1)"></td>
                                <td class="px-4 py-3 text-center text-gray-600">
                                    <span x-text="judoka.gewicht ? judoka.gewicht + ' kg' : '-'"></span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button @click="editJudoka(judoka)"
                                                class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium px-3 py-1 rounded">
                                            Bewerk
                                        </button>
                                        <button @click="toggleActief(judoka)"
                                                class="text-xs font-medium px-3 py-1 rounded"
                                                :class="judoka.actief ? 'bg-yellow-100 hover:bg-yellow-200 text-yellow-700' : 'bg-green-100 hover:bg-green-200 text-green-700'"
                                                x-text="judoka.actief ? 'Archiveer' : 'Activeer'">
                                        </button>
                                        <button @click="deleteJudoka(judoka)"
                                                class="bg-red-100 hover:bg-red-200 text-red-700 text-xs font-medium px-3 py-1 rounded">
                                            Verwijder
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>
    </div>

    {{-- CSV Import Modal --}}
    <div x-show="showImportModal" x-transition x-cloak
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
         @click.self="showImportModal = false">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-4">CSV Import</h3>
            <p class="text-sm text-gray-600 mb-4">
                Upload een CSV of Excel bestand met kolommen: <strong>naam</strong>, <strong>geboortejaar</strong>,
                geslacht (M/V), band, gewicht. Na upload kun je de kolom-toewijzing controleren.
            </p>
            <form action="{{ route('organisator.stambestand.import.upload', $organisator) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="file" name="bestand" accept=".csv,.txt,.xlsx,.xls" required
                       class="w-full border rounded px-3 py-2 text-sm mb-4">
                <div class="flex gap-3">
                    <button type="submit"
                            class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-6 rounded text-sm">
                        Upload &amp; Preview
                    </button>
                    <button type="button" @click="showImportModal = false"
                            class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-6 rounded text-sm">
                        Annuleren
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function stambestandPage() {
    return {
        zoek: '',
        filter: 'actief',
        feedback: '',
        feedbackType: 'success',
        sortKolom: 'naam',
        sortRichting: 'asc',
        showForm: false,
        showImportModal: false,
        saving: false,
        editId: null,
        form: { naam: '', geboortejaar: '', geslacht: 'M', band: 'wit', gewicht: '', notities: '' },
        judokas: @php
            $judokaData = $judokas->map(function($j) {
                return [
                    'id' => $j->id,
                    'naam' => $j->naam,
                    'geboortejaar' => $j->geboortejaar,
                    'geslacht' => $j->geslacht,
                    'band' => $j->band,
                    'gewicht' => $j->gewicht,
                    'notities' => $j->notities,
                    'actief' => $j->actief,
                ];
            })->values();
        @endphp {!! json_encode($judokaData) !!},

        bandVolgorde: { wit: 0, geel: 1, oranje: 2, groen: 3, blauw: 4, bruin: 5, zwart: 6 },

        get gefilterd() {
            let lijst = this.judokas;

            if (this.filter === 'actief') lijst = lijst.filter(j => j.actief);
            else if (this.filter === 'inactief') lijst = lijst.filter(j => !j.actief);

            if (this.zoek) {
                const terms = this.zoek.toLowerCase().split(/\s+/).filter(t => t.length > 0);
                const gewichtFilters = [];
                const tekstTerms = [];

                terms.forEach(term => {
                    // Weight patterns: -45, +55, 20-30, 20kg
                    const clean = term.replace(/kg$/i, '');
                    if (/^[+-]?\d+(\.\d+)?$/.test(clean) || /^\d+(\.\d+)?-\d+(\.\d+)?$/.test(clean)) {
                        gewichtFilters.push(clean);
                    } else {
                        tekstTerms.push(term);
                    }
                });

                // Apply weight filters
                gewichtFilters.forEach(f => {
                    if (f.startsWith('-')) {
                        const max = parseFloat(f.substring(1));
                        lijst = lijst.filter(j => j.gewicht && parseFloat(j.gewicht) <= max);
                    } else if (f.startsWith('+')) {
                        const min = parseFloat(f.substring(1));
                        lijst = lijst.filter(j => j.gewicht && parseFloat(j.gewicht) >= min);
                    } else if (f.includes('-')) {
                        const [min, max] = f.split('-').map(Number);
                        lijst = lijst.filter(j => j.gewicht && parseFloat(j.gewicht) >= min && parseFloat(j.gewicht) <= max);
                    } else {
                        const exact = parseFloat(f);
                        lijst = lijst.filter(j => j.gewicht && Math.abs(parseFloat(j.gewicht) - exact) < 0.5);
                    }
                });

                // Text search over all fields
                if (tekstTerms.length > 0) {
                    lijst = lijst.filter(j => {
                        const text = [j.naam, j.geboortejaar, j.geslacht, j.band, j.gewicht ? j.gewicht + 'kg' : '', j.notities || '']
                            .filter(Boolean).join(' ').toLowerCase();
                        return tekstTerms.every(term => text.includes(term));
                    });
                }
            }

            const kolom = this.sortKolom;
            const richting = this.sortRichting === 'asc' ? 1 : -1;

            return [...lijst].sort((a, b) => {
                let va = a[kolom];
                let vb = b[kolom];
                if (kolom === 'band') {
                    va = this.bandVolgorde[va] ?? 99;
                    vb = this.bandVolgorde[vb] ?? 99;
                }
                if (typeof va === 'string') {
                    return va.localeCompare(vb, 'nl') * richting;
                }
                return ((va ?? 0) - (vb ?? 0)) * richting;
            });
        },

        sorteer(kolom) {
            if (this.sortKolom === kolom) {
                this.sortRichting = this.sortRichting === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortKolom = kolom;
                this.sortRichting = kolom === 'naam' ? 'asc' : 'desc';
            }
        },

        openForm() {
            this.editId = null;
            this.form = { naam: '', geboortejaar: '', geslacht: 'M', band: 'wit', gewicht: '', notities: '' };
            this.showForm = true;
        },

        editJudoka(judoka) {
            this.editId = judoka.id;
            this.form = {
                naam: judoka.naam,
                geboortejaar: judoka.geboortejaar,
                geslacht: judoka.geslacht,
                band: judoka.band,
                gewicht: judoka.gewicht || '',
                notities: judoka.notities || '',
            };
            this.showForm = true;
        },

        async saveJudoka() {
            this.saving = true;
            this.feedback = '';

            const url = this.editId
                ? `{{ url($organisator->slug . '/judokas') }}/${this.editId}`
                : '{{ route("organisator.stambestand.store", $organisator) }}';

            const method = this.editId ? 'PUT' : 'POST';
            const body = { ...this.form };
            if (!body.gewicht) body.gewicht = null;
            if (!body.notities) body.notities = null;

            try {
                const response = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(body),
                });

                const data = await response.json();

                if (!response.ok) {
                    const errors = data.errors ? Object.values(data.errors).flat().join(', ') : (data.message || 'Fout bij opslaan');
                    this.feedback = errors;
                    this.feedbackType = 'error';
                    this.saving = false;
                    return;
                }

                if (data.success) {
                    if (this.editId) {
                        const idx = this.judokas.findIndex(j => j.id === this.editId);
                        if (idx !== -1) this.judokas[idx] = { ...this.judokas[idx], ...data.judoka };
                    } else {
                        this.judokas.push({ ...data.judoka, actief: true });
                    }
                    this.showForm = false;
                    this.feedback = this.editId ? 'Judoka bijgewerkt' : 'Judoka toegevoegd';
                    this.feedbackType = 'success';
                }
            } catch (e) {
                this.feedback = 'Verbindingsfout';
                this.feedbackType = 'error';
            }

            this.saving = false;
        },

        async toggleActief(judoka) {
            try {
                const response = await fetch(`{{ url($organisator->slug . '/judokas') }}/${judoka.id}/toggle`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                });
                const data = await response.json();
                if (data.success) {
                    judoka.actief = data.actief;
                    this.feedback = data.actief ? 'Judoka geactiveerd' : 'Judoka gearchiveerd';
                    this.feedbackType = 'success';
                }
            } catch (e) {
                this.feedback = 'Verbindingsfout';
                this.feedbackType = 'error';
            }
        },

        async deleteJudoka(judoka) {
            if (!confirm(`"${judoka.naam}" verwijderen uit het stambestand?`)) return;

            try {
                const response = await fetch(`{{ url($organisator->slug . '/judokas') }}/${judoka.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                });
                const data = await response.json();
                if (data.success) {
                    this.judokas = this.judokas.filter(j => j.id !== judoka.id);
                    this.feedback = 'Judoka verwijderd';
                    this.feedbackType = 'success';
                }
            } catch (e) {
                this.feedback = 'Verbindingsfout';
                this.feedbackType = 'error';
            }
        },

    }
}
</script>
@endsection
