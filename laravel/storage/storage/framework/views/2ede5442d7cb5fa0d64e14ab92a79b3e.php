
<div class="bg-white rounded-lg shadow p-6 mb-6" x-data="deviceToegangen()">
    <div class="flex items-center justify-between mb-4 pb-2 border-b">
        <h2 class="text-xl font-bold text-gray-800"><?php echo e(__('Device Toegangen')); ?></h2>
        <button type="button"
                @click="showVrijwilligersModal = true"
                class="text-blue-600 hover:text-blue-800 text-sm flex items-center gap-1">
            <span>👥</span>
            <span><?php echo e(__('Vrijwilligers beheren')); ?></span>
        </button>
    </div>
    <p class="text-gray-600 mb-4">
        <?php echo e(__('Maak toegangen aan voor vrijwilligers. Elke toegang heeft een unieke URL en PIN.')); ?>

        <br><span class="text-sm text-gray-500"><?php echo e(__('Het device wordt gekoppeld bij eerste login - zo kunnen alleen geautoriseerde devices de interface gebruiken.')); ?></span>
    </p>

    
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

    
    <template x-for="rol in rollen" :key="rol.key">
        <div x-show="activeRol === rol.key" x-cloak>
            <div class="space-y-3 mb-4">
                <template x-if="toegangenPerRol[rol.key]?.length === 0">
                    <p class="text-gray-400 italic py-4 text-center"><?php echo e(__('Nog geen toegangen aangemaakt')); ?></p>
                </template>
                <template x-for="toegang in toegangenPerRol[rol.key]" :key="toegang.id">
                    <div class="p-4 border rounded-lg bg-gray-50">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-4">
                                
                                <div>
                                    <span class="font-bold text-gray-800" x-text="toegang.label"></span>
                                    <span class="block text-xs" :class="toegang.is_gebonden ? 'text-green-600' : 'text-gray-400'" x-text="toegang.status"></span>
                                </div>
                                
                                <div class="flex-1 max-w-xs flex items-center gap-2" x-show="rol.key !== 'mat'">
                                    <select @change="selectVrijwilliger(toegang, $event.target.value)"
                                            class="w-full text-sm border border-gray-300 rounded px-2 py-1 focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                                        <option value=""><?php echo e(__('-- Selecteer vrijwilliger --')); ?></option>
                                        <template x-for="v in vrijwilligersPerFunctie[rol.key] || []" :key="v.id">
                                            <option :value="v.id" :selected="toegang.naam === v.voornaam" x-text="v.voornaam + (v.telefoonnummer ? ' (' + v.telefoonnummer + ')' : '') + (v.email ? ' - ' + v.email : '')"></option>
                                        </template>
                                    </select>
                                    <span x-show="savedId === toegang.id" x-cloak
                                          class="text-green-600 text-xs font-medium whitespace-nowrap">
                                        ✓ <?php echo e(__('Opgeslagen')); ?>

                                    </span>
                                </div>
                                
                                <div class="text-center">
                                    <span class="text-xs text-gray-500 block">PIN</span>
                                    <span class="font-mono font-bold text-lg" x-text="toegang.pincode"></span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                
                                <a x-show="toegang.telefoon"
                                   :href="getWhatsAppUrl(toegang)"
                                   target="_blank"
                                   class="bg-green-500 hover:bg-green-600 text-white px-3 py-1.5 rounded text-sm"
                                   title="<?php echo e(__('Stuur via WhatsApp')); ?>">
                                    <span>📱 WhatsApp</span>
                                </a>
                                
                                <a x-show="toegang.email"
                                   :href="getEmailUrl(toegang)"
                                   class="bg-purple-500 hover:bg-purple-600 text-white px-3 py-1.5 rounded text-sm"
                                   title="<?php echo e(__('Stuur via Email')); ?>">
                                    <span>📧 Email</span>
                                </a>
                                
                                <button type="button"
                                        @click="copyUrl(toegang)"
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded text-sm">
                                    <span x-show="copiedId !== 'url_' + toegang.id">📋 URL</span>
                                    <span x-show="copiedId === 'url_' + toegang.id" x-cloak>✓</span>
                                </button>
                                
                                <button type="button"
                                        @click="copyPin(toegang)"
                                        class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1.5 rounded text-sm">
                                    <span x-show="copiedId !== 'pin_' + toegang.id">📋 PIN</span>
                                    <span x-show="copiedId === 'pin_' + toegang.id" x-cloak>✓</span>
                                </button>
                                
                                <a :href="toegang.url" target="_blank"
                                   class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-2 py-1.5 rounded text-sm" title="<?php echo e(__('Test')); ?>">
                                    <?php echo e(__('Test')); ?>

                                </a>
                                
                                <button type="button"
                                        @click="resetToegang(toegang)"
                                        x-show="toegang.is_gebonden"
                                        class="text-orange-600 hover:text-orange-800 text-sm px-2" title="<?php echo e(__('Reset device binding')); ?>">
                                    <?php echo e(__('Reset')); ?>

                                </button>
                                
                                <button type="button"
                                        @click="deleteToegang(toegang)"
                                        class="text-red-400 hover:text-red-600 text-lg px-1" title="<?php echo e(__('Verwijder')); ?>">
                                    &times;
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            
            <button type="button"
                    @click="addToegang(rol.key)"
                    class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded text-sm flex items-center gap-1">
                <span>+</span>
                <span x-text="rol.key === 'mat' ? '<?php echo e(__('Mat toegang toevoegen')); ?>' : rol.naam + ' <?php echo e(__('toegang toevoegen')); ?>'"></span>
            </button>
        </div>
    </template>

    
    <div class="mt-6 pt-4 border-t">
        <button type="button"
                @click="resetAll()"
                class="text-red-600 hover:text-red-800 text-sm">
            <?php echo e(__('Alle device bindings resetten')); ?>

        </button>
        <span class="text-xs text-gray-400 ml-2"><?php echo e(__('(voor nieuw toernooi of bij problemen)')); ?></span>
    </div>

    
    <div class="mt-6 p-4 bg-blue-50 rounded-lg" x-show="Object.values(toegangenPerRol).flat().length > 0">
        <h4 class="font-bold text-blue-800 mb-2"><?php echo e(__('Voorbeeld bericht voor WhatsApp:')); ?></h4>
        <div class="bg-white p-3 rounded border text-sm text-gray-700">
            <?php echo e(__('Hoi! Morgen is het toernooi. Klik op je link en voer de PIN in om in te loggen.')); ?><br><br>
            <em class="text-gray-500"><?php echo e(__('Stuur elke vrijwilliger zijn/haar eigen link + PIN!')); ?></em>
        </div>
    </div>

    
    <div x-show="showVrijwilligersModal"
         x-cloak
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
         @click.self="showVrijwilligersModal = false">
        <div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[80vh] overflow-hidden flex flex-col">
            <div class="p-4 border-b flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-800"><?php echo e(__('Vrijwilligers')); ?></h3>
                <button type="button" @click="showVrijwilligersModal = false" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>

            <div class="p-4 overflow-y-auto flex-1">
                
                <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                    <div class="grid grid-cols-12 gap-2">
                        <input type="text"
                               x-model="newVrijwilliger.voornaam"
                               placeholder="<?php echo e(__('Voornaam')); ?>"
                               class="col-span-3 text-sm border border-gray-300 rounded px-2 py-1.5 focus:ring-1 focus:ring-blue-500">
                        <input type="text"
                               x-model="newVrijwilliger.telefoonnummer"
                               placeholder="<?php echo e(__('Telefoon')); ?>"
                               class="col-span-3 text-sm border border-gray-300 rounded px-2 py-1.5 focus:ring-1 focus:ring-blue-500">
                        <input type="email"
                               x-model="newVrijwilliger.email"
                               placeholder="<?php echo e(__('Email')); ?>"
                               class="col-span-3 text-sm border border-gray-300 rounded px-2 py-1.5 focus:ring-1 focus:ring-blue-500">
                        <select x-model="newVrijwilliger.functie"
                                class="col-span-2 text-sm border border-gray-300 rounded px-2 py-1.5 focus:ring-1 focus:ring-blue-500">
                            <template x-for="rol in rollen" :key="rol.key">
                                <option :value="rol.key" x-text="rol.naam"></option>
                            </template>
                        </select>
                        <button type="button"
                                @click="addVrijwilliger()"
                                :disabled="!newVrijwilliger.voornaam"
                                class="col-span-1 bg-green-500 hover:bg-green-600 disabled:bg-gray-300 text-white rounded text-sm">
                            +
                        </button>
                    </div>
                </div>

                
                <div class="space-y-2">
                    <template x-if="vrijwilligers.length === 0">
                        <p class="text-gray-400 italic text-center py-4"><?php echo e(__('Nog geen vrijwilligers toegevoegd')); ?></p>
                    </template>
                    <template x-for="v in vrijwilligers" :key="v.id">
                        <div class="flex items-center justify-between p-2 border rounded hover:bg-gray-50">
                            <div class="flex items-center gap-3 flex-wrap">
                                <span class="font-medium" x-text="v.voornaam"></span>
                                <span class="text-gray-500 text-sm" x-text="v.telefoonnummer || '-'"></span>
                                <span x-show="v.email" class="text-gray-500 text-sm" x-text="v.email"></span>
                                <span class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded" x-text="v.functie_label"></span>
                            </div>
                            <button type="button"
                                    @click="deleteVrijwilliger(v)"
                                    class="text-red-400 hover:text-red-600 text-lg px-2">
                                &times;
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            <div class="p-4 border-t bg-gray-50">
                <p class="text-xs text-gray-500"><?php echo e(__('Vrijwilligers worden bewaard en zijn beschikbaar voor al je toernooien.')); ?></p>
            </div>
        </div>
    </div>

