<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#1e40af">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="/manifest-weging.json">
    <link rel="icon" type="image/png" sizes="192x192" href="/icon-192x192.png">
    <link rel="apple-touch-icon" href="/icon-192x192.png">
    <title>Weging - {{ $toernooi->naam }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        body { overscroll-behavior: none; -webkit-user-select: none; user-select: none; }
        #qr-reader { width: 100%; }
        #qr-reader video { border-radius: 0.5rem; }
        #qr-reader__dashboard, #qr-reader__scan_region > img { display: none !important; }
        .numpad-btn { font-size: 1.25rem; font-weight: bold; min-height: 50px; }
    </style>
</head>
<body class="bg-blue-900 min-h-screen text-white" x-data="wegingApp()" x-init="init()">
    <!-- Header -->
    <header class="bg-blue-800 px-4 py-3 flex items-center justify-between shadow-lg">
        <div>
            <h1 class="text-lg font-bold">Weging</h1>
            <p class="text-blue-200 text-sm">{{ $toernooi->naam }}</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="text-2xl font-mono" id="clock"></div>
            <button @click="showAbout = true" class="p-2 hover:bg-blue-700 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </button>
        </div>
    </header>

    <main class="p-3 space-y-3">
        <!-- TOP: Scanner section (1/3 of screen) -->
        <div class="bg-blue-800/50 rounded-lg p-3">
            <!-- Mode toggle -->
            <div class="flex gap-2 mb-3">
                <button @click="modus = 'zoek'; stopScanner()"
                        :class="modus === 'zoek' ? 'bg-blue-600 text-white' : 'bg-blue-700/50 text-blue-200'"
                        class="flex-1 py-2 rounded font-medium text-sm">
                    🔍 Zoeken
                </button>
                <button @click="modus = 'scan'; startScanner()"
                        :class="modus === 'scan' ? 'bg-blue-600 text-white' : 'bg-blue-700/50 text-blue-200'"
                        class="flex-1 py-2 rounded font-medium text-sm">
                    📷 Scannen
                </button>
            </div>

            <!-- Scanner (compact) -->
            <div x-show="modus === 'scan'" class="relative">
                <div id="qr-reader" style="max-width: 280px; margin: 0 auto;"></div>
                <button @click="stopScanner()" class="absolute top-2 right-2 bg-red-600 hover:bg-red-700 px-2 py-1 rounded text-xs">
                    Stop
                </button>
            </div>

            <!-- Search input -->
            <div x-show="modus === 'zoek'">
                <input type="text" x-model="zoekterm" @input.debounce.300ms="zoekJudoka()"
                       placeholder="Zoek op naam..."
                       class="w-full border-2 border-blue-500 bg-blue-800 rounded-lg px-4 py-3 text-lg focus:border-blue-300 focus:outline-none placeholder-blue-400">

                <div x-show="resultaten.length > 0" class="mt-2 bg-white rounded-lg max-h-40 overflow-y-auto">
                    <template x-for="judoka in resultaten" :key="judoka.id">
                        <div @click="selecteerJudoka(judoka)"
                             class="p-3 hover:bg-blue-100 cursor-pointer border-b last:border-0 text-gray-800">
                            <div class="font-medium" x-text="judoka.naam"></div>
                            <div class="text-sm text-gray-600">
                                <span x-text="judoka.club || 'Geen club'"></span> |
                                <span x-text="judoka.gewichtsklasse + ' kg'"></span>
                                <span x-show="judoka.gewogen" class="text-green-600 ml-2">✓ gewogen</span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- UNDER SCANNER: Blok + Stats + Countdown -->
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
                <!-- Countdown timer + sluit knop -->
                @foreach($toernooi->blokken as $blok)
                @if($blok->weging_einde && !$blok->weging_gesloten)
                <div x-data="countdown('{{ $blok->weging_einde->toISOString() }}')"
                     x-show="blokFilter == '{{ $blok->nummer }}' || blokFilter === ''"
                     x-init="start()"
                     class="flex items-center gap-3">
                    <div class="text-right">
                        <div class="text-xs text-gray-500">Blok {{ $blok->nummer }} tot {{ $blok->weging_einde->format('H:i') }}</div>
                        <div class="font-mono text-lg font-bold" :class="expired ? 'text-red-600' : (warning ? 'text-yellow-600' : 'text-blue-600')" x-text="display"></div>
                    </div>
                    <form action="{{ route('toernooi.blok.sluit-weging', [$toernooi, $blok]) }}" method="POST">
                        @csrf
                        <button type="submit" onclick="return confirm('Weging Blok {{ $blok->nummer }} sluiten?')"
                                class="px-3 py-2 rounded text-sm font-medium transition-colors"
                                :class="expired ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700'">
                            Sluit
                        </button>
                    </form>
                </div>
                @elseif($blok->weging_gesloten)
                <div x-show="blokFilter == '{{ $blok->nummer }}'" class="text-right text-gray-500 text-sm">
                    ✓ Blok {{ $blok->nummer }} gesloten
                </div>
                @endif
                @endforeach
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

    </main>

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

    <script>
    // Clock
    function updateClock() {
        document.getElementById('clock').textContent = new Date().toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' });
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
                let url = `{{ route('toernooi.judoka.zoek', $toernooi) }}?q=${encodeURIComponent(this.zoekterm)}`;
                if (this.blokFilter) url += `&blok=${this.blokFilter}`;
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
                if (key === 'C') { this.gewichtInput = ''; return; }
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
                        { fps: 10, qrbox: { width: 180, height: 180 }, aspectRatio: 1.0 },
                        async (text) => {
                            let qr = text;
                            if (text.includes('/weegkaart/')) qr = text.split('/weegkaart/').pop();
                            await this.scanQR(qr);
                        },
                        () => {}
                    );
                } catch (err) {
                    this.melding = 'Camera niet beschikbaar';
                    this.meldingType = 'error';
                    this.modus = 'zoek';
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
                if (this.scanner) {
                    try { await this.scanner.stop(); } catch (e) {}
                    this.scanner = null;
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
                    this.modus = 'zoek';
                    if (navigator.vibrate) navigator.vibrate(100);
                } else {
                    this.melding = data.message || 'Niet gevonden';
                    this.meldingType = 'error';
                    this.modus = 'zoek';
                }
            },

            async registreerGewicht() {
                if (!this.geselecteerd || !this.gewichtInput || this.bezig) return;
                this.bezig = true;
                this.melding = '';
                try {
                    const response = await fetch(`{{ url('toernooi/' . $toernooi->id . '/weging') }}/${this.geselecteerd.id}/registreer`, {
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

    @include('partials.pwa-mobile', ['pwaApp' => 'weging'])
</body>
</html>
