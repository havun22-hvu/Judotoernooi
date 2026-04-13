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
        <div class="flex-1 bg-gray-100 rounded-full h-2 max-w-md">
            <div class="h-2 rounded-full {{ $toernooi->bezettings_percentage >= 100 ? 'bg-red-500' : 'bg-blue-500' }}"
                 class="w-[{{ min($toernooi->bezettings_percentage, 100) }}%]"></div>
        </div>
        <span class="text-sm text-gray-500">
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
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5 flex items-center gap-4">
        <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </div>
        <div>
            <div class="text-2xl font-bold text-gray-900">{{ $statistieken['totaal_judokas'] }}</div>
            <div class="text-sm text-gray-500">{{ __("Judoka's") }}</div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5 flex items-center gap-4">
        <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center">
            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
        </div>
        <div>
            <div class="text-2xl font-bold text-gray-900">{{ $statistieken['totaal_poules'] }}</div>
            <div class="text-sm text-gray-500">{{ __('Poules') }}</div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5 flex items-center gap-4">
        <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-slate-50 flex items-center justify-center">
            <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
        </div>
        <div>
            <div class="text-2xl font-bold text-gray-900">{{ $statistieken['totaal_wedstrijden'] }}</div>
            <div class="text-sm text-gray-500">{{ __('Wedstrijden') }}</div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5 flex items-center gap-4">
        <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center">
            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div>
            <div class="text-2xl font-bold text-gray-900">{{ $statistieken['aanwezig'] }}</div>
            <div class="text-sm text-gray-500">{{ __('Aanwezig') }}</div>
        </div>
    </div>
</div>

@if($toernooi->betaling_actief)
<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6 mb-8">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">{{ __('Betalingsoverzicht') }}</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div>
            <div class="text-3xl font-bold text-gray-900">&euro;{{ number_format($statistieken['totaal_ontvangen'] ?? 0, 2, ',', '.') }}</div>
            <div class="text-sm text-gray-500">{{ __('Totaal ontvangen') }}</div>
        </div>
        <div>
            <div class="text-3xl font-bold text-gray-900">{{ $statistieken['betaald_judokas'] ?? 0 }}</div>
            <div class="text-sm text-gray-500">{{ __("Betaalde judoka's") }}</div>
        </div>
        <div>
            <div class="text-3xl font-bold text-gray-900">{{ $statistieken['aantal_betalingen'] ?? 0 }}</div>
            <div class="text-sm text-gray-500">{{ __('Transacties') }}</div>
        </div>
    </div>
    @if(($statistieken['totaal_judokas'] - ($statistieken['betaald_judokas'] ?? 0)) > 0)
    <p class="text-sm text-orange-600 mt-4">
        {{ __(':aantal judoka(\'s) nog niet betaald', ['aantal' => $statistieken['totaal_judokas'] - ($statistieken['betaald_judokas'] ?? 0)]) }}
    </p>
    @endif
