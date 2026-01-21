@extends('layouts.print')

@section('title', $titel)

@push('styles')
<style>
    @media print {
        .print-container {
            max-width: none !important;
            padding: 0 !important;
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
        .poule-page {
            page-break-after: always;
        }
        .poule-page:last-child {
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
        table-layout: fixed;
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
        font-size: 13px;
        padding: 6px 4px;
    }
    .sub-header {
        font-size: 11px;
        font-weight: normal;
        color: #9ca3af;
    }
    .judoka-row td {
        height: 36px;
    }
    .nr-cel {
        width: 32px;
        font-size: 14px;
    }
    .naam-cel {
        width: 280px;
        max-width: 280px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 13px;
    }
    .score-cel {
        width: 28px;
        text-align: center;
        font-size: 13px;
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
        width: 40px;
        background: #f3f4f6;
        color: #000;
        text-align: center;
        font-size: 13px;
        font-weight: bold;
    }
    .plts-cel {
        width: 36px;
        background: #fef9c3;
        color: #000;
        text-align: center;
        font-size: 13px;
    }
    .poule-header {
        background: #f3f4f6;
        border: 2px solid #333;
        padding: 12px 16px;
        margin-bottom: 12px;
    }
</style>
@endpush

@section('content')
@if($poulesMetSchema->isEmpty())
<p class="text-gray-500 text-center py-8">Geen poules met wedstrijden gevonden. Zorg dat poules op de mat staan en wedstrijden zijn gegenereerd.</p>
@else

@foreach($poulesMetSchema as $item)
@php
    $poule = $item['poule'];
    $schema = $item['schema'];
    $aantal = $item['aantal'];
    $isLandscape = $aantal >= 6;
    $judokas = $poule->judokas->values();
    $wedstrijden = $poule->wedstrijden->keyBy(function($w) {
        return $w->judoka_wit_id . '-' . $w->judoka_blauw_id;
    });
@endphp

<div class="poule-page {{ $isLandscape ? 'landscape' : '' }}">
    <!-- Poule header -->
    <div class="poule-header">
        <div class="flex justify-between items-center">
            <div>
                <span class="font-bold text-lg">
                    Poule #{{ $poule->nummer }} - {{ $poule->titel ?? ($poule->leeftijdsklasse . ' ' . $poule->gewichtsklasse) }}
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
                $totaalWP = 0;
                $totaalJP = 0;
            @endphp
            <tr class="judoka-row">
                <td class="px-1 text-center font-bold nr-cel">{{ $judokaNr }}</td>
                <td class="px-2 naam-cel" title="{{ $judoka->naam }} ({{ $judoka->club?->naam ?? '-' }})">
                    {{ $judoka->naam }}
                    <span class="text-gray-400 text-xs">({{ Str::limit($judoka->club?->naam ?? '-', 10) }})</span>
                </td>
                @foreach($schema as $schemaWedstrijd)
                    @php
                        // Check if this judoka participates in this match
                        $witNr = $schemaWedstrijd[0];
                        $blauwNr = $schemaWedstrijd[1];
                        $participates = in_array($judokaNr, $schemaWedstrijd);

                        $wp = '';
                        $jp = '';

                        if ($participates) {
                            // Find the actual match
                            $witJudoka = $judokas[$witNr - 1] ?? null;
                            $blauwJudoka = $judokas[$blauwNr - 1] ?? null;

                            if ($witJudoka && $blauwJudoka) {
                                $key = $witJudoka->id . '-' . $blauwJudoka->id;
                                $match = $wedstrijden->get($key);

                                if ($match && $match->is_gespeeld) {
                                    if ($judokaNr === $witNr) {
                                        // This judoka is white
                                        $wp = $match->winnaar_id === $judoka->id ? '2' : '0';
                                        $jp = $match->score_wit ?? '';
                                        $totaalWP += (int)$wp;
                                        $totaalJP += (int)$jp;
                                    } else {
                                        // This judoka is blue
                                        $wp = $match->winnaar_id === $judoka->id ? '2' : '0';
                                        $jp = $match->score_blauw ?? '';
                                        $totaalWP += (int)$wp;
                                        $totaalJP += (int)$jp;
                                    }
                                }
                            }
                        }
                    @endphp
                    @if($participates)
                    <td class="score-cel w-cel">{{ $wp }}</td>
                    <td class="score-cel j-cel">{{ $jp }}</td>
                    @else
                    <td class="score-cel w-cel inactief"></td>
                    <td class="score-cel j-cel inactief"></td>
                    @endif
                @endforeach
                <td class="totaal-cel">{{ $totaalWP ?: '' }}</td>
                <td class="totaal-cel">{{ $totaalJP ?: '' }}</td>
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
@endsection
