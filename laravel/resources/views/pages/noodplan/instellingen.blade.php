@extends('layouts.print')

@section('title', 'Instellingen Samenvatting')

@section('content')
<div class="space-y-6">
    <!-- Algemene info -->
    <div class="p-4 bg-gray-50 rounded">
        <h2 class="text-lg font-bold mb-3">Toernooi Informatie</h2>
        <table class="w-full text-sm">
            <tr>
                <td class="py-1 text-gray-500 w-40">Naam:</td>
                <td class="py-1 font-medium">{{ $toernooi->naam }}</td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">Datum:</td>
                <td class="py-1 font-medium">{{ $toernooi->datum->format('d-m-Y') }}</td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">Locatie:</td>
                <td class="py-1 font-medium">{{ $toernooi->locatie ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">Organisatie:</td>
                <td class="py-1 font-medium">{{ $toernooi->organisatie ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">Aantal matten:</td>
                <td class="py-1 font-medium">{{ $toernooi->aantal_matten ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">Aantal blokken:</td>
                <td class="py-1 font-medium">{{ $toernooi->aantal_blokken ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <!-- Bloktijden -->
    <div class="p-4 bg-gray-50 rounded">
        <h2 class="text-lg font-bold mb-3">Bloktijden</h2>
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-200">
                    <th class="p-2 text-left">Blok</th>
                    <th class="p-2 text-center">Weging Start</th>
                    <th class="p-2 text-center">Weging Einde</th>
                    <th class="p-2 text-center">Start Wedstrijden</th>
                </tr>
            </thead>
            <tbody>
                @foreach($blokken as $blok)
                <tr class="{{ $loop->index % 2 == 0 ? 'bg-white' : 'bg-gray-50' }}">
                    <td class="p-2 font-bold">Blok {{ $blok->nummer }}</td>
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
        <h2 class="text-lg font-bold mb-3">Weging</h2>
        <table class="w-full text-sm">
            <tr>
                <td class="py-1 text-gray-500 w-40">Weging verplicht:</td>
                <td class="py-1 font-medium">{{ $toernooi->weging_verplicht ? 'Ja' : 'Nee' }}</td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">Gewichtstolerantie:</td>
                <td class="py-1 font-medium">{{ $toernooi->gewicht_tolerantie ?? 0.5 }} kg</td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">Max wegingen:</td>
                <td class="py-1 font-medium">{{ $toernooi->max_wegingen ?? 'Onbeperkt' }}</td>
            </tr>
        </table>
    </div>

    <!-- Belangrijke contacten -->
    <div class="p-4 bg-yellow-50 border border-yellow-200 rounded">
        <h2 class="text-lg font-bold mb-3 text-yellow-800">Noodcontacten</h2>
        <p class="text-sm text-yellow-700">
            Bij technische problemen: contacteer de organisator of sitebeheerder.
        </p>
        <p class="text-sm text-yellow-700 mt-2">
            Gebruik de contactlijst voor coach telefoonnummers.
        </p>
    </div>
</div>
@endsection
