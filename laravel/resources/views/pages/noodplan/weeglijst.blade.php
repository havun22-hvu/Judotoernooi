@extends('layouts.print')

@section('title', 'Weeglijst')

@section('content')
<table class="w-full text-sm">
    <thead>
        <tr class="bg-gray-200">
            <th class="p-2 text-left">#</th>
            <th class="p-2 text-left">Naam</th>
            <th class="p-2 text-left">Club</th>
            <th class="p-2 text-center">Geslacht</th>
            <th class="p-2 text-center">Leeftijd</th>
            <th class="p-2 text-center">Band</th>
            <th class="p-2 text-center">Gewichtsklasse</th>
            <th class="p-2 text-center">Gewogen</th>
            <th class="p-2 text-center w-24">Handtekening</th>
        </tr>
    </thead>
    <tbody>
        @foreach($judokas->groupBy('club_id') as $clubId => $clubJudokas)
            @php $clubNaam = $clubJudokas->first()->club?->naam ?? 'Onbekend'; @endphp
            <tr class="bg-blue-100">
                <td colspan="9" class="p-2 font-bold">{{ $clubNaam }} ({{ $clubJudokas->count() }})</td>
            </tr>
            @foreach($clubJudokas as $idx => $judoka)
            <tr class="{{ $idx % 2 == 0 ? 'bg-white' : 'bg-gray-50' }}">
                <td class="p-2">{{ $loop->parent->iteration }}.{{ $idx + 1 }}</td>
                <td class="p-2 font-medium">{{ $judoka->naam }}</td>
                <td class="p-2 text-sm text-gray-600">{{ $judoka->club?->naam ?? '-' }}</td>
                <td class="p-2 text-center">{{ $judoka->geslacht?->value ?? '-' }}</td>
                <td class="p-2 text-center">{{ $judoka->geboortedatum?->age ?? '-' }}</td>
                <td class="p-2 text-center">{{ $judoka->band_enum?->label() ?? '-' }}</td>
                <td class="p-2 text-center">{{ $judoka->gewichtsklasse ?? '-' }}</td>
                <td class="p-2 text-center">
                    @if($judoka->gewogen_gewicht)
                        <span class="text-green-600 font-bold">{{ number_format($judoka->gewogen_gewicht, 1) }} kg</span>
                    @else
                        <span class="text-gray-400">-</span>
                    @endif
                </td>
                <td class="p-2 border-l-2"></td>
            </tr>
            @endforeach
        @endforeach
    </tbody>
</table>

<div class="mt-6 text-sm text-gray-600">
    <p><strong>Totaal:</strong> {{ $judokas->count() }} judoka's</p>
    <p><strong>Gewogen:</strong> {{ $judokas->whereNotNull('gewogen_gewicht')->count() }}</p>
    <p><strong>Nog te wegen:</strong> {{ $judokas->whereNull('gewogen_gewicht')->count() }}</p>
</div>
@endsection
