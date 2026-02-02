<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultaten - {{ $toernooi->naam }}</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
    <style>
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
            .avoid-break { page-break-inside: avoid; }
            header, footer { display: none !important; }
            .print-full { width: 100% !important; max-width: none !important; }
        }
        @page { margin: 1cm; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header (no print) -->
    <header class="bg-blue-600 text-white py-4 no-print">
        <div class="max-w-6xl mx-auto px-4 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold">{{ $toernooi->naam }}</h1>
                <p class="text-blue-200">Resultaten Overzicht</p>
            </div>
            <div class="flex gap-3">
                <button onclick="window.print()" class="bg-white text-blue-600 px-4 py-2 rounded-lg font-medium hover:bg-blue-50 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Printen
                </button>
                <a href="{{ route('toernooi.edit', $toernooi->routeParams()) }}" class="bg-blue-500 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-400">
                    Terug
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-6 print-full">
        <!-- Print Header -->
        <div class="hidden print:block mb-6 text-center border-b-2 border-gray-300 pb-4">
            <h1 class="text-3xl font-bold">{{ $toernooi->naam }}</h1>
            <p class="text-gray-600">{{ $toernooi->datum->format('d F Y') }} - Resultaten</p>
        </div>

        <!-- Tab Navigation (no print) -->
        <div class="mb-6 no-print" x-data="{ tab: 'ranking' }">
            <div class="flex border-b border-gray-300">
                <button @click="tab = 'ranking'" :class="tab === 'ranking' ? 'bg-white border-b-2 border-blue-600 text-blue-600' : 'text-gray-600 hover:text-gray-800'"
                        class="px-6 py-3 font-medium">
                    Club Ranking
                </button>
                <button @click="tab = 'uitslagen'" :class="tab === 'uitslagen' ? 'bg-white border-b-2 border-blue-600 text-blue-600' : 'text-gray-600 hover:text-gray-800'"
                        class="px-6 py-3 font-medium">
                    Alle Uitslagen
                </button>
            </div>

            <!-- Club Ranking Tab -->
            <div x-show="tab === 'ranking'" class="mt-6">
                @include('pages.resultaten._club-ranking', ['clubRanking' => $clubRanking])
            </div>

            <!-- Uitslagen Tab -->
            <div x-show="tab === 'uitslagen'" class="mt-6">
                @include('pages.resultaten._uitslagen', ['uitslagen' => $uitslagen])
            </div>
        </div>

        <!-- Print Version: Both sections -->
        <div class="hidden print:block">
            <h2 class="text-2xl font-bold mb-4">Club Ranking</h2>
            @include('pages.resultaten._club-ranking', ['clubRanking' => $clubRanking])

            <div class="page-break"></div>

            <h2 class="text-2xl font-bold mb-4 mt-8">Alle Uitslagen</h2>
            @include('pages.resultaten._uitslagen', ['uitslagen' => $uitslagen])
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-gray-400 text-center py-4 no-print">
        <p>Gegenereerd op {{ now()->format('d-m-Y H:i') }}</p>
    </footer>

</body>
</html>
