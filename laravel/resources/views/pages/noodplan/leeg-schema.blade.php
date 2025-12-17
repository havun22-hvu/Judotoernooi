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
            max-width: 297mm !important; /* A4 landscape width */
        }
    }
    .schema-table {
        width: 100%;
        table-layout: fixed;
    }
    .schema-table .naam-col {
        width: 18%;
    }
    .schema-table .totaal-col {
        width: 4%;
    }
    .schema-table .plaats-col {
        width: 3%;
    }
    .invulvak {
        display: inline-block;
        border: 1px solid #333;
        min-width: 20px;
        height: 22px;
        vertical-align: middle;
    }
    .invulvak-breed {
        min-width: 28px;
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
    <table class="schema-table text-sm border-collapse border border-gray-400">
        <thead>
            <tr class="bg-gray-200">
                <th class="naam-col px-2 py-2 text-left font-bold border border-gray-400">Naam (Club)</th>
                @foreach($schema as $idx => $wedstrijd)
                <th class="px-1 py-2 text-center font-bold border border-gray-400">
                    <div class="font-bold">{{ $idx + 1 }}</div>
                    <div class="text-xs text-gray-600">{{ $wedstrijd[0] }}-{{ $wedstrijd[1] }}</div>
                </th>
                @endforeach
                <th class="totaal-col px-1 py-2 text-center font-bold border border-gray-400 bg-gray-300">WP</th>
                <th class="totaal-col px-1 py-2 text-center font-bold border border-gray-400 bg-gray-300">JP</th>
                <th class="plaats-col px-1 py-2 text-center font-bold border border-gray-400 bg-gray-300">#</th>
            </tr>
        </thead>
        <tbody>
            @for($i = 1; $i <= $aantal; $i++)
            <tr>
                <!-- Nr + Naam (Club) -->
                <td class="px-2 py-3 border border-gray-400">
                    <span class="font-bold text-lg">{{ $i }}.</span>
                    <span class="inline-block border-b border-gray-400 ml-2" style="width: 85%;"></span>
                </td>
                <!-- Wedstrijd cellen -->
                @foreach($schema as $idx => $wedstrijd)
                <td class="py-2 text-center border border-gray-400 {{ in_array($i, $wedstrijd) ? 'bg-white' : 'bg-gray-500' }}">
                    @if(in_array($i, $wedstrijd))
                    <div class="flex justify-center gap-0">
                        <span class="invulvak"></span>
                        <span class="invulvak invulvak-breed"></span>
                    </div>
                    @endif
                </td>
                @endforeach
                <!-- Totaal WP -->
                <td class="py-2 text-center border border-gray-400 bg-gray-100"></td>
                <!-- Totaal JP -->
                <td class="py-2 text-center border border-gray-400 bg-gray-100"></td>
                <!-- Plaats -->
                <td class="py-2 text-center border border-gray-400 bg-gray-100"></td>
            </tr>
            @endfor
        </tbody>
    </table>

    <!-- Legenda -->
    <div class="mt-3 text-sm text-gray-600">
        <strong>Legenda:</strong> Eerste vakje = WP (0-10), Tweede vakje = JP (0, 5, 7, 10) &nbsp;|&nbsp; Grijze cellen = speelt niet
    </div>
</div>

<!-- Info -->
<div class="no-print mt-6 p-4 bg-blue-50 rounded">
    <p class="text-sm text-blue-800">
        Schema voor {{ $aantal }} judoka's = {{ count($schema) }} wedstrijden.
        Gebruik de Print knop hierboven om meerdere kopieën te printen.
    </p>
</div>
@endsection
