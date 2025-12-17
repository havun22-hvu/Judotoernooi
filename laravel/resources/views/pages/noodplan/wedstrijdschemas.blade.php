@extends('layouts.print')

@section('title', $titel)

@section('content')
@foreach($poules->groupBy('blok_id') as $blokId => $blokPoules)
    @php $blokNummer = $blokPoules->first()->blok?->nummer ?? '?'; @endphp

    @if(!$blok)
    <h2 class="text-xl font-bold mb-4 mt-6 first:mt-0 bg-gray-200 p-2">Blok {{ $blokNummer }}</h2>
    @endif

    @foreach($blokPoules as $poule)
    <div class="mb-8 no-break {{ !$loop->last ? 'page-break' : '' }}">
        <!-- Poule header -->
        <div class="bg-gray-100 p-3 mb-3">
            <div class="flex justify-between items-center">
                <div>
                    <span class="font-bold text-lg">
                        {{ $poule->categorie?->naam ?? 'Poule' }}
                        @if($poule->naam) - {{ $poule->naam }} @endif
                    </span>
                    @if($poule->mat_nummer)
                    <span class="ml-2 text-sm bg-blue-600 text-white px-2 py-1 rounded">Mat {{ $poule->mat_nummer }}</span>
                    @endif
                </div>
                <span class="text-sm text-gray-600">
                    {{ $poule->wedstrijden->where('uitslag_wit', '!=', null)->count() }}/{{ $poule->wedstrijden->count() }} wedstrijden
                </span>
            </div>
        </div>

        <!-- Deelnemers -->
        <div class="mb-3">
            <table class="w-full text-xs">
                <tr class="bg-gray-50">
                    @foreach($poule->judokas as $idx => $judoka)
                    <td class="p-1 border">
                        <span class="font-bold">{{ $idx + 1 }}.</span>
                        {{ $judoka->naam }}
                        <span class="text-gray-500">({{ $judoka->club?->naam ?? '?' }})</span>
                    </td>
                    @endforeach
                </tr>
            </table>
        </div>

        <!-- Wedstrijden -->
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-200">
                    <th class="p-2 text-center w-8">#</th>
                    <th class="p-2 text-left">Wit</th>
                    <th class="p-2 text-center w-16">Score</th>
                    <th class="p-2 text-left">Blauw</th>
                    <th class="p-2 text-center w-16">Score</th>
                    <th class="p-2 text-center w-20">Winnaar</th>
                </tr>
            </thead>
            <tbody>
                @foreach($poule->wedstrijden->sortBy('volgorde') as $idx => $wedstrijd)
                @php
                    $witJudoka = $poule->judokas->firstWhere('id', $wedstrijd->wit_judoka_id);
                    $blauwJudoka = $poule->judokas->firstWhere('id', $wedstrijd->blauw_judoka_id);
                    $heeftUitslag = $wedstrijd->uitslag_wit !== null;
                @endphp
                <tr class="{{ $idx % 2 == 0 ? 'bg-white' : 'bg-gray-50' }} {{ $heeftUitslag ? '' : 'text-gray-400' }}">
                    <td class="p-2 text-center font-bold">{{ $idx + 1 }}</td>
                    <td class="p-2 {{ $wedstrijd->winnaar_id == $wedstrijd->wit_judoka_id ? 'font-bold text-green-700' : '' }}">
                        {{ $witJudoka?->naam ?? '?' }}
                    </td>
                    <td class="p-2 text-center font-mono">
                        @if($heeftUitslag)
                            {{ $wedstrijd->uitslag_wit }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="p-2 {{ $wedstrijd->winnaar_id == $wedstrijd->blauw_judoka_id ? 'font-bold text-green-700' : '' }}">
                        {{ $blauwJudoka?->naam ?? '?' }}
                    </td>
                    <td class="p-2 text-center font-mono">
                        @if($heeftUitslag)
                            {{ $wedstrijd->uitslag_blauw }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="p-2 text-center">
                        @if($wedstrijd->winnaar_id)
                            @php $winnaar = $poule->judokas->firstWhere('id', $wedstrijd->winnaar_id); @endphp
                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">
                                {{ $winnaar?->naam ?? '?' }}
                            </span>
                        @elseif($heeftUitslag)
                            <span class="text-gray-400">Gelijk</span>
                        @else
                            -
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Klassement -->
        @if($poule->wedstrijden->where('uitslag_wit', '!=', null)->count() > 0)
        <div class="mt-4 p-3 bg-gray-50 rounded">
            <h4 class="font-bold text-sm mb-2">Voorlopig Klassement</h4>
            <table class="w-full text-xs">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="p-1 text-center w-8">#</th>
                        <th class="p-1 text-left">Naam</th>
                        <th class="p-1 text-center w-12">W</th>
                        <th class="p-1 text-center w-12">V</th>
                        <th class="p-1 text-center w-12">WP</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        // Bereken klassement
                        $klassement = $poule->judokas->map(function($judoka) use ($poule) {
                            $gewonnen = $poule->wedstrijden->where('winnaar_id', $judoka->id)->count();
                            $verloren = $poule->wedstrijden
                                ->filter(fn($w) => $w->winnaar_id && $w->winnaar_id != $judoka->id && ($w->wit_judoka_id == $judoka->id || $w->blauw_judoka_id == $judoka->id))
                                ->count();
                            $wp = $poule->wedstrijden
                                ->filter(fn($w) => $w->wit_judoka_id == $judoka->id)
                                ->sum('uitslag_wit')
                                + $poule->wedstrijden
                                ->filter(fn($w) => $w->blauw_judoka_id == $judoka->id)
                                ->sum('uitslag_blauw');
                            return ['judoka' => $judoka, 'gewonnen' => $gewonnen, 'verloren' => $verloren, 'wp' => $wp];
                        })->sortByDesc('gewonnen')->sortByDesc('wp')->values();
                    @endphp
                    @foreach($klassement as $idx => $item)
                    <tr>
                        <td class="p-1 text-center font-bold">{{ $idx + 1 }}</td>
                        <td class="p-1">{{ $item['judoka']->naam }}</td>
                        <td class="p-1 text-center text-green-600">{{ $item['gewonnen'] }}</td>
                        <td class="p-1 text-center text-red-600">{{ $item['verloren'] }}</td>
                        <td class="p-1 text-center">{{ $item['wp'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
    @endforeach
@endforeach

@if($poules->isEmpty())
<p class="text-gray-500 text-center py-8">Geen poules gevonden</p>
@endif
@endsection
