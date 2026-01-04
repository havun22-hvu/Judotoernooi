    @php $pwaApp = 'weging'; @endphp
<div x-data="wegingApp()" x-init="init()">
    <!-- TOP: Scanner gebied - VASTE HOOGTE -->
    <div class="bg-blue-800/50 rounded-lg p-3 mb-2">
        <!-- Scanner area - VASTE HOOGTE zodat zoekvak niet springt -->
        <div class="flex items-center justify-center" style="height: 220px;">
            <!-- Scan button (when not scanning) -->
            <button x-show="modus === 'zoek'" @click="modus = 'scan'; startScanner()"
                    class="bg-green-600 hover:bg-green-700 text-white rounded-full w-28 h-28 flex flex-col items-center justify-center shadow-lg">
                <span class="text-3xl mb-1">📷</span>
                <span class="font-bold">Scan</span>
            </button>

            <!-- Scanner (when scanning) -->
            <div x-show="modus === 'scan'" class="w-full">
                <div id="qr-reader" style="width: 100%; max-width: 280px; margin: 0 auto;"></div>
                <!-- Stop knop ONDER scanner -->
                <div class="text-center mt-2">
                    <button @click="stopScanner()" class="px-6 py-2 bg-red-600 hover:bg-red-700 rounded-lg font-bold">
                        Stop Scanner
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Zoek input - STICKY VASTE POSITIE buiten scanner box -->
    <div class="mb-2">
        <input type="text" x-model="zoekterm" @input.debounce.300ms="zoekJudoka()"
               placeholder="Zoek op naam..."
               class="w-full border-2 border-blue-400 bg-blue-900 rounded-lg px-4 py-2 text-center focus:border-white focus:outline-none placeholder-blue-300 text-white">
    </div>

    <!-- Search results (overlay) -->
    <div x-show="resultaten.length > 0" class="absolute left-3 right-3 bg-white rounded-lg shadow-lg max-h-48 overflow-y-auto z-10" style="top: 320px;">
        <template x-for="judoka in resultaten" :key="judoka.id">
            <div @click="selecteerJudoka(judoka)"
                 class="p-3 hover:bg-blue-100 cursor-pointer border-b last:border-0 text-gray-800">
                <div class="font-medium" x-text="judoka.naam"></div>
                <div class="text-sm text-gray-600">
                    <span x-text="judoka.club || 'Geen club'"></span> |
                    <span x-text="judoka.gewichtsklasse + ' kg'"></span>
                    <span x-show="judoka.gewogen" class="text-green-600 ml-2">✓</span>
                </div>
            </div>
        </template>
    </div>

    <!-- BOTTOM HALF: Blok/Stats + Judoka (fixed position) -->
    <div class="flex-1 flex flex-col space-y-3 overflow-y-auto">
    <div class="bg-white rounded-lg shadow p-3 text-gray-800">
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
                <select x-model="blokFilter" @change="selectBlok()"
                        class="border-2 border-gray-300 rounded px-2 py-1 text-sm font-medium">
                    <option value="">Alle blokken</option>
                    @foreach($toernooi->blokken as $blok)
                    <option value="{{ $blok->nummer }}">Blok {{ $blok->nummer }}</option>
                    @endforeach
                </select>
                <div class="text-lg font-bold">
                    <span class="text-green-600" x-text="stats.gewogen">0</span>
                    <span class="text-gray-400">/</span>
                    <span class="text-gray-600" x-text="stats.totaal">0</span>
                </div>
            </div>
        </div>
    </div>

    <!-- BOTTOM: Judoka details + Numpad -->
    <div class="bg-white rounded-lg shadow p-4 text-gray-800">
        <!-- No selection -->
        <div x-show="!geselecteerd" class="text-center py-8 text-gray-400">
            <p class="text-lg">Scan of zoek een judoka</p>
        </div>

        <!-- Selected judoka -->
        <div x-show="geselecteerd" x-cloak>
            <!-- Info card -->
            <div class="border-2 rounded-lg p-3 mb-3" :class="geselecteerd?.gewogen ? 'border-green-500 bg-green-50' : 'border-gray-200'">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-xl font-bold" x-text="geselecteerd?.naam"></h2>
                        <p class="text-gray-600 text-sm" x-text="geselecteerd?.club || 'Geen club'"></p>
                    </div>
                    <button @click="geselecteerd = null; gewichtInput = ''" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
                </div>
                <div class="flex gap-2 mt-2 text-sm">
                    <span class="bg-gray-100 px-2 py-1 rounded" x-text="(geselecteerd?.gewichtsklasse || '?') + ' kg'"></span>
                    <span class="bg-gray-100 px-2 py-1 rounded" x-text="geselecteerd?.leeftijdsklasse || '?'"></span>
                    <span class="bg-gray-100 px-2 py-1 rounded" x-text="geselecteerd?.blok ? 'Blok ' + geselecteerd.blok : '-'"></span>
                </div>
                <div x-show="geselecteerd?.gewogen" class="mt-2 text-green-700 font-medium">
                    Al gewogen: <span x-text="geselecteerd?.gewicht_gewogen + ' kg'"></span>
                </div>
            </div>

            <!-- Weight input -->
            <div class="mb-3">
                <div class="relative">
                    <input type="text" x-model="gewichtInput" readonly
                           class="w-full border-2 rounded-lg px-4 py-3 text-2xl text-center font-bold"
                           :class="gewichtInput ? 'border-blue-500' : 'border-gray-300'"
                           placeholder="0.0">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400">kg</span>
                </div>
            </div>

            <!-- Compact Numpad -->
            <div class="grid grid-cols-4 gap-1 mb-3">
                <template x-for="n in ['7','8','9','C','4','5','6','.','1','2','3','0']">
                    <button @click="numpadInput(n)"
                            class="numpad-btn rounded-lg transition-colors"
                            :class="n === 'C' ? 'bg-red-100 hover:bg-red-200 text-red-700' : 'bg-gray-100 hover:bg-gray-200'"
                            x-text="n"></button>
                </template>
            </div>

            <!-- Register button -->
            <button @click="registreerGewicht()"
                    :disabled="!gewichtInput || bezig"
                    class="w-full bg-green-600 hover:bg-green-700 disabled:bg-gray-300 text-white font-bold py-3 rounded-lg text-lg">
                <span x-text="bezig ? 'Bezig...' : '✓ Registreer'"></span>
            </button>

            <!-- Feedback -->
            <div x-show="melding" x-transition class="mt-3 p-3 rounded-lg text-center font-medium"
                 :class="meldingType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                 x-text="melding"></div>
        </div>
    </div>
    </div>

    <!-- About Modal -->
    <div x-show="showAbout" x-cloak class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl p-6 max-w-sm w-full text-gray-800">
            <h2 class="text-xl font-bold mb-4">Over</h2>
            <p class="mb-2"><strong>Weging Interface</strong></p>
            <p class="text-gray-600 mb-4">{{ $toernooi->naam }}</p>
            <p class="text-sm text-gray-500 mb-4">Versie {{ config('toernooi.version') }}</p>
            <button @click="showAbout = false" class="w-full bg-blue-600 text-white py-2 rounded-lg font-medium">
                Sluiten
            </button>
        </div>
    </div>
