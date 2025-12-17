@extends('layouts.print')

@section('title', 'Zaaloverzicht')

@section('content')
@foreach($blokken as $blok)
<div class="mb-8 {{ !$loop->last ? 'page-break' : '' }}">
    <h2 class="text-xl font-bold mb-2 bg-gray-200 p-3">
        Blok {{ $blok->nummer }}
        @if($blok->starttijd)
        <span class="text-sm font-normal text-gray-600 ml-2">Start: {{ $blok->starttijd->format('H:i') }}</span>
        @endif
    </h2>

    @if($blok->weging_start || $blok->weging_einde)
    <p class="text-sm text-gray-600 mb-3">
        Weging: {{ $blok->weging_start?->format('H:i') ?? '?' }} - {{ $blok->weging_einde?->format('H:i') ?? '?' }}
    </p>
    @endif

    @php
        $matten = $blok->poules->groupBy('mat_nummer');
        $aantalMatten = $toernooi->aantal_matten ?? 4;
    @endphp

    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-100">
                <th class="p-2 text-left w-20">Mat</th>
                <th class="p-2 text-left">Poules</th>
                <th class="p-2 text-center w-24">Judoka's</th>
                <th class="p-2 text-center w-24">Wedstrijden</th>
            </tr>
        </thead>
        <tbody>
            @for($mat = 1; $mat <= $aantalMatten; $mat++)
                @php $matPoules = $matten->get($mat) ?? collect(); @endphp
                <tr class="{{ $mat % 2 == 0 ? 'bg-gray-50' : 'bg-white' }}">
                    <td class="p-2 font-bold text-lg">Mat {{ $mat }}</td>
                    <td class="p-2">
                        @if($matPoules->isEmpty())
                            <span class="text-gray-400">Geen poules</span>
                        @else
                            @foreach($matPoules as $poule)
                                <div class="inline-block bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs mr-1 mb-1">
                                    {{ $poule->categorie?->naam ?? 'Poule' }}
                                    @if($poule->naam) ({{ $poule->naam }}) @endif
                                </div>
                            @endforeach
                        @endif
                    </td>
                    <td class="p-2 text-center font-medium">
                        {{ $matPoules->sum(fn($p) => $p->judokas->count()) }}
                    </td>
                    <td class="p-2 text-center">
                        {{ $matPoules->sum(fn($p) => $p->wedstrijden->count()) }}
                    </td>
                </tr>
            @endfor
        </tbody>
        <tfoot>
            <tr class="bg-gray-200 font-bold">
                <td class="p-2">Totaal</td>
                <td class="p-2">{{ $blok->poules->count() }} poules</td>
                <td class="p-2 text-center">{{ $blok->poules->sum(fn($p) => $p->judokas->count()) }}</td>
                <td class="p-2 text-center">{{ $blok->poules->sum(fn($p) => $p->wedstrijden->count()) }}</td>
            </tr>
        </tfoot>
    </table>
</div>
@endforeach
@endsection
