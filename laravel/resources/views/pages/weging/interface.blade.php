@extends('layouts.app')

@section('title', 'Weging Interface')

@push('styles')
<style>
    /* Tablet-optimized layout */
    @media (min-width: 768px) {
        .weging-container { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
    }
    /* Large numpad buttons */
    .numpad-btn {
        font-size: 1.5rem;
        font-weight: bold;
        min-height: 60px;
    }
    /* Status colors */
    .status-gewogen { background-color: #dcfce7; border-color: #16a34a; }
    .status-aanwezig { background-color: #fef3c7; border-color: #d97706; }
    .status-afwezig { background-color: #fee2e2; border-color: #dc2626; }
    /* Scanner viewport */
    #qr-reader { width: 100%; max-width: 400px; margin: 0 auto; }
    #qr-reader video { border-radius: 0.5rem; }
</style>
@endpush

@section('content')
<div x-data="wegingInterface()" x-init="init()" class="max-w-6xl mx-auto">
    <!-- Header with stats -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex flex-wrap justify-between items-center gap-4">
            <h1 class="text-2xl font-bold text-gray-800">Weging Interface</h1>
            <div class="flex gap-4 text-sm">
                <span class="px-3 py-1 rounded-full bg-green-100 text-green-800">
                    Gewogen: <span x-text="stats.gewogen">0</span>
                </span>
                <span class="px-3 py-1 rounded-full bg-yellow-100 text-yellow-800">
                    Aanwezig: <span x-text="stats.aanwezig">0</span>
                </span>
                <span class="px-3 py-1 rounded-full bg-gray-100 text-gray-800">
                    Totaal: <span x-text="stats.totaal">0</span>
                </span>
            </div>
        </div>

        <!-- Blok filter -->
        <div class="mt-4 flex flex-wrap gap-2">
            <button @click="selectBlok(null)"
                    :class="blokFilter === null ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'"
                    class="px-4 py-2 rounded font-medium">
                Alle blokken
            </button>
            @foreach($toernooi->blokken as $blok)
            <button @click="selectBlok({{ $blok->nummer }})"
                    :class="blokFilter === {{ $blok->nummer }} ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'"
                    class="px-4 py-2 rounded font-medium">
                Blok {{ $blok->nummer }}
                @if($blok->weging_start)
                <span class="text-xs opacity-75">({{ $blok->weging_start->format('H:i') }})</span>
                @endif
            </button>
            @endforeach
        </div>
    </div>

    <div class="weging-container">
        <!-- Left: Search/Scan -->
        <div class="space-y-4">
            <!-- Mode toggle -->
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex gap-2 mb-4">
                    <button @click="modus = 'zoek'"
                            :class="modus === 'zoek' ? 'bg-blue-600 text-white' : 'bg-gray-200'"
                            class="flex-1 py-3 rounded font-medium flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        Zoeken
                    </button>
                    <button @click="modus = 'scan'; startScanner()"
                            :class="modus === 'scan' ? 'bg-blue-600 text-white' : 'bg-gray-200'"
                            class="flex-1 py-3 rounded font-medium flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                        </svg>
                        QR Scan
                    </button>
                </div>

                <!-- Search mode -->
                <div x-show="modus === 'zoek'">
                    <input type="text" x-model="zoekterm" @input.debounce.300ms="zoekJudoka()"
                           placeholder="Zoek op naam..."
                           class="w-full border-2 rounded-lg px-4 py-3 text-lg focus:border-blue-500 focus:outline-none">

                    <div x-show="resultaten.length > 0" class="mt-2 border rounded-lg max-h-64 overflow-y-auto">
                        <template x-for="judoka in resultaten" :key="judoka.id">
                            <div @click="selecteerJudoka(judoka)"
                                 class="p-3 hover:bg-blue-50 cursor-pointer border-b last:border-0 flex justify-between items-center">
                                <div>
                                    <div class="font-medium" x-text="judoka.naam"></div>
                                    <div class="text-sm text-gray-600">
                                        <span x-text="judoka.club || 'Geen club'"></span> |
                                        <span x-text="judoka.gewichtsklasse + ' kg'"></span>
                                    </div>
                                </div>
                                <div x-show="judoka.gewogen" class="text-green-600 text-sm font-medium">
                                    <span x-text="judoka.gewicht_gewogen + ' kg'"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- QR Scanner mode -->
                <div x-show="modus === 'scan'">
                    <div id="qr-reader"></div>
                    <p class="text-center text-gray-500 mt-2 text-sm">Richt de camera op de QR-code</p>
                </div>
            </div>

            <!-- Recent weighings -->
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="font-bold text-gray-700 mb-3">Recente wegingen</h3>
                <div class="space-y-2 max-h-48 overflow-y-auto">
                    <template x-for="item in recenteWegingen" :key="item.id">
                        <div class="flex justify-between items-center text-sm p-2 bg-gray-50 rounded">
                            <span x-text="item.naam"></span>
                            <span class="font-medium" x-text="item.gewicht + ' kg'"></span>
                        </div>
                    </template>
                    <div x-show="recenteWegingen.length === 0" class="text-gray-400 text-sm text-center py-4">
                        Nog geen wegingen
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Judoka details + weight input -->
        <div class="bg-white rounded-lg shadow p-4">
            <!-- No selection state -->
            <div x-show="!geselecteerd" class="text-center py-12 text-gray-400">
                <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <p>Zoek of scan een judoka</p>
            </div>

            <!-- Selected judoka -->
            <div x-show="geselecteerd" x-cloak>
                <!-- Judoka info card -->
                <div class="border-2 rounded-lg p-4 mb-4"
                     :class="geselecteerd?.gewogen ? 'status-gewogen border-green-500' : 'border-gray-200'">
                    <div class="flex justify-between items-start">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800" x-text="geselecteerd?.naam"></h2>
                            <p class="text-gray-600" x-text="geselecteerd?.club || 'Geen club'"></p>
                        </div>
                        <button @click="geselecteerd = null; gewichtInput = ''"
                                class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-3 gap-2 mt-4">
                        <div class="bg-gray-100 rounded p-2 text-center">
                            <div class="text-xs text-gray-500">Klasse</div>
                            <div class="font-bold" x-text="(geselecteerd?.gewichtsklasse || '?') + ' kg'"></div>
                        </div>
                        <div class="bg-gray-100 rounded p-2 text-center">
                            <div class="text-xs text-gray-500">Leeftijd</div>
                            <div class="font-bold" x-text="geselecteerd?.leeftijdsklasse || '?'"></div>
                        </div>
                        <div class="bg-gray-100 rounded p-2 text-center">
                            <div class="text-xs text-gray-500">Blok</div>
                            <div class="font-bold" x-text="geselecteerd?.blok ? 'Blok ' + geselecteerd.blok : '-'"></div>
                        </div>
                    </div>

                    <!-- Already weighed indicator -->
                    <div x-show="geselecteerd?.gewogen" class="mt-4 bg-green-100 text-green-800 p-3 rounded text-center">
                        Al gewogen: <span class="font-bold" x-text="geselecteerd?.gewicht_gewogen + ' kg'"></span>
                    </div>
                </div>

                <!-- Weight input -->
                <div class="mb-4">
                    <label class="block text-gray-700 font-bold mb-2">Gewicht (kg)</label>
                    <div class="relative">
                        <input type="text" x-model="gewichtInput" readonly
                               class="w-full border-2 rounded-lg px-4 py-4 text-3xl text-center font-bold focus:border-blue-500"
                               :class="gewichtInput ? 'border-blue-500' : 'border-gray-300'"
                               placeholder="0.0">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-xl">kg</span>
                    </div>
                </div>

                <!-- Numpad -->
                <div class="grid grid-cols-3 gap-2 mb-4">
                    <template x-for="n in ['7','8','9','4','5','6','1','2','3','.','0','C']">
                        <button @click="numpadInput(n)"
                                class="numpad-btn bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
                                :class="n === 'C' ? 'bg-red-100 hover:bg-red-200 text-red-700' : ''"
                                x-text="n"></button>
                    </template>
                </div>

                <!-- Action buttons -->
                <div class="space-y-2">
                    <button @click="registreerGewicht()"
                            :disabled="!gewichtInput || bezig"
                            class="w-full bg-green-600 hover:bg-green-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-bold py-4 rounded-lg text-xl flex items-center justify-center gap-2">
                        <svg x-show="bezig" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span x-text="bezig ? 'Bezig...' : 'Registreer Gewicht'"></span>
                    </button>

                    <div class="grid grid-cols-2 gap-2">
                        <button @click="markeerAanwezig()"
                                :disabled="bezig"
                                class="bg-yellow-500 hover:bg-yellow-600 text-white font-medium py-3 rounded-lg">
                            Alleen aanwezig
                        </button>
                        <button @click="markeerAfwezig()"
                                :disabled="bezig"
                                class="bg-red-500 hover:bg-red-600 text-white font-medium py-3 rounded-lg">
                            Afwezig
                        </button>
                    </div>
                </div>

                <!-- Feedback message -->
                <div x-show="melding" x-transition
                     class="mt-4 p-4 rounded-lg text-center font-medium"
                     :class="meldingType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                    <span x-text="melding"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QR Scanner library -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
function wegingInterface() {
    return {
        modus: 'zoek',
        zoekterm: '',
        resultaten: [],
        geselecteerd: null,
        gewichtInput: '',
        melding: '',
        meldingType: 'success',
        bezig: false,
        blokFilter: null,
        recenteWegingen: [],
        stats: { gewogen: 0, aanwezig: 0, totaal: 0 },
        scanner: null,

        init() {
            this.laadStats();
        },

        async laadStats() {
            // Stats could be loaded from API
            // For now we use initial data
        },

        selectBlok(nummer) {
            this.blokFilter = nummer;
            this.zoekJudoka();
        },

        async zoekJudoka() {
            if (this.zoekterm.length < 2) {
                this.resultaten = [];
                return;
            }

            let url = `{{ route('toernooi.judoka.zoek', $toernooi) }}?q=${encodeURIComponent(this.zoekterm)}`;
            if (this.blokFilter) {
                url += `&blok=${this.blokFilter}`;
            }

            const response = await fetch(url);
            this.resultaten = await response.json();
        },

        selecteerJudoka(judoka) {
            this.geselecteerd = judoka;
            this.resultaten = [];
            this.zoekterm = '';
            this.gewichtInput = judoka.gewicht_gewogen ? String(judoka.gewicht_gewogen) : '';
            this.melding = '';
        },

        numpadInput(key) {
            if (key === 'C') {
                this.gewichtInput = '';
                return;
            }
            if (key === '.' && this.gewichtInput.includes('.')) return;
            if (this.gewichtInput.length >= 5) return;

            this.gewichtInput += key;
        },

        async startScanner() {
            if (this.scanner) {
                try { await this.scanner.stop(); } catch (e) {}
            }

            await this.$nextTick();

            this.scanner = new Html5Qrcode("qr-reader");

            try {
                await this.scanner.start(
                    { facingMode: "environment" },
                    { fps: 10, qrbox: { width: 250, height: 250 } },
                    async (decodedText) => {
                        // Extract QR code from URL or use directly
                        let qrCode = decodedText;
                        if (decodedText.includes('/weegkaart/')) {
                            qrCode = decodedText.split('/weegkaart/').pop();
                        }

                        await this.scanQR(qrCode);
                    },
                    (errorMessage) => {
                        // Ignore scan errors
                    }
                );
            } catch (err) {
                console.error('Camera error:', err);
                this.melding = 'Camera niet beschikbaar';
                this.meldingType = 'error';
            }
        },

        async scanQR(qrCode) {
            // Stop scanner temporarily
            if (this.scanner) {
                try { await this.scanner.stop(); } catch (e) {}
            }

            const response = await fetch(`{{ route('toernooi.weging.scan-qr', $toernooi) }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ qr_code: qrCode })
            });

            const data = await response.json();

            if (data.success) {
                this.selecteerJudoka(data.judoka);
                this.modus = 'zoek'; // Switch to search mode to show details
            } else {
                this.melding = data.message || 'Judoka niet gevonden';
                this.meldingType = 'error';
                // Restart scanner
                setTimeout(() => this.startScanner(), 2000);
            }
        },

        async registreerGewicht() {
            if (!this.geselecteerd || !this.gewichtInput || this.bezig) return;

            this.bezig = true;
            this.melding = '';

            try {
                const response = await fetch(`/toernooi/{{ $toernooi->id }}/weging/${this.geselecteerd.id}/registreer`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ gewicht: parseFloat(this.gewichtInput) })
                });

                const data = await response.json();

                if (data.success) {
                    // Update recent list
                    this.recenteWegingen.unshift({
                        id: this.geselecteerd.id,
                        naam: this.geselecteerd.naam,
                        gewicht: this.gewichtInput
                    });
                    if (this.recenteWegingen.length > 10) this.recenteWegingen.pop();

                    if (data.binnen_klasse) {
                        this.melding = `Gewicht ${this.gewichtInput} kg geregistreerd!`;
                        this.meldingType = 'success';
                    } else {
                        this.melding = `Let op: ${data.opmerking}`;
                        this.meldingType = 'error';
                    }

                    // Mark as weighed
                    this.geselecteerd.gewogen = true;
                    this.geselecteerd.gewicht_gewogen = this.gewichtInput;

                    // Clear after 2 seconds for next judoka
                    setTimeout(() => {
                        this.geselecteerd = null;
                        this.gewichtInput = '';
                        this.melding = '';
                    }, 2000);
                }
            } catch (error) {
                this.melding = 'Fout bij registreren';
                this.meldingType = 'error';
            } finally {
                this.bezig = false;
            }
        },

        async markeerAanwezig() {
            if (!this.geselecteerd || this.bezig) return;

            this.bezig = true;

            try {
                await fetch(`/toernooi/{{ $toernooi->id }}/weging/${this.geselecteerd.id}/aanwezig`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                this.melding = 'Gemarkeerd als aanwezig';
                this.meldingType = 'success';

                setTimeout(() => {
                    this.geselecteerd = null;
                    this.gewichtInput = '';
                    this.melding = '';
                }, 1500);
            } finally {
                this.bezig = false;
            }
        },

        async markeerAfwezig() {
            if (!this.geselecteerd || this.bezig) return;

            this.bezig = true;

            try {
                await fetch(`/toernooi/{{ $toernooi->id }}/weging/${this.geselecteerd.id}/afwezig`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                this.melding = 'Gemarkeerd als afwezig';
                this.meldingType = 'error';

                setTimeout(() => {
                    this.geselecteerd = null;
                    this.gewichtInput = '';
                    this.melding = '';
                }, 1500);
            } finally {
                this.bezig = false;
            }
        }
    }
}
</script>
@endsection
