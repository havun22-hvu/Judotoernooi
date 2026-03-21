<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#1e40af">
    <link rel="manifest" href="/manifest-dojo.json">
    <link rel="icon" type="image/png" sizes="192x192" href="/icon-192x192.png">
    <link rel="apple-touch-icon" href="/icon-192x192.png">
    <title>{{ __('Dojo Scanner') }} - {{ $toernooi->naam }}</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        body { overscroll-behavior: none; -webkit-user-select: none; user-select: none; }
        #reader { width: 100%; }
        #reader video { border-radius: 0.5rem; }
        #reader__scan_region { background: transparent !important; }
        #reader__dashboard { display: none !important; }
        .result-overlay { animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
    </style>
</head>
<body class="bg-blue-900 min-h-screen text-white">
    <!-- Header -->
    <header class="bg-blue-800 px-4 py-3 flex items-center justify-between shadow-lg">
        <div>
            <h1 class="text-lg font-bold">{{ __('Dojo Scanner') }}</h1>
            <p class="text-blue-200 text-sm">{{ $toernooi->naam }}</p>
        </div>
        <div class="text-right">
            <div class="text-2xl font-mono" id="clock"></div>
            <div class="text-blue-200 text-sm" id="scan-count">0 {{ __('gescand') }}</div>
        </div>
    </header>

    <!-- Tab Bar -->
    <div class="flex border-b border-blue-700 bg-blue-800">
        <button onclick="showTab('scanner')" id="tab-scanner"
                class="flex-1 py-3 text-center font-medium border-b-2 border-white">
            {{ __('Scanner') }}
        </button>
        <button onclick="showTab('overzicht')" id="tab-overzicht"
                class="flex-1 py-3 text-center font-medium border-b-2 border-transparent text-blue-300">
            {{ __('Overzicht') }}
        </button>
    </div>

    <!-- TAB 1: Scanner -->
    <main id="content-scanner" class="p-3 flex flex-col relative" style="height: calc(100vh - 110px);">
        <!-- TOP HALF: Scanner area (fixed height 45%) -->
        <div class="bg-blue-800/50 rounded-lg p-3 mb-3 flex flex-col" style="height: 45%;">
            <div class="flex-1 flex items-center justify-center">
                <button id="scan-button" onclick="startScanner()"
                        class="bg-green-600 hover:bg-green-700 text-white rounded-full w-28 h-28 flex flex-col items-center justify-center shadow-lg">
                    <span class="text-3xl mb-1">📷</span>
                    <span class="font-bold text-sm">{{ __('Scan') }}</span>
                </button>

                <div id="scanner-container" class="text-center w-full" style="display: none;">
                    <div id="reader" style="width: 100%; max-width: 300px; min-height: 200px; margin: 0 auto;"></div>
                    <button onclick="stopScanner()" class="mt-1 px-4 py-1 bg-red-600 hover:bg-red-700 rounded text-sm">
                        {{ __('Stop') }}
                    </button>
                </div>
            </div>

            <div class="mt-2">
                <button onclick="showManualEntry()"
                        class="w-full border-2 border-blue-500 bg-blue-800 rounded-lg px-4 py-2 text-center text-blue-300 hover:bg-blue-700">
                    {{ __('Of voer code handmatig in...') }}
                </button>
            </div>
        </div>

        <!-- BOTTOM HALF: Info & Instructions -->
        <div class="flex-1 flex flex-col space-y-3 overflow-y-auto">
            <div class="bg-white rounded-lg shadow p-4 text-gray-800">
                <h2 class="font-bold text-lg mb-2">{{ __('Instructies') }}</h2>
                <ol class="list-decimal list-inside space-y-1 text-sm">
                    <li>{{ __('Scan de QR-code op de coach kaart') }}</li>
                    <li>{{ __('Controleer de foto met de persoon') }}</li>
                    <li>{{ __('Bevestig of weiger toegang') }}</li>
                </ol>
            </div>

            <div class="bg-white rounded-lg shadow p-4 text-gray-800">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">{{ __('Coaches gescand vandaag') }}</span>
                    <span class="text-2xl font-bold text-green-600" id="total-scanned">0</span>
                </div>
            </div>
        </div>
    </main>

    <!-- TAB 2: Overzicht -->
    <main id="content-overzicht" class="hidden p-3" style="height: calc(100vh - 110px); overflow-y: auto;">
        <!-- Zoekbalk -->
        <div class="mb-3">
            <input type="text" id="club-search" placeholder="{{ __('Zoek budoschool...') }}"
                   class="w-full bg-blue-800 border border-blue-600 rounded-lg px-4 py-3 text-white placeholder-blue-400"
                   oninput="filterClubs(this.value)">
        </div>

        <!-- Club lijst -->
        <div id="clubs-list" class="space-y-2">
            <div class="text-center text-blue-300 py-8">{{ __('Laden...') }}</div>
        </div>

        <!-- Club detail (hidden by default) -->
        <div id="club-detail" class="hidden">
            <button onclick="hideClubDetail()" class="flex items-center gap-2 text-blue-300 mb-3">
                <span>←</span>
                <span id="club-detail-naam">{{ __('Club naam') }}</span>
            </button>

            <div id="club-kaarten" class="space-y-2">
                <!-- Kaarten worden hier ingeladen -->
            </div>
        </div>
    </main>

    <!-- Result overlay -->
    <div id="result-container" class="hidden fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4">
        <div class="result-overlay w-full max-w-md"></div>
    </div>

    <!-- Manual entry modal -->
    <div id="manual-entry" class="hidden fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl p-6 w-full max-w-md text-gray-800">
            <h2 class="text-xl font-bold mb-4">{{ __('Handmatig invoeren') }}</h2>
            <p class="text-gray-600 mb-4">{{ __('Voer de code onder de QR-code in:') }}</p>
            <input type="text" id="manual-code"
                   class="w-full border-2 border-gray-300 rounded-lg px-4 py-3 text-lg font-mono text-center uppercase"
                   placeholder="XXXXXX" maxlength="32"
                   oninput="this.value = this.value.toUpperCase()">
            <div class="flex gap-3 mt-4">
                <button onclick="hideManualEntry()"
                        class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 py-3 rounded-lg font-medium">
                    {{ __('Annuleren') }}
                </button>
                <button onclick="submitManualCode()"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-medium">
                    {{ __('Controleren') }}
                </button>
            </div>
        </div>
    </div>

    <!-- Kaart detail modal -->
    <div id="kaart-detail-modal" class="hidden fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-md max-h-[80vh] overflow-y-auto text-gray-800">
            <div class="p-4 border-b flex justify-between items-center">
                <h2 class="font-bold" id="kaart-detail-title">{{ __('Kaart Detail') }}</h2>
                <button onclick="hideKaartDetail()" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
            </div>
            <div id="kaart-detail-content" class="p-4">
                <!-- Content wordt dynamisch geladen -->
            </div>
        </div>
    </div>

    <script>
        const organisatorSlug = '{{ $toernooi->organisator->slug }}';
        const toernooiSlug = '{{ $toernooi->slug }}';
        let html5QrCode = null;
        let scanCount = 0;
        let isProcessing = false;
        let scannerActive = false;
        let allClubs = [];
        let selectedClubId = null;

        // Tab switching
        function showTab(tab) {
            document.getElementById('content-scanner').classList.toggle('hidden', tab !== 'scanner');
            document.getElementById('content-overzicht').classList.toggle('hidden', tab !== 'overzicht');

            document.getElementById('tab-scanner').classList.toggle('border-white', tab === 'scanner');
            document.getElementById('tab-scanner').classList.toggle('border-transparent', tab !== 'scanner');
            document.getElementById('tab-scanner').classList.toggle('text-blue-300', tab !== 'scanner');

            document.getElementById('tab-overzicht').classList.toggle('border-white', tab === 'overzicht');
            document.getElementById('tab-overzicht').classList.toggle('border-transparent', tab !== 'overzicht');
            document.getElementById('tab-overzicht').classList.toggle('text-blue-300', tab !== 'overzicht');

            if (tab === 'overzicht') {
                loadClubs();
            }
        }

        // Load clubs
        async function loadClubs() {
            try {
                const response = await fetch(`/${organisatorSlug}/${toernooiSlug}/dojo/clubs`);
                allClubs = await response.json();
                renderClubs(allClubs);
            } catch (error) {
                console.error('Error loading clubs:', error);
                document.getElementById('clubs-list').innerHTML = '<div class="text-red-400 text-center py-4">{{ __('Fout bij laden') }}</div>';
            }
        }

        function renderClubs(clubs) {
            const container = document.getElementById('clubs-list');
            if (clubs.length === 0) {
                container.innerHTML = '<div class="text-blue-300 text-center py-4">{{ __('Geen clubs gevonden') }}</div>';
                return;
            }

            container.innerHTML = clubs.map(club => `
                <div onclick="showClubDetail(${club.id})" class="bg-blue-800 rounded-lg p-3 cursor-pointer hover:bg-blue-700">
                    <div class="flex justify-between items-center">
                        <span class="font-medium">${club.naam}</span>
                        <span class="text-blue-300 text-sm">${club.totaal_kaarten} kaarten</span>
                    </div>
                    <div class="flex gap-4 mt-2 text-sm">
                        <span class="text-green-400">✓ ${club.ingecheckt} in</span>
                        <span class="text-gray-400">🚪 ${club.uitgecheckt} uit</span>
                        <span class="text-blue-400">⬚ ${club.ongebruikt} ongebruikt</span>
                    </div>
                </div>
            `).join('');
        }

        function filterClubs(query) {
            const filtered = allClubs.filter(c => c.naam.toLowerCase().includes(query.toLowerCase()));
            renderClubs(filtered);
        }

        // Club detail
        async function showClubDetail(clubId) {
            selectedClubId = clubId;
            document.getElementById('clubs-list').classList.add('hidden');
            document.getElementById('club-detail').classList.remove('hidden');

            try {
                const response = await fetch(`/${organisatorSlug}/${toernooiSlug}/dojo/club/${clubId}`);
                const data = await response.json();

                document.getElementById('club-detail-naam').textContent = data.club.naam;

                document.getElementById('club-kaarten').innerHTML = data.kaarten.map(kaart => {
                    const statusIcon = kaart.status === 'in' ? '✓' : kaart.status === 'uit' ? '🚪' : '⬚';
                    const statusClass = kaart.status === 'in' ? 'text-green-400' : kaart.status === 'uit' ? 'text-gray-400' : 'text-blue-400';
                    const statusText = kaart.status === 'in' ? `IN (${kaart.status_tijd})` :
                                       kaart.status === 'uit' ? `UIT (${kaart.status_tijd || '--'})` : '--';

                    return `
                        <div onclick="showKaartDetail('${kaart.qr_code}')" class="bg-blue-800 rounded-lg p-3 cursor-pointer hover:bg-blue-700">
                            <div class="flex justify-between items-center">
                                <span>Kaart ${kaart.nummer}: ${kaart.naam}</span>
                                <span class="${statusClass}">${statusIcon} ${statusText}</span>
                            </div>
                        </div>
                    `;
                }).join('');
            } catch (error) {
                console.error('Error loading club detail:', error);
            }
        }

        function hideClubDetail() {
            document.getElementById('clubs-list').classList.remove('hidden');
            document.getElementById('club-detail').classList.add('hidden');
            loadClubs(); // Refresh
        }

        // Kaart detail modal
        async function showKaartDetail(qrCode) {
            document.getElementById('kaart-detail-modal').classList.remove('hidden');
            document.getElementById('kaart-detail-content').innerHTML = '<div class="text-center py-4">{{ __('Laden...') }}</div>';

            try {
                const response = await fetch(`/coach-kaart/${qrCode}/geschiedenis`);
                const html = await response.text();
                // Extract just the content we need
                document.getElementById('kaart-detail-content').innerHTML = `
                    <a href="/coach-kaart/${qrCode}/scan" class="block w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg text-center font-medium mb-4">
                        {{ __('Bekijk volledige kaart') }}
                    </a>
                    <p class="text-sm text-gray-500 text-center">{{ __('Klik om check-in/uit te doen') }}</p>
                `;
            } catch (error) {
                console.error('Error loading kaart detail:', error);
                document.getElementById('kaart-detail-content').innerHTML = '<div class="text-red-500 text-center py-4">{{ __('Fout bij laden') }}</div>';
            }
        }

        function hideKaartDetail() {
            document.getElementById('kaart-detail-modal').classList.add('hidden');
        }

        // Auto-select club after scan
        function selectClubAfterScan(clubId) {
            showTab('overzicht');
            setTimeout(() => showClubDetail(clubId), 100);
        }

        // Clock
        function updateClock() {
            const now = new Date();
            document.getElementById('clock').textContent =
                now.toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' });
        }
        updateClock();
        setInterval(updateClock, 1000);

        // Scanner functions
        async function startScanner() {
            if (scannerActive) return;

            document.getElementById('scan-button').style.display = 'none';
            document.getElementById('scanner-container').style.display = 'block';

            html5QrCode = new Html5Qrcode("reader");

            try {
                await html5QrCode.start(
                    { facingMode: "environment" },
                    { fps: 10, qrbox: { width: 220, height: 220 }, aspectRatio: 1.0 },
                    onScanSuccess,
                    onScanFailure
                );
                scannerActive = true;
            } catch (err) {
                console.error("Camera error:", err);
                document.getElementById('reader').innerHTML = `
                    <div class="bg-red-900 text-white p-4 rounded-lg text-center">
                        <p class="font-bold">{{ __('Camera niet beschikbaar') }}</p>
                        <p class="text-sm mt-1">{{ __('Gebruik handmatig invoeren') }}</p>
                    </div>
                `;
                setTimeout(() => {
                    document.getElementById('scanner-container').style.display = 'none';
                    document.getElementById('scan-button').style.display = 'flex';
                }, 2000);
            }
        }

        async function stopScanner() {
            if (!scannerActive || !html5QrCode) return;
            try { await html5QrCode.stop(); } catch (err) {}
            scannerActive = false;
            html5QrCode = null;
            document.getElementById('scanner-container').style.display = 'none';
            document.getElementById('scan-button').style.display = 'flex';
        }

        async function onScanSuccess(decodedText) {
            if (isProcessing) return;
            isProcessing = true;

            let qrCode = decodedText;
            const match = decodedText.match(/coach-kaart\/([a-zA-Z0-9]+)/);
            if (match) qrCode = match[1];

            if (navigator.vibrate) navigator.vibrate(100);

            window.location.href = `/coach-kaart/${qrCode}/scan`;
        }

        function onScanFailure(error) {}

        function showManualEntry() {
            document.getElementById('manual-entry').classList.remove('hidden');
            document.getElementById('manual-code').focus();
        }

        function hideManualEntry() {
            document.getElementById('manual-entry').classList.add('hidden');
            document.getElementById('manual-code').value = '';
        }

        async function submitManualCode() {
            const code = document.getElementById('manual-code').value.trim();
            if (!code) return;
            hideManualEntry();
            window.location.href = `/coach-kaart/${code}/scan`;
        }

        document.getElementById('manual-code').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') submitManualCode();
        });
    </script>

    @include('partials.pwa-mobile', ['pwaApp' => 'dojo'])

    @include('partials.chat-widget', [
        'chatType' => 'dojo',
        'chatId' => $toegang->id ?? null,
        'toernooiId' => $toernooi->id,
        'chatApiBase' => route('toernooi.chat.index', $toernooi->routeParams()),
    ])
</body>
</html>
