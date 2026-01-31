@extends('layouts.print')

@section('title', $titel)

@push('styles')
<style>
    @media print {
        .print-container {
            max-width: none !important;
            padding: 0 !important;
        }
        /* Hide elements with no-print class */
        .no-print,
        .print-toolbar {
            display: none !important;
        }
        /* Hide unchecked poules */
        .poule-page.print-exclude {
            display: none !important;
        }
        /* Override layout print styles voor schema */
        .schema-table,
        .schema-table * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }
        .header-row,
        .header-row th,
        .header-row th div,
        .header-row th * {
            background: #1f2937 !important;
            color: white !important;
        }
        .header-row .sub-header {
            color: #9ca3af !important;
        }
        .score-cel.inactief {
            background: #1f2937 !important;
        }
        .totaal-cel {
            background: #f3f4f6 !important;
            color: #000 !important;
        }
        .plts-cel {
            background: #fef9c3 !important;
            color: #000 !important;
        }
        /* Page breaks between poules */
        .poule-page:not(.print-exclude) {
            page-break-after: always;
        }
        .poule-page:not(.print-exclude):last-of-type {
            page-break-after: avoid;
        }
        /* Landscape pages for 6+ judokas */
        .poule-page.landscape {
            page: landscape;
        }
    }
    @page {
        size: A4 portrait;
        margin: 0.5cm;
    }
    @page landscape {
        size: A4 landscape;
        margin: 0.5cm;
    }
    @media screen {
        .print-container {
            max-width: none !important;
        }
        .poule-page {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 2px dashed #ccc;
        }
    }
    .schema-table {
        width: auto;
        border-collapse: collapse;
    }
    .schema-table th,
    .schema-table td {
        border: 1px solid #333;
    }
    .header-row {
        background: #1f2937;
        color: white;
    }
    .header-row th {
        border-color: #374151;
        font-size: 11px;
        padding: 4px 2px;
    }
    .sub-header {
        font-size: 9px;
        font-weight: normal;
        color: #9ca3af;
    }
    .judoka-row td {
        height: 36px;
    }
    .nr-cel {
        width: 28px;
        font-size: 13px;
    }
    .naam-cel {
        font-size: 12px;
        padding: 4px 6px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .score-cel {
        width: 22px;
        text-align: center;
        font-size: 12px;
    }
    .score-cel.w-cel {
        border-right: 1px solid #ccc;
    }
    .score-cel.w-cel.inactief {
        border-right: none;
    }
    .score-cel.j-cel {
        border-left: none;
        border-right: 2px solid #333;
    }
    .score-cel.inactief {
        background: #1f2937;
    }
    .totaal-cel {
        width: 30px;
        background: #f3f4f6;
        color: #000;
        text-align: center;
        font-size: 12px;
        font-weight: bold;
    }
    .plts-cel {
        width: 30px;
        background: #fef9c3;
        color: #000;
        text-align: center;
        font-size: 12px;
    }
    .poule-header {
        background: #f3f4f6;
        border: 2px solid #333;
        padding: 8px 12px;
        margin-bottom: 8px;
    }
</style>
@endpush

@section('content')
@if($poulesMetSchema->isEmpty())
<p class="text-gray-500 text-center py-8">Geen poules met wedstrijden gevonden. Zorg dat poules op de mat staan en wedstrijden zijn gegenereerd.</p>
@else

<!-- Print toolbar (niet printen) -->
<div class="print-toolbar no-print mb-6 p-4 bg-gray-100 rounded-lg sticky top-0 z-50 shadow">
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-4">
            <span class="font-medium text-gray-700">Selecteer schema's om te printen:</span>
            <button onclick="selectAllPoules(true)" type="button" class="text-sm text-blue-600 hover:text-blue-800">Alles aan</button>
            <button onclick="selectAllPoules(false)" type="button" class="text-sm text-gray-600 hover:text-gray-800">Alles uit</button>
        </div>
        <div class="flex items-center gap-4">
            <span class="text-sm text-gray-500" id="print-counter">{{ $poulesMetSchema->count() }} van {{ $poulesMetSchema->count() }} geselecteerd</span>
            <button onclick="window.print()" class="px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700 font-medium">
                Print geselecteerde
            </button>
        </div>
    </div>
</div>

@php
// Helper functie voor slimme club afkorting
function abbreviateClubName($name, $maxLength = 15) {
    if (!$name || strlen($name) <= $maxLength) {
        return $name ?? '-';
    }
    // Vervang veelvoorkomende woorden met afkortingen
    $replacements = [
        'Judoschool' => 'J.S.',
        'judoschool' => 'J.S.',
        'Sportcentrum' => 'S.C.',
        'sportcentrum' => 'S.C.',
        'Sportvereniging' => 'S.V.',
        'sportvereniging' => 'S.V.',
        'Judo Vereniging' => 'J.V.',
        'Judovereniging' => 'J.V.',
        'judovereniging' => 'J.V.',
    ];
    $abbreviated = str_replace(array_keys($replacements), array_values($replacements), $name);
    // Als nog steeds te lang, truncate
    if (strlen($abbreviated) > $maxLength) {
        return Str::limit($abbreviated, $maxLength);
    }
    return $abbreviated;
}
@endphp

@foreach($poulesMetSchema as $index => $item)
@php
    $poule = $item['poule'];
    $schema = $item['schema'];
    $aantal = $item['aantal'];
    $aantalWedstrijden = count($schema);
    // Landscape als meer dan 6 wedstrijden (kolommen)
    $isLandscape = $aantalWedstrijden > 6;
    $judokas = $poule->judokas->values();
@endphp

<div class="poule-page {{ $isLandscape ? 'landscape' : '' }}"
     x-data="pouleSelect()"
     :class="{ 'print-exclude': !printInclude, 'opacity-50': !printInclude }"
     data-poule-id="{{ $poule->id }}">
    <!-- Poule header -->
    <div class="poule-header">
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
                <!-- Print checkbox -->
                <label class="no-print flex items-center cursor-pointer">
                    <input type="checkbox" x-model="printInclude" checked class="w-5 h-5 text-yellow-600 rounded border-gray-300 focus:ring-yellow-500">
                </label>
                <span class="font-bold text-lg">
                    Poule #{{ $poule->nummer }} - {{ $poule->getDisplayTitel() }}
                </span>
            </div>
            <div class="text-sm text-gray-600">
                @if($poule->mat)
                <span class="font-bold">Mat {{ $poule->mat->nummer }}</span> |
                @endif
                @if($poule->blok)
                Blok {{ $poule->blok->nummer }}
                @endif
            </div>
        </div>
    </div>

    <!-- Matrix tabel -->
    <table class="schema-table text-sm">
        <thead>
            <tr class="header-row">
                <th class="px-1 py-1 text-center nr-cel">Nr</th>
                <th class="px-2 py-1 text-left naam-cel">Naam</th>
                @foreach($schema as $idx => $wedstrijd)
                <th class="py-1 text-center" colspan="2" style="min-width: 36px;">
                    <div class="font-bold">{{ $idx + 1 }}</div>
                    <div class="sub-header">W &nbsp; J</div>
                </th>
                @endforeach
                <th class="px-1 py-1 text-center totaal-cel">WP</th>
                <th class="px-1 py-1 text-center totaal-cel">JP</th>
                <th class="px-1 py-1 text-center plts-cel">Plts</th>
            </tr>
        </thead>
        <tbody>
            @foreach($judokas as $idx => $judoka)
            @php
                $judokaNr = $idx + 1;
            @endphp
            <tr class="judoka-row">
                <td class="px-1 text-center font-bold nr-cel">{{ $judokaNr }}</td>
                <td class="px-2 naam-cel" title="{{ $judoka->naam }} ({{ $judoka->club?->naam ?? '-' }})">
                    {{ $judoka->naam }}
                    <span class="text-gray-400 text-xs">({{ abbreviateClubName($judoka->club?->naam) }})</span>
                </td>
                @foreach($schema as $schemaWedstrijd)
                    @php
                        // Check if this judoka participates in this match
                        $witNr = $schemaWedstrijd[0];
                        $blauwNr = $schemaWedstrijd[1];
                        $participates = in_array($judokaNr, $schemaWedstrijd);
                    @endphp
                    @if($participates)
                    {{-- Lege cellen voor handmatig invullen --}}
                    <td class="score-cel w-cel"></td>
                    <td class="score-cel j-cel"></td>
                    @else
                    <td class="score-cel w-cel inactief"></td>
                    <td class="score-cel j-cel inactief"></td>
                    @endif
                @endforeach
                <td class="totaal-cel"></td>
                <td class="totaal-cel"></td>
                <td class="plts-cel"></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Legenda -->
    <div class="mt-2 text-xs text-gray-600">
        <strong>W</strong> = Wedstrijdpunten (0 of 2) | <strong>J</strong> = Judopunten | Plts = handmatig invullen
    </div>
</div>
@endforeach

@endif

<!-- Info (niet printen) -->
<div class="no-print mt-6 p-4 bg-blue-50 rounded">
    <p class="text-sm text-blue-800">
        {{ $poulesMetSchema->count() }} poule(s) met wedstrijden gevonden.
        @if($blok)
        (Blok {{ $blok->nummer }})
        @endif
    </p>
</div>

<script>
const totalPoules = {{ $poulesMetSchema->count() }};

function pouleSelect() {
    return {
        printInclude: true,
        init() {
            this.$watch('printInclude', () => {
                this.$nextTick(() => updatePrintCounter());
            });
        }
    };
}

function selectAllPoules(checked) {
    document.querySelectorAll('.poule-page').forEach(el => {
        const checkbox = el.querySelector('input[type="checkbox"]');
        if (checkbox) {
            checkbox.checked = checked;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
}

function updatePrintCounter() {
    const selected = document.querySelectorAll('.poule-page:not(.print-exclude)').length;
    const counter = document.getElementById('print-counter');
    if (counter) {
        counter.textContent = selected + ' van ' + totalPoules + ' geselecteerd';
    }
}

document.addEventListener('DOMContentLoaded', () => setTimeout(updatePrintCounter, 200));
</script>
@endsection
