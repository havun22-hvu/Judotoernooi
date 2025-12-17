@extends('layouts.print')

@section('title', isset($club) ? "Coachkaarten - {$club->naam}" : 'Coachkaarten')

@section('content')
<div class="kaart-grid">
    @foreach($coachkaarten as $kaart)
    <div class="kaart no-break">
        <div class="flex justify-between items-start mb-3">
            <div>
                <h3 class="font-bold text-lg">COACHKAART</h3>
                <p class="text-sm text-gray-600">Toegang Dojo</p>
            </div>
            @if($kaart->qr_code)
            <div class="qr-placeholder">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data={{ urlencode(route('coach-kaart.scan', $kaart->qr_code)) }}"
                     alt="QR" class="w-full h-full">
            </div>
            @else
            <div class="qr-placeholder">
                Geen QR
            </div>
            @endif
        </div>

        <table class="w-full text-sm">
            <tr>
                <td class="py-1 text-gray-500 w-24">Club:</td>
                <td class="py-1 font-bold">{{ $kaart->club?->naam ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">Coach:</td>
                <td class="py-1 font-medium">
                    @if($kaart->coach)
                        {{ $kaart->coach->naam }}
                    @else
                        <span class="text-gray-400">Niet toegewezen</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">Status:</td>
                <td class="py-1">
                    @if($kaart->geactiveerd_op)
                        <span class="text-green-600 font-medium">Geactiveerd</span>
                    @else
                        <span class="text-orange-600">Niet geactiveerd</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">Kaart #:</td>
                <td class="py-1 font-mono text-xs">{{ $kaart->qr_code ?? '-' }}</td>
            </tr>
        </table>

        <div class="mt-3 pt-2 border-t text-xs text-gray-500 flex justify-between">
            <span>{{ $toernooi->naam }}</span>
            <span>{{ $toernooi->datum->format('d-m-Y') }}</span>
        </div>
    </div>
    @endforeach
</div>

@if($coachkaarten->isEmpty())
<p class="text-gray-500 text-center py-8">Geen coachkaarten gevonden</p>
@endif

<div class="no-print mt-6 text-sm text-gray-600">
    <p><strong>Totaal:</strong> {{ $coachkaarten->count() }} coachkaarten</p>
</div>
@endsection
