@extends('layouts.print')

@section('title', "Leeg wedstrijdschema - {$aantal} judoka's")

@push('styles')
<style>
    @media print {
        @page {
            size: {{ $aantal >= 5 ? 'A4 landscape' : 'A4 portrait' }};
            margin: 0.5cm;
        }
    }
</style>
@endpush

@section('content')
<div class="mb-6">
    <!-- Header met invulvelden -->
    <div class="border-2 border-black px-4 py-2 mb-4">
        <div class="flex gap-6 text-xs">
            <div>Poule: <span class="inline-block border-b border-black w-16"></span></div>
            <div>Leeftijd: <span class="inline-block border-b border-black w-28"></span></div>
            <div>Gewicht: <span class="inline-block border-b border-black w-24"></span></div>
            <div>Blok: <span class="inline-block border-b border-black w-12"></span></div>
            <div>Mat: <span class="inline-block border-b border-black w-12"></span></div>
        </div>
    </div>

    <!-- Matrix tabel -->
    <div class="overflow-x-auto">
        <table class="w-full {{ $aantal >= 5 ? 'text-xs' : 'text-sm' }} border-collapse border">
            <thead>
                <tr class="bg-gray-200">
                    <th class="px-1 py-1 text-left font-bold border {{ $aantal >= 6 ? 'min-w-[140px]' : 'min-w-[200px]' }}">Naam (Club)</th>
                    @foreach($schema as $idx => $wedstrijd)
                    <th class="px-0 py-1 text-center font-bold border-l-2 border-gray-400 border-y border-r {{ $aantal >= 6 ? 'w-10' : 'w-14' }}" colspan="2">
                        <div class="text-xs font-bold">{{ $idx + 1 }}</div>
                        <div class="text-xs text-gray-500">{{ $wedstrijd[0] }}-{{ $wedstrijd[1] }}</div>
                    </th>
                    @endforeach
                    <th class="px-0 py-1 text-center font-bold bg-blue-100 border w-8 text-xs">WP</th>
                    <th class="px-0 py-1 text-center font-bold bg-blue-100 border w-8 text-xs">JP</th>
                    <th class="px-0 py-1 text-center font-bold bg-yellow-100 border w-6 text-xs">#</th>
                </tr>
            </thead>
            <tbody>
                @for($i = 1; $i <= $aantal; $i++)
                <tr>
                    <!-- Nr + Naam (Club) -->
                    <td class="px-1 py-1 border">
                        <span class="font-bold">{{ $i }}.</span>
                        <span class="inline-block border-b border-gray-400 {{ $aantal >= 6 ? 'w-28' : 'w-40' }} ml-1"></span>
                    </td>
                    <!-- Wedstrijd cellen -->
                    @foreach($schema as $idx => $wedstrijd)
                    <td class="px-0 py-1 text-center border-l-2 border-gray-400 border-y border-r {{ in_array($i, $wedstrijd) ? 'bg-white' : 'bg-gray-600' }}" colspan="2">
                        @if(in_array($i, $wedstrijd))
                        <div class="flex justify-center gap-0">
                            <span class="inline-block border border-gray-400 {{ $aantal >= 6 ? 'w-4 h-4' : 'w-5 h-5' }}"></span>
                            <span class="inline-block border border-gray-400 {{ $aantal >= 6 ? 'w-5 h-4' : 'w-6 h-5' }}"></span>
                        </div>
                        @endif
                    </td>
                    @endforeach
                    <!-- Totaal WP -->
                    <td class="px-0 py-1 text-center bg-blue-50 border"></td>
                    <!-- Totaal JP -->
                    <td class="px-0 py-1 text-center bg-blue-50 border"></td>
                    <!-- Plaats -->
                    <td class="px-0 py-1 text-center bg-yellow-50 border"></td>
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

<!-- Info -->
<div class="no-print mt-6 p-4 bg-blue-50 rounded">
    <p class="text-sm text-blue-800">
        Schema voor {{ $aantal }} judoka's = {{ count($schema) }} wedstrijden.
        Gebruik de Print knop hierboven om meerdere kopieën te printen.
    </p>
</div>
@endsection
