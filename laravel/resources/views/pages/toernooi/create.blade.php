@extends('layouts.app')

@section('title', 'Nieuw Toernooi')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-8">Nieuw Toernooi Aanmaken</h1>

    <form action="{{ route('toernooi.store') }}" method="POST" class="bg-white rounded-lg shadow p-6">
        @csrf

        <div class="mb-4">
            <label for="naam" class="block text-gray-700 font-bold mb-2">Naam Toernooi *</label>
            <input type="text" name="naam" id="naam" value="{{ old('naam') }}" placeholder="Bijv. Judo Toernooi 2025"
                   class="w-full border rounded px-3 py-2 @error('naam') border-red-500 @enderror" required>
            @error('naam')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="datum" class="block text-gray-700 font-bold mb-2">Datum *</label>
            <input type="date" name="datum" id="datum" value="{{ old('datum', date('Y-m-d')) }}"
                   class="w-full border rounded px-3 py-2 @error('datum') border-red-500 @enderror" required>
            @error('datum')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="organisatie" class="block text-gray-700 font-bold mb-2">Organisatie</label>
            <input type="text" name="organisatie" id="organisatie" value="{{ old('organisatie') }}" placeholder="Naam van de organiserende club"
                   class="w-full border rounded px-3 py-2">
        </div>

        <div class="mb-4">
            <label for="locatie" class="block text-gray-700 font-bold mb-2">Locatie</label>
            <input type="text" name="locatie" id="locatie" value="{{ old('locatie') }}"
                   class="w-full border rounded px-3 py-2">
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label for="aantal_matten" class="block text-gray-700 font-bold mb-2">Aantal Matten</label>
                <input type="number" name="aantal_matten" id="aantal_matten" value="{{ old('aantal_matten', 7) }}"
                       class="w-full border rounded px-3 py-2" min="1" max="20">
            </div>
            <div>
                <label for="aantal_blokken" class="block text-gray-700 font-bold mb-2">Aantal Blokken</label>
                <input type="number" name="aantal_blokken" id="aantal_blokken" value="{{ old('aantal_blokken', 6) }}"
                       class="w-full border rounded px-3 py-2" min="1" max="12">
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4 mb-4">
            <div>
                <label for="min_judokas_poule" class="block text-gray-700 font-bold mb-2">Min/Poule</label>
                <input type="number" name="min_judokas_poule" id="min_judokas_poule" value="{{ old('min_judokas_poule', 3) }}"
                       class="w-full border rounded px-3 py-2" min="2" max="10">
            </div>
            <div>
                <label for="optimal_judokas_poule" class="block text-gray-700 font-bold mb-2">Optimaal/Poule</label>
                <input type="number" name="optimal_judokas_poule" id="optimal_judokas_poule" value="{{ old('optimal_judokas_poule', 5) }}"
                       class="w-full border rounded px-3 py-2" min="3" max="10">
            </div>
            <div>
                <label for="max_judokas_poule" class="block text-gray-700 font-bold mb-2">Max/Poule</label>
                <input type="number" name="max_judokas_poule" id="max_judokas_poule" value="{{ old('max_judokas_poule', 6) }}"
                       class="w-full border rounded px-3 py-2" min="4" max="12">
            </div>
        </div>

        <div class="mb-6">
            <label for="gewicht_tolerantie" class="block text-gray-700 font-bold mb-2">Gewicht Tolerantie (kg)</label>
            <input type="number" name="gewicht_tolerantie" id="gewicht_tolerantie" value="{{ old('gewicht_tolerantie', 0.5) }}"
                   class="w-full border rounded px-3 py-2" min="0" max="5" step="0.1">
        </div>

        <div class="flex justify-end space-x-4">
            <a href="{{ route('toernooi.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                Annuleren
            </a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Toernooi Aanmaken
            </button>
        </div>
    </form>
</div>
@endsection
