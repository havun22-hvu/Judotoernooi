@extends('layouts.print')

@section('title', __('Poule Schema') . ' - ' . $poule->leeftijdsklasse . ' ' . $poule->gewichtsklasse)

@push('styles')
<style>
    @media print {
        @page {
            size: A4 landscape;
            margin: 0.5cm;
        }
        .print-container {
            max-width: none !important;
            padding: 0 !important;
        }
    }
    @media screen {
        .print-container {
            max-width: none !important;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
    }
</style>
@endpush

@section('content')
<div class="mb-6">
    <!-- Poule header -->
    <div class="bg-yellow-100 border-2 border-yellow-400 p-4 mb-4">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-bold">
                    {{ $poule->leeftijdsklasse }} {{ $poule->gewichtsklasse }}
                    @if($poule->naam) - {{ $poule->naam }} @endif
                </h2>
                <p class="text-gray-600">
                    @if($poule->mat_nummer)
                    <span class="font-bold">{{ __('Mat') }} {{ $poule->mat_nummer }}</span> •
                    @endif
                    {{ $poule->judokas->count() }} {{ __("judoka's") }} •
                    {{ $poule->wedstrijden->count() }} {{ __('wedstrijden') }}
                </p>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-500">{{ __('Momentopname') }}</p>
                <p class="font-mono font-bold">{{ now()->format('H:i:s') }}</p>
            </div>
        </div>
    </div>

    <!-- Deelnemers -->
    <div class="mb-4 p-3 bg-gray-50 rounded">
        <h3 class="font-bold mb-2">{{ __('Deelnemers') }}</h3>
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-200">
                    <th class="p-2 text-center w-8">#</th>
                    <th class="p-2 text-left">{{ __('Naam') }}</th>
                    <th class="p-2 text-left">{{ __('Club') }}</th>
                    <th class="p-2 text-center">{{ __('Band') }}</th>
                    <th class="p-2 text-center">{{ __('Gewicht') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($poule->judokas as $idx => $judoka)
                <tr class="{{ $idx % 2 == 0 ? 'bg-white' : 'bg-gray-50' }}">
                    <td class="p-2 text-center font-bold">{{ $idx + 1 }}</td>
                    <td class="p-2 font-medium">{{ $judoka->naam }}</td>
                    <td class="p-2">{{ $judoka->club?->naam ?? '-' }}</td>
                    <td class="p-2 text-center">{{ $judoka->band_enum?->label() ?? '-' }}</td>
                    <td class="p-2 text-center">
                        {{ $judoka->gewicht_gewogen ? number_format($judoka->gewicht_gewogen, 1) . ' kg' : '-' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Wedstrijden -->
    <div class="mb-4">
        <h3 class="font-bold mb-2">{{ __('Wedstrijden') }}</h3>
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-200">
                    <th class="p-2 text-center w-8">#</th>
                    <th class="p-2 text-left">{{ __('Wit') }}</th>
                    <th class="p-2 text-center w-16">{{ __('Score') }}</th>
                    <th class="p-2 text-left">{{ __('Blauw') }}</th>
                    <th class="p-2 text-center w-16">{{ __('Score') }}</th>
                    <th class="p-2 text-center w-24">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($poule->wedstrijden->sortBy('volgorde') as $idx => $wedstrijd)
                @php
                    $witJudoka = $poule->judokas->firstWhere('id', $wedstrijd->wit_judoka_id);
                    $blauwJudoka = $poule->judokas->firstWhere('id', $wedstrijd->blauw_judoka_id);
                    $heeftUitslag = $wedstrijd->uitslag_wit !== null;
                    $isActief = $wedstrijd->is_huidige;
                @endphp
                <tr class="{{ $isActief ? 'bg-yellow-100 border-2 border-yellow-400' : ($idx % 2 == 0 ? 'bg-white' : 'bg-gray-50') }}">
                    <td class="p-2 text-center font-bold">
                        {{ $idx + 1 }}
                        @if($isActief)
                        <span class="text-yellow-600">*</span>
                        @endif
                    </td>
                    <td class="p-2 {{ $wedstrijd->winnaar_id == $wedstrijd->wit_judoka_id ? 'font-bold text-green-700' : '' }}">
                        {{ $witJudoka?->naam ?? '?' }}
                    </td>
                    <td class="p-2 text-center font-mono font-bold">
                        @if($heeftUitslag)
                            {{ $wedstrijd->uitslag_wit }}
                        @elseif($isActief)
                            <span class="text-yellow-600">{{ __('BEZIG') }}</span>
                        @else
                            -
                        @endif
                    </td>
                    <td class="p-2 {{ $wedstrijd->winnaar_id == $wedstrijd->blauw_judoka_id ? 'font-bold text-green-700' : '' }}">
                        {{ $blauwJudoka?->naam ?? '?' }}
                    </td>
                    <td class="p-2 text-center font-mono font-bold">
                        @if($heeftUitslag)
                            {{ $wedstrijd->uitslag_blauw }}
                        @elseif($isActief)
                            <span class="text-yellow-600">{{ __('BEZIG') }}</span>
                        @else
                            -
                        @endif
                    </td>
                    <td class="p-2 text-center">
                        @if($heeftUitslag)
                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">{{ __('Klaar') }}</span>
                        @elseif($isActief)
                            <span class="bg-yellow-200 text-yellow-800 px-2 py-1 rounded text-xs font-bold">{{ __('ACTIEF') }}</span>
                        @else
                            <span class="text-gray-400 text-xs">{{ __('Wachtend') }}</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Klassement -->
    <div class="p-3 bg-gray-50 rounded">
        <h3 class="font-bold mb-2">{{ __('Huidige Stand') }}</h3>
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-200">
                    <th class="p-2 text-center w-8">#</th>
                    <th class="p-2 text-left">{{ __('Naam') }}</th>
                    <th class="p-2 text-center w-16">{{ __('Gewonnen') }}</th>
                    <th class="p-2 text-center w-16">{{ __('Verloren') }}</th>
                    <th class="p-2 text-center w-16">{{ __('WP') }}</th>
                </tr>
            </thead>
            <tbody>
                @php
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
                <tr class="{{ $idx % 2 == 0 ? 'bg-white' : 'bg-gray-50' }}">
                    <td class="p-2 text-center font-bold">{{ $idx + 1 }}</td>
                    <td class="p-2 font-medium">{{ $item['judoka']->naam }}</td>
                    <td class="p-2 text-center text-green-600 font-bold">{{ $item['gewonnen'] }}</td>
                    <td class="p-2 text-center text-red-600">{{ $item['verloren'] }}</td>
                    <td class="p-2 text-center">{{ $item['wp'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- Ruimte voor handmatige notities -->
<div class="mt-6 p-4 border-2 border-dashed border-gray-300 rounded min-h-32">
    <p class="text-sm text-gray-400 mb-2">{{ __('Notities') }}:</p>
</div>
@endsection
