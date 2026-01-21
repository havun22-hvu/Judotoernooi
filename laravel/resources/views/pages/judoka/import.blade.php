@extends('layouts.app')

@section('title', 'Importeer Deelnemers')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-8">Deelnemers Importeren</h1>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-bold mb-4">Bestandsformaat</h2>
        <p class="text-gray-600 mb-4">Upload een CSV of Excel bestand met de volgende kolommen:</p>
        <ul class="list-disc list-inside text-gray-600 space-y-1">
            <li><strong>Naam</strong> (verplicht)</li>
            <li><strong>Geboortejaar</strong> (verplicht)</li>
            <li><strong>Geslacht</strong> (M of V)</li>
            <li><strong>Band</strong> (wit, geel, oranje, groen, blauw, bruin, zwart)</li>
            <li><strong>Club</strong></li>
            <li><strong>Gewicht</strong></li>
        </ul>
    </div>

    <form action="{{ route('toernooi.judoka.import.store', $toernooi) }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow p-6" data-loading="Bestand importeren...">
        @csrf

        <div class="mb-6">
            <label for="bestand" class="block text-gray-700 font-bold mb-2">Selecteer Bestand *</label>
            <input type="file" name="bestand" id="bestand" accept=".csv,.xlsx,.xls"
                   class="w-full border rounded px-3 py-2 @error('bestand') border-red-500 @enderror" required>
            @error('bestand')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
            <p class="text-gray-500 text-sm mt-1">Ondersteunde formaten: CSV, XLSX, XLS</p>
        </div>

        <div class="flex justify-end space-x-4">
            <a href="{{ route('toernooi.judoka.index', $toernooi) }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                Annuleren
            </a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Importeren
            </button>
        </div>
    </form>
</div>
@endsection
