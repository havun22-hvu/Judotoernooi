<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Weegkaart - {{ $judoka->naam }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; background: white; }
        }
        body {
            -webkit-user-select: none;
            user-select: none;
        }
        #weegkaart {
            max-width: 360px;
            margin: 0 auto;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-2 pb-24"
      x-data="{
          fromPortal: new URLSearchParams(window.location.search).has('from_portal'),
          confirmed: localStorage.getItem('weegkaart_{{ $judoka->qr_code }}') === 'true',
          get showContent() { return this.fromPortal || this.confirmed }
      }">

    {{-- Confirmation Modal - only show when NOT from portal and NOT confirmed --}}
    <div x-show="!fromPortal && !confirmed" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-sm w-full p-6 text-center">
            <div class="text-5xl mb-4">⚠️</div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">Weegkaart opslaan?</h2>
            <p class="text-gray-600 mb-4">
                Je gaat de weegkaart van <strong class="text-blue-700">{{ $judoka->naam }}</strong> op dit apparaat zetten.
            </p>
            <p class="text-sm text-gray-500 mb-6">
                Is dit jouw kind of ben je de begeleider?
            </p>
            <div class="flex gap-3 justify-center">
                <button
                    @click="window.history.back()"
                    class="px-5 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-medium"
                >
                    Nee, terug
                </button>
                <button
                    @click="localStorage.setItem('weegkaart_{{ $judoka->qr_code }}', 'true'); confirmed = true"
                    class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium"
                >
                    Ja, doorgaan
                </button>
            </div>
        </div>
    </div>

    <div id="weegkaart" class="bg-white rounded-xl shadow-xl w-full overflow-hidden" x-show="showContent" x-cloak>
        {{-- Header --}}
        <div class="bg-blue-700 text-white px-3 py-2 flex justify-between items-center">
            <span class="text-sm font-medium truncate">{{ $judoka->toernooi->naam ?? 'Judo Toernooi' }}</span>
            <span class="text-blue-200 text-sm">{{ $judoka->toernooi->datum?->format('d-m-Y') ?? '' }}</span>
        </div>

        {{-- NAAM PROMINENT --}}
        <div class="px-3 py-3 bg-gray-50 border-b-2 border-blue-200">
            <h1 class="text-2xl font-black text-gray-900 text-center leading-tight">{{ $judoka->naam }}</h1>
            <p class="text-base font-medium text-blue-600 text-center mt-1">{{ $judoka->club?->naam ?? 'Geen club' }}</p>
        </div>

        {{-- Classification row --}}
        @php
            $bandColors = [
                'wit' => 'bg-white text-gray-800 border-2 border-gray-400',
                'geel' => 'bg-yellow-400 text-yellow-900',
                'oranje' => 'bg-orange-500 text-white',
                'groen' => 'bg-green-600 text-white',
                'blauw' => 'bg-blue-600 text-white',
                'bruin' => 'bg-amber-800 text-white',
                'zwart' => 'bg-gray-900 text-white',
            ];
            $bandClass = $bandColors[strtolower($judoka->band ?? '')] ?? 'bg-gray-200 text-gray-700';
        @endphp
        <div class="px-3 py-2 grid grid-cols-4 gap-1 text-center border-b">
            <div class="flex flex-col">
                <span class="text-[10px] text-gray-500 uppercase">Leeftijd</span>
                <span class="text-sm font-bold text-purple-700">{{ $judoka->leeftijdsklasse ?? '?' }}</span>
            </div>
            <div class="flex flex-col">
                <span class="text-[10px] text-gray-500 uppercase">Gewicht</span>
                <span class="text-sm font-bold text-green-700">
                    @if($judoka->gewichtsklasse === 'Variabel')
                        {{ $judoka->gewicht ? $judoka->gewicht . ' kg' : 'Variabel' }}
                    @else
                        {{ $judoka->gewichtsklasse ?? '?' }} kg
                    @endif
                </span>
            </div>
            <div class="flex flex-col">
                <span class="text-[10px] text-gray-500 uppercase">Band</span>
                <span class="text-sm font-bold px-2 py-0.5 rounded {{ $bandClass }}">{{ ucfirst($judoka->band ?? '?') }}</span>
            </div>
            <div class="flex flex-col">
                <span class="text-[10px] text-gray-500 uppercase">Geslacht</span>
                <span class="text-sm font-bold {{ $judoka->geslacht === 'M' ? 'text-blue-600' : 'text-pink-600' }}">
{{ $judoka->geslacht }}
                </span>
            </div>
        </div>

        {{-- BLOK + MAT + TIJDEN --}}
        @if($blok)
        @php
            // Kindvriendelijke heldere kleuren voor matten
            $matKleuren = [
                'rood' => 'bg-red-500',
                'blauw' => 'bg-blue-500',
                'groen' => 'bg-green-500',
                'geel' => 'bg-yellow-400',
                'oranje' => 'bg-orange-500',
                'paars' => 'bg-purple-500',
                'roze' => 'bg-pink-500',
                'bruin' => 'bg-amber-700',
                'wit' => 'bg-white border-2 border-gray-400',
                'zwart' => 'bg-gray-900 !text-white',
            ];
            $matKleur = $mat?->kleur ? ($matKleuren[strtolower($mat->kleur)] ?? 'bg-gray-500') : 'bg-gray-500';
        @endphp
        <div class="px-3 py-2 bg-amber-50 border-b flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="bg-amber-500 text-white text-sm font-bold px-2 py-0.5 rounded">{{ $blok->naam }}</span>
                @if($mat)
                <span class="{{ $matKleur }} text-black text-lg font-black px-3 py-1 rounded-lg shadow-sm">
                    Mat {{ $mat->nummer }}
                </span>
                @endif
            </div>
            {{-- Tijden --}}
            <div class="text-right text-xs">
                @if($blok->weging_start && $blok->weging_einde)
                <div class="text-gray-600">
                    <span class="font-medium">Weging:</span>
                    <span class="font-bold text-gray-800">{{ $blok->weging_start->format('H:i') }}-{{ $blok->weging_einde->format('H:i') }}</span>
                </div>
                @endif
                @if($blok->starttijd)
                <div class="text-gray-600">
                    <span class="font-medium">Start:</span>
                    <span class="font-bold text-gray-800">{{ $blok->starttijd->format('H:i') }}</span>
                </div>
                @endif
            </div>
        </div>
        @else
        <div class="px-3 py-2 bg-gray-100 border-b text-center">
            <span class="text-sm text-gray-500">⏳ Nog niet ingedeeld</span>
        </div>
        @endif

        {{-- QR CODE --}}
        <div class="p-4 flex flex-col items-center bg-white">
            <img
                src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data={{ urlencode(route('weegkaart.show', $judoka->qr_code)) }}"
                alt="QR Code"
                class="w-52 h-52"
                crossorigin="anonymous"
            >
            <p class="mt-2 text-xs text-gray-400 font-mono">{{ strtoupper(Str::limit($judoka->qr_code, 12, '')) }}</p>
        </div>

        {{-- Footer --}}
        <div class="px-3 py-2 bg-blue-50 border-t flex justify-between items-center">
            <p class="text-xs text-blue-700 font-medium">📱 Toon bij weging • Scan QR-code</p>
            @if($judoka->toernooi->weegkaarten_gemaakt_op)
            <p class="text-[10px] text-gray-400">{{ $judoka->toernooi->weegkaarten_gemaakt_op->format('d-m H:i') }}</p>
            @endif
        </div>
    </div>

    {{-- Action buttons --}}
    <div x-show="showContent" x-cloak class="no-print fixed bottom-4 left-0 right-0 flex justify-center gap-2 px-4">
        <button
            onclick="downloadWeegkaart()"
            class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-full shadow-lg flex items-center gap-2 transition-colors text-sm"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Opslaan
        </button>
        <button
            onclick="shareWeegkaart()"
            class="bg-green-600 hover:bg-green-700 text-white font-medium py-2.5 px-5 rounded-full shadow-lg flex items-center gap-2 transition-colors text-sm"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
            </svg>
            Delen
        </button>
    </div>

    <script>
        async function downloadWeegkaart() {
            const element = document.getElementById('weegkaart');
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
                link.download = 'weegkaart-{{ Str::slug($judoka->naam) }}.png';
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

        async function shareWeegkaart() {
            const shareData = {
                title: 'Weegkaart {{ $judoka->naam }}',
                text: 'Weegkaart voor {{ $judoka->naam }} - {{ $judoka->toernooi->naam ?? "Judo Toernooi" }}',
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
