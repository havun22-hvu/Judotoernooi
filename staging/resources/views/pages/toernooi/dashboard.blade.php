@extends('layouts.app')

@section('title', __('Dashboard'))

@section('content')
<div class="mb-8">
    <div class="flex justify-between items-start">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ $toernooi->naam }}</h1>
            <p class="text-gray-600">{{ $toernooi->datum->format('d-m-Y') }}{{ $toernooi->organisatie ? ' - ' . $toernooi->organisatie : '' }}</p>
        </div>
        <div class="flex items-center space-x-2">
            <a href="{{ route('toernooi.edit', $toernooi->routeParams()) }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                {{ __('Instellingen') }}
            </a>
        </div>
    </div>

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
        {{ __('Inschrijving') }}: {{ $toernooi->isInschrijvingOpen() ? __('Open tot') : __('Gesloten sinds') }} {{ $toernooi->inschrijving_deadline->format('d-m-Y') }}
    </p>
    @endif
</div>

{{-- Freemium Banner --}}
<x-freemium-banner :toernooi="$toernooi" />

{{-- DO NOT REMOVE: Statistics cards - Judoka's, Poules, Wedstrijden, Aanwezig --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-3xl font-bold text-blue-600">{{ $statistieken['totaal_judokas'] }}</div>
        <div class="text-gray-600">{{ __("Judoka's") }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-3xl font-bold text-green-600">{{ $statistieken['totaal_poules'] }}</div>
        <div class="text-gray-600">{{ __('Poules') }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-3xl font-bold text-orange-600">{{ $statistieken['totaal_wedstrijden'] }}</div>
        <div class="text-gray-600">{{ __('Wedstrijden') }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-3xl font-bold text-purple-600">{{ $statistieken['aanwezig'] }}</div>
        <div class="text-gray-600">{{ __('Aanwezig') }}</div>
    </div>
</div>

@if($toernooi->betaling_actief)
<div class="bg-white rounded-lg shadow p-6 mb-8">
    <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <span class="text-green-600">€</span> {{ __('Betalingsoverzicht') }}
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div>
            <div class="text-3xl font-bold text-green-600">&euro;{{ number_format($statistieken['totaal_ontvangen'] ?? 0, 2, ',', '.') }}</div>
            <div class="text-gray-600">{{ __('Totaal ontvangen') }}</div>
        </div>
        <div>
            <div class="text-3xl font-bold text-blue-600">{{ $statistieken['betaald_judokas'] ?? 0 }}</div>
            <div class="text-gray-600">{{ __("Betaalde judoka's") }}</div>
        </div>
        <div>
            <div class="text-3xl font-bold text-gray-600">{{ $statistieken['aantal_betalingen'] ?? 0 }}</div>
            <div class="text-gray-600">{{ __('Transacties') }}</div>
        </div>
    </div>
    @if(($statistieken['totaal_judokas'] - ($statistieken['betaald_judokas'] ?? 0)) > 0)
    <p class="text-sm text-orange-600 mt-4">
        {{ __(':aantal judoka(\'s) nog niet betaald', ['aantal' => $statistieken['totaal_judokas'] - ($statistieken['betaald_judokas'] ?? 0)]) }}
    </p>
    @endif
</div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    {{-- DO NOT REMOVE: Voorbereiding section - all workflow buttons for tournament setup --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">{{ __('Voorbereiding') }}</h2>
        <div class="space-y-3">
            <a href="{{ route('toernooi.edit', $toernooi->routeParams()) }}" class="block bg-gray-100 hover:bg-gray-200 p-3 rounded">
                ⚙️ {{ __('Toernooi Instellingen') }}
            </a>
            <a href="{{ route('toernooi.club.index', $toernooi->routeParams()) }}" class="block bg-blue-100 hover:bg-blue-200 p-3 rounded">
                🏢 {{ __('Clubs & Uitnodigingen') }}
            </a>
            <a href="{{ route('toernooi.judoka.import', $toernooi->routeParams()) }}" class="block bg-blue-100 hover:bg-blue-200 p-3 rounded">
                📥 {{ __('Deelnemers Importeren') }}
            </a>
            <a href="{{ route('toernooi.judoka.index', $toernooi->routeParams()) }}" class="block bg-blue-100 hover:bg-blue-200 p-3 rounded">
                👥 {{ __('Deelnemerslijst') }} ({{ $statistieken['totaal_judokas'] }})
            </a>
            @if($statistieken['totaal_judokas'] > 0)
                @php
                    $nietGecategoriseerd = $toernooi->countNietGecategoriseerd();
                    $heeftOverlap = false;
                    if (!empty($toernooi->gewichtsklassen)) {
                        $classifier = new \App\Services\CategorieClassifier($toernooi->gewichtsklassen);
                        $heeftOverlap = !empty($classifier->detectOverlap());
                    }
                    $heeftCategorieProbleem = $nietGecategoriseerd > 0 || $heeftOverlap;
                @endphp
                @if($heeftCategorieProbleem)
                <div class="w-full bg-gray-200 p-3 rounded opacity-60 cursor-not-allowed">
                    <span class="text-gray-500">🎯 {{ __('Genereer Poule-indeling') }}</span>
                    <p class="text-xs text-red-600 mt-1">
                        ⚠️ {{ __('Los eerst de categorie-problemen op') }}
                    </p>
                </div>
                @else
                <form action="{{ route('toernooi.poule.genereer', $toernooi->routeParams()) }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full text-left bg-green-100 hover:bg-green-200 p-3 rounded">
                        🎯 {{ __('Genereer Poule-indeling') }}
                    </button>
                </form>
                @endif
            @endif
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">{{ __('Blok/Mat Indeling') }}</h2>
        <div class="space-y-3">
            @if($statistieken['totaal_poules'] > 0)
            <form action="{{ route('toernooi.blok.genereer-verdeling', $toernooi->routeParams()) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="w-full text-left bg-yellow-100 hover:bg-yellow-200 p-3 rounded">
                    📋 {{ __('Genereer Blok/Mat Verdeling') }}
                </button>
            </form>
            @endif
            <a href="{{ route('toernooi.blok.zaaloverzicht', $toernooi->routeParams()) }}" class="block bg-yellow-100 hover:bg-yellow-200 p-3 rounded">
                🏟️ {{ __('Zaaloverzicht') }}
            </a>
            <a href="{{ route('toernooi.blok.index', $toernooi->routeParams()) }}" class="block bg-yellow-100 hover:bg-yellow-200 p-3 rounded">
                ⏱️ {{ __('Blokken Beheer') }}
            </a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    {{-- DO NOT REMOVE: Toernooidag section - Weging, Mat Interface, and Afsluiten buttons are essential --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">{{ __('Toernooidag') }}</h2>
        <div class="space-y-3">
            <a href="{{ route('toernooi.weging.interface', $toernooi->routeParams()) }}" class="block bg-purple-100 hover:bg-purple-200 p-3 rounded">
                ⚖️ {{ __('Weging Interface') }}
            </a>
            <a href="{{ route('toernooi.mat.interface', $toernooi->routeParams()) }}" class="block bg-purple-100 hover:bg-purple-200 p-3 rounded">
                🥋 {{ __('Mat Interface') }}
            </a>
            <a href="{{ route('toernooi.afsluiten', $toernooi->routeParams()) }}"
               class="block {{ $toernooi->isAfgesloten() ? 'bg-green-100 hover:bg-green-200' : 'bg-red-100 hover:bg-red-200' }} p-3 rounded">
                {{ $toernooi->isAfgesloten() ? '🏆 ' . __('Afgesloten - Bekijk Resultaten') : '🔒 ' . __('Toernooi Afsluiten') }}
            </a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">{{ __('Per Leeftijdsklasse') }}</h2>
        <div class="space-y-2">
            @forelse($statistieken['per_leeftijdsklasse'] as $klasse => $aantal)
            <div class="flex justify-between">
                <span>{{ $klasse }}</span>
                <span class="font-bold">{{ $aantal }}</span>
            </div>
            @empty
            <p class="text-gray-500">{{ __('Nog geen deelnemers') }}</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
