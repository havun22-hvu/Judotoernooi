{{-- Device Toegangen Beheer --}}
<div class="bg-white rounded-lg shadow p-6 mb-6" x-data="deviceToegangen">
    <div class="flex items-center justify-between mb-4 pb-2 border-b">
        <h2 class="text-xl font-bold text-gray-800">{{ __('Device Toegangen') }}</h2>
        <button type="button"
                @click="openVrijwilligersModal()"
                class="text-blue-600 hover:text-blue-800 text-sm flex items-center gap-1">
            <span>👥</span>
            <span>{{ __('Vrijwilligers beheren') }}</span>
        </button>
    </div>
    <p class="text-gray-600 mb-4">
        {{ __('Maak toegangen aan voor vrijwilligers. Elke toegang heeft een unieke 12-cijferige URL.') }}
        <br><span class="text-sm text-gray-500">{{ __('Het device wordt automatisch gekoppeld bij de eerste keer dat de link wordt geopend — zo kunnen alleen geautoriseerde devices de interface gebruiken.') }}</span>
    </p>

    {{-- Tabs per rol --}}
    <div class="flex border-b mb-4 overflow-x-auto">
        <template x-for="rol in rollen" :key="rol.key">
            <button type="button"
                    @click="setActiveRol(rol)"
                    :class="tabClass(rol)"
                    class="px-4 py-2 font-medium border-b-2 -mb-px transition-colors text-sm flex items-center gap-1 whitespace-nowrap">
                <span x-text="rol.icon"></span>
                <span x-text="rol.naam"></span>
                <span class="ml-1 text-xs bg-gray-200 px-1.5 py-0.5 rounded-full" x-text="countForRol(rol)"></span>
            </button>
        </template>
    </div>

    {{-- Content per rol --}}
    <template x-for="rol in rollen" :key="rol.key">
        <div x-show="isActiveRol(rol)" x-cloak>
            <div class="space-y-3 mb-4">
                <template x-if="rolIsLeeg(rol)">
                    <p class="text-gray-400 italic py-4 text-center">{{ __('Nog geen toegangen aangemaakt') }}</p>
                </template>
                <template x-for="toegang in toegangenForRol(rol)" :key="toegang.id">
                    <div class="p-4 border rounded-lg bg-gray-50">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-4">
                                {{-- Label --}}
                                <div>
                                    <span class="font-bold text-gray-800" x-text="toegang.label"></span>
                                    <span class="block text-xs" :class="statusClass(toegang)" x-text="toegang.status"></span>
                                </div>
                                {{-- Vrijwilliger dropdown (niet voor mat) --}}
                                <div class="flex-1 max-w-xs flex items-center gap-2" x-show="rolIsNietMat(rol)">
                                    <select @change="selectVrijwilliger(toegang, $event.target.value)"
                                            class="w-full text-sm border border-gray-300 rounded px-2 py-1 focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">{{ __('-- Selecteer vrijwilliger --') }}</option>
                                        <template x-for="v in vrijwilligersForRol(rol)" :key="v.id">
                                            <option :value="v.id" :selected="isVrijwilligerSelected(toegang, v)" x-text="vrijwilligerLabel(v)"></option>
                                        </template>
                                    </select>
                                    <span x-show="isJustSaved(toegang)" x-cloak
                                          class="text-green-600 text-xs font-medium whitespace-nowrap">
                                        ✓ {{ __('Opgeslagen') }}
                                    </span>
                                </div>
                                {{-- Token (eerste 4 tekens) --}}
                                <div class="text-center">
                                    <span class="text-xs text-gray-500 block">Token</span>
                                    <span class="font-mono font-bold text-lg text-gray-400" x-text="tokenPrefix(toegang)"></span>
                                </div>
                            </div>
                            <table class="text-sm">
                                <tbody>
                                    {{-- Rij 1: Interface --}}
                                    <tr>
                                        <td class="pr-3 py-1 text-xs text-gray-400 font-medium align-middle whitespace-nowrap" x-text="interfaceLabel(rol)"></td>
                                        <td class="py-1">
                                            <div class="flex items-center gap-1.5">
                                                <a x-show="kanWhatsApp(rol, toegang)" :href="getWhatsAppUrl(toegang)" target="_blank"
                                                   class="bg-green-500 hover:bg-green-600 text-white px-2.5 py-1 rounded text-xs">WhatsApp</a>
                                                <a x-show="kanEmail(rol, toegang)" :href="getEmailUrl(toegang)"
                                                   class="bg-blue-500 hover:bg-blue-600 text-white px-2.5 py-1 rounded text-xs">Email</a>
                                                <button type="button" @click="copyUrl(toegang)" class="bg-blue-600 hover:bg-blue-700 text-white px-2.5 py-1 rounded text-xs">
                                                    <span x-show="notCopied('url', toegang)">URL</span><span x-show="isCopied('url', toegang)" x-cloak>✓</span>
                                                </button>
                                                <button type="button" @click="toggleQrMat(toegang)"
                                                        class="bg-gray-200 hover:bg-gray-300 text-gray-600 px-2 py-1 rounded text-xs" title="{{ __('QR code') }}">QR</button>
                                                <button type="button" @click="resetToegang(toegang)" x-show="toegang.is_gebonden"
                                                        class="text-orange-600 hover:text-orange-800 text-xs px-1">{{ __('Reset') }}</button>
                                                <a x-show="rolIsNietMat(rol)" :href="toegang.url" target="_blank"
                                                   class="text-gray-500 hover:text-gray-700 text-xs px-1">{{ __('Test') }}</a>
                                                <button type="button" x-show="rolIsNietMat(rol)" @click="deleteToegang(toegang)"
                                                        class="text-red-400 hover:text-red-600 text-sm px-1">&times;</button>
                                            </div>
                                        </td>
                                    </tr>
                                    {{-- Rij 2: LCD (alleen voor mat) --}}
                                    <tr x-show="rolIsMat(rol)">
                                        <td class="pr-3 py-1 text-xs text-gray-400 font-medium align-middle whitespace-nowrap">{{ __('LCD') }}</td>
                                        <td class="py-1">
                                            <div class="flex items-center gap-1.5">
                                                <button type="button" @click="copyTvUrl(toegang)"
                                                        class="bg-green-600 hover:bg-green-700 text-white px-2.5 py-1 rounded text-xs">
                                                    <span x-show="notCopied('tv', toegang)">URL</span><span x-show="isCopied('tv', toegang)" x-cloak>✓</span>
                                                </button>
                                                <button type="button" @click="toggleQrLcd(toegang)"
                                                        class="bg-gray-200 hover:bg-gray-300 text-gray-600 px-2 py-1 rounded text-xs">QR</button>
                                                <button type="button" @click="toggleTvLink(toegang)"
                                                        class="bg-blue-600 hover:bg-blue-700 text-white px-2.5 py-1 rounded text-xs">{{ __('Koppel TV') }}</button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            {{-- QR popup --}}
                            <template x-if="qrVisibleVoor(toegang)">
                                <div class="mt-2 p-4 bg-gray-50 rounded-lg border text-center">
                                    <p class="text-sm font-medium text-gray-700 mb-2" x-text="qrPopupTitel()"></p>
                                    <img :src="qrImageUrl()" class="w-36 h-36 mx-auto rounded mb-2">
                                    <div class="flex items-center gap-2 bg-white rounded p-2 text-xs">
                                        <input type="text" :value="qrUrl" readonly class="flex-1 bg-transparent text-gray-600 border-0 outline-none truncate text-xs">
                                        <button @click="copyQrUrl(toegang)"
                                                class="text-blue-600 hover:text-blue-800 font-medium whitespace-nowrap">
                                            <span x-show="notCopied('qr', toegang)">{{ __('Kopieer') }}</span>
                                            <span x-show="isCopied('qr', toegang)" x-cloak>✓</span>
                                        </button>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-2">{{ __('Scan met telefoon of open op de TV browser') }}</p>
                                </div>
                            </template>
                            {{-- TV Koppel popup --}}
                            <div x-show="tvLinkVisible(toegang)" x-cloak
                                 class="mt-2 p-4 bg-blue-50 rounded-lg border border-blue-200">
                                <p class="text-sm font-medium text-gray-700 mb-2">{{ __('Koppel TV aan Mat') }} <span x-text="toegang.mat_nummer"></span></p>
                                <p class="text-xs text-gray-500 mb-3">{{ __('Open') }} <strong>{{ url('/tv') }}</strong> {{ __('op de TV. Voer de code in die op het TV-scherm verschijnt:') }}</p>
                                <div class="flex items-center gap-2" x-data="tvCode">
                                    <input type="text" maxlength="4" placeholder="0000"
                                           x-model="tvCode"
                                           class="font-mono text-lg font-bold text-center w-24 border border-gray-300 rounded px-2 py-1.5 tracking-widest"
                                           @keyup.enter="linkTv(toegang, tvCode)">
                                    <button type="button" @click="linkTv(toegang, tvCode)"
                                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded text-sm font-medium">{{ __('Koppel') }}</button>
                                    <span x-show="tvLinkSuccess()" x-cloak class="text-green-600 text-sm font-medium">{{ __('Gekoppeld!') }}</span>
                                    <span x-show="tvLinkError()" x-cloak class="text-red-600 text-sm font-medium" x-text="tvLinkErrorMessage"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Add button (niet voor mat — die worden automatisch aangemaakt) --}}
            <button type="button" x-show="rolIsNietMat(rol)"
                    @click="addToegang(rol.key)"
                    class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded text-sm flex items-center gap-1">
                <span>+</span>
                <span x-text="addToegangLabel(rol)"></span>
            </button>
        </div>
    </template>

    {{-- Reset All Button --}}
    <div class="mt-6 pt-4 border-t">
        <button type="button"
                @click="resetAll()"
                class="text-red-600 hover:text-red-800 text-sm">
            {{ __('Alle device bindings resetten') }}
        </button>
        <span class="text-xs text-gray-400 ml-2">{{ __('(voor nieuw toernooi of bij problemen)') }}</span>
    </div>

    {{-- WhatsApp voorbeeld --}}
    <div class="mt-6 p-4 bg-blue-50 rounded-lg" x-show="hasAnyToegang">
        <h4 class="font-bold text-blue-800 mb-2">{{ __('Voorbeeld bericht voor WhatsApp:') }}</h4>
        <div class="bg-white p-3 rounded border text-sm text-gray-700">
            {{ __('Hoi! Morgen is het toernooi. Klik op je link — het apparaat wordt automatisch gekoppeld.') }}<br><br>
            <em class="text-gray-500">{{ __('Stuur elke vrijwilliger zijn/haar eigen link!') }}</em>
        </div>
    </div>

    {{-- Vrijwilligers Modal --}}
    <div x-show="showVrijwilligersModal"
         x-cloak
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
         @click.self="closeVrijwilligersModal()">
        <div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[80vh] overflow-hidden flex flex-col">
            <div class="p-4 border-b flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-800">{{ __('Vrijwilligers') }}</h3>
                <button type="button" @click="closeVrijwilligersModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>

            <div class="p-4 overflow-y-auto flex-1">
                {{-- Add new vrijwilliger --}}
                <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                    <div class="grid grid-cols-12 gap-2">
                        <input type="text"
                               x-model="newVrijwilliger.voornaam"
                               placeholder="{{ __('Voornaam') }}"
                               class="col-span-3 text-sm border border-gray-300 rounded px-2 py-1.5 focus:ring-1 focus:ring-blue-500">
                        <input type="text"
                               x-model="newVrijwilliger.telefoonnummer"
                               placeholder="{{ __('Telefoon') }}"
                               class="col-span-3 text-sm border border-gray-300 rounded px-2 py-1.5 focus:ring-1 focus:ring-blue-500">
                        <input type="email"
                               x-model="newVrijwilliger.email"
                               placeholder="{{ __('Email') }}"
                               class="col-span-3 text-sm border border-gray-300 rounded px-2 py-1.5 focus:ring-1 focus:ring-blue-500">
                        <select x-model="newVrijwilliger.functie"
                                class="col-span-2 text-sm border border-gray-300 rounded px-2 py-1.5 focus:ring-1 focus:ring-blue-500">
                            <template x-for="rol in rollen" :key="rol.key">
                                <option :value="rol.key" x-text="rol.naam"></option>
                            </template>
                        </select>
                        <button type="button"
                                @click="addVrijwilliger()"
                                :disabled="addVrijwilligerDisabled"
                                class="col-span-1 bg-green-500 hover:bg-green-600 disabled:bg-gray-300 text-white rounded text-sm">
                            +
                        </button>
                    </div>
                </div>

                {{-- List vrijwilligers --}}
                <div class="space-y-2">
                    <template x-if="vrijwilligersLeeg">
                        <p class="text-gray-400 italic text-center py-4">{{ __('Nog geen vrijwilligers toegevoegd') }}</p>
                    </template>
                    <template x-for="v in vrijwilligers" :key="v.id">
                        <div class="flex items-center justify-between p-2 border rounded hover:bg-gray-50">
                            <div class="flex items-center gap-3 flex-wrap">
                                <span class="font-medium" x-text="v.voornaam"></span>
                                <span class="text-gray-500 text-sm" x-text="telefoonOfStreep(v)"></span>
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
                <p class="text-xs text-gray-500">{{ __('Vrijwilligers worden bewaard en zijn beschikbaar voor al je toernooien.') }}</p>
            </div>
        </div>
    </div>

