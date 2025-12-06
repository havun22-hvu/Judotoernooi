@extends('layouts.app')

@section('title', 'Instellingen')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Toernooi Instellingen</h1>
        <a href="{{ route('toernooi.show', $toernooi) }}" class="text-blue-600 hover:text-blue-800">
            &larr; Terug naar Dashboard
        </a>
    </div>

    <form action="{{ route('toernooi.update', $toernooi) }}" method="POST">
        @csrf
        @method('PUT')

        <!-- ALGEMEEN -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b">Algemeen</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="naam" class="block text-gray-700 font-medium mb-1">Naam Toernooi *</label>
                    <input type="text" name="naam" id="naam" value="{{ old('naam', $toernooi->naam) }}"
                           class="w-full border rounded px-3 py-2 @error('naam') border-red-500 @enderror" required>
                    @error('naam')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="organisatie" class="block text-gray-700 font-medium mb-1">Organisatie</label>
                    <input type="text" name="organisatie" id="organisatie" value="{{ old('organisatie', $toernooi->organisatie) }}"
                           placeholder="Naam van de organiserende club" class="w-full border rounded px-3 py-2">
                </div>

                <div>
                    <label for="datum" class="block text-gray-700 font-medium mb-1">Datum Toernooi *</label>
                    <input type="date" name="datum" id="datum" value="{{ old('datum', $toernooi->datum->format('Y-m-d')) }}"
                           class="w-full border rounded px-3 py-2 @error('datum') border-red-500 @enderror" required>
                    @error('datum')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="locatie" class="block text-gray-700 font-medium mb-1">Locatie</label>
                    <input type="text" name="locatie" id="locatie" value="{{ old('locatie', $toernooi->locatie) }}"
                           placeholder="Adres of naam sporthal" class="w-full border rounded px-3 py-2">
                </div>
            </div>
        </div>

        <!-- INSCHRIJVING -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b">Inschrijving</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="inschrijving_deadline" class="block text-gray-700 font-medium mb-1">Inschrijving Deadline</label>
                    <input type="date" name="inschrijving_deadline" id="inschrijving_deadline"
                           value="{{ old('inschrijving_deadline', $toernooi->inschrijving_deadline?->format('Y-m-d')) }}"
                           class="w-full border rounded px-3 py-2">
                    <p class="text-gray-500 text-sm mt-1">Tot wanneer kunnen clubs judoka's opgeven?</p>
                </div>

                <div>
                    <label for="max_judokas" class="block text-gray-700 font-medium mb-1">Maximum Aantal Deelnemers</label>
                    <input type="number" name="max_judokas" id="max_judokas"
                           value="{{ old('max_judokas', $toernooi->max_judokas) }}"
                           placeholder="Leeg = onbeperkt" class="w-full border rounded px-3 py-2" min="1">
                    <p class="text-gray-500 text-sm mt-1">Coaches krijgen waarschuwing bij 80%</p>
                </div>
            </div>
        </div>

        <!-- MATTEN & BLOKKEN -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b">Matten & Tijdsblokken</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="aantal_matten" class="block text-gray-700 font-medium mb-1">Aantal Matten</label>
                    <input type="number" name="aantal_matten" id="aantal_matten"
                           value="{{ old('aantal_matten', $toernooi->aantal_matten) }}"
                           class="w-full border rounded px-3 py-2" min="1" max="20">
                    <p class="text-gray-500 text-sm mt-1">Hoeveel wedstrijdmatten zijn beschikbaar?</p>
                </div>

                <div>
                    <label for="aantal_blokken" class="block text-gray-700 font-medium mb-1">Aantal Tijdsblokken</label>
                    <input type="number" name="aantal_blokken" id="aantal_blokken"
                           value="{{ old('aantal_blokken', $toernooi->aantal_blokken) }}"
                           class="w-full border rounded px-3 py-2" min="1" max="12">
                    <p class="text-gray-500 text-sm mt-1">In hoeveel tijdsblokken wordt het toernooi verdeeld?</p>
                </div>
            </div>
        </div>

        <!-- POULE INSTELLINGEN -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b">Poule Instellingen</h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="min_judokas_poule" class="block text-gray-700 font-medium mb-1">Minimum per Poule</label>
                    <input type="number" name="min_judokas_poule" id="min_judokas_poule"
                           value="{{ old('min_judokas_poule', $toernooi->min_judokas_poule) }}"
                           class="w-full border rounded px-3 py-2" min="2" max="10">
                </div>

                <div>
                    <label for="optimal_judokas_poule" class="block text-gray-700 font-medium mb-1">Optimaal per Poule</label>
                    <input type="number" name="optimal_judokas_poule" id="optimal_judokas_poule"
                           value="{{ old('optimal_judokas_poule', $toernooi->optimal_judokas_poule) }}"
                           class="w-full border rounded px-3 py-2" min="3" max="10">
                </div>

                <div>
                    <label for="max_judokas_poule" class="block text-gray-700 font-medium mb-1">Maximum per Poule</label>
                    <input type="number" name="max_judokas_poule" id="max_judokas_poule"
                           value="{{ old('max_judokas_poule', $toernooi->max_judokas_poule) }}"
                           class="w-full border rounded px-3 py-2" min="4" max="12">
                </div>
            </div>

            <div class="mt-4 p-3 bg-blue-50 rounded text-sm text-blue-800">
                <strong>Tip:</strong> Bij een poule van 3 spelen alle judoka's 2 wedstrijden. Bij 4 judoka's zijn dat 3 wedstrijden,
                bij 5 judoka's 4 wedstrijden, etc.
            </div>
        </div>

        <!-- GEWICHT -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b">Gewicht</h2>

            <div class="max-w-md">
                <label for="gewicht_tolerantie" class="block text-gray-700 font-medium mb-1">Gewichtstolerantie (kg)</label>
                <input type="number" name="gewicht_tolerantie" id="gewicht_tolerantie"
                       value="{{ old('gewicht_tolerantie', $toernooi->gewicht_tolerantie) }}"
                       class="w-full border rounded px-3 py-2" min="0" max="5" step="0.1">
                <p class="text-gray-500 text-sm mt-1">
                    Hoeveel kg mag een judoka boven de gewichtsklasse-limiet wegen?
                    Standaard: 0.5 kg. Gebruik 0.3 voor strikter beleid.
                </p>
            </div>
        </div>

        <!-- ACTIES -->
        <div class="flex justify-between items-center">
            <a href="{{ route('toernooi.show', $toernooi) }}" class="text-gray-600 hover:text-gray-800">
                Annuleren
            </a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg">
                Instellingen Opslaan
            </button>
        </div>
    </form>

    <!-- WACHTWOORDEN (apart formulier) -->
    <div class="bg-white rounded-lg shadow p-6 mt-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b">Wachtwoorden</h2>
        <p class="text-gray-600 mb-4">
            Stel wachtwoorden in voor de verschillende rollen. De login pagina is te vinden op:
            <a href="{{ route('toernooi.auth.login', $toernooi) }}" class="text-blue-600 hover:underline" target="_blank">
                {{ route('toernooi.auth.login', $toernooi) }}
            </a>
        </p>

        <form action="{{ route('toernooi.wachtwoorden', $toernooi) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-4 border rounded-lg">
                    <div class="flex items-center mb-2">
                        <span class="text-2xl mr-2">👑</span>
                        <div>
                            <h3 class="font-bold">Admin</h3>
                            <p class="text-sm text-gray-500">Volledig beheer</p>
                        </div>
                        @if($toernooi->heeftWachtwoord('admin'))
                        <span class="ml-auto text-green-600 text-sm">Ingesteld</span>
                        @endif
                    </div>
                    <input type="password" name="wachtwoord_admin" placeholder="Nieuw wachtwoord..."
                           class="w-full border rounded px-3 py-2 text-sm" autocomplete="new-password">
                </div>

                <div class="p-4 border rounded-lg">
                    <div class="flex items-center mb-2">
                        <span class="text-2xl mr-2">⚖️</span>
                        <div>
                            <h3 class="font-bold">Jury</h3>
                            <p class="text-sm text-gray-500">Hoofdtafel overzicht</p>
                        </div>
                        @if($toernooi->heeftWachtwoord('jury'))
                        <span class="ml-auto text-green-600 text-sm">Ingesteld</span>
                        @endif
                    </div>
                    <input type="password" name="wachtwoord_jury" placeholder="Nieuw wachtwoord..."
                           class="w-full border rounded px-3 py-2 text-sm" autocomplete="new-password">
                </div>

                <div class="p-4 border rounded-lg">
                    <div class="flex items-center mb-2">
                        <span class="text-2xl mr-2">⚖️</span>
                        <div>
                            <h3 class="font-bold">Weging</h3>
                            <p class="text-sm text-gray-500">Alleen weeglijst</p>
                        </div>
                        @if($toernooi->heeftWachtwoord('weging'))
                        <span class="ml-auto text-green-600 text-sm">Ingesteld</span>
                        @endif
                    </div>
                    <input type="password" name="wachtwoord_weging" placeholder="Nieuw wachtwoord..."
                           class="w-full border rounded px-3 py-2 text-sm" autocomplete="new-password">
                </div>

                <div class="p-4 border rounded-lg">
                    <div class="flex items-center mb-2">
                        <span class="text-2xl mr-2">🥋</span>
                        <div>
                            <h3 class="font-bold">Mat</h3>
                            <p class="text-sm text-gray-500">Wedstrijdschema per mat</p>
                        </div>
                        @if($toernooi->heeftWachtwoord('mat'))
                        <span class="ml-auto text-green-600 text-sm">Ingesteld</span>
                        @endif
                    </div>
                    <input type="password" name="wachtwoord_mat" placeholder="Nieuw wachtwoord..."
                           class="w-full border rounded px-3 py-2 text-sm" autocomplete="new-password">
                </div>
            </div>

            <div class="mt-4 p-3 bg-yellow-50 rounded text-sm text-yellow-800">
                <strong>Let op:</strong> Laat een veld leeg om het huidige wachtwoord te behouden.
                Wachtwoorden worden versleuteld opgeslagen.
            </div>

            <div class="mt-4 text-right">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg">
                    Wachtwoorden Opslaan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
