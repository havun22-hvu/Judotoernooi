@extends('layouts.app')

@section('title', 'Blokken')

@section('content')
<div class="flex justify-between items-center mb-8">
    <h1 class="text-3xl font-bold text-gray-800">Blokken</h1>
    <form action="{{ route('toernooi.blok.genereer-verdeling', $toernooi) }}" method="POST">
        @csrf
        <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded">
            📋 Herverdelen
        </button>
    </form>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @foreach($blokken as $blok)
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Blok {{ $blok->nummer }}</h2>
            @if($blok->weging_gesloten)
            <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full">Weging gesloten</span>
            @else
            <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">Weging open</span>
            @endif
        </div>

        <div class="text-gray-600 mb-4">
            <div>Poules: {{ $blok->poules->count() }}</div>
            <div>Wedstrijden: {{ $statistieken[$blok->nummer]['totaal_wedstrijden'] ?? 0 }}</div>
        </div>

        <div class="flex space-x-2">
            <a href="{{ route('toernooi.blok.show', [$toernooi, $blok]) }}" class="bg-blue-100 hover:bg-blue-200 text-blue-800 py-2 px-3 rounded text-sm">
                Bekijk
            </a>
            @if(!$blok->weging_gesloten)
            <form action="{{ route('toernooi.blok.sluit-weging', [$toernooi, $blok]) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="bg-orange-100 hover:bg-orange-200 text-orange-800 py-2 px-3 rounded text-sm"
                        onclick="return confirm('Weging sluiten voor Blok {{ $blok->nummer }}?')">
                    Sluit Weging
                </button>
            </form>
            @else
            <form action="{{ route('toernooi.blok.genereer-wedstrijdschemas', [$toernooi, $blok]) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="bg-green-100 hover:bg-green-200 text-green-800 py-2 px-3 rounded text-sm">
                    Schema's
                </button>
            </form>
            @endif
        </div>
    </div>
    @endforeach
</div>
@endsection
