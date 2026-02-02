<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Print') - {{ $toernooi->naam }}</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
    <style>
        @media print {
            /* Verberg niet-printbare elementen */
            .no-print { display: none !important; }

            /* Reset achtergronden voor inkbesparing */
            body { background: white !important; }
            * { color: black !important; }

            /* Pagina breaks */
            .page-break { page-break-after: always; }
            .page-break-before { page-break-before: always; }
            .no-break { page-break-inside: avoid; }

            /* Tabel borders zichtbaar */
            table { border-collapse: collapse; }
            table, th, td { border: 1px solid #333 !important; }

            /* A4 marges */
            @page {
                margin: 1cm;
                size: A4;
            }

            /* Verwijder schaduwen en ronde hoeken */
            * {
                box-shadow: none !important;
                border-radius: 0 !important;
            }
        }

        /* Screen preview styles */
        @media screen {
            body { background: #f3f4f6; }
            .print-container {
                background: white;
                max-width: 210mm;
                margin: 0 auto;
                padding: 1cm;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
        }

        /* Weegkaart/coachkaart grid */
        .kaart-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10mm;
        }

        .kaart {
            border: 2px solid #333;
            padding: 5mm;
            page-break-inside: avoid;
        }

        /* QR code placeholder */
        .qr-placeholder {
            width: 25mm;
            height: 25mm;
            border: 1px dashed #666;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8px;
            color: #666;
        }
    </style>
    @stack('styles')
</head>
<body class="min-h-screen">
    <!-- Sticky header (toolbar + toernooi info) -->
    <div class="no-print sticky top-0 z-50 bg-white">
        <!-- Print toolbar -->
        <div class="bg-blue-800 text-white p-4">
            <div class="max-w-7xl mx-auto flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <a href="{{ route('toernooi.noodplan.index', $toernooi->routeParams()) }}" class="text-blue-200 hover:text-white">
                        &larr; Terug naar Case of Emergency
                    </a>
                    <span class="text-blue-200">|</span>
                    <span class="font-bold">@yield('title')</span>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-blue-200">
                        Geprint: {{ now()->format('d-m-Y H:i') }}
                    </span>
                    <button onclick="window.print()" class="bg-white text-blue-800 px-4 py-2 rounded font-bold hover:bg-blue-100">
                        Print
                    </button>
                </div>
            </div>
        </div>

        <!-- Toernooi header -->
        <div class="bg-white px-4 py-3 border-b-2 border-gray-300">
            <div class="max-w-7xl mx-auto flex justify-between items-start">
                <div>
                    <h1 class="text-2xl font-bold">{{ $toernooi->naam }}</h1>
                    <p class="text-gray-600">{{ $toernooi->datum->format('d-m-Y') }} - {{ $toernooi->locatie }}</p>
                </div>
                <div class="text-right text-sm text-gray-500">
                    <p>@yield('title')</p>
                    <p>{{ now()->format('d-m-Y H:i') }}</p>
                </div>
            </div>
        </div>

        @yield('toolbar')
    </div>

    <!-- Print content -->
    <div class="print-container">
        @yield('content')
    </div>

    @yield('scripts')
</body>
</html>
