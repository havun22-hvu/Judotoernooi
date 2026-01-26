@extends('layouts.app')

@section('title', 'Upgrade Vereist - ' . $toernooi->naam)

@section('content')
<div class="max-w-2xl mx-auto text-center">
    <div class="bg-white rounded-lg shadow-lg p-8">
        {{-- Lock icon --}}
        <div class="mx-auto w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center mb-6">
            <svg class="w-10 h-10 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>

        <h1 class="text-3xl font-bold text-gray-800 mb-4">Print/Noodplan Geblokkeerd</h1>

        <p class="text-gray-600 mb-6">
            De Print en Noodplan functies zijn alleen beschikbaar voor toernooien met een betaald abonnement.
        </p>

        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-yellow-800 mb-2">Gratis tier beperkingen:</h3>
            <ul class="text-sm text-yellow-700 space-y-1">
                <li>Maximaal 50 judoka's</li>
                <li>Geen toegang tot Print/Noodplan</li>
                <li>Beperkte functies</li>
            </ul>
        </div>

        <p class="text-gray-600 mb-6">
            Upgrade je toernooi om alle functies te ontgrendelen, waaronder:
        </p>

        <ul class="text-left bg-green-50 border border-green-200 rounded-lg p-4 mb-6 text-sm text-green-700 space-y-2">
            <li class="flex items-center">
                <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
                Print weegkaarten, coachkaarten en wedstrijdschema's
            </li>
            <li class="flex items-center">
                <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
                Noodplan backup voor offline werken
            </li>
            <li class="flex items-center">
                <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
                Export naar Excel/PDF
            </li>
            <li class="flex items-center">
                <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
                Meer judoka's (tot 500)
            </li>
        </ul>

        <div class="flex justify-center space-x-4">
            <a href="{{ route('toernooi.upgrade', $toernooi) }}" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg">
                Upgrade Nu
            </a>
            <a href="{{ route('toernooi.show', $toernooi) }}" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 px-6 rounded-lg">
                Terug naar Dashboard
            </a>
        </div>
    </div>
</div>
@endsection
