<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Coach Kaart - {{ $coachKaart->club->naam }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
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

        {{-- QR CODE --}}
        <div class="p-4 flex flex-col items-center bg-white">
            <img
                src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data={{ urlencode(route('coach-kaart.scan', $coachKaart->qr_code)) }}"
                alt="QR Code"
                class="w-52 h-52"
                crossorigin="anonymous"
            >
            <p class="mt-2 text-xs text-gray-400 font-mono">{{ $coachKaart->qr_code }}</p>
        </div>

        {{-- Footer --}}
        <div class="px-3 py-2 bg-purple-50 text-center border-t">
            <p class="text-xs text-purple-700 font-medium">🥋 Toegang tot de Dojo • Scan QR-code</p>
        </div>
    </div>

    {{-- Action buttons --}}
    <div class="no-print fixed bottom-4 left-0 right-0 flex justify-center gap-2 px-4">
        <button
            onclick="downloadCoachkaart()"
            class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-2.5 px-5 rounded-full shadow-lg flex items-center gap-2 transition-colors text-sm"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Opslaan
        </button>
        <button
            onclick="shareCoachkaart()"
            class="bg-green-600 hover:bg-green-700 text-white font-medium py-2.5 px-5 rounded-full shadow-lg flex items-center gap-2 transition-colors text-sm"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
            </svg>
            Delen
        </button>
    </div>

    <script>
        async function downloadCoachkaart() {
            const element = document.getElementById('coachkaart');
            const button = event.target.closest('button');
            const originalText = button.innerHTML;

            button.innerHTML = '<svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
            button.disabled = true;

            try {
                const canvas = await html2canvas(element, {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: '#ffffff',
                    logging: false
                });

                const link = document.createElement('a');
                link.download = 'coachkaart-{{ Str::slug($coachKaart->club->naam) }}-{{ $kaartNummer }}.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            } catch (error) {
                console.error('Download failed:', error);
                alert('Download mislukt. Probeer een screenshot te maken.');
            } finally {
                button.innerHTML = originalText;
                button.disabled = false;
            }
        }

        async function shareCoachkaart() {
            const shareData = {
                title: 'Coach Kaart {{ $coachKaart->club->naam }}',
                text: 'Coach kaart voor {{ $coachKaart->club->naam }} - {{ $coachKaart->toernooi->naam ?? "Judo Toernooi" }}',
                url: window.location.href
            };

            if (navigator.share) {
                try {
                    await navigator.share(shareData);
                } catch (err) {
                    if (err.name !== 'AbortError') {
                        copyToClipboard();
                    }
                }
            } else {
                copyToClipboard();
            }
        }

        function copyToClipboard() {
            navigator.clipboard.writeText(window.location.href).then(() => {
                alert('Link gekopieerd naar klembord!');
            }).catch(() => {
                prompt('Kopieer deze link:', window.location.href);
            });
        }
    </script>
</body>
</html>
