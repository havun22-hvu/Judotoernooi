@extends('layouts.app')

@section('title', 'Geen Actief Toernooi')

@section('content')
<div class="text-center py-16">
    <div class="text-6xl mb-4">🥋</div>
    <h1 class="text-3xl font-bold text-gray-800 mb-4">Geen Actief Toernooi</h1>
    <p class="text-gray-600 mb-8">Er is momenteel geen actief toernooi. Maak een nieuw toernooi aan om te beginnen.</p>

    <a href="{{ route('toernooi.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg text-lg">
        Nieuw Toernooi Aanmaken
    </a>
</div>
@endsection
