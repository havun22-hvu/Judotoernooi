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
    <title>Dojo Scanner - {{ $toernooi->naam }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
            <h1 class="text-lg font-bold">Dojo Scanner</h1>
            <p class="text-blue-200 text-sm">{{ $toernooi->naam }}</p>
        </div>
        <div class="text-right">
            <div class="text-2xl font-mono" id="clock"></div>
            <div class="text-blue-200 text-sm" id="scan-count">0 gescand</div>
        </div>
    </header>

    <main class="p-3 flex flex-col relative" style="height: calc(100vh - 60px);">
        <!-- TOP HALF: Scanner area (fixed height 45%) -->
        <div class="bg-blue-800/50 rounded-lg p-3 mb-3 flex flex-col" style="height: 45%;">
            <!-- Scanner area (fixed) -->
            <div class="flex-1 flex items-center justify-center">
                <!-- Scan button (when not scanning) -->
                <button id="scan-button" onclick="startScanner()"
                        class="bg-green-600 hover:bg-green-700 text-white rounded-full w-28 h-28 flex flex-col items-center justify-center shadow-lg">
                    <span class="text-3xl mb-1">📷</span>
                    <span class="font-bold text-sm">Scan</span>
                </button>

                <!-- Scanner (when scanning) -->
                <div id="scanner-container" class="text-center w-full" style="display: none;">
                    <div id="reader" style="width: 100%; max-width: 300px; min-height: 200px; margin: 0 auto;"></div>
                    <button onclick="stopScanner()" class="mt-1 px-4 py-1 bg-red-600 hover:bg-red-700 rounded text-sm">
                        Stop
                    </button>
                </div>
            </div>

            <!-- Manual input (fixed position below scanner) -->
            <div class="mt-2">
                <button onclick="showManualEntry()"
                        class="w-full border-2 border-blue-500 bg-blue-800 rounded-lg px-4 py-2 text-center text-blue-300 hover:bg-blue-700">
                    Of voer code handmatig in...
                </button>
            </div>
        </div>

        <!-- BOTTOM HALF: Info & Instructions (fixed position 55%) -->
        <div class="flex-1 flex flex-col space-y-3 overflow-y-auto">
            <!-- Instructions -->
            <div class="bg-white rounded-lg shadow p-4 text-gray-800">
                <h2 class="font-bold text-lg mb-2">Instructies</h2>
                <ol class="list-decimal list-inside space-y-1 text-sm">
                    <li>Scan de QR-code op de coach kaart</li>
                    <li>Controleer de foto met de persoon</li>
                    <li>Bevestig of weiger toegang</li>
                </ol>
            </div>

            <!-- Stats -->
            <div class="bg-white rounded-lg shadow p-4 text-gray-800">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Coaches gescand vandaag</span>
                    <span class="text-2xl font-bold text-green-600" id="total-scanned">0</span>
                </div>
            </div>

            <!-- Info -->
            <div class="bg-blue-800/50 rounded-lg p-3 text-center text-sm">
                <p>Scan de QR-code op de coach kaart</p>
                <p class="text-blue-300 mt-1">Controleer de foto met de persoon</p>
            </div>
        </div>

        <!-- Result overlay (hidden by default) -->
        <div id="result-container" class="hidden fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4">
            <div class="result-overlay w-full max-w-md">
                <!-- Will be filled dynamically -->
            </div>
        </div>

        <!-- Manual entry modal (hidden by default) -->
        <div id="manual-entry" class="hidden fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl p-6 w-full max-w-md text-gray-800">
                <h2 class="text-xl font-bold mb-4">Handmatig invoeren</h2>
                <p class="text-gray-600 mb-4">Voer de code onder de QR-code in:</p>
                <input type="text" id="manual-code"
                       class="w-full border-2 border-gray-300 rounded-lg px-4 py-3 text-lg font-mono text-center uppercase"
                       placeholder="XXXXXX" maxlength="32"
                       oninput="this.value = this.value.toUpperCase()">
                <div class="flex gap-3 mt-4">
                    <button onclick="hideManualEntry()"
                            class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 py-3 rounded-lg font-medium">
                        Annuleren
                    </button>
                    <button onclick="submitManualCode()"
                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-medium">
                        Controleren
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script>
        let html5QrCode = null;
        let scanCount = 0;
        let isProcessing = false;
        let scannerActive = false;

        // Update clock
        function updateClock() {
            const now = new Date();
            document.getElementById('clock').textContent =
                now.toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' });
        }
        updateClock();
        setInterval(updateClock, 1000);

        // Start scanner
        async function startScanner() {
            if (scannerActive) return;

            document.getElementById('scan-button').style.display = 'none';
            document.getElementById('scanner-container').style.display = 'block';

            html5QrCode = new Html5Qrcode("reader");

            try {
                await html5QrCode.start(
                    { facingMode: "environment" },
                    {
                        fps: 10,
                        qrbox: { width: 220, height: 220 },
                        aspectRatio: 1.0
                    },
                    onScanSuccess,
                    onScanFailure
                );
                scannerActive = true;
            } catch (err) {
                console.error("Camera error:", err);
                document.getElementById('reader').innerHTML = `
                    <div class="bg-red-900 text-white p-4 rounded-lg text-center">
                        <p class="font-bold">Camera niet beschikbaar</p>
                        <p class="text-sm mt-1">Gebruik handmatig invoeren</p>
                    </div>
                `;
                setTimeout(() => {
                    document.getElementById('scanner-container').style.display = 'none';
                    document.getElementById('scan-button').style.display = 'flex';
                }, 2000);
            }
        }

        // Stop scanner
        async function stopScanner() {
            if (!scannerActive || !html5QrCode) return;

            try {
                await html5QrCode.stop();
            } catch (err) {
                console.error("Stop error:", err);
            }

            scannerActive = false;
            html5QrCode = null;

            document.getElementById('scanner-container').style.display = 'none';
            document.getElementById('scan-button').style.display = 'flex';
        }

        // Handle successful scan
        async function onScanSuccess(decodedText) {
            if (isProcessing) return;
            isProcessing = true;

            let qrCode = decodedText;
            const match = decodedText.match(/coach-kaart\/([a-zA-Z0-9]+)/);
            if (match) {
                qrCode = match[1];
            }

            if (navigator.vibrate) {
                navigator.vibrate(100);
            }

            await checkCoachKaart(qrCode);
        }

        function onScanFailure(error) {
            // Ignore scan failures (no QR in frame)
        }

        // Check coach kaart via API
        async function checkCoachKaart(qrCode) {
            try {
                const response = await fetch(`/coach-kaart/${qrCode}/scan`);

                if (!response.ok) {
                    showResult({
                        valid: false,
                        status: 'error',
                        message: 'Coach kaart niet gevonden'
                    });
                    return;
                }

                window.location.href = `/coach-kaart/${qrCode}/scan`;

            } catch (error) {
                console.error('Scan error:', error);
                showResult({
                    valid: false,
                    status: 'error',
                    message: 'Fout bij controleren'
                });
            }
        }

        // Show result overlay
        function showResult(data) {
            const container = document.getElementById('result-container');
            const bgColor = data.status === 'valid' ? 'bg-green-600' :
                           data.status === 'already_scanned' ? 'bg-yellow-500' : 'bg-red-600';
            const icon = data.status === 'valid' ? '✓' :
                        data.status === 'already_scanned' ? '!' : '✗';

            container.querySelector('.result-overlay').innerHTML = `
                <div class="${bgColor} rounded-xl p-6 text-center text-white">
                    <div class="text-6xl mb-4">${icon}</div>
                    ${data.foto ? `<img src="${data.foto}" class="w-48 h-48 object-cover rounded-full mx-auto mb-4 border-4 border-white">` : ''}
                    <h2 class="text-2xl font-bold mb-2">${data.naam || 'Onbekend'}</h2>
                    <p class="text-xl">${data.message}</p>
                    ${data.club ? `<p class="text-lg opacity-80 mt-2">${data.club}</p>` : ''}
                    <button onclick="hideResult()" class="mt-6 bg-white/20 hover:bg-white/30 px-8 py-3 rounded-lg font-medium">
                        Volgende scan
                    </button>
                </div>
            `;
            container.classList.remove('hidden');

            setTimeout(() => hideResult(), 5000);
        }

        function hideResult() {
            document.getElementById('result-container').classList.add('hidden');
            isProcessing = false;
        }

        // Manual entry
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
            isProcessing = true;
            await checkCoachKaart(code);
        }

        document.getElementById('manual-code').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                submitManualCode();
            }
        });
    </script>

    @include('partials.pwa-mobile', ['pwaApp' => 'dojo'])

    {{-- Chat Widget --}}
    @include('partials.chat-widget', [
        'chatType' => 'dojo',
        'chatId' => null,
        'toernooiId' => $toernooi->id,
        'chatApiBase' => route('toernooi.chat.index', $toernooi),
    ])
</body>
</html>
