<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Weegkaart - {{ $judoka->naam }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
        }
        body {
            -webkit-user-select: none;
            user-select: none;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-2">
    <div id="weegkaart" class="bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden">
        {{-- Header with tournament name --}}
        <div class="bg-blue-600 text-white px-4 py-3 text-center">
            <h1 class="text-lg font-bold">{{ $judoka->toernooi->naam ?? 'Judo Toernooi' }}</h1>
            <p class="text-blue-100 text-sm">{{ $judoka->toernooi->datum?->format('d-m-Y') ?? '' }}</p>
        </div>

        {{-- Judoka info --}}
        <div class="px-4 py-4 border-b">
            <h2 class="text-2xl font-bold text-gray-800 text-center">{{ $judoka->naam }}</h2>
            <p class="text-gray-500 text-center mt-1">{{ $judoka->club?->naam ?? 'Geen club' }}</p>
        </div>

        {{-- Classification badges --}}
        <div class="px-4 py-3 flex justify-center gap-2 flex-wrap border-b">
            {{-- Weight class --}}
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                {{ $judoka->gewichtsklasse ?? '?' }} kg
            </span>
            {{-- Belt --}}
            @php
                $bandColors = [
                    'wit' => 'bg-gray-100 text-gray-800 border border-gray-300',
                    'geel' => 'bg-yellow-300 text-yellow-900',
                    'oranje' => 'bg-orange-400 text-white',
                    'groen' => 'bg-green-500 text-white',
                    'blauw' => 'bg-blue-500 text-white',
                    'bruin' => 'bg-amber-700 text-white',
                    'zwart' => 'bg-gray-900 text-white',
                ];
                $bandClass = $bandColors[strtolower($judoka->band ?? '')] ?? 'bg-gray-200 text-gray-700';
            @endphp
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $bandClass }}">
                {{ ucfirst($judoka->band ?? '?') }}
            </span>
            {{-- Age class --}}
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800">
                {{ $judoka->leeftijdsklasse ?? '?' }}
            </span>
            {{-- Gender --}}
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $judoka->geslacht === 'M' ? 'bg-blue-100 text-blue-800' : 'bg-pink-100 text-pink-800' }}">
                {{ $judoka->geslacht === 'M' ? '♂ Jongen' : '♀ Meisje' }}
            </span>
        </div>

        {{-- Block info with times --}}
        @if($blok)
        <div class="px-4 py-3 bg-amber-50 border-b">
            <div class="text-center">
                <span class="text-lg font-bold text-amber-800">{{ $blok->naam }}</span>
            </div>
            <div class="mt-2 grid grid-cols-2 gap-4 text-center">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Weging</p>
                    <p class="text-lg font-semibold text-gray-800">
                        @if($blok->weging_start && $blok->weging_einde)
                            {{ $blok->weging_start->format('H:i') }} - {{ $blok->weging_einde->format('H:i') }}
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Wedstrijden</p>
                    <p class="text-lg font-semibold text-gray-800">
                        @if($blok->starttijd)
                            vanaf {{ $blok->starttijd->format('H:i') }}
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>
        @else
        <div class="px-4 py-3 bg-gray-50 border-b text-center text-gray-500">
            <p class="text-sm">Nog niet ingedeeld in een blok</p>
        </div>
        @endif

        {{-- QR Code --}}
        <div class="px-4 py-4 flex flex-col items-center">
            <img
                src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode(route('weegkaart.show', $judoka->qr_code)) }}"
                alt="QR Code"
                class="w-48 h-48"
                crossorigin="anonymous"
            >
            <p class="mt-2 text-xs text-gray-400 font-mono">{{ Str::limit($judoka->qr_code, 18, '...') }}</p>
        </div>

        {{-- Instructions --}}
        <div class="px-4 py-3 bg-gray-50 text-center text-xs text-gray-500">
            Toon deze QR-code bij de weging
        </div>
    </div>

    {{-- Download button (outside card for clean image) --}}
    <div class="no-print fixed bottom-4 left-0 right-0 flex justify-center gap-3 px-4">
        <button
            onclick="downloadWeegkaart()"
            class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-full shadow-lg flex items-center gap-2 transition-colors"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Download afbeelding
        </button>
    </div>

    <script>
        async function downloadWeegkaart() {
            const element = document.getElementById('weegkaart');
            const button = event.target.closest('button');
            const originalText = button.innerHTML;

            button.innerHTML = '<svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Even geduld...';
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
    </script>
</body>
</html>
