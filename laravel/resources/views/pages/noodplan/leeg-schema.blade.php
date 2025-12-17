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
        table-layout: fixed;
        border-collapse: collapse;
    }
    .schema-table th,
    .schema-table td {
        border: 1px solid #000;
    }
    .wedstrijd-cel {
        position: relative;
        height: 32px;
    }
    .wedstrijd-cel.actief::after {
        content: '';
        position: absolute;
        top: 4px;
        bottom: 4px;
        left: 50%;
        border-left: 1px solid #999;
    }
    .wedstrijd-cel.inactief {
        background: #000;
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

    <!-- Matrix tabel -->
    <table class="schema-table text-sm">
        <thead>
            <tr class="bg-gray-200">
                <th class="px-2 py-1 text-center font-bold" style="width: 30px;">Nr</th>
                <th class="px-2 py-1 text-left font-bold" style="width: 180px;">Naam (Club)</th>
                @foreach($schema as $idx => $wedstrijd)
                <th class="px-0 py-1 text-center font-bold text-xs">
                    <div>{{ $idx + 1 }}</div>
                    <div class="text-gray-500 font-normal" style="font-size: 9px;">W | J</div>
                </th>
                @endforeach
                <th class="px-1 py-1 text-center font-bold bg-gray-300" style="width: 35px;">WP</th>
                <th class="px-1 py-1 text-center font-bold bg-gray-300" style="width: 35px;">JP</th>
                <th class="px-1 py-1 text-center font-bold bg-gray-300" style="width: 30px;">Plts</th>
            </tr>
        </thead>
        <tbody>
            @for($i = 1; $i <= $aantal; $i++)
            <tr>
                <td class="px-2 py-0 text-center font-bold text-lg">{{ $i }}</td>
                <td class="px-2 py-0">
                    <span class="inline-block border-b border-gray-400 w-full" style="min-height: 20px;"></span>
                </td>
                @foreach($schema as $idx => $wedstrijd)
                <td class="wedstrijd-cel {{ in_array($i, $wedstrijd) ? 'actief' : 'inactief' }}"></td>
                @endforeach
                <td class="px-1 py-0 text-center bg-gray-100"></td>
                <td class="px-1 py-0 text-center bg-gray-100"></td>
                <td class="px-1 py-0 text-center bg-gray-100"></td>
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
