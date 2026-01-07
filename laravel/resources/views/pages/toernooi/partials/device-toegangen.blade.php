{{-- Vrijwilligers Beheer --}}
<div class="bg-white rounded-lg shadow p-6 mb-6" x-data="vrijwilligersBeheer()">
    <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b">Vrijwilligers</h2>
    <p class="text-gray-600 mb-4">
        Beheer vrijwilligers met PWA toegang. Elke vrijwilliger krijgt een unieke URL en PIN.
    </p>

    {{-- Vrijwilligers Tabel --}}
    <div class="overflow-x-auto mb-4">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Naam</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Telefoon</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Email</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Rol</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-700">Acties</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <template x-if="vrijwilligers.length === 0">
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400 italic">
                            Nog geen vrijwilligers toegevoegd
                        </td>
                    </tr>
                </template>
                <template x-for="v in vrijwilligers" :key="v.id">
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900" x-text="v.naam"></td>
                        <td class="px-4 py-3 text-gray-600" x-text="v.telefoon || '-'"></td>
                        <td class="px-4 py-3 text-gray-600" x-text="v.email || '-'"></td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium"
                                  :class="rolKleuren[v.rol]">
                                <span x-text="rolIcoon(v.rol)"></span>
                                <span x-text="v.label"></span>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1 text-xs"
                                  :class="v.is_gebonden ? 'text-green-600' : 'text-gray-400'">
                                <span x-text="v.is_gebonden ? '✓' : '⏳'"></span>
                                <span x-text="v.is_gebonden ? 'Gebonden' : 'Wacht'"></span>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" @click="copyUrl(v)"
                                        class="text-blue-600 hover:text-blue-800 px-2 py-1 text-xs rounded hover:bg-blue-50"
                                        :title="'URL: ' + v.url + ' PIN: ' + v.pincode">
                                    <span x-show="copiedId !== v.id">URL</span>
                                    <span x-show="copiedId === v.id" x-cloak>OK!</span>
                                </button>
                                <button type="button" @click="resetVrijwilliger(v)" x-show="v.is_gebonden"
                                        class="text-orange-600 hover:text-orange-800 px-2 py-1 text-xs rounded hover:bg-orange-50">
                                    Reset
                                </button>
                                <button type="button" @click="openEditModal(v)"
                                        class="text-gray-600 hover:text-gray-800 px-2 py-1 text-xs rounded hover:bg-gray-100">
                                    Bewerk
                                </button>
                                <button type="button" @click="deleteVrijwilliger(v)"
                                        class="text-red-400 hover:text-red-600 px-2 py-1 text-xs rounded hover:bg-red-50">
                                    &times;
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    {{-- Add Button --}}
    <button type="button" @click="openAddModal()"
            class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded text-sm flex items-center gap-1">
        <span>+</span> Vrijwilliger toevoegen
    </button>

    {{-- Reset All --}}
    <div class="mt-6 pt-4 border-t">
        <button type="button" @click="resetAll()"
                class="text-red-600 hover:text-red-800 text-sm">
            Alle device bindings resetten
        </button>
        <span class="text-xs text-gray-400 ml-2">(voor nieuw toernooi of bij problemen)</span>
    </div>

    {{-- Modal --}}
    <div x-show="showModal" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50" @click="closeModal()"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-bold mb-4" x-text="editingId ? 'Vrijwilliger bewerken' : 'Vrijwilliger toevoegen'"></h3>

                <form @submit.prevent="saveVrijwilliger()">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Naam *</label>
                            <input type="text" x-model="form.naam" required
                                   class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telefoon</label>
                            <input type="tel" x-model="form.telefoon"
                                   class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="06-12345678">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" x-model="form.email"
                                   class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="naam@email.nl">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Rol *</label>
                            <select x-model="form.rol" required
                                    class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Selecteer rol...</option>
                                <template x-for="rol in rollen" :key="rol.key">
                                    <option :value="rol.key" x-text="rol.icon + ' ' + rol.naam"></option>
                                </template>
                            </select>
                        </div>
                        <div x-show="form.rol === 'mat'">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mat nummer *</label>
                            <input type="number" x-model="form.mat_nummer" min="1"
                                   class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 mt-6">
                        <button type="button" @click="closeModal()"
                                class="px-4 py-2 text-gray-600 hover:text-gray-800">
                            Annuleren
                        </button>
                        <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                            <span x-text="editingId ? 'Opslaan' : 'Toevoegen'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function vrijwilligersBeheer() {
    return {
        vrijwilligers: [],
        showModal: false,
        editingId: null,
        copiedId: null,
        form: {
            naam: '',
            telefoon: '',
            email: '',
            rol: '',
            mat_nummer: null,
        },
        rollen: [
            { key: 'hoofdjury', naam: 'Hoofdjury', icon: '⚖️' },
            { key: 'mat', naam: 'Mat', icon: '🥋' },
            { key: 'weging', naam: 'Weging', icon: '⚖️' },
            { key: 'spreker', naam: 'Spreker', icon: '🎙️' },
            { key: 'dojo', naam: 'Dojo', icon: '🚪' },
        ],
        rolKleuren: {
            hoofdjury: 'bg-purple-100 text-purple-800',
            mat: 'bg-blue-100 text-blue-800',
            weging: 'bg-green-100 text-green-800',
            spreker: 'bg-yellow-100 text-yellow-800',
            dojo: 'bg-gray-100 text-gray-800',
        },

        async init() {
            await this.loadVrijwilligers();
        },

        rolIcoon(rol) {
            const r = this.rollen.find(x => x.key === rol);
            return r ? r.icon : '';
        },

        async loadVrijwilligers() {
            try {
                const response = await fetch('{{ route("toernooi.device-toegang.index", $toernooi) }}', {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                });
                if (response.ok) {
                    this.vrijwilligers = await response.json();
                }
            } catch (e) {
                console.error('Load failed:', e);
            }
        },

        openAddModal() {
            this.editingId = null;
            this.form = { naam: '', telefoon: '', email: '', rol: '', mat_nummer: null };
            this.showModal = true;
        },

        openEditModal(v) {
            this.editingId = v.id;
            this.form = {
                naam: v.naam,
                telefoon: v.telefoon || '',
                email: v.email || '',
                rol: v.rol,
                mat_nummer: v.mat_nummer,
            };
            this.showModal = true;
        },

        closeModal() {
            this.showModal = false;
            this.editingId = null;
        },

        async saveVrijwilliger() {
            const data = { ...this.form };
            if (data.rol === 'mat' && !data.mat_nummer) {
                const matVrijwilligers = this.vrijwilligers.filter(v => v.rol === 'mat');
                const usedNumbers = matVrijwilligers.map(v => v.mat_nummer).filter(n => n);
                let nr = 1;
                while (usedNumbers.includes(nr)) nr++;
                data.mat_nummer = nr;
            }

            try {
                const url = this.editingId
                    ? `{{ url("toernooi/{$toernooi->id}/api/device-toegang") }}/${this.editingId}`
                    : '{{ route("toernooi.device-toegang.store", $toernooi) }}';
                const method = this.editingId ? 'PUT' : 'POST';

                const response = await fetch(url, {
                    method,
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(data),
                });

                if (response.ok) {
                    const result = await response.json();
                    if (this.editingId) {
                        const idx = this.vrijwilligers.findIndex(v => v.id === this.editingId);
                        if (idx !== -1) this.vrijwilligers[idx] = result;
                    } else {
                        this.vrijwilligers.push(result);
                    }
                    this.closeModal();
                } else {
                    const err = await response.json();
                    alert(err.message || 'Er ging iets mis');
                }
            } catch (e) {
                console.error('Save failed:', e);
            }
        },

        async resetVrijwilliger(v) {
            if (!confirm(`Device binding van ${v.naam} resetten?`)) return;
            try {
                const response = await fetch(`{{ url("toernooi/{$toernooi->id}/api/device-toegang") }}/${v.id}/reset`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                });
                if (response.ok) {
                    v.is_gebonden = false;
                    v.device_info = null;
                    v.status = 'Wacht op binding';
                }
            } catch (e) {
                console.error('Reset failed:', e);
            }
        },

        async deleteVrijwilliger(v) {
            if (!confirm(`${v.naam} verwijderen?`)) return;
            try {
                const response = await fetch(`{{ url("toernooi/{$toernooi->id}/api/device-toegang") }}/${v.id}`, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                });
                if (response.ok) {
                    this.vrijwilligers = this.vrijwilligers.filter(x => x.id !== v.id);
                }
            } catch (e) {
                console.error('Delete failed:', e);
            }
        },

        async resetAll() {
            if (!confirm('ALLE device bindings resetten? Alle vrijwilligers moeten opnieuw hun PIN invoeren.')) return;
            try {
                const response = await fetch('{{ route("toernooi.device-toegang.reset-all", $toernooi) }}', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                });
                if (response.ok) {
                    this.vrijwilligers.forEach(v => {
                        v.is_gebonden = false;
                        v.device_info = null;
                        v.status = 'Wacht op binding';
                    });
                }
            } catch (e) {
                console.error('Reset all failed:', e);
            }
        },

        copyUrl(v) {
            const text = `${v.url}\nPIN: ${v.pincode}`;
            navigator.clipboard.writeText(text);
            this.copiedId = v.id;
            setTimeout(() => this.copiedId = null, 2000);
        },
    };
}
</script>
