@extends('layouts.app')

@section('title', $judoka->naam)

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">{{ $judoka->naam }}</h1>
                <p class="text-gray-600">{{ $judoka->club?->naam ?? __('Geen club') }}</p>
            </div>
            <div class="text-right">
                @if($judoka->aanwezigheid === 'aanwezig')
                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full">{{ __('Aanwezig') }}</span>
                @elseif($judoka->aanwezigheid === 'afwezig')
                <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full">{{ __('Afwezig') }}</span>
                @else
                <span class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full">{{ __('Onbekend') }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">{{ __('Gegevens') }}</h2>
            <table class="w-full">
                <tbody>
                    <tr>
                        <td class="text-gray-600 py-1.5">{{ __('Geboortejaar') }}</td>
                        <td class="font-medium text-right py-1.5">{{ $judoka->geboortejaar }}</td>
                    </tr>
                    <tr>
                        <td class="text-gray-600 py-1.5">{{ __('Leeftijd') }}</td>
                        <td class="font-medium text-right py-1.5">{{ __(':leeftijd jaar', ['leeftijd' => $judoka->leeftijd]) }}</td>
                    </tr>
                    <tr>
                        <td class="text-gray-600 py-1.5">{{ __('Geslacht') }}</td>
                        <td class="font-medium text-right py-1.5">{{ $judoka->geslacht === 'M' ? __('Man') : __('Vrouw') }}</td>
                    </tr>
                    <tr>
                        <td class="text-gray-600 py-1.5">{{ __('Band') }}</td>
                        <td class="font-medium text-right py-1.5">{{ \App\Enums\Band::toKleur($judoka->band) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">{{ __('Classificatie') }}</h2>
            <table class="w-full">
                <tbody>
                    <tr>
                        <td class="text-gray-600 py-1.5">{{ __('Leeftijdsklasse') }}</td>
                        <td class="font-medium text-right py-1.5">{{ $judoka->leeftijdsklasse }}</td>
                    </tr>
                    <tr>
                        <td class="text-gray-600 py-1.5">{{ __('Gewichtsklasse') }}</td>
                        <td class="font-medium text-right py-1.5">
                            @if($judoka->gewichtsklasse === 'Variabel')
                                {{ $judoka->gewicht ? $judoka->gewicht . ' kg' : __('Variabel') }}
                            @else
                                {{ $judoka->gewichtsklasse }} kg
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="text-gray-600 py-1.5">{{ __('Opgegeven gewicht') }}</td>
                        <td class="font-medium text-right py-1.5">{{ $judoka->gewicht ? $judoka->gewicht . ' kg' : '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-gray-600 py-1.5">{{ __('Gewogen gewicht') }}</td>
                        <td class="font-medium text-right py-1.5">{{ $judoka->gewicht_gewogen ? $judoka->gewicht_gewogen . ' kg' : '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    @if($judoka->poules->count() > 0)
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-bold mb-4">{{ __('Poules') }}</h2>
        @foreach($judoka->poules as $poule)
        <div class="border rounded p-4 mb-2">
            <div class="font-medium">{{ $poule->getDisplayTitel() }}</div>
            <div class="text-gray-600 text-sm">
                {{ __('Blok') }} {{ $poule->blok?->nummer ?? '?' }} - {{ __('Mat') }} {{ $poule->mat?->nummer ?? '?' }}
            </div>
        </div>
        @endforeach
    </div>
    @endif

    <div class="flex justify-between items-center">
        @php
            $terugUrl = route('toernooi.judoka.index', $toernooi->routeParams());
            if (request('filter') === 'onvolledig') {
                $terugUrl .= '#onvolledig';
            }
        @endphp
        <a href="{{ $terugUrl }}" class="text-blue-600 hover:text-blue-800"
           @if(request('filter') === 'onvolledig') onclick="sessionStorage.setItem('toonOnvolledig', 'true')" @endif>
            &larr; {{ __('Terug naar lijst') }}
        </a>
        <div class="flex gap-2">
            @if($judoka->qr_code)
            <a href="{{ route('weegkaart.show', $judoka->qr_code) }}?from_portal" target="_blank" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                {{ __('Weegkaart') }}
            </a>
            @endif
            <a href="{{ route('toernooi.judoka.edit', $toernooi->routeParamsWith(['judoka' => $judoka])) }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                {{ __('Bewerken') }}
            </a>
        </div>
    </div>
</div>
@endsection
