@extends('layouts.print')

@section('title', isset($club) ? __('Coachkaarten') . " - {$club->naam}" : __('Coachkaarten'))

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js">
@endpush

@section('content')
<div class="kaart-grid">
    @foreach($coachkaarten as $kaart)
    <div class="kaart no-break">
        <div class="flex justify-between items-start mb-3">
            <div>
                <h3 class="font-bold text-lg">{{ __('COACHKAART') }}</h3>
                <p class="text-sm text-gray-600">{{ __('Toegang Dojo') }}</p>
            </div>
            @if($kaart->qr_code)
            <div class="qr-placeholder">
                <canvas id="qr-coach-{{ $kaart->id }}" width="100" height="100"></canvas>
            </div>
            @else
            <div class="qr-placeholder">
                {{ __('Geen QR') }}
            </div>
            @endif
        </div>

        <table class="w-full text-sm">
            <tr>
                <td class="py-1 text-gray-500 w-24">{{ __('Club') }}:</td>
                <td class="py-1 font-bold">{{ $kaart->club?->naam ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">{{ __('Coach') }}:</td>
                <td class="py-1 font-medium">
                    @if($kaart->coach)
                        {{ $kaart->coach->naam }}
                    @else
                        <span class="text-gray-400">{{ __('Niet toegewezen') }}</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">{{ __('Status') }}:</td>
                <td class="py-1">
                    @if($kaart->geactiveerd_op)
                        <span class="text-green-600 font-medium">{{ __('Geactiveerd') }}</span>
                    @else
                        <span class="text-orange-600">{{ __('Niet geactiveerd') }}</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td class="py-1 text-gray-500">{{ __('Kaart') }} #:</td>
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
<p class="text-gray-500 text-center py-8">{{ __('Geen coachkaarten gevonden') }}</p>
@endif

<div class="no-print mt-6 text-sm text-gray-600">
    <p><strong>{{ __('Totaal') }}:</strong> {{ $coachkaarten->count() }} {{ __('coachkaarten') }}</p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    @foreach($coachkaarten as $kaart)
    @if($kaart->qr_code)
    QRCode.toCanvas(document.getElementById('qr-coach-{{ $kaart->id }}'), '{{ route('coach-kaart.scan', $kaart->qr_code) }}', {
        width: 100,
        margin: 1
    });
    @endif
    @endforeach
});
</script>
@endsection
