@extends('layouts.app')

@section('title', 'Mijn Judoka\'s - ' . $organisator->naam)

@section('content')
<div class="max-w-6xl mx-auto" x-data="stambestandPage()">
    {{-- Header --}}
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Mijn Judoka's</h1>
            <p class="text-gray-500">Stambestand - persistent overzicht van alle judoka's</p>
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

    {{-- Feedback --}}
    <div x-show="feedback" x-transition x-cloak
         :class="feedbackType === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'"
         class="border rounded px-4 py-3 mb-4">
        <span x-text="feedback"></span>
    </div>

    {{-- Zoek + Filter --}}
    <div class="mb-4 flex flex-wrap gap-3 items-center">
        <input type="text" x-model="zoek" placeholder="Zoek judoka..."
               class="w-full md:w-72 border rounded px-3 py-2 text-sm">
        <select x-model="filter" class="border rounded px-3 py-2 text-sm">
            <option value="actief">Actief</option>
            <option value="inactief">Inactief</option>
            <option value="alle">Alle</option>
        </select>
        <span class="text-sm text-gray-500" x-text="gefilterd.length + ' judoka\'s'"></span>
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
                <select x-model="form.band" required class="w-full border rounded px-3 py-2 text-sm">
                    <option value="wit">Wit</option>
                    <option value="geel">Geel</option>
                    <option value="oranje">Oranje</option>
                    <option value="groen">Groen</option>
                    <option value="blauw">Blauw</option>
                    <option value="bruin">Bruin</option>
                    <option value="zwart">Zwart</option>
                </select>
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
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-block px-2 py-0.5 rounded text-xs font-medium"
                                          :class="bandClass(judoka.band)"
                                          x-text="judoka.band.charAt(0).toUpperCase() + judoka.band.slice(1)"></span>
                                </td>
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
                Upload een CSV bestand met kolommen: <strong>naam</strong>, <strong>geboortejaar</strong>,
                geslacht (M/V), band, gewicht.
            </p>
            <form @submit.prevent="importCsv()">
                <input type="file" x-ref="csvFile" accept=".csv,.txt" required
                       class="w-full border rounded px-3 py-2 text-sm mb-4">
                <div class="flex gap-3">
                    <button type="submit" :disabled="importing"
                            class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-6 rounded text-sm">
                        <span x-text="importing ? 'Importeren...' : 'Importeer'"></span>
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
        importing: false,
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
                const z = this.zoek.toLowerCase();
                lijst = lijst.filter(j => j.naam.toLowerCase().includes(z));
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

        bandClass(band) {
            const classes = {
                wit: 'bg-gray-100 text-gray-800 border border-gray-300',
                geel: 'bg-yellow-100 text-yellow-800',
                oranje: 'bg-orange-100 text-orange-800',
                groen: 'bg-green-100 text-green-800',
                blauw: 'bg-blue-100 text-blue-800',
                bruin: 'bg-amber-800 text-white',
                zwart: 'bg-gray-900 text-white',
            };
            return classes[band] || 'bg-gray-100 text-gray-800';
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

        async importCsv() {
            this.importing = true;
            this.feedback = '';

            const formData = new FormData();
            formData.append('csv_file', this.$refs.csvFile.files[0]);

            try {
                const response = await fetch('{{ route("organisator.stambestand.import", $organisator) }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                const data = await response.json();

                if (!response.ok) {
                    this.feedback = data.error || data.message || 'Import mislukt';
                    this.feedbackType = 'error';
                } else if (data.success) {
                    this.feedback = data.message;
                    this.feedbackType = 'success';
                    this.showImportModal = false;
                    setTimeout(() => location.reload(), 1500);
                }
            } catch (e) {
                this.feedback = 'Verbindingsfout';
                this.feedbackType = 'error';
            }

            this.importing = false;
        },
    }
}
</script>
@endsection
