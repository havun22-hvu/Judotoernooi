@extends('layouts.print')

@section('title', __('Leeg wedstrijdschema') . " - {$aantal} " . __("judoka's"))

@push('styles')
<style>
    @media print {
        @page {
            size: A4 landscape;
            margin: 0.5cm;
        }
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
    }
    @media screen {
        .print-container {
            max-width: none !important;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
    }
    .schema-table {
        width: auto;
        border-collapse: collapse;
        table-layout: fixed;
        margin: 0 auto;
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
    }
    .sub-header {
        font-size: 10px;
        font-weight: normal;
        color: #9ca3af;
    }
    .judoka-row td {
        height: 32px;
    }
    .naam-cel {
        width: 220px;
    }
    .score-cel {
        width: 24px;
        text-align: center;
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
    }
    .plts-cel {
        width: 36px;
        background: #fef9c3;
        color: #000;
    }
</style>
@endpush

@section('content')
<div class="mb-4">
    <!-- Header met invulvelden -->
    <div class="border-2 border-black px-4 py-2 mb-4">
        <div class="flex gap-8 text-sm">
            <div>{{ __('Poule') }}: <span class="inline-block border-b border-black w-20"></span></div>
            <div>{{ __('Leeftijd') }}: <span class="inline-block border-b border-black w-32"></span></div>
            <div>{{ __('Gewicht') }}: <span class="inline-block border-b border-black w-28"></span></div>
            <div>{{ __('Blok') }}: <span class="inline-block border-b border-black w-16"></span></div>
            <div>{{ __('Mat') }}: <span class="inline-block border-b border-black w-16"></span></div>
        </div>
    </div>

    <!-- Matrix tabel zoals online versie -->
    <table class="schema-table text-xs">
        <thead>
            <tr class="header-row">
                <th class="px-1 py-1 text-center" style="width: 24px;">{{ __('Nr') }}</th>
                <th class="px-2 py-1 text-left naam-cel">{{ __('Naam') }}</th>
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
            @for($i = 1; $i <= $aantal; $i++)
            <tr class="judoka-row">
                <td class="px-1 text-center font-bold">{{ $i }}</td>
                <td class="px-2 naam-cel"></td>
                @foreach($schema as $idx => $wedstrijd)
                    @if(in_array($i, $wedstrijd))
                    <td class="score-cel w-cel"></td>
                    <td class="score-cel j-cel"></td>
                    @else
                    <td class="score-cel w-cel inactief"></td>
                    <td class="score-cel j-cel inactief"></td>
                    @endif
                @endforeach
                <td class="totaal-cel text-center"></td>
                <td class="totaal-cel text-center"></td>
                <td class="plts-cel text-center"></td>
            </tr>
            @endfor
        </tbody>
    </table>

    <!-- Legenda -->
    <div class="mt-3 text-xs text-gray-600">
        <strong>W</strong> = {{ __('Wedstrijdpunten (0 of 2)') }} &nbsp;|&nbsp; <strong>J</strong> = {{ __('Judopunten') }} (Yuko=5, Waza-Ari=7, Ippon=10)
    </div>
</div>

<!-- Info -->
<div class="no-print mt-6 p-4 bg-blue-50 rounded">
    <p class="text-sm text-blue-800">
        {{ __("Schema voor :aantal judoka's = :wedstrijden wedstrijden.", ['aantal' => $aantal, 'wedstrijden' => count($schema)]) }}
    </p>
</div>
@endsection
