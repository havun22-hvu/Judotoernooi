@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800">{{ $toernooi->naam }}</h1>
    <p class="text-gray-600">{{ $toernooi->datum->format('d-m-Y') }} - {{ $toernooi->organisatie }}</p>

    @if($toernooi->max_judokas)
    <div class="mt-4 flex items-center space-x-4">
        <div class="flex-1 bg-gray-200 rounded-full h-4 max-w-md">
            <div class="h-4 rounded-full {{ $toernooi->bezettings_percentage >= 100 ? 'bg-red-500' : ($toernooi->bezettings_percentage >= 80 ? 'bg-orange-500' : 'bg-green-500') }}"
                 style="width: {{ min($toernooi->bezettings_percentage, 100) }}%"></div>
        </div>
        <span class="text-sm font-medium {{ $toernooi->bezettings_percentage >= 100 ? 'text-red-600' : ($toernooi->bezettings_percentage >= 80 ? 'text-orange-600' : 'text-gray-600') }}">
            {{ $statistieken['totaal_judokas'] }} / {{ $toernooi->max_judokas }} ({{ $toernooi->bezettings_percentage }}%)
        </span>
    </div>
    @endif

    @if($toernooi->inschrijving_deadline)
    <p class="text-sm mt-2 {{ $toernooi->isInschrijvingOpen() ? 'text-green-600' : 'text-red-600' }}">
        Inschrijving: {{ $toernooi->isInschrijvingOpen() ? 'Open tot' : 'Gesloten sinds' }} {{ $toernooi->inschrijving_deadline->format('d-m-Y') }}
    </p>
    @endif
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-3xl font-bold text-blue-600">{{ $statistieken['totaal_judokas'] }}</div>
        <div class="text-gray-600">Judoka's</div>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-3xl font-bold text-green-600">{{ $statistieken['totaal_poules'] }}</div>
        <div class="text-gray-600">Poules</div>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-3xl font-bold text-orange-600">{{ $statistieken['totaal_wedstrijden'] }}</div>
        <div class="text-gray-600">Wedstrijden</div>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-3xl font-bold text-purple-600">{{ $statistieken['aanwezig'] }}</div>
        <div class="text-gray-600">Aanwezig</div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">Voorbereiding</h2>
        <div class="space-y-3">
            <a href="{{ route('toernooi.club.index', $toernooi) }}" class="block bg-blue-100 hover:bg-blue-200 p-3 rounded">
                🏢 Clubs & Uitnodigingen
            </a>
            <a href="{{ route('toernooi.judoka.import', $toernooi) }}" class="block bg-blue-100 hover:bg-blue-200 p-3 rounded">
                📥 Deelnemers Importeren
            </a>
            <a href="{{ route('toernooi.judoka.index', $toernooi) }}" class="block bg-blue-100 hover:bg-blue-200 p-3 rounded">
                👥 Deelnemerslijst ({{ $statistieken['totaal_judokas'] }})
            </a>
            @if($statistieken['totaal_judokas'] > 0)
            <form action="{{ route('toernooi.poule.genereer', $toernooi) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="w-full text-left bg-green-100 hover:bg-green-200 p-3 rounded">
                    🎯 Genereer Poule-indeling
                </button>
            </form>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">Blok/Mat Indeling</h2>
        <div class="space-y-3">
            @if($statistieken['totaal_poules'] > 0)
            <form action="{{ route('toernooi.blok.genereer-verdeling', $toernooi) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="w-full text-left bg-yellow-100 hover:bg-yellow-200 p-3 rounded">
                    📋 Genereer Blok/Mat Verdeling
                </button>
            </form>
            @endif
            <a href="{{ route('toernooi.blok.zaaloverzicht', $toernooi) }}" class="block bg-yellow-100 hover:bg-yellow-200 p-3 rounded">
                🏟️ Zaaloverzicht
            </a>
            <a href="{{ route('toernooi.blok.index', $toernooi) }}" class="block bg-yellow-100 hover:bg-yellow-200 p-3 rounded">
                ⏱️ Blokken Beheer
            </a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">Toernooidag</h2>
        <div class="space-y-3">
            <a href="{{ route('toernooi.weging.interface', $toernooi) }}" class="block bg-purple-100 hover:bg-purple-200 p-3 rounded">
                ⚖️ Weging Interface
            </a>
            <a href="{{ route('toernooi.mat.interface', $toernooi) }}" class="block bg-purple-100 hover:bg-purple-200 p-3 rounded">
                🥋 Mat Interface
            </a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">Per Leeftijdsklasse</h2>
        <div class="space-y-2">
            @forelse($statistieken['per_leeftijdsklasse'] as $klasse => $aantal)
            <div class="flex justify-between">
                <span>{{ $klasse }}</span>
                <span class="font-bold">{{ $aantal }}</span>
            </div>
            @empty
            <p class="text-gray-500">Nog geen deelnemers</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
