@extends('layouts.print')

@section('title', isset($club) ? "Weegkaarten - {$club->naam}" : 'Weegkaarten')

@section('content')
<div class="kaart-grid">
    @foreach($judokas as $judoka)
    <div class="kaart no-break">
        <div class="flex justify-between items-start mb-3">
            <div>
                <h3 class="font-bold text-lg">{{ $judoka->naam }}</h3>
                <p class="text-sm text-gray-600">{{ $judoka->club?->naam ?? 'Onbekend' }}</p>
            </div>
            <div class="qr-placeholder">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data={{ urlencode(route('weegkaart.show', $judoka->qr_code)) }}"
                     alt="QR" class="w-full h-full">
            </div>
        </div>

        @php
            $poule = $judoka->poules->first();
        @endphp

        @if($poule)
        <div class="mb-2 p-2 bg-blue-50 rounded text-center">
            <span class="font-bold text-blue-800">Blok {{ $poule->blok?->nummer ?? '?' }}</span>
        </div>
        @else
        <div class="mb-2 p-2 bg-yellow-50 rounded text-center text-yellow-700 text-sm">
            Nog geen poule toegewezen
        </div>
        @endif

        <table class="w-full text-xs">
            <tr>
                <td class="py-1 text-gray-500">Geslacht:</td>
                <td class="py-1 font-medium">{{ $judoka->geslacht_enum?->label() ?? $judoka->geslacht ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">Band:</td>
                <td class="py-1 font-medium">{{ $judoka->band_enum?->label() ?? $judoka->band ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">Gewichtsklasse:</td>
                <td class="py-1 font-medium">{{ $judoka->gewichtsklasse ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">Geboortejaar:</td>
                <td class="py-1 font-medium">{{ $judoka->geboortejaar ?? '-' }}</td>
            </tr>
            <tr class="border-t">
                <td class="py-1 text-gray-500">Gewogen:</td>
                <td class="py-1 font-bold text-lg">
                    @if($judoka->gewicht_gewogen)
                        {{ number_format($judoka->gewicht_gewogen, 1) }} kg
                    @else
                        ______ kg
                    @endif
                </td>
            </tr>
        </table>

        <div class="mt-3 pt-2 border-t text-xs text-gray-500 flex justify-between">
            <span>{{ $toernooi->naam }}</span>
            <span>{{ $toernooi->datum->format('d-m-Y') }}</span>
        </div>
    </div>
    @endforeach
</div>

@if($judokas->isEmpty())
<p class="text-gray-500 text-center py-8">Geen judoka's gevonden</p>
@endif

<div class="no-print mt-6 text-sm text-gray-600">
    <p><strong>Totaal:</strong> {{ $judokas->count() }} weegkaarten</p>
</div>
@endsection
