{{-- Device Toegangen Beheer --}}
<div class="bg-white rounded-lg shadow p-6 mb-6" x-data="deviceToegangen()">
    <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b">Device Toegangen</h2>
    <p class="text-gray-600 mb-4">
        Maak toegangen aan voor vrijwilligers. Elke toegang heeft een unieke URL en PIN.
        <br><span class="text-sm text-gray-500">Het device wordt gekoppeld bij eerste login - zo kunnen alleen geautoriseerde devices de interface gebruiken.</span>
    </p>

    {{-- Tabs per rol --}}
    <div class="flex border-b mb-4 overflow-x-auto">
        <template x-for="rol in rollen" :key="rol.key">
            <button type="button"
                    @click="activeRol = rol.key"
                    :class="activeRol === rol.key ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="px-4 py-2 font-medium border-b-2 -mb-px transition-colors text-sm flex items-center gap-1 whitespace-nowrap">
                <span x-text="rol.icon"></span>
                <span x-text="rol.naam"></span>
                <span class="ml-1 text-xs bg-gray-200 px-1.5 py-0.5 rounded-full" x-text="toegangenPerRol[rol.key]?.length || 0"></span>
            </button>
        </template>
    </div>

    {{-- Content per rol --}}
    <template x-for="rol in rollen" :key="rol.key">
        <div x-show="activeRol === rol.key" x-cloak>
            <div class="space-y-3 mb-4">
                <template x-if="toegangenPerRol[rol.key]?.length === 0">
                    <p class="text-gray-400 italic py-4 text-center">Nog geen toegangen aangemaakt</p>
                </template>
                <template x-for="toegang in toegangenPerRol[rol.key]" :key="toegang.id">
                    <div class="p-4 border rounded-lg bg-gray-50">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-4">
                                {{-- Label --}}
                                <div>
                                    <span class="font-bold text-gray-800" x-text="toegang.label"></span>
                                    <span class="block text-xs" :class="toegang.is_gebonden ? 'text-green-600' : 'text-gray-400'" x-text="toegang.status"></span>
                                </div>
                                {{-- PIN --}}
                                <div class="text-center">
                                    <span class="text-xs text-gray-500 block">PIN</span>
                                    <span class="font-mono font-bold text-lg" x-text="toegang.pincode"></span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                {{-- Copy URL --}}
                                <button type="button"
                                        @click="copyUrl(toegang)"
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded text-sm">
                                    <span x-show="copiedId !== toegang.id">Kopieer URL</span>
                                    <span x-show="copiedId === toegang.id" x-cloak>Gekopieerd!</span>
                                </button>
                                {{-- Test link --}}
                                <a :href="toegang.url" target="_blank"
                                   class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-2 py-1.5 rounded text-sm" title="Test">
                                    Test
                                </a>
                                {{-- Reset --}}
                                <button type="button"
                                        @click="resetToegang(toegang)"
                                        x-show="toegang.is_gebonden"
                                        class="text-orange-600 hover:text-orange-800 text-sm px-2" title="Reset device binding">
                                    Reset
                                </button>
                                {{-- Delete --}}
                                <button type="button"
                                        @click="deleteToegang(toegang)"
                                        class="text-red-400 hover:text-red-600 text-lg px-1" title="Verwijder">
                                    &times;
                                </button>
                            </div>
                        </div>
                        {{-- Vrijwilliger gegevens --}}
                        <div class="text-sm text-gray-600 border-t pt-2 mt-2" x-show="toegang.naam">
                            <span class="font-medium" x-text="toegang.naam"></span>
                            <span x-show="toegang.telefoon" class="ml-3">📞 <span x-text="toegang.telefoon"></span></span>
                            <span x-show="toegang.email" class="ml-3">✉️ <span x-text="toegang.email"></span></span>
                            <button type="button" @click="editToegang(toegang)" class="ml-2 text-blue-600 hover:text-blue-800 text-xs">bewerk</button>
                        </div>
                        <div x-show="!toegang.naam" class="text-sm text-gray-400 border-t pt-2 mt-2">
                            <button type="button" @click="editToegang(toegang)" class="text-blue-600 hover:text-blue-800">+ Vrijwilliger koppelen</button>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Add button --}}
            <button type="button"
                    @click="addToegang(rol.key)"
                    class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded text-sm flex items-center gap-1">
                <span>+</span>
                <span x-text="rol.key === 'mat' ? 'Mat toegang toevoegen' : rol.naam + ' toegang toevoegen'"></span>
            </button>
        </div>
    </template>

    {{-- Reset All Button --}}
    <div class="mt-6 pt-4 border-t">
        <button type="button"
                @click="resetAll()"
                class="text-red-600 hover:text-red-800 text-sm">
            Alle device bindings resetten
        </button>
        <span class="text-xs text-gray-400 ml-2">(voor nieuw toernooi of bij problemen)</span>
    </div>

    {{-- WhatsApp voorbeeld --}}
    <div class="mt-6 p-4 bg-blue-50 rounded-lg" x-show="Object.values(toegangenPerRol).flat().length > 0">
        <h4 class="font-bold text-blue-800 mb-2">Voorbeeld bericht voor WhatsApp:</h4>
        <div class="bg-white p-3 rounded border text-sm text-gray-700">
            Hoi! Morgen is het toernooi. Klik op je link en voer de PIN in om in te loggen.<br><br>
            <em class="text-gray-500">Stuur elke vrijwilliger zijn/haar eigen link + PIN!</em>
        </div>
    </div>

    {{-- Edit Modal --}}
    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50" @click="closeEditModal()"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-bold mb-4">Vrijwilliger koppelen</h3>
                <form @submit.prevent="saveVrijwilliger()">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Naam</label>
                            <input type="text" x-model="editForm.naam"
                                   class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Jan de Vries">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telefoon</label>
                            <input type="tel" x-model="editForm.telefoon"
                                   class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="06-12345678">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" x-model="editForm.email"
                                   class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="naam@email.nl">
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-6">
                        <button type="button" @click="closeEditModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800">Annuleren</button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Opslaan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Vrijwilligers Overzicht --}}
