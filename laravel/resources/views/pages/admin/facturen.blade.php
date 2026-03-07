@extends('layouts.app')

@section('title', __('Havun Admin') . ' - Facturen')

@section('content')
<div class="flex justify-between items-center mb-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Facturen</h1>
        <p class="text-gray-500 mt-1">Alle toernooi upgrade betalingen</p>
    </div>
    <a href="{{ route('admin.klanten') }}" class="text-blue-600 hover:text-blue-800">
        &larr; Terug naar Klanten
    </a>
</div>

{{-- Stats --}}
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <p class="text-sm text-gray-500">Totaal ontvangen</p>
        <p class="text-2xl font-bold text-green-600">&euro; {{ number_format($stats['totaal_betaald'], 2, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <p class="text-sm text-gray-500">Betaalde facturen</p>
        <p class="text-2xl font-bold text-gray-800">{{ $stats['aantal_betaald'] }}</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <p class="text-sm text-gray-500">Open betalingen</p>
        <p class="text-2xl font-bold {{ $stats['aantal_open'] > 0 ? 'text-yellow-600' : 'text-gray-800' }}">{{ $stats['aantal_open'] }}</p>
    </div>
</div>

{{-- Tabel --}}
<div class="bg-white rounded-lg shadow overflow-hidden">
    @if($betalingen->count() > 0)
    <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Factuurnummer</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Datum</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Klant</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Toernooi</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tier</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Provider</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Bedrag</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Betaald op</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach($betalingen as $betaling)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-mono text-gray-700">
                        {{ $betaling->factuurnummer ?? '-' }}
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        {{ $betaling->created_at->format('d-m-Y') }}
                    </td>
                    <td class="px-4 py-3 text-sm">
                        @if($betaling->organisator)
                            <a href="{{ route('admin.klanten.edit', $betaling->organisator) }}" class="text-blue-600 hover:text-blue-800">
                                {{ $betaling->organisator->naam }}
                            </a>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm">
                        @if($betaling->toernooi)
                            <a href="{{ route('toernooi.show', $betaling->toernooi->routeParams()) }}" class="text-blue-600 hover:text-blue-800">
                                {{ $betaling->toernooi->naam }}
                            </a>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm">
                        {{ ucfirst($betaling->tier ?? '-') }}
                        @if($betaling->max_judokas)
                            <span class="text-gray-500">({{ $betaling->max_judokas }})</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($betaling->payment_provider === 'stripe')
                            <span class="px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs">Stripe</span>
                        @else
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">Mollie</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-right font-medium">
                        &euro; {{ number_format($betaling->bedrag, 2, ',', '.') }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($betaling->status === 'paid')
                            <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs">Betaald</span>
                        @elseif($betaling->status === 'open')
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs">Open</span>
                        @elseif($betaling->status === 'expired')
                            <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs">Verlopen</span>
                        @elseif($betaling->status === 'failed')
                            <span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs">Mislukt</span>
                        @else
                            <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs">{{ $betaling->status }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        {{ $betaling->betaald_op?->format('d-m-Y H:i') ?? '-' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50">
                <tr>
                    <td colspan="6" class="px-4 py-3 text-sm font-medium text-gray-700">
                        Totaal betaald ({{ $betalingen->where('status', 'paid')->count() }} facturen)
                    </td>
                    <td class="px-4 py-3 text-right font-bold text-green-600">
                        &euro; {{ number_format($betalingen->where('status', 'paid')->sum('bedrag'), 2, ',', '.') }}
                    </td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    @else
    <div class="px-6 py-8 text-center text-gray-500">
        Nog geen betalingen gevonden
    </div>
    @endif
</div>
@endsection
