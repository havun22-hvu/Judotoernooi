@extends('layouts.print')

@section('title', "Leeg wedstrijdschema - {$aantal} judoka's")

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
    }
    @media screen {
        .print-container {
            max-width: 297mm !important;
        }
    }
    .schema-table {
        width: 100%;
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
    }
    .sub-header {
        font-size: 10px;
        font-weight: normal;
        color: #9ca3af;
    }
    .judoka-row td {
        height: 28px;
    }
    .naam-cel {
        min-width: 160px;
    }
    .score-cel {
        width: 18px;
        text-align: center;
    }
    .score-cel.inactief {
        background: #1f2937;
    }
    .totaal-cel {
        width: 32px;
        background: #f3f4f6;
    }
    .plts-cel {
        width: 28px;
        background: #fef9c3;
    }
</style>
@endpush

@section('content')
<div class="mb-4">
    <!-- Header met invulvelden -->
    <div class="border-2 border-black px-4 py-2 mb-4">
        <div class="flex gap-8 text-sm">
            <div>Poule: <span class="inline-block border-b border-black w-20"></span></div>
            <div>Leeftijd: <span class="inline-block border-b border-black w-32"></span></div>
            <div>Gewicht: <span class="inline-block border-b border-black w-28"></span></div>
            <div>Blok: <span class="inline-block border-b border-black w-16"></span></div>
            <div>Mat: <span class="inline-block border-b border-black w-16"></span></div>
        </div>
    </div>

    <!-- Matrix tabel zoals online versie -->
    <table class="schema-table text-xs">
        <thead>
            <tr class="header-row">
                <th class="px-1 py-1 text-center" style="width: 24px;">Nr</th>
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
            @for($i = 1; $i <= $aantal; $i++)
            <tr class="judoka-row">
                <td class="px-1 text-center font-bold">{{ $i }}</td>
                <td class="px-2 naam-cel">
                    <span class="inline-block border-b border-gray-300 w-full">&nbsp;</span>
                </td>
                @foreach($schema as $idx => $wedstrijd)
                    @if(in_array($i, $wedstrijd))
                    <td class="score-cel border-r-0"></td>
                    <td class="score-cel border-l-0"></td>
                    @else
                    <td class="score-cel inactief border-r-0"></td>
                    <td class="score-cel inactief border-l-0"></td>
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
        <strong>W</strong> = Wedstrijdpunten (0 of 2) &nbsp;|&nbsp; <strong>J</strong> = Judopunten (Yuko=5, Waza-Ari=7, Ippon=10)
    </div>
</div>

<!-- Info -->
<div class="no-print mt-6 p-4 bg-blue-50 rounded">
    <p class="text-sm text-blue-800">
        Schema voor {{ $aantal }} judoka's = {{ count($schema) }} wedstrijden.
    </p>
</div>
@endsection
