@extends('layouts.print')

@section('title', 'Weeglijst')

@section('content')
@foreach($judokasPerBlok as $blokNummer => $judokas)
<div class="{{ !$loop->last ? 'page-break' : '' }}">
    <h1 class="text-2xl font-bold text-gray-800 mb-4">Weeglijst Blok {{ $blokNummer }}</h1>

    <table class="w-full text-sm">
        <thead>
            <tr class="bg-blue-800 text-white">
                <th class="px-3 py-2 text-left w-12">#</th>
                <th class="px-3 py-2 text-left">Naam</th>
                <th class="px-3 py-2 text-left">Club</th>
                <th class="px-3 py-2 text-center">Geslacht</th>
                <th class="px-3 py-2 text-center">Leeftijdsklasse</th>
                <th class="px-3 py-2 text-center">Band</th>
                <th class="px-3 py-2 text-center">Gewichtsklasse</th>
                <th class="px-3 py-2 text-center">Gewogen</th>
            </tr>
        </thead>
        <tbody>
            @foreach($judokas as $idx => $judoka)
            <tr class="{{ $idx % 2 == 0 ? 'bg-white' : 'bg-gray-50' }}">
                <td class="px-3 py-2">{{ $idx + 1 }}</td>
                <td class="px-3 py-2 font-medium">{{ $judoka->naam }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $judoka->club?->naam ?? '-' }}</td>
                <td class="px-3 py-2 text-center">{{ $judoka->geslacht?->value ?? '-' }}</td>
                <td class="px-3 py-2 text-center">{{ $judoka->leeftijdsklasse ?? '-' }}</td>
                <td class="px-3 py-2 text-center">{{ $judoka->band_enum?->label() ?? '-' }}</td>
                <td class="px-3 py-2 text-center">{{ $judoka->gewichtsklasse ?? '-' }}</td>
                <td class="px-3 py-2 text-center font-bold {{ $judoka->gewicht_gewogen ? 'text-green-600' : 'text-gray-400' }}">
                    {{ $judoka->gewicht_gewogen ? number_format($judoka->gewicht_gewogen, 1) . ' kg' : '-' }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-4 text-sm text-gray-600">
        <strong>Totaal Blok {{ $blokNummer }}:</strong> {{ $judokas->count() }} judoka's
    </div>
</div>
@endforeach

@if($judokasPerBlok->flatten()->isEmpty())
<p class="text-gray-500 text-center py-8">Geen judoka's gevonden</p>
@endif
@endsection
