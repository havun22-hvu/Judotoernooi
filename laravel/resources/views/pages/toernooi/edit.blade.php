@extends('layouts.app')

@section('title', 'Bewerk Toernooi')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-8">Toernooi Bewerken</h1>

    <form action="{{ route('toernooi.update', $toernooi) }}" method="POST" class="bg-white rounded-lg shadow p-6">
        @csrf
        @method('PUT')

        <div class="mb-4">
            <label for="naam" class="block text-gray-700 font-bold mb-2">Naam *</label>
            <input type="text" name="naam" id="naam" value="{{ old('naam', $toernooi->naam) }}"
                   class="w-full border rounded px-3 py-2 @error('naam') border-red-500 @enderror" required>
            @error('naam')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="datum" class="block text-gray-700 font-bold mb-2">Datum *</label>
            <input type="date" name="datum" id="datum" value="{{ old('datum', $toernooi->datum->format('Y-m-d')) }}"
                   class="w-full border rounded px-3 py-2 @error('datum') border-red-500 @enderror" required>
            @error('datum')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="organisatie" class="block text-gray-700 font-bold mb-2">Organisatie</label>
            <input type="text" name="organisatie" id="organisatie" value="{{ old('organisatie', $toernooi->organisatie) }}"
                   class="w-full border rounded px-3 py-2">
        </div>

        <div class="mb-4">
            <label for="locatie" class="block text-gray-700 font-bold mb-2">Locatie</label>
            <input type="text" name="locatie" id="locatie" value="{{ old('locatie', $toernooi->locatie) }}"
                   class="w-full border rounded px-3 py-2">
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label for="aantal_matten" class="block text-gray-700 font-bold mb-2">Aantal Matten</label>
                <input type="number" name="aantal_matten" id="aantal_matten" value="{{ old('aantal_matten', $toernooi->aantal_matten) }}"
                       class="w-full border rounded px-3 py-2" min="1" max="20">
            </div>
            <div>
                <label for="aantal_blokken" class="block text-gray-700 font-bold mb-2">Aantal Blokken</label>
                <input type="number" name="aantal_blokken" id="aantal_blokken" value="{{ old('aantal_blokken', $toernooi->aantal_blokken) }}"
                       class="w-full border rounded px-3 py-2" min="1" max="12">
            </div>
        </div>

        <div class="mb-6">
            <label for="gewicht_tolerantie" class="block text-gray-700 font-bold mb-2">Gewicht Tolerantie (kg)</label>
            <input type="number" name="gewicht_tolerantie" id="gewicht_tolerantie" value="{{ old('gewicht_tolerantie', $toernooi->gewicht_tolerantie) }}"
                   class="w-full border rounded px-3 py-2" min="0" max="5" step="0.1">
        </div>

        <div class="flex justify-end space-x-4">
            <a href="{{ route('toernooi.show', $toernooi) }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                Annuleren
            </a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Opslaan
            </button>
        </div>
    </form>
</div>
@endsection
