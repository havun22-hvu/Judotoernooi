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
            @if($judoka->weegkaart_token)
            <div class="qr-placeholder">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data={{ urlencode(route('weegkaart.show', $judoka->weegkaart_token)) }}"
                     alt="QR" class="w-full h-full">
            </div>
            @else
            <div class="qr-placeholder">
                Geen QR
            </div>
            @endif
        </div>

        <table class="w-full text-xs">
            <tr>
                <td class="py-1 text-gray-500">Geboortedatum:</td>
                <td class="py-1 font-medium">{{ $judoka->geboortedatum?->format('d-m-Y') ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">Leeftijd:</td>
                <td class="py-1 font-medium">{{ $judoka->geboortedatum?->age ?? '-' }} jaar</td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">Geslacht:</td>
                <td class="py-1 font-medium">{{ $judoka->geslacht?->label() ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">Band:</td>
                <td class="py-1 font-medium">{{ $judoka->band?->label() ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">Gewichtsklasse:</td>
                <td class="py-1 font-medium">{{ $judoka->gewichtsklasse ?? '-' }}</td>
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