</div>

<script>
function deviceToegangen() {
    return {
        activeRol: 'mat',
        copiedId: null,
        savedId: null,
        toegangen: [],
        vrijwilligers: [],
        showVrijwilligersModal: false,
        newVrijwilliger: { voornaam: '', telefoonnummer: '', email: '', functie: 'mat' },
        toernooiNaam: '<?php echo e($toernooi->naam); ?>',
        rollen: [
            { key: 'hoofdjury', naam: '<?php echo e(__('Hoofdjury')); ?>', icon: '⚖️' },
            { key: 'mat', naam: '<?php echo e(__('Mat')); ?>', icon: '🥋' },
            { key: 'weging', naam: '<?php echo e(__('Weging')); ?>', icon: '⚖️' },
            { key: 'spreker', naam: '<?php echo e(__('Spreker')); ?>', icon: '🎙️' },
            { key: 'dojo', naam: '<?php echo e(__('Dojo')); ?>', icon: '🚪' },
        ],

        get toegangenPerRol() {
            const grouped = {};
            this.rollen.forEach(r => grouped[r.key] = []);
            this.toegangen.forEach(t => {
                if (grouped[t.rol]) grouped[t.rol].push(t);
            });
            return grouped;
        },

        get vrijwilligersPerFunctie() {
            const grouped = {};
            this.rollen.forEach(r => grouped[r.key] = []);
            this.vrijwilligers.forEach(v => {
                if (grouped[v.functie]) grouped[v.functie].push(v);
            });
            return grouped;
        },

        async init() {
            await Promise.all([this.loadToegangen(), this.loadVrijwilligers()]);
        },

        async loadToegangen() {
            try {
                const response = await fetch('<?php echo e(route("toernooi.device-toegang.index", $toernooi->routeParams())); ?>', {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                });
                if (response.ok) {
                    this.toegangen = await response.json();
                }
            } catch (e) {}
        },

        async loadVrijwilligers() {
            try {
                const response = await fetch('<?php echo e(route("toernooi.vrijwilligers.index", $toernooi->routeParams())); ?>', {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                });
                if (response.ok) {
                    this.vrijwilligers = await response.json();
                }
            } catch (e) {}
        },

        async addVrijwilliger() {
            if (!this.newVrijwilliger.voornaam) return;
            try {
                const response = await fetch('<?php echo e(route("toernooi.vrijwilligers.store", $toernooi->routeParams())); ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.newVrijwilliger),
                });
                if (response.ok) {
                    const nieuweV = await response.json();
                    this.vrijwilligers.push(nieuweV);
                    this.newVrijwilliger = { voornaam: '', telefoonnummer: '', email: '', functie: this.newVrijwilliger.functie };
                }
            } catch (e) {
                console.error('Failed to add vrijwilliger:', e);
            }
        },

        async deleteVrijwilliger(v) {
            if (!confirm(`${v.voornaam} <?php echo e(__('verwijderen?')); ?>`)) return;
            try {
                const response = await fetch(`<?php echo e(url($toernooi->organisator->slug . '/toernooi/' . $toernooi->slug . '/api/vrijwilligers')); ?>/${v.id}`, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>', 'Accept': 'application/json' },
                });
                if (response.ok) {
                    this.vrijwilligers = this.vrijwilligers.filter(x => x.id !== v.id);
                }
            } catch (e) {}
        },

        async selectVrijwilliger(toegang, vrijwilligerId) {
            const v = this.vrijwilligers.find(x => x.id == vrijwilligerId);
            const naam = v ? v.voornaam : '';
            const telefoon = v ? v.telefoonnummer : null;
            const email = v ? v.email : null;

            try {
                const response = await fetch(`<?php echo e(url($toernooi->organisator->slug . '/toernooi/' . $toernooi->slug . '/api/device-toegang')); ?>/${toegang.id}`, {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ naam, telefoon, email }),
                });
                if (response.ok) {
                    const updated = await response.json();
                    Object.assign(toegang, updated);
                    this.savedId = toegang.id;
                    setTimeout(() => this.savedId = null, 2000);
                }
            } catch (e) {
                console.error('Failed to update toegang:', e);
            }
        },

        getWhatsAppUrl(toegang) {
            if (!toegang.telefoon) return '';
            let nummer = toegang.telefoon.replace(/[^0-9+]/g, '');
            if (nummer.startsWith('06')) {
                nummer = '+31' + nummer.substring(1);
            } else if (nummer.startsWith('0')) {
                nummer = '+31' + nummer.substring(1);
            }
            const bericht = `<?php echo e(__('Hoi')); ?> ${toegang.naam || '<?php echo e(__('daar')); ?>'}! <?php echo e(__('Hier is je link voor')); ?> ${toegang.label} <?php echo e(__('op')); ?> ${this.toernooiNaam}:\n${toegang.url}\nPIN: ${toegang.pincode}`;
            return 'https://wa.me/' + nummer.replace('+', '') + '?text=' + encodeURIComponent(bericht);
        },

        getEmailUrl(toegang) {
            if (!toegang.email) return '';
            const subject = `<?php echo e(__('Toegang')); ?> ${toegang.label} - ${this.toernooiNaam}`;
            const body = `<?php echo e(__('Hoi')); ?> ${toegang.naam || '<?php echo e(__('daar')); ?>'}!\n\n<?php echo e(__('Hier is je link voor')); ?> ${toegang.label} <?php echo e(__('op')); ?> ${this.toernooiNaam}:\n${toegang.url}\n\nPIN: ${toegang.pincode}\n\n<?php echo e(__('Klik op de link en voer de PIN in om in te loggen.')); ?>`;
            return 'mailto:' + encodeURIComponent(toegang.email) + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
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
                const response = await fetch('<?php echo e(route("toernooi.device-toegang.store", $toernooi->routeParams())); ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>',
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

        async resetToegang(toegang) {
            if (!confirm('<?php echo e(__('Device binding resetten? Het device moet opnieuw de PIN invoeren.')); ?>')) return;
            try {
                const response = await fetch(`<?php echo e(url($toernooi->organisator->slug . '/toernooi/' . $toernooi->slug . '/api/device-toegang')); ?>/${toegang.id}/reset`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>', 'Accept': 'application/json' },
                });
                if (response.ok) {
                    toegang.is_gebonden = false;
                    toegang.device_info = null;
                    toegang.status = '<?php echo e(__('Wacht op binding')); ?>';
                    window.dispatchEvent(new CustomEvent('toegangen-updated'));
                }
            } catch (e) {}
        },

        async deleteToegang(toegang) {
            if (!confirm('<?php echo e(__('Deze toegang verwijderen?')); ?>')) return;
            try {
                const response = await fetch(`<?php echo e(url($toernooi->organisator->slug . '/toernooi/' . $toernooi->slug . '/api/device-toegang')); ?>/${toegang.id}`, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>', 'Accept': 'application/json' },
                });
                if (response.ok) {
                    this.toegangen = this.toegangen.filter(t => t.id !== toegang.id);
                    window.dispatchEvent(new CustomEvent('toegangen-updated'));
                }
            } catch (e) {}
        },

        async resetAll() {
            if (!confirm('<?php echo e(__('ALLE device bindings resetten?')); ?>')) return;
            try {
                const response = await fetch('<?php echo e(route("toernooi.device-toegang.reset-all", $toernooi->routeParams())); ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>', 'Accept': 'application/json' },
                });
                if (response.ok) {
                    this.toegangen.forEach(t => {
                        t.is_gebonden = false;
                        t.device_info = null;
                        t.status = '<?php echo e(__('Wacht op binding')); ?>';
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
<?php /**PATH /var/www/judotoernooi/staging/resources/views/pages/toernooi/partials/device-toegangen.blade.php ENDPATH**/ ?>