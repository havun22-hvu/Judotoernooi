@extends('layouts.print')

@section('title', 'Poule-indeling' . ($blok ? " Blok {$blok->nummer}" : ''))

@section('content')
@php
    $enkelBlok = $blokken->count() === 1;
    $isFirst = true;
@endphp

@foreach($blokken as $blok)
    @foreach($matten as $mat)
        @php
            $matPoules = $blok->poules->where('mat_id', $mat->id)->sortBy(fn($p) => $p->nummer);
        @endphp
        @if($matPoules->isNotEmpty())
        <div class="{{ !$isFirst ? 'page-break' : '' }}">
            <h2 class="text-xl font-bold text-blue-800 mb-3 border-b-2 border-blue-300 pb-2">
                @if(!$enkelBlok)Blok {{ $blok->nummer }} - @endifMat {{ $mat->nummer }}{{ $mat->label ? " ({$mat->label})" : '' }}
            </h2>

            @foreach($matPoules as $poule)
            <div class="mb-4">
                <h3 class="font-medium text-gray-700 mb-1">
                    Poule #{{ $poule->nummer }} - {{ $poule->getDisplayTitel() }}
                </h3>
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="px-2 py-1 text-left w-8">#</th>
                            <th class="px-2 py-1 text-left">Naam</th>
                            <th class="px-2 py-1 text-left">Club</th>
                            <th class="px-2 py-1 text-center w-16">Gewicht</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($poule->judokas as $idx => $judoka)
                        <tr class="border-b {{ $idx % 2 == 0 ? 'bg-white' : 'bg-gray-50' }}">
                            <td class="px-2 py-1 text-gray-500">{{ $idx + 1 }}</td>
                            <td class="px-2 py-1 font-medium">{{ $judoka->naam }}</td>
                            <td class="px-2 py-1 text-gray-600 text-xs">{{ $judoka->club?->naam ?? '-' }}</td>
                            <td class="px-2 py-1 text-center text-xs">
                                {{ $judoka->gewicht_gewogen ? number_format($judoka->gewicht_gewogen, 1) : ($judoka->gewicht ? number_format($judoka->gewicht, 1) : '-') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endforeach
            @php $isFirst = false; @endphp
        </div>
        @endif
    @endforeach
@endforeach

@if($blokken->flatMap(fn($b) => $b->poules)->isEmpty())
<p class="text-gray-500 text-center py-8">Geen poules gevonden</p>
@endif
@endsection
