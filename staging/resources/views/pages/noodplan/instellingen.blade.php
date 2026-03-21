@extends('layouts.print')

@section('title', __('Instellingen Samenvatting'))

@section('content')
<div class="space-y-6">
    <!-- Algemene info -->
    <div class="p-4 bg-gray-50 rounded">
        <h2 class="text-lg font-bold mb-3">{{ __('Toernooi Informatie') }}</h2>
        <table class="w-full text-sm">
            <tr>
                <td class="py-1 text-gray-500 w-40">{{ __('Naam') }}:</td>
                <td class="py-1 font-medium">{{ $toernooi->naam }}</td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">{{ __('Datum') }}:</td>
                <td class="py-1 font-medium">{{ $toernooi->datum->format('d-m-Y') }}</td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">{{ __('Locatie') }}:</td>
                <td class="py-1 font-medium">{{ $toernooi->locatie ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">{{ __('Organisatie') }}:</td>
                <td class="py-1 font-medium">{{ $toernooi->organisatie ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">{{ __('Aantal matten') }}:</td>
                <td class="py-1 font-medium">{{ $toernooi->aantal_matten ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">{{ __('Aantal blokken') }}:</td>
                <td class="py-1 font-medium">{{ $toernooi->aantal_blokken ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <!-- Bloktijden -->
    <div class="p-4 bg-gray-50 rounded">
        <h2 class="text-lg font-bold mb-3">{{ __('Bloktijden') }}</h2>
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-200">
                    <th class="p-2 text-left">{{ __('Blok') }}</th>
                    <th class="p-2 text-center">{{ __('Weging Start') }}</th>
                    <th class="p-2 text-center">{{ __('Weging Einde') }}</th>
                    <th class="p-2 text-center">{{ __('Start Wedstrijden') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($blokken as $blok)
                <tr class="{{ $loop->index % 2 == 0 ? 'bg-white' : 'bg-gray-50' }}">
                    <td class="p-2 font-bold">{{ __('Blok') }} {{ $blok->nummer }}</td>
                    <td class="p-2 text-center">{{ $blok->weging_start?->format('H:i') ?? '-' }}</td>
                    <td class="p-2 text-center">{{ $blok->weging_einde?->format('H:i') ?? '-' }}</td>
                    <td class="p-2 text-center font-medium">{{ $blok->starttijd?->format('H:i') ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Weging instellingen -->
    <div class="p-4 bg-gray-50 rounded">
        <h2 class="text-lg font-bold mb-3">{{ __('Weging') }}</h2>
        <table class="w-full text-sm">
            <tr>
                <td class="py-1 text-gray-500 w-40">{{ __('Weging verplicht') }}:</td>
                <td class="py-1 font-medium">{{ $toernooi->weging_verplicht ? __('Ja') : __('Nee') }}</td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">{{ __('Gewichtstolerantie') }}:</td>
                <td class="py-1 font-medium">{{ $toernooi->gewicht_tolerantie ?? 0.5 }} kg</td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">{{ __('Max wegingen') }}:</td>
                <td class="py-1 font-medium">{{ $toernooi->max_wegingen ?? __('Onbeperkt') }}</td>
            </tr>
        </table>
    </div>

    <!-- Belangrijke contacten -->
    <div class="p-4 bg-yellow-50 border border-yellow-200 rounded">
        <h2 class="text-lg font-bold mb-3 text-yellow-800">{{ __('Noodcontacten') }}</h2>
        <p class="text-sm text-yellow-700">
            {{ __('Bij technische problemen: contacteer de organisator of sitebeheerder.') }}
        </p>
        <p class="text-sm text-yellow-700 mt-2">
            {{ __('Gebruik de contactlijst voor coach telefoonnummers.') }}
        </p>
    </div>
</div>
@endsection
