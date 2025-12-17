@extends('layouts.print')

@section('title', $titel)

@section('content')
@foreach($poules->groupBy('blok_id') as $blokId => $blokPoules)
    @php $blokNummer = $blokPoules->first()->blok?->nummer ?? '?'; @endphp

    @if(!$blok)
    <h2 class="text-xl font-bold mb-4 mt-6 first:mt-0">Blok {{ $blokNummer }}</h2>
    @endif

    @foreach($blokPoules as $index => $poule)
    @if($poule->judokas->count() > 0)
    <div class="mb-6 no-break {{ !$loop->last ? 'page-break' : '' }}">
        <div class="bg-blue-600 text-white p-3 mb-2">
            <div class="flex justify-between items-center">
                <div>
                    <span class="font-bold text-lg">
                        #{{ $poule->nummer }} {{ $poule->leeftijdsklasse }} / {{ $poule->gewichtsklasse }}
                        @if($poule->naam) - {{ $poule->naam }} @endif
                    </span>
                    @if($poule->blok || $poule->mat_nummer)
                    <span class="ml-2 text-sm text-blue-200">
                        @if($poule->blok)Blok {{ $poule->blok->nummer }}@endif
                        @if($poule->blok && $poule->mat_nummer) - @endif
                        @if($poule->mat_nummer)Mat {{ $poule->mat_nummer }}@endif
                    </span>
                    @endif
                </div>
                <div class="text-right text-sm">
                    <div>{{ $poule->judokas->count() }} judoka's</div>
                    <div class="text-blue-200">{{ $poule->wedstrijden->count() }} wedstrijden</div>
                </div>
            </div>
        </div>

        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-200">
                    <th class="p-2 text-left w-8">#</th>
                    <th class="p-2 text-left">Naam</th>
                    <th class="p-2 text-left">Club</th>
                    <th class="p-2 text-center w-16">Band</th>
                    <th class="p-2 text-center w-20">Klasse</th>
                </tr>
            </thead>
            <tbody>
                @foreach($poule->judokas as $idx => $judoka)
                <tr class="{{ $idx % 2 == 0 ? 'bg-white' : 'bg-gray-50' }}">
                    <td class="p-2 font-bold">{{ $idx + 1 }}</td>
                    <td class="p-2 font-medium">{{ $judoka->naam }}</td>
                    <td class="p-2">{{ $judoka->club?->naam ?? '-' }}</td>
                    <td class="p-2 text-center">{{ $judoka->band_enum?->label() ?? '-' }}</td>
                    <td class="p-2 text-center">{{ $judoka->gewichtsklasse ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
    @endforeach
@endforeach

@if($poules->isEmpty())
<p class="text-gray-500 text-center py-8">Geen poules gevonden</p>
@endif
@endsection
