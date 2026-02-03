<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#7c3aed">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="🥋 COACH {{ $coachKaart->club->naam }}">
    <title>🥋 COACH - {{ $coachKaart->club->naam }}</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
    <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; background: white; }
        }
        body {
            -webkit-user-select: none;
            user-select: none;
        }
        #coachkaart {
            max-width: 360px;
            margin: 0 auto;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-2 pb-24">
    <div id="coachkaart" class="bg-white rounded-xl shadow-xl w-full overflow-hidden">
        {{-- Header --}}
        <div class="bg-purple-700 text-white px-3 py-2 flex justify-between items-center">
            <span class="text-sm font-medium truncate">{{ $coachKaart->toernooi->naam ?? 'Judo Toernooi' }}</span>
            <span class="text-purple-200 text-sm">{{ $coachKaart->toernooi->datum?->format('d-m-Y') ?? '' }}</span>
        </div>

        {{-- COACH met FOTO - groot en duidelijk --}}
        <div class="px-3 py-4 bg-purple-100 border-b-2 border-purple-300">
            <div class="flex items-center justify-center gap-4">
                {{-- Pasfoto --}}
                @if($coachKaart->foto)
                <img src="{{ $coachKaart->getFotoUrl() }}" alt="Foto {{ $coachKaart->naam }}"
                     class="w-24 h-24 object-cover rounded-lg border-4 border-purple-300 shadow-lg">
                @endif

                <div class="text-center">
                    <span class="bg-purple-600 text-white text-xl font-black px-4 py-1.5 rounded-lg tracking-wider inline-block mb-2">COACH</span>
                    <p class="text-xl font-bold text-purple-900">{{ $coachKaart->naam }}</p>
                    <p class="text-sm font-medium text-purple-700">{{ $coachKaart->club->naam }}</p>
                    @if($coachKaart->club->plaats)
                    <p class="text-xs text-purple-600">{{ $coachKaart->club->plaats }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Info --}}
        <div class="px-3 py-3 bg-gray-50 border-b">
            <div class="flex justify-center gap-6 text-center">
                <div>
                    <span class="text-xs text-gray-500 uppercase">Judoka's</span>
                    <p class="text-lg font-bold text-purple-700">{{ $aantalJudokas }}</p>
                </div>
                <div>
                    <span class="text-xs text-gray-500 uppercase">Kaart</span>
                    <p class="text-lg font-bold text-gray-700">{{ $kaartNummer }} / {{ $totaalKaarten }}</p>
                </div>
            </div>
        </div>

        {{-- QR CODE - only on bound device, refreshes every 4 minutes --}}
        @if($isCorrectDevice)
        <div class="p-4 flex flex-col items-center bg-white">
            <canvas id="qr-coach-{{ $coachKaart->id }}" width="208" height="208"></canvas>
            <p class="mt-2 text-xs text-gray-400 font-mono">{{ $coachKaart->qr_code }}</p>
            <p id="qr-timer" class="text-xs text-gray-400 mt-1"></p>
        </div>

        {{-- Info over overdracht als incheck systeem actief is --}}
        @if($coachKaart->toernooi->coach_incheck_actief && $coachKaart->isIngecheckt())
        <div class="px-4 py-3 bg-blue-50 border-t border-blue-200">
            <p class="text-sm text-blue-800 font-medium mb-1">ℹ️ Wilt u deze kaart overdragen?</p>
            <p class="text-xs text-blue-700">
                Ga naar de dojo scanner en check uit. Daarna kan de nieuwe coach de kaart overnemen.
            </p>
        </div>
        @endif
        @else
        <div class="p-6 bg-red-50 text-center">
            <div class="text-red-600 text-4xl mb-3">🔒</div>
            <h3 class="font-bold text-red-800 mb-2">Kaart overgedragen</h3>
            <p class="text-red-600 text-sm mb-4">
                Deze coach kaart is nu actief op een ander apparaat.
            </p>

            {{-- Show current coach info --}}
            <div class="bg-white rounded-lg p-4 mt-4 border border-red-200">
                <p class="text-gray-500 text-xs uppercase mb-2">Huidige coach</p>
                @if($coachKaart->foto)
                <img src="{{ $coachKaart->getFotoUrl() }}" alt="{{ $coachKaart->naam }}"
                     class="w-20 h-20 object-cover rounded-lg mx-auto border-2 border-gray-200 mb-2">
                @endif
                <p class="font-bold text-gray-900">{{ $coachKaart->naam }}</p>
                <p class="text-gray-500 text-sm">Sinds {{ $coachKaart->geactiveerd_op?->format('H:i') }}</p>
            </div>

            <a href="{{ route('coach-kaart.activeer', $coachKaart->qr_code) }}"
               class="mt-4 inline-block bg-amber-600 hover:bg-amber-700 text-white font-bold py-3 px-6 rounded-lg">
                Deze kaart overnemen
            </a>

            <p class="text-xs text-gray-500 mt-3">
                Je hebt de pincode nodig om over te nemen.
            </p>
        </div>
        @endif

        {{-- Footer --}}
        <div class="px-3 py-2 bg-purple-50 text-center border-t">
            <p class="text-xs text-purple-700 font-medium">🥋 Toegang tot de Dojo • Scan QR-code</p>
        </div>
    </div>

    {{-- No download button for coach cards - they should only be shown on device, not saved as images --}}

    <script>
        // Time-based QR code - regenerates every 4 minutes to prevent screenshot fraud
        const QR_VALID_MINUTES = 5;
        const QR_REFRESH_MINUTES = 4;
        const qrCode = '{{ $coachKaart->qr_code }}';
        const appKey = '{{ substr(config('app.key'), 7, 32) }}'; // Remove 'base64:' prefix, use first 32 chars

        let qrTimestamp = null;
        let timerInterval = null;

        // Simple hash function for client-side signature (matches server's first 16 chars of HMAC)
        async function generateSignature(timestamp) {
            const data = qrCode + '|' + timestamp;
            const encoder = new TextEncoder();
            const keyData = encoder.encode(appKey);
            const msgData = encoder.encode(data);

            const cryptoKey = await crypto.subtle.importKey(
                'raw', keyData, { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']
            );
            const signature = await crypto.subtle.sign('HMAC', cryptoKey, msgData);
            const hashArray = Array.from(new Uint8Array(signature));
            const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
            return hashHex.substring(0, 16);
        }

        async function generateTimedUrl() {
            qrTimestamp = Math.floor(Date.now() / 1000);
            const signature = await generateSignature(qrTimestamp);
            return '{{ url('/coach-kaart') }}/' + qrCode + '/scan?t=' + qrTimestamp + '&s=' + signature;
        }

        async function generateQR() {
            if (typeof QRCode === 'undefined') {
                console.log('QRCode library not loaded yet, retrying...');
                setTimeout(generateQR, 100);
                return;
            }

            const canvas = document.getElementById('qr-coach-{{ $coachKaart->id }}');
            if (canvas) {
                const url = await generateTimedUrl();
                // Clear canvas first
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);

                QRCode.toCanvas(canvas, url, {
                    width: 208,
                    margin: 1
                });
                console.log('QR generated with timestamp:', qrTimestamp);
                startTimer();
            }
        }

        function startTimer() {
            if (timerInterval) clearInterval(timerInterval);

            const timerEl = document.getElementById('qr-timer');
            const refreshAt = qrTimestamp + (QR_REFRESH_MINUTES * 60);

            timerInterval = setInterval(() => {
                const now = Math.floor(Date.now() / 1000);
                const remaining = refreshAt - now;

                if (remaining <= 0) {
                    generateQR(); // Refresh QR
                } else if (remaining <= 60) {
                    timerEl.textContent = 'Ververst over ' + remaining + 's';
                    timerEl.className = 'text-xs text-orange-500 mt-1';
                } else {
                    timerEl.textContent = '';
                }
            }, 1000);
        }

        document.addEventListener('DOMContentLoaded', generateQR);

        // Also regenerate when page becomes visible again (user switches back to tab)
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                generateQR();
            }
        });
    </script>
</body>
</html>
