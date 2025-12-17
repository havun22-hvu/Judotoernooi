@extends('layouts.print')

@section('title', "Leeg wedstrijdschema - {$aantal} judoka's")

@section('content')
<div class="mb-6">
    <!-- Header met invulvelden -->
    <div class="bg-green-700 text-white px-4 py-3 mb-4">
        <div class="flex gap-8 text-sm">
            <div>Poule #: <span class="inline-block border-b border-white w-12"></span></div>
            <div>Leeftijdsklasse: <span class="inline-block border-b border-white w-24"></span></div>
            <div>Gewichtsklasse: <span class="inline-block border-b border-white w-20"></span></div>
            <div>Blok: <span class="inline-block border-b border-white w-8"></span></div>
            <div>Mat: <span class="inline-block border-b border-white w-8"></span></div>
        </div>
    </div>

    <!-- Matrix tabel -->
    <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse border">
            <thead>
                <tr class="bg-gray-200">
                    <th class="px-2 py-2 text-left font-bold border min-w-[200px]">Naam</th>
                    <th class="px-2 py-2 text-left font-bold border w-32">Club</th>
                    @foreach($schema as $idx => $wedstrijd)
                    <th class="px-1 py-2 text-center font-bold border w-16" colspan="2">
                        <div class="text-xs">{{ $idx + 1 }}</div>
                        <div class="text-xs text-gray-500">{{ $wedstrijd[0] }}-{{ $wedstrijd[1] }}</div>
                    </th>
                    @endforeach
                    <th class="px-1 py-2 text-center font-bold bg-blue-100 border w-10 text-xs">WP</th>
                    <th class="px-1 py-2 text-center font-bold bg-blue-100 border w-10 text-xs">JP</th>
                    <th class="px-1 py-2 text-center font-bold bg-yellow-100 border w-8 text-xs">#</th>
                </tr>
            </thead>
            <tbody>
                @for($i = 1; $i <= $aantal; $i++)
                <tr>
                    <!-- Nr + Naam -->
                    <td class="px-2 py-2 border">
                        <span class="font-bold">{{ $i }}.</span>
                        <span class="inline-block border-b border-gray-400 w-32 ml-1"></span>
                    </td>
                    <!-- Club -->
                    <td class="px-2 py-2 border">
                        <span class="inline-block border-b border-gray-400 w-24"></span>
                    </td>
                    <!-- Wedstrijd cellen -->
                    @foreach($schema as $idx => $wedstrijd)
                    <td class="px-0 py-1 text-center border {{ in_array($i, $wedstrijd) ? 'bg-white' : 'bg-gray-400' }}" colspan="2">
                        @if(in_array($i, $wedstrijd))
                        <div class="flex justify-center gap-1">
                            <span class="inline-block border border-gray-400 w-5 h-5"></span>
                            <span class="inline-block border border-gray-400 w-6 h-5"></span>
                        </div>
                        @endif
                    </td>
                    @endforeach
                    <!-- Totaal WP -->
                    <td class="px-1 py-1 text-center bg-blue-50 border"></td>
                    <!-- Totaal JP -->
                    <td class="px-1 py-1 text-center bg-blue-50 border"></td>
                    <!-- Plaats -->
                    <td class="px-1 py-1 text-center bg-yellow-50 border"></td>
                </tr>
                @endfor
            </tbody>
        </table>
    </div>

    <!-- Legenda -->
    <div class="mt-4 text-xs text-gray-600">
        <p><strong>Legenda:</strong> Eerste vakje = WP (0-10), Tweede vakje = JP (0, 5, 7, 10)</p>
        <p>Grijze cellen = judoka speelt niet in deze wedstrijd</p>
    </div>
</div>

<!-- Klassement tabel -->
<div class="mt-8 p-4 bg-gray-50 border rounded">
    <h3 class="font-bold mb-3">Eindklassement</h3>
    <table class="w-full text-sm border">
        <thead>
            <tr class="bg-gray-200">
                <th class="p-2 text-center border w-12">Plaats</th>
                <th class="p-2 text-center border w-8">#</th>
                <th class="p-2 text-left border">Naam</th>
                <th class="p-2 text-left border w-32">Club</th>
                <th class="p-2 text-center border w-12">WP</th>
                <th class="p-2 text-center border w-12">JP</th>
            </tr>
        </thead>
        <tbody>
            @for($i = 1; $i <= $aantal; $i++)
            <tr>
                <td class="p-2 text-center border font-bold">
                    @if($i == 1) 🥇 @elseif($i == 2) 🥈 @elseif($i == 3) 🥉 @else {{ $i }} @endif
                </td>
                <td class="p-2 text-center border"></td>
                <td class="p-2 border"></td>
                <td class="p-2 border"></td>
                <td class="p-2 text-center border"></td>
                <td class="p-2 text-center border"></td>
            </tr>
            @endfor
        </tbody>
    </table>
</div>

<!-- Print tip -->
<div class="no-print mt-6 p-4 bg-blue-50 rounded">
    <p class="text-sm text-blue-800">
        <strong>Tip:</strong> Print meerdere kopieën (Ctrl+P → aantal exemplaren).
        Schema voor {{ $aantal }} judoka's = {{ count($schema) }} wedstrijden.
    </p>
</div>
@endsection