</div>

<script @nonce>
document.addEventListener('alpine:init', () => {
    Alpine.data('deviceToegangen', () => ({
        activeRol: 'mat',
        copiedId: null,
        savedId: null,
        showQr: null,
        qrUrl: '',
        showTvLink: null,
        tvLinkStatus: null,
        tvLinkErrorMessage: '',
        toegangen: [],
        vrijwilligers: [],
        showVrijwilligersModal: false,
        newVrijwilliger: { voornaam: '', telefoonnummer: '', email: '', functie: 'mat' },
        toernooiNaam: @js($toernooi->naam),
        toernooiId: {{ $toernooi->id }},
        rollen: [
            { key: 'hoofdjury', naam: '{{ __('Hoofdjury') }}', icon: '⚖️' },
            { key: 'mat', naam: '{{ __('Mat') }}', icon: '🥋' },
            { key: 'weging', naam: '{{ __('Weging') }}', icon: '⚖️' },
            { key: 'spreker', naam: '{{ __('Spreker') }}', icon: '🎙️' },
            { key: 'dojo', naam: '{{ __('Dojo') }}', icon: '🚪' },
        ],
        urls: {
            toegangen: '{{ route("toernooi.device-toegang.index", $toernooi->routeParams()) }}',
            toegangStore: '{{ route("toernooi.device-toegang.store", $toernooi->routeParams()) }}',
            toegangBase: '{{ url($toernooi->organisator->slug . "/toernooi/" . $toernooi->slug . "/api/device-toegang") }}',
            resetAll: '{{ route("toernooi.device-toegang.reset-all", $toernooi->routeParams()) }}',
            qr: '{{ route("toernooi.device-toegang.qr", $toernooi->routeParams()) }}',
            vrijwilligers: '{{ route("toernooi.vrijwilligers.index", $toernooi->routeParams()) }}',
            vrijwilligerStore: '{{ route("toernooi.vrijwilligers.store", $toernooi->routeParams()) }}',
            vrijwilligerBase: '{{ url($toernooi->organisator->slug . "/toernooi/" . $toernooi->slug . "/api/vrijwilligers") }}',
            scoreboardBase: '{{ url($toernooi->organisator->slug . "/" . $toernooi->slug . "/mat/scoreboard-live") }}',
            tvBase: '{{ url("/tv") }}',
            tvLink: '{{ route("tv.link") }}',
        },
        teksten: {
            verwijderen: '{{ __("verwijderen?") }}',
            daar: '{{ __("daar") }}',
            hoi: '{{ __("Hoi") }}',
            hierIsLink: '{{ __("Hier is je link voor") }}',
            op: '{{ __("op") }}',
            toegang: '{{ __("Toegang") }}',
            klikLink: '{{ __("Klik op de link om in te loggen — het apparaat wordt automatisch gekoppeld.") }}',
            wachtOpBinding: '{{ __("Wacht op binding") }}',
            bevestigReset: '{{ __("Device binding resetten? Het volgende apparaat dat de link opent wordt automatisch gekoppeld.") }}',
            bevestigVerwijder: '{{ __("Deze toegang verwijderen?") }}',
            bevestigResetAll: '{{ __("ALLE device bindings resetten?") }}',
            ongeldigeCode: '{{ __("Voer een 4-cijferige code in") }}',
            koppelingMislukt: '{{ __("Koppeling mislukt") }}',
            netwerkfout: '{{ __("Netwerkfout") }}',
            labelInterface: '{{ __("Interface") }}',
            labelLcd: '{{ __("LCD Scorebord") }}',
            labelMatInterface: '{{ __("Mat Interface") }}',
            toevoegenSuffix: '{{ __("toegang toevoegen") }}',
        },

        // --- Tab helpers ---
        setActiveRol(rol) { this.activeRol = rol.key; },
        isActiveRol(rol) { return this.activeRol === rol.key; },
        tabClass(rol) {
            return this.activeRol === rol.key
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700';
        },
        countForRol(rol) { return (this.toegangenPerRol[rol.key] || []).length; },
        toegangenForRol(rol) { return this.toegangenPerRol[rol.key] || []; },
        vrijwilligersForRol(rol) { return this.vrijwilligersPerFunctie[rol.key] || []; },
        rolIsLeeg(rol) { return (this.toegangenPerRol[rol.key] || []).length === 0; },
        rolIsMat(rol) { return rol.key === 'mat'; },
        rolIsNietMat(rol) { return rol.key !== 'mat'; },

        // --- Rendering helpers ---
        statusClass(toegang) { return toegang.is_gebonden ? 'text-green-600' : 'text-gray-400'; },
        tokenPrefix(toegang) { return toegang.code ? toegang.code.substring(0, 4) : ''; },
        interfaceLabel(rol) { return rol.key === 'mat' ? this.teksten.labelInterface : ''; },
        addToegangLabel(rol) { return `${rol.naam} ${this.teksten.toevoegenSuffix}`; },
        isVrijwilligerSelected(toegang, v) { return toegang.naam === v.voornaam; },
        vrijwilligerLabel(v) {
            const tel = v.telefoonnummer ? ` (${v.telefoonnummer})` : '';
            const email = v.email ? ` - ${v.email}` : '';
            return `${v.voornaam}${tel}${email}`;
        },
        telefoonOfStreep(v) { return v.telefoonnummer || '-'; },
        kanWhatsApp(rol, toegang) { return rol.key !== 'mat' && toegang.telefoon; },
        kanEmail(rol, toegang) { return rol.key !== 'mat' && toegang.email; },
        isJustSaved(toegang) { return this.savedId === toegang.id; },
        isCopied(prefix, toegang) { return this.copiedId === `${prefix}_${toegang.id}`; },
        notCopied(prefix, toegang) { return this.copiedId !== `${prefix}_${toegang.id}`; },
        qrVisibleVoor(toegang) {
            return this.showQr === `mat_${toegang.id}` || this.showQr === `lcd_${toegang.id}`;
        },
        qrPopupTitel() {
            return (this.showQr && this.showQr.startsWith('lcd_')) ? this.teksten.labelLcd : this.teksten.labelMatInterface;
        },
        qrImageUrl() { return `${this.urls.qr}?url=${encodeURIComponent(this.qrUrl)}`; },
        tvLinkVisible(toegang) { return this.showTvLink === toegang.id; },
        tvLinkSuccess() { return this.tvLinkStatus === 'success'; },
        tvLinkError() { return this.tvLinkStatus === 'error'; },

        // --- Computed ---
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
        get hasAnyToegang() { return Object.values(this.toegangenPerRol).flat().length > 0; },
        get vrijwilligersLeeg() { return this.vrijwilligers.length === 0; },
        get addVrijwilligerDisabled() { return !this.newVrijwilliger.voornaam; },

        // --- Modal helpers ---
        openVrijwilligersModal() { this.showVrijwilligersModal = true; },
        closeVrijwilligersModal() { this.showVrijwilligersModal = false; },

        // --- QR/TV toggle helpers ---
        toggleQrMat(toegang) {
            const key = `mat_${toegang.id}`;
            this.showQr = this.showQr === key ? null : key;
            this.qrUrl = toegang.url;
        },
        toggleQrLcd(toegang) {
            const key = `lcd_${toegang.id}`;
            this.showQr = this.showQr === key ? null : key;
            const suffix = toegang.code ? toegang.code.substring(0, 4) : '';
            this.qrUrl = `${this.urls.tvBase}/${suffix}`;
        },
        toggleTvLink(toegang) {
            this.showTvLink = this.showTvLink === toegang.id ? null : toegang.id;
        },

        // --- Clipboard helpers ---
        copyUrl(toegang) {
            navigator.clipboard.writeText(toegang.url);
            this.flashCopied(`url_${toegang.id}`);
        },
        copyTvUrl(toegang) {
            const suffix = toegang.code ? toegang.code.substring(0, 4) : '';
            navigator.clipboard.writeText(`${this.urls.tvBase}/${suffix}`);
            this.flashCopied(`tv_${toegang.id}`);
        },
        copyQrUrl(toegang) {
            navigator.clipboard.writeText(this.qrUrl);
            this.flashCopied(`qr_${toegang.id}`);
        },
        flashCopied(id) {
            this.copiedId = id;
            setTimeout(() => { this.copiedId = null; }, 2000);
        },

        // --- Init & API ---
        async init() {
            await Promise.all([this.loadToegangen(), this.loadVrijwilligers()]);
        },

        async loadToegangen() {
            try {
                const response = await fetch(this.urls.toegangen, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                });
                if (response.ok) this.toegangen = await response.json();
            } catch (e) {}
        },

        async loadVrijwilligers() {
            try {
                const response = await fetch(this.urls.vrijwilligers, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                });
                if (response.ok) this.vrijwilligers = await response.json();
            } catch (e) {}
        },

        _csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },

        async addVrijwilliger() {
            if (!this.newVrijwilliger.voornaam) return;
            try {
                const response = await fetch(this.urls.vrijwilligerStore, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this._csrf(),
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
            if (!confirm(`${v.voornaam} ${this.teksten.verwijderen}`)) return;
            try {
                const response = await fetch(`${this.urls.vrijwilligerBase}/${v.id}`, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': this._csrf(), 'Accept': 'application/json' },
                });
                if (response.ok) this.vrijwilligers = this.vrijwilligers.filter(x => x.id !== v.id);
            } catch (e) {}
        },

        async selectVrijwilliger(toegang, vrijwilligerId) {
            const v = this.vrijwilligers.find(x => x.id == vrijwilligerId);
            const naam = v ? v.voornaam : '';
            const telefoon = v ? v.telefoonnummer : null;
            const email = v ? v.email : null;
            try {
                const response = await fetch(`${this.urls.toegangBase}/${toegang.id}`, {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this._csrf(),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ naam, telefoon, email }),
                });
                if (response.ok) {
                    const updated = await response.json();
                    Object.assign(toegang, updated);
                    this.savedId = toegang.id;
                    setTimeout(() => { this.savedId = null; }, 2000);
                }
            } catch (e) {
                console.error('Failed to update toegang:', e);
            }
        },

        getWhatsAppUrl(toegang) {
            if (!toegang.telefoon) return '';
            let nummer = toegang.telefoon.replace(/[^0-9+]/g, '');
            if (nummer.startsWith('06') || nummer.startsWith('0')) {
                nummer = '+31' + nummer.substring(1);
            }
            const bericht = `${this.teksten.hoi} ${toegang.naam || this.teksten.daar}! ${this.teksten.hierIsLink} ${toegang.label} ${this.teksten.op} ${this.toernooiNaam}:\n${toegang.url}`;
            return 'https://wa.me/' + nummer.replace('+', '') + '?text=' + encodeURIComponent(bericht);
        },

        getEmailUrl(toegang) {
            if (!toegang.email) return '';
            const subject = `${this.teksten.toegang} ${toegang.label} - ${this.toernooiNaam}`;
            const body = `${this.teksten.hoi} ${toegang.naam || this.teksten.daar}!\n\n${this.teksten.hierIsLink} ${toegang.label} ${this.teksten.op} ${this.toernooiNaam}:\n${toegang.url}\n\n${this.teksten.klikLink}`;
            return 'mailto:' + encodeURIComponent(toegang.email) + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
        },

        async addToegang(rolKey) {
            const data = { rol: rolKey, naam: '' };
            if (rolKey === 'mat') {
                const matToegangen = this.toegangenPerRol.mat || [];
                const usedNumbers = matToegangen.map(t => t.mat_nummer).filter(n => n);
                let matNummer = 1;
                while (usedNumbers.includes(matNummer)) matNummer++;
                data.mat_nummer = matNummer;
            }
            try {
                const response = await fetch(this.urls.toegangStore, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this._csrf(),
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
            if (!confirm(this.teksten.bevestigReset)) return;
            try {
                const response = await fetch(`${this.urls.toegangBase}/${toegang.id}/reset`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': this._csrf(), 'Accept': 'application/json' },
                });
                if (response.ok) {
                    toegang.is_gebonden = false;
                    toegang.device_info = null;
                    toegang.status = this.teksten.wachtOpBinding;
                    window.dispatchEvent(new CustomEvent('toegangen-updated'));
                }
            } catch (e) {}
        },

        async deleteToegang(toegang) {
            if (!confirm(this.teksten.bevestigVerwijder)) return;
            try {
                const response = await fetch(`${this.urls.toegangBase}/${toegang.id}`, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': this._csrf(), 'Accept': 'application/json' },
                });
                if (response.ok) {
                    this.toegangen = this.toegangen.filter(t => t.id !== toegang.id);
                    window.dispatchEvent(new CustomEvent('toegangen-updated'));
                }
            } catch (e) {}
        },

        async resetAll() {
            if (!confirm(this.teksten.bevestigResetAll)) return;
            try {
                const response = await fetch(this.urls.resetAll, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': this._csrf(), 'Accept': 'application/json' },
                });
                if (response.ok) {
                    this.toegangen.forEach(t => {
                        t.is_gebonden = false;
                        t.device_info = null;
                        t.status = this.teksten.wachtOpBinding;
                    });
                    window.dispatchEvent(new CustomEvent('toegangen-updated'));
                }
            } catch (e) {}
        },

        getLcdUrl(toegang) {
            return `${this.urls.scoreboardBase}/${toegang.mat_nummer}`;
        },

        async linkTv(toegang, code) {
            if (!code || code.length !== 4) {
                this.tvLinkStatus = 'error';
                this.tvLinkErrorMessage = this.teksten.ongeldigeCode;
                return;
            }
            this.tvLinkStatus = null;
            this.tvLinkErrorMessage = '';
            try {
                const response = await fetch(this.urls.tvLink, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this._csrf(),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        code: code,
                        toernooi_id: this.toernooiId,
                        mat_nummer: toegang.mat_nummer,
                    }),
                });
                const data = await response.json();
                if (data.success) {
                    this.tvLinkStatus = 'success';
                    setTimeout(() => { this.showTvLink = null; this.tvLinkStatus = null; }, 3000);
                } else {
                    this.tvLinkStatus = 'error';
                    this.tvLinkErrorMessage = data.message || this.teksten.koppelingMislukt;
                }
            } catch (e) {
                this.tvLinkStatus = 'error';
                this.tvLinkErrorMessage = this.teksten.netwerkfout;
            }
        },

    }));
});
</script>
