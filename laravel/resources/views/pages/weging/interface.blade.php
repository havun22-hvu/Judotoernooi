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
<body class="bg-blue-900 min-h-screen text-white">
    <!-- Standalone Header (device-bound PWA) -->
    <header class="bg-blue-800 px-4 py-2 flex items-center justify-between shadow-lg">
        <div>
            <h1 class="text-lg font-bold">Weging</h1>
            <p class="text-blue-200 text-xs">{{ $toernooi->naam }}</p>
        </div>
        <div class="flex items-center gap-2">
            <!-- Countdown voor actief blok -->
            @php $actieveBlok = $toernooi->blokken->where('weging_gesloten', false)->first(); @endphp
            @if($actieveBlok && $actieveBlok->weging_einde)
            <div x-data="countdown('{{ $actieveBlok->weging_start?->toISOString() }}', '{{ $actieveBlok->weging_einde->toISOString() }}', {{ $actieveBlok->nummer }})" x-init="start()" class="text-right">
                <div class="text-[10px] text-blue-300">Blok {{ $actieveBlok->nummer }}</div>
                <div class="text-sm font-mono font-bold" :class="expired ? 'text-red-400 animate-pulse' : (warning ? 'text-yellow-400' : 'text-white')" x-text="display"></div>
            </div>
            @endif
            <div class="text-xl font-mono" id="clock"></div>
            <button onclick="document.querySelector('[x-data]').__x.$data.showAbout = true" class="p-1 hover:bg-blue-700 rounded">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </button>
        </div>
    </header>

    <main class="p-3 flex flex-col relative" style="height: calc(100vh - 60px);">
        @include('pages.weging.partials._content')
    </main>

    @include('partials.pwa-mobile', ['pwaApp' => 'weging'])

    {{-- Chat Widget --}}
    @include('partials.chat-widget', [
        'chatType' => 'weging',
        'chatId' => null,
        'toernooiId' => $toernooi->id,
        'chatApiBase' => route('toernooi.chat.index', $toernooi->routeParams()),
    ])

<!-- Weegtijd voorbij alert -->
<div id="weegtijd-alert" class="hidden fixed inset-0 bg-red-900/95 z-[100] flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl p-6 max-w-sm text-center animate-pulse">
        <div class="text-6xl mb-4">⏰</div>
        <h2 class="text-2xl font-bold text-red-600 mb-2">Weegtijd voorbij!</h2>
        <p class="text-gray-600 mb-4">De weegtijd voor <span id="alert-blok" class="font-bold">Blok X</span> is verstreken.</p>
        <button onclick="sluitWeegtijdAlert()" class="bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-8 rounded-lg">
            Begrepen
        </button>
    </div>
</div>

<script>
// Countdown timer met melding bij einde
function countdown(starttijd, eindtijd, blokNummer) {
    return {
        start_time: starttijd ? new Date(starttijd) : null,
        end: new Date(eindtijd),
        blok: blokNummer,
        display: '',
        expired: false,
        warning: false,
        alerted: false,
        interval: null,
        start() {
            this.update();
            this.interval = setInterval(() => this.update(), 1000);
        },
        update() {
            const now = new Date();
            // Toon niets als weging nog niet begonnen is
            if (this.start_time && now < this.start_time) {
                this.display = '';
                return;
            }
            const diff = this.end - now;
            if (diff <= 0) {
                this.expired = true;
                this.display = 'Voorbij!';
                if (!this.alerted) {
                    this.alerted = true;
                    toonWeegtijdAlert(this.blok);
                }
                clearInterval(this.interval);
                return;
            }
            this.warning = diff <= 5 * 60 * 1000;
            const h = Math.floor(diff / 3600000);
            const m = Math.floor((diff % 3600000) / 60000);
            const s = Math.floor((diff % 60000) / 1000);
            this.display = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        }
    }
}

function toonWeegtijdAlert(blokNummer) {
    document.getElementById('alert-blok').textContent = 'Blok ' + blokNummer;
    document.getElementById('weegtijd-alert').classList.remove('hidden');
    // Vibratie
    if (navigator.vibrate) navigator.vibrate([200, 100, 200, 100, 200]);
    // Geluid (beep)
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        osc.type = 'sine';
        osc.frequency.value = 800;
        osc.connect(ctx.destination);
        osc.start();
        setTimeout(() => osc.stop(), 500);
    } catch(e) {}
}

function sluitWeegtijdAlert() {
    document.getElementById('weegtijd-alert').classList.add('hidden');
}
</script>
</body>
</html>
