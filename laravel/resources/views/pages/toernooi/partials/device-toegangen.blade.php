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
                                {{-- Naam (editable) --}}
                                <div class="flex-1 max-w-xs" x-show="rol.key !== 'mat'">
                                    <input type="text"
                                           :value="toegang.naam"
                                           @blur="updateNaam(toegang, $event.target.value)"
                                           @keydown.enter="$event.target.blur()"
                                           placeholder="Naam vrijwilliger..."
                                           class="w-full text-sm border border-gray-300 rounded px-2 py-1 focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
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
                                    <span x-show="copiedId !== 'url_' + toegang.id">📋 URL</span>
                                    <span x-show="copiedId === 'url_' + toegang.id" x-cloak>✓</span>
                                </button>
                                {{-- Copy PIN --}}
                                <button type="button"
                                        @click="copyPin(toegang)"
                                        class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1.5 rounded text-sm">
                                    <span x-show="copiedId !== 'pin_' + toegang.id">📋 PIN</span>
                                    <span x-show="copiedId === 'pin_' + toegang.id" x-cloak>✓</span>
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

</div>

<script>
function deviceToegangen() {
    return {
        activeRol: 'mat',
        copiedId: null,
        toegangen: [],
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
                const response = await fetch('{{ route("toernooi.device-toegang.index", $toernooi->routeParams()) }}', {
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
                const response = await fetch('{{ route("toernooi.device-toegang.store", $toernooi->routeParams()) }}', {
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

        async updateNaam(toegang, naam) {
            if (toegang.naam === naam) return;
            try {
                const response = await fetch(`{{ url($toernooi->organisator->slug . '/toernooi/' . $toernooi->slug . '/api/device-toegang') }}/${toegang.id}`, {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ naam }),
                });
                if (response.ok) {
                    const updated = await response.json();
                    Object.assign(toegang, updated);
                } else {
                    console.error('Failed to update naam:', response.status, await response.text());
                }
            } catch (e) {
                console.error('Failed to update naam:', e);
            }
        },

        async resetToegang(toegang) {
            if (!confirm('Device binding resetten? Het device moet opnieuw de PIN invoeren.')) return;
            try {
                const response = await fetch(`{{ url($toernooi->organisator->slug . '/toernooi/' . $toernooi->slug . '/api/device-toegang') }}/${toegang.id}/reset`, {
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
                const response = await fetch(`{{ url($toernooi->organisator->slug . '/toernooi/' . $toernooi->slug . '/api/device-toegang') }}/${toegang.id}`, {
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
                const response = await fetch('{{ route("toernooi.device-toegang.reset-all", $toernooi->routeParams()) }}', {
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
            navigator.clipboard.writeText(toegang.url);
            this.copiedId = 'url_' + toegang.id;
            setTimeout(() => this.copiedId = null, 2000);
        },

        copyPin(toegang) {
            navigator.clipboard.writeText(toegang.pincode);
            this.copiedId = 'pin_' + toegang.id;
            setTimeout(() => this.copiedId = null, 2000);
        },
    };
}

</script>
