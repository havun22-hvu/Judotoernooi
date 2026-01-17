@extends('layouts.print')

@section('title', 'Weeglijst')

@section('content')
@foreach($judokasPerBlok as $blokNummer => $judokas)
<div class="{{ !$loop->last ? 'page-break' : '' }}">
    <h1 class="text-2xl font-bold text-gray-800 mb-4">Weeglijst Blok {{ $blokNummer }}</h1>

    <table class="w-full text-sm border-collapse">
        <thead>
            <tr class="bg-blue-800 text-white">
                <th class="px-2 py-2 text-left w-10">#</th>
                <th class="px-2 py-2 text-left">Naam</th>
                <th class="px-2 py-2 text-left">Club</th>
                <th class="px-2 py-2 text-center w-20">Leeftijd</th>
                <th class="px-2 py-2 text-center w-16">Gew.kl.</th>
                <th class="px-2 py-2 text-center w-20">Opgegeven</th>
                <th class="px-2 py-2 text-center w-20">Gewogen</th>
            </tr>
        </thead>
        <tbody>
            @foreach($judokas as $idx => $judoka)
            <tr class="border-b {{ $idx % 2 == 0 ? 'bg-white' : 'bg-gray-50' }}">
                <td class="px-2 py-1">{{ $idx + 1 }}</td>
                <td class="px-2 py-1 font-medium">{{ $judoka->naam }}</td>
                <td class="px-2 py-1 text-gray-600 text-xs">{{ $judoka->club?->naam ?? '-' }}</td>
                <td class="px-2 py-1 text-center text-xs">{{ $judoka->leeftijdsklasse ?? '-' }}</td>
                <td class="px-2 py-1 text-center text-xs">{{ $judoka->gewichtsklasse ?? '-' }}</td>
                <td class="px-2 py-1 text-center text-xs">{{ $judoka->gewicht_opgegeven ? number_format($judoka->gewicht_opgegeven, 1) : '-' }}</td>
                <td class="px-2 py-1 text-center border-l">
                    @if($judoka->gewicht_gewogen)
                        <span class="font-bold">{{ number_format($judoka->gewicht_gewogen, 1) }}</span>
                    @else
                        <span class="inline-block w-16 border-b border-gray-400">&nbsp;</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-4 text-sm text-gray-600">
        <strong>Totaal:</strong> {{ $judokas->count() }} judoka's
    </div>
</div>
@endforeach

@if($judokasPerBlok->flatten()->isEmpty())
<p class="text-gray-500 text-center py-8">Geen judoka's gevonden</p>
@endif
@endsection