</div>

<script>
// Clock
function updateClock() {
    const clockEl = document.getElementById('clock');
    if (clockEl) {
        clockEl.textContent = new Date().toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' });
    }
}
updateClock();
setInterval(updateClock, 1000);

// Countdown timer
function countdown(eindtijd) {
    return {
        end: new Date(eindtijd),
        display: '',
        expired: false,
        warning: false,
        interval: null,
        start() {
            this.update();
            this.interval = setInterval(() => this.update(), 1000);
        },
        update() {
            const diff = this.end - new Date();
            if (diff <= 0) {
                this.expired = true;
                this.display = 'Voorbij!';
                clearInterval(this.interval);
                return;
            }
            this.warning = diff <= 5 * 60 * 1000;
            const m = Math.floor(diff / 60000);
            const s = Math.floor((diff % 60000) / 1000);
            this.display = `${m}:${String(s).padStart(2, '0')}`;
        }
    }
}

// Main app
function wegingApp() {
    return {
        modus: 'zoek',
        zoekterm: '',
        resultaten: [],
        geselecteerd: null,
        gewichtInput: '',
        melding: '',
        meldingType: 'success',
        bezig: false,
        blokFilter: '',
        stats: { gewogen: 0, totaal: 0 },
        scanner: null,
        showAbout: false,

        init() {},

        selectBlok() {
            this.zoekJudoka();
        },

        async zoekJudoka() {
            if (this.zoekterm.length < 2) {
                this.resultaten = [];
                return;
            }
            // Use current origin to avoid cached wrong URLs
            let url = `${window.location.origin}/toernooi/{{ $toernooi->slug }}/judoka/zoek?q=${encodeURIComponent(this.zoekterm)}`;
            if (this.blokFilter) url += `&blok=${this.blokFilter}`;
            try {
                const response = await fetch(url);
                this.resultaten = await response.json();
            } catch (e) {
                console.error('Zoek error:', e);
            }
        },

        selecteerJudoka(judoka) {
            this.geselecteerd = judoka;
            this.resultaten = [];
            this.zoekterm = '';
            this.gewichtInput = judoka.gewicht_gewogen ? String(judoka.gewicht_gewogen) : '';
            this.melding = '';
        },

        numpadInput(key) {
            if (key === 'C') { this.gewichtInput = ''; return; }
            if (key === '.' && this.gewichtInput.includes('.')) return;
            if (this.gewichtInput.length >= 5) return;
            this.gewichtInput += key;
        },

        async startScanner() {
            if (this.scanner) {
                try { await this.scanner.stop(); } catch (e) {}
            }
            this.melding = 'Camera starten...';
            this.meldingType = 'success';
            await this.$nextTick();
            // Extra delay om div zichtbaar te laten worden
            await new Promise(resolve => setTimeout(resolve, 300));
            this.scanner = new Html5Qrcode("qr-reader");
            try {
                this.melding = 'Camera openen...';
                await this.scanner.start(
                    { facingMode: "environment" },
                    { fps: 10, qrbox: { width: 200, height: 200 } },
                    async (text) => {
                        // QR gevonden - toon debug info op scherm
                        this.melding = 'QR: ' + text.substring(0, 50);
                        let qr = text;
                        if (text.includes('/weegkaart/')) qr = text.split('/weegkaart/').pop();
                        this.melding = 'Code: ' + qr.substring(0, 20);
                        await this.scanQR(qr);
                    },
                    (errorMessage) => {
                        // Scan error (normaal bij zoeken) - negeer
                    }
                );
                this.melding = 'Richt camera op QR code';
            } catch (err) {
                this.melding = 'Camera fout: ' + (err.message || err);
                this.meldingType = 'error';
                this.modus = 'zoek';
                console.error('Scanner error:', err);
            }
        },

        async stopScanner() {
            if (this.scanner) {
                try { await this.scanner.stop(); } catch (e) {}
                this.scanner = null;
            }
            this.modus = 'zoek';
        },

        async scanQR(qrCode) {
            console.log('scanQR called with:', qrCode);
            if (this.scanner) {
                try { await this.scanner.stop(); } catch (e) {}
                this.scanner = null;
            }
            this.modus = 'zoek';
            this.melding = 'Zoeken: ' + qrCode.substring(0, 20) + '...';
            this.meldingType = 'success';

            // Use current origin to avoid cached wrong URLs
            const url = `${window.location.origin}/toernooi/{{ $toernooi->slug }}/weging/scan-qr`;
            console.log('Fetching URL:', url);
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ qr_code: qrCode })
                });
                console.log('Response status:', response.status);
                const data = await response.json();
                console.log('Response data:', data);
                if (data.success) {
                    this.selecteerJudoka(data.judoka);
                    this.melding = '';
                    if (navigator.vibrate) navigator.vibrate(100);
                } else {
                    this.melding = data.message || 'Niet gevonden';
                    this.meldingType = 'error';
                }
            } catch (err) {
                console.error('Fetch error:', err);
                this.melding = 'API fout: ' + err.message;
                this.meldingType = 'error';
            }
        },

        async registreerGewicht() {
            if (!this.geselecteerd || !this.gewichtInput || this.bezig) return;
            this.bezig = true;
            this.melding = '';
            try {
                // Use current origin to avoid cached wrong URLs
                const response = await fetch(`${window.location.origin}/toernooi/{{ $toernooi->slug }}/weging/${this.geselecteerd.id}/registreer`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ gewicht: parseFloat(this.gewichtInput) })
                });
                const data = await response.json();
                if (data.success) {
                    this.stats.gewogen++;
                    if (data.binnen_klasse) {
                        this.melding = `✓ ${this.gewichtInput} kg geregistreerd`;
                        this.meldingType = 'success';
                    } else {
                        this.melding = `⚠️ ${data.opmerking}`;
                        this.meldingType = 'error';
                    }
                    this.geselecteerd.gewogen = true;
                    this.geselecteerd.gewicht_gewogen = this.gewichtInput;
                    setTimeout(() => {
                        this.geselecteerd = null;
                        this.gewichtInput = '';
                        this.melding = '';
                    }, 2000);
                } else {
                    this.melding = data.message || 'Fout';
                    this.meldingType = 'error';
                }
            } catch (error) {
                this.melding = 'Fout: ' + error.message;
                this.meldingType = 'error';
            } finally {
                this.bezig = false;
            }
        }
    }
}
</script>
