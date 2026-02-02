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
        .score-cel.gespeeld {
            background: #d1fae5 !important;
        }
        .totaal-cel {
            background: #f3f4f6 !important;
            color: #000 !important;
        }
        .plts-cel {
            background: #fef9c3 !important;
            color: #000 !important;
        }
        .title-row td {
            background: #1f2937 !important;
            color: white !important;
        }
        .info-row td {
            background: #f3f4f6 !important;
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
    .score-cel.gespeeld {
        background: #d1fae5;
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
    .title-row td {
        background: #1f2937;
        color: white;
        padding: 6px 12px;
        font-size: 11px;
        border: none;
    }
    .info-row td {
        background: #f3f4f6;
        padding: 6px 12px;
        font-size: 12px;
        border: none;
        border-bottom: 2px solid #333;
    }
</style>
@endpush

@section('toolbar')
@if(!$poulesMetSchema->isEmpty())
<div class="bg-gray-100 px-4 py-3 border-b">
    <div class="max-w-7xl mx-auto flex items-center justify-between flex-wrap gap-4">
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
@endif
@endsection

@section('content')
@if($poulesMetSchema->isEmpty())
<p class="text-gray-500 text-center py-8">Geen poules met wedstrijden gevonden. Zorg dat poules op de mat staan en wedstrijden zijn gegenereerd.</p>
@else

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

@php
    // showScores = true voor live versie, false voor lege versie
    $showScores = $showScores ?? false;
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

    // Maak wedstrijd lookup op positie als we scores tonen
    $wedstrijdByPositie = [];
    if ($showScores) {
        foreach ($poule->wedstrijden as $w) {
            $wedstrijdByPositie[$w->volgorde ?? $w->id] = $w;
        }
    }
@endphp

@php
    // Bereken totaal aantal kolommen: Nr + Naam + (wedstrijden * 2) + WP + JP + Plts = 2 + (n*2) + 3 = 5 + (n*2)
    $totalCols = 5 + (count($schema) * 2);
@endphp
<div class="poule-page {{ $isLandscape ? 'landscape' : '' }}"
     x-data="pouleSelect()"
     :class="{ 'print-exclude': !printInclude, 'opacity-50': !printInclude }"
     data-poule-id="{{ $poule->id }}">

    <!-- Matrix tabel met header rows -->
    <table class="schema-table text-sm">
        <thead>
            <!-- Toernooi titel row -->
            <tr class="title-row">
                <td colspan="{{ $totalCols }}">
                    <div style="display: flex; justify-content: space-between;">
                        <span>{{ $toernooi->naam }}</span>
                        <span>{{ $toernooi->datum->format('d-m-Y') }}</span>
                    </div>
                </td>
            </tr>
            <!-- Poule info row -->
            <tr class="info-row">
                <td colspan="{{ $totalCols }}">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <label class="no-print" style="cursor: pointer;">
                                <input type="checkbox" x-model="printInclude" checked style="width: 18px; height: 18px;">
                            </label>
                            <strong>Poule #{{ $poule->nummer }} - {{ $poule->getDisplayTitel() }}</strong>
                        </div>
                        <span>
                            @if($poule->mat)<strong>Mat {{ $poule->mat->nummer }}</strong> | @endif
                            @if($poule->blok)Blok {{ $poule->blok->nummer }}@endif
                        </span>
                    </div>
                </td>
            </tr>
            <!-- Column headers -->
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
                $totaalWP = 0;
                $totaalJP = 0;
                $heeftGespeeldeWedstrijd = false;
            @endphp
            <tr class="judoka-row">
                <td class="px-1 text-center font-bold nr-cel">{{ $judokaNr }}</td>
                <td class="px-2 naam-cel" title="{{ $judoka->naam }} ({{ $judoka->club?->naam ?? '-' }})">
                    {{ $judoka->naam }}
                    <span class="text-gray-400 text-xs">({{ abbreviateClubName($judoka->club?->naam) }})</span>
                </td>
                @foreach($schema as $wedstrijdIdx => $schemaWedstrijd)
                    @php
                        // Check if this judoka participates in this match
                        $witNr = $schemaWedstrijd[0];
                        $blauwNr = $schemaWedstrijd[1];
                        $participates = in_array($judokaNr, $schemaWedstrijd);

                        $wp = '';
                        $jp = '';
                        $gespeeld = false;

                        if ($showScores && $participates) {
                            // Zoek de wedstrijd in de database wedstrijden
                            $witJudoka = $judokas[$witNr - 1] ?? null;
                            $blauwJudoka = $judokas[$blauwNr - 1] ?? null;

                            $wedstrijd = $poule->wedstrijden->first(function($w) use ($witJudoka, $blauwJudoka) {
                                return ($w->judoka_wit_id == $witJudoka?->id && $w->judoka_blauw_id == $blauwJudoka?->id)
                                    || ($w->judoka_blauw_id == $witJudoka?->id && $w->judoka_wit_id == $blauwJudoka?->id);
                            });

                            if ($wedstrijd && $wedstrijd->is_gespeeld) {
                                $gespeeld = true;
                                $heeftGespeeldeWedstrijd = true;
                                // Is deze judoka wit of blauw in de wedstrijd?
                                $isWit = $wedstrijd->judoka_wit_id == $judoka->id;
                                $wp = $isWit ? ($wedstrijd->score_wit ?? 0) : ($wedstrijd->score_blauw ?? 0);
                                $jp = $isWit ? ($wedstrijd->judopunten_wit ?? 0) : ($wedstrijd->judopunten_blauw ?? 0);

                                $totaalWP += (int)$wp;
                                $totaalJP += (int)$jp;
                            }
                        }
                    @endphp
                    @if($participates)
                    <td class="score-cel w-cel {{ $gespeeld ? 'gespeeld' : '' }}">{{ $showScores && $gespeeld ? $wp : '' }}</td>
                    <td class="score-cel j-cel {{ $gespeeld ? 'gespeeld' : '' }}">{{ $showScores && $gespeeld ? $jp : '' }}</td>
                    @else
                    <td class="score-cel w-cel inactief"></td>
                    <td class="score-cel j-cel inactief"></td>
                    @endif
                @endforeach
                <td class="totaal-cel">{{ $showScores && $heeftGespeeldeWedstrijd ? $totaalWP : '' }}</td>
                <td class="totaal-cel">{{ $showScores && $heeftGespeeldeWedstrijd ? $totaalJP : '' }}</td>
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