</div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">
    {{-- DO NOT REMOVE: Voorbereiding section - all workflow buttons for tournament setup --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="bg-blue-800 px-5 py-3 flex items-center gap-3">
            <span class="flex items-center justify-center w-7 h-7 rounded-full bg-white/20 text-white text-sm font-bold">1</span>
            <h2 class="text-white font-semibold">{{ __('Voorbereiding') }}</h2>
        </div>
        <div class="p-4 space-y-1">
            <a href="{{ route('toernooi.edit', $toernooi->routeParams()) }}" class="group flex items-center justify-between px-3 py-2.5 rounded-lg text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    {{ __('Toernooi Instellingen') }}
                </span>
                <svg class="w-4 h-4 text-gray-300 group-hover:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('toernooi.club.index', $toernooi->routeParams()) }}" class="group flex items-center justify-between px-3 py-2.5 rounded-lg text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    {{ __('Clubs & Uitnodigingen') }}
                </span>
                <svg class="w-4 h-4 text-gray-300 group-hover:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('toernooi.judoka.import', $toernooi->routeParams()) }}" class="group flex items-center justify-between px-3 py-2.5 rounded-lg text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    {{ __('Deelnemers Importeren') }}
                </span>
                <svg class="w-4 h-4 text-gray-300 group-hover:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('toernooi.judoka.index', $toernooi->routeParams()) }}" class="group flex items-center justify-between px-3 py-2.5 rounded-lg text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    {{ __('Deelnemerslijst') }} ({{ $statistieken['totaal_judokas'] }})
                </span>
                <svg class="w-4 h-4 text-gray-300 group-hover:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
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
                <div class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-400 cursor-not-allowed">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <div>
                        <span>{{ __('Genereer Poule-indeling') }}</span>
                        <p class="text-xs text-red-500 mt-0.5">{{ __('Los eerst de categorie-problemen op') }}</p>
                    </div>
                </div>
                @else
                <div class="pt-2 mt-1 border-t border-gray-100">
                    <form action="{{ route('toernooi.poule.genereer', $toernooi->routeParams()) }}" method="POST"
                        @if($statistieken['totaal_poules'] > 0)
                            onsubmit="return confirm('{{ __('Let op: er zijn al :poules poules met :wedstrijden wedstrijden. Bij opnieuw genereren gaat de huidige indeling verloren. Weet je het zeker?', ['poules' => $statistieken['totaal_poules'], 'wedstrijden' => $statistieken['totaal_wedstrijden']]) }}')"
                        @endif
                    >
                        @csrf
                        @if($statistieken['totaal_poules'] > 0)
                        <button type="submit" class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-gray-600 hover:border-red-400 hover:text-red-600 text-sm transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            {{ __('Opnieuw genereren') }}
                        </button>
                        @else
                        <button type="submit" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border-2 border-blue-600 text-blue-700 hover:bg-blue-600 hover:text-white text-sm font-semibold transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            {{ __('Genereer Poule-indeling') }}
                        </button>
                        @endif
                    </form>
                </div>
                @endif
            @endif
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="bg-blue-800 px-5 py-3 flex items-center gap-3">
            <span class="flex items-center justify-center w-7 h-7 rounded-full bg-white/20 text-white text-sm font-bold">2</span>
            <h2 class="text-white font-semibold">{{ __('Blok/Mat Indeling') }}</h2>
        </div>
        <div class="p-4 space-y-1">
            @if($statistieken['totaal_poules'] > 0)
            <div class="pb-2 mb-1 border-b border-gray-100">
                <form action="{{ route('toernooi.blok.genereer-verdeling', $toernooi->routeParams()) }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border-2 border-blue-600 text-blue-700 hover:bg-blue-600 hover:text-white text-sm font-semibold transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        {{ __('Genereer Blok/Mat Verdeling') }}
                    </button>
                </form>
            </div>
            @endif
            <a href="{{ route('toernooi.blok.zaaloverzicht', $toernooi->routeParams()) }}" class="group flex items-center justify-between px-3 py-2.5 rounded-lg text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                    {{ __('Zaaloverzicht') }}
                </span>
                <svg class="w-4 h-4 text-gray-300 group-hover:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('toernooi.blok.index', $toernooi->routeParams()) }}" class="group flex items-center justify-between px-3 py-2.5 rounded-lg text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    {{ __('Blokken Beheer') }}
                </span>
                <svg class="w-4 h-4 text-gray-300 group-hover:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    {{-- DO NOT REMOVE: Toernooidag section - Weging, Mat Interface, and Afsluiten buttons are essential --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="bg-blue-800 px-5 py-3 flex items-center gap-3">
            <span class="flex items-center justify-center w-7 h-7 rounded-full bg-white/20 text-white text-sm font-bold">3</span>
            <h2 class="text-white font-semibold">{{ __('Toernooidag') }}</h2>
        </div>
        <div class="p-4 space-y-1">
            <a href="{{ route('toernooi.weging.interface', $toernooi->routeParams()) }}" class="group flex items-center justify-between px-3 py-2.5 rounded-lg text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                    {{ __('Weging Interface') }}
                </span>
                <svg class="w-4 h-4 text-gray-300 group-hover:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('toernooi.mat.interface', $toernooi->routeParams()) }}" class="group flex items-center justify-between px-3 py-2.5 rounded-lg text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                    {{ __('Mat Interface') }}
                </span>
                <svg class="w-4 h-4 text-gray-300 group-hover:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('toernooi.afsluiten', $toernooi->routeParams()) }}"
               class="group flex items-center justify-between px-3 py-2.5 rounded-lg transition-colors {{ $toernooi->isAfgesloten() ? 'text-green-700 hover:bg-green-50' : 'text-gray-700 hover:bg-blue-50 hover:text-blue-700' }}">
                <span class="flex items-center gap-3">
                    @if($toernooi->isAfgesloten())
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ __('Afgesloten - Bekijk Resultaten') }}
                    @else
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    {{ __('Toernooi Afsluiten') }}
                    @endif
                </span>
                <svg class="w-4 h-4 text-gray-300 group-hover:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="bg-blue-800 px-5 py-3">
            <h2 class="text-white font-semibold">{{ __('Per Leeftijdsklasse') }}</h2>
        </div>
        <div class="p-4 space-y-2">
            @forelse($statistieken['per_leeftijdsklasse'] as $klasse => $aantal)
            <div class="flex justify-between items-center px-3 py-2 rounded-lg hover:bg-gray-50">
                <span class="text-gray-700">{{ $klasse }}</span>
                <span class="font-bold text-gray-900 bg-gray-100 px-2.5 py-0.5 rounded-full text-sm">{{ $aantal }}</span>
            </div>
            @empty
            <p class="text-gray-500 px-3">{{ __('Nog geen deelnemers') }}</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
