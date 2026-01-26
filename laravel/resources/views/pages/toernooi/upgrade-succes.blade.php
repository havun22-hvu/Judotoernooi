@extends('layouts.app')

@section('title', 'Upgrade Succesvol - ' . $toernooi->naam)

@section('content')
<div class="max-w-2xl mx-auto text-center">
    <div class="bg-white rounded-lg shadow-lg p-8">
        {{-- Success icon --}}
        <div class="mx-auto w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mb-6">
            <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        <h1 class="text-3xl font-bold text-gray-800 mb-4">Upgrade Succesvol!</h1>

        @if($betaling->isBetaald())
            <p class="text-gray-600 mb-6">
                Je toernooi <strong>{{ $toernooi->naam }}</strong> is succesvol geupgrade naar de
                <strong>{{ $betaling->tier }}</strong> staffel.
            </p>

            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <h3 class="font-semibold text-green-800 mb-2">Wat is er ontgrendeld?</h3>
                <ul class="text-sm text-green-700 space-y-1">
                    <li>Maximaal {{ $betaling->max_judokas }} judoka's</li>
                    <li>Toegang tot Print/Noodplan functies</li>
                    <li>Alle premium functies</li>
                </ul>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 mb-6 text-sm text-gray-600">
                <p><strong>Betalingskenmerk:</strong> {{ $betaling->mollie_payment_id }}</p>
                <p><strong>Bedrag:</strong> &euro;{{ number_format($betaling->bedrag, 2, ',', '.') }}</p>
                <p><strong>Betaald op:</strong> {{ $betaling->betaald_op?->format('d-m-Y H:i') }}</p>
            </div>
        @else
            <p class="text-gray-600 mb-6">
                Je betaling wordt verwerkt. Je toernooi wordt automatisch geupgrade zodra de betaling is bevestigd.
            </p>

            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                <p class="text-yellow-700 text-sm">
                    Dit kan enkele seconden tot minuten duren. Vernieuw deze pagina om de status te controleren.
                </p>
            </div>
        @endif

        <div class="flex justify-center space-x-4">
            <a href="{{ route('toernooi.show', $toernooi) }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg">
                Naar Toernooi Dashboard
            </a>
            @if($toernooi->isPaidTier())
            <a href="{{ route('toernooi.noodplan.index', $toernooi) }}" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 px-6 rounded-lg">
                Naar Print/Noodplan
            </a>
            @endif
        </div>
    </div>
</div>
@endsection