<div class="bg-white rounded-lg shadow p-6 mb-6" x-data="vrijwilligersOverzicht()">
    <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b">Vrijwilligers Overzicht</h2>
    <p class="text-gray-600 mb-4 text-sm">Overzicht van alle vrijwilligers met een toegekende rol.</p>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700">Naam</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700">Telefoon</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700">Email</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700">Rol</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <template x-if="vrijwilligers.length === 0">
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400 italic">Nog geen vrijwilligers gekoppeld aan toegangen</td></tr>
                </template>
                <template x-for="v in vrijwilligers" :key="v.id">
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 font-medium text-gray-900" x-text="v.naam"></td>
                        <td class="px-4 py-2 text-gray-600" x-text="v.telefoon || '-'"></td>
                        <td class="px-4 py-2 text-gray-600" x-text="v.email || '-'"></td>
                        <td class="px-4 py-2">
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium"
                                  :class="rolKleuren[v.rol]" x-text="v.label"></span>
                        </td>
                        <td class="px-4 py-2">
                            <span class="text-xs" :class="v.is_gebonden ? 'text-green-600' : 'text-gray-400'"
                                  x-text="v.is_gebonden ? '✓ Gebonden' : '⏳ Wacht'"></span>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</div>

<script>
function deviceToegangen() {
    return {
        activeRol: 'mat',
        copiedId: null,
        toegangen: [],
        showEditModal: false,
        editingToegang: null,
        editForm: { naam: '', telefoon: '', email: '' },
        rollen: [
            { key: 'hoofdjury', naam: 'Hoofdjury', icon: '⚖️' },
            { key: 'mat', naam: 'Mat', icon: '🥋' },
            { key: 'weging', naam: 'Weging', icon: '⚖️' },
            { key: 'spreker', naam: 'Spreker', icon: '🎙️' },
            { key: 'dojo', naam: 'Dojo', icon: '🚪' },
        ],

        get toegangenPerRol() {
            const grouped = {};
            this.rollen.forEach(r => grouped[r.key] = []);
            this.toegangen.forEach(t => {
                if (grouped[t.rol]) grouped[t.rol].push(t);
            });
            return grouped;
        },

        async init() {
            await this.loadToegangen();
        },

        async loadToegangen() {
            try {
                const response = await fetch('{{ route("toernooi.device-toegang.index", $toernooi) }}', {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                });
                if (response.ok) {
                    this.toegangen = await response.json();
                }
            } catch (e) {}
        },

        async addToegang(rol) {
            const data = { rol, naam: '' };
            if (rol === 'mat') {
                const matToegangen = this.toegangenPerRol.mat || [];
                const usedNumbers = matToegangen.map(t => t.mat_nummer).filter(n => n);
                let matNummer = 1;
                while (usedNumbers.includes(matNummer)) matNummer++;
                data.mat_nummer = matNummer;
            }

            try {
                const response = await fetch('{{ route("toernooi.device-toegang.store", $toernooi) }}', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(data),
                });
                if (response.ok) {
                    const nieuweT = await response.json();
                    this.toegangen.push(nieuweT);
                    window.dispatchEvent(new CustomEvent('toegangen-updated'));
                }
            } catch (e) {
                console.error('Failed to add toegang:', e);
            }
        },

        editToegang(toegang) {
            this.editingToegang = toegang;
            this.editForm = {
                naam: toegang.naam || '',
                telefoon: toegang.telefoon || '',
                email: toegang.email || '',
            };
            this.showEditModal = true;
        },

        closeEditModal() {
            this.showEditModal = false;
            this.editingToegang = null;
        },

        async saveVrijwilliger() {
            if (!this.editingToegang) return;
            try {
                const response = await fetch(`{{ url("toernooi/{$toernooi->id}/api/device-toegang") }}/${this.editingToegang.id}`, {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        naam: this.editForm.naam,
                        telefoon: this.editForm.telefoon,
                        email: this.editForm.email,
                        rol: this.editingToegang.rol,
                        mat_nummer: this.editingToegang.mat_nummer,
                    }),
                });
                if (response.ok) {
                    const updated = await response.json();
                    const idx = this.toegangen.findIndex(t => t.id === updated.id);
                    if (idx !== -1) this.toegangen[idx] = updated;
                    this.closeEditModal();
                    window.dispatchEvent(new CustomEvent('toegangen-updated'));
                }
            } catch (e) {
                console.error('Failed to save:', e);
            }
        },

        async resetToegang(toegang) {
            if (!confirm('Device binding resetten? Het device moet opnieuw de PIN invoeren.')) return;
            try {
                const response = await fetch(`{{ url("toernooi/{$toernooi->id}/api/device-toegang") }}/${toegang.id}/reset`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                });
                if (response.ok) {
                    toegang.is_gebonden = false;
                    toegang.device_info = null;
                    toegang.status = 'Wacht op binding';
                    window.dispatchEvent(new CustomEvent('toegangen-updated'));
                }
            } catch (e) {}
        },

        async deleteToegang(toegang) {
            if (!confirm('Deze toegang verwijderen?')) return;
            try {
                const response = await fetch(`{{ url("toernooi/{$toernooi->id}/api/device-toegang") }}/${toegang.id}`, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                });
                if (response.ok) {
                    this.toegangen = this.toegangen.filter(t => t.id !== toegang.id);
                    window.dispatchEvent(new CustomEvent('toegangen-updated'));
                }
            } catch (e) {}
        },

        async resetAll() {
            if (!confirm('ALLE device bindings resetten?')) return;
            try {
                const response = await fetch('{{ route("toernooi.device-toegang.reset-all", $toernooi) }}', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                });
                if (response.ok) {
                    this.toegangen.forEach(t => {
                        t.is_gebonden = false;
                        t.device_info = null;
                        t.status = 'Wacht op binding';
                    });
                    window.dispatchEvent(new CustomEvent('toegangen-updated'));
                }
            } catch (e) {}
        },

        copyUrl(toegang) {
            const text = `${toegang.url}\nPIN: ${toegang.pincode}`;
            navigator.clipboard.writeText(text);
            this.copiedId = toegang.id;
            setTimeout(() => this.copiedId = null, 2000);
        },
    };
}

function vrijwilligersOverzicht() {
    return {
        vrijwilligers: [],
        rolKleuren: {
            hoofdjury: 'bg-purple-100 text-purple-800',
            mat: 'bg-blue-100 text-blue-800',
            weging: 'bg-green-100 text-green-800',
            spreker: 'bg-yellow-100 text-yellow-800',
            dojo: 'bg-gray-100 text-gray-800',
        },

        async init() {
            await this.loadVrijwilligers();
            window.addEventListener('toegangen-updated', () => this.loadVrijwilligers());
        },

        async loadVrijwilligers() {
            try {
                const response = await fetch('{{ route("toernooi.device-toegang.index", $toernooi) }}', {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                });
                if (response.ok) {
                    const all = await response.json();
                    this.vrijwilligers = all.filter(t => t.naam);
                }
            } catch (e) {}
        },
    };
}
</script>
