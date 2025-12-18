@extends('layouts.print')

@section('title', $titel)

@section('content')
<h1 class="text-2xl font-bold text-gray-800 mb-4">{{ $titel }}</h1>

<table class="w-full text-sm">
    <thead>
        <tr class="bg-blue-800 text-white">
            <th class="px-3 py-2 text-left">#</th>
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
        @if($blok ?? false)
            {{-- Per blok: alfabetisch op naam --}}
            @foreach($judokas as $idx => $judoka)
            <tr class="{{ $idx % 2 == 0 ? 'bg-white' : 'bg-gray-50' }} hover:bg-gray-100">
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
        @else
            {{-- Alle judoka's: gegroepeerd per club --}}
            @foreach($judokas->groupBy('club_id') as $clubId => $clubJudokas)
                @php $clubNaam = $clubJudokas->first()->club?->naam ?? 'Onbekend'; @endphp
                <tr class="bg-blue-100">
                    <td colspan="8" class="px-3 py-2 font-bold">{{ $clubNaam }} ({{ $clubJudokas->count() }})</td>
                </tr>
                @foreach($clubJudokas as $idx => $judoka)
                <tr class="{{ $idx % 2 == 0 ? 'bg-white' : 'bg-gray-50' }} hover:bg-gray-100">
                    <td class="px-3 py-2">{{ $loop->parent->iteration }}.{{ $idx + 1 }}</td>
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
            @endforeach
        @endif
    </tbody>
</table>

<div class="mt-6 text-sm text-gray-600 flex gap-6">
    <p><strong>Totaal:</strong> {{ $judokas->count() }} judoka's</p>
    <p><strong>Gewogen:</strong> {{ $judokas->whereNotNull('gewicht_gewogen')->count() }}</p>
    <p><strong>Nog te wegen:</strong> {{ $judokas->whereNull('gewicht_gewogen')->count() }}</p>
</div>
@endsection
