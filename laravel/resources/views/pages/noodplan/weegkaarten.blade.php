@extends('layouts.print')

@section('title', isset($club) ? "Weegkaarten - {$club->naam}" : 'Weegkaarten')

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script>
<style>
    .weegkaart {
        border: 2px solid #333;
        border-radius: 8px;
        overflow: hidden;
        page-break-inside: avoid;
        background: white;
    }
    .weegkaart-header {
        background: #dbeafe;
        color: #1e3a8a;
        padding: 5px 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 10px;
        font-weight: 600;
        border-bottom: 1px solid #93c5fd;
    }
    .weegkaart-naam {
        background: white;
        border-bottom: 1px solid #ccc;
        padding: 6px 8px;
        text-align: center;
    }
    .weegkaart-naam h3 {
        font-size: 14px;
        font-weight: 900;
        margin: 0;
        color: #111;
    }
    .weegkaart-naam p {
        font-size: 10px;
        color: #333;
        margin: 2px 0 0;
        font-weight: 500;
    }
    .weegkaart-classificatie {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 2px;
        padding: 4px 6px;
        border-bottom: 1px solid #e5e7eb;
        text-align: center;
    }
    .weegkaart-classificatie .label {
        font-size: 7px;
        text-transform: uppercase;
        color: #666;
    }
    .weegkaart-classificatie .value {
        font-size: 10px;
        font-weight: 700;
        color: #111;
    }
    .weegkaart-blok {
        background: white;
        padding: 6px 8px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .weegkaart-blok .mat-badge {
        color: #111;
        padding: 2px 8px;
        border: 2px solid currentColor;
        font-weight: 900;
        font-size: 11px;
    }
    /* Mat kleuren - lichte achtergronden voor print contrast */
    .mat-rood { background: #fee2e2; border-color: #dc2626; color: #991b1b; }
    .mat-blauw { background: #dbeafe; border-color: #2563eb; color: #1e40af; }
    .mat-groen { background: #dcfce7; border-color: #16a34a; color: #166534; }
    .mat-geel { background: #fef9c3; border-color: #ca8a04; color: #854d0e; }
    .mat-oranje { background: #ffedd5; border-color: #ea580c; color: #9a3412; }
    .mat-paars { background: #f3e8ff; border-color: #9333ea; color: #6b21a8; }
    .mat-roze { background: #fce7f3; border-color: #db2777; color: #9d174d; }
    .mat-default { background: #f3f4f6; border-color: #374151; color: #111; }
    .weegkaart-blok .blok-badge {
        background: white;
        color: #111;
        padding: 2px 6px;
        border: 1px solid #666;
        font-weight: 700;
        font-size: 9px;
        margin-right: 4px;
    }
    .weegkaart-blok .tijden {
        font-size: 9px;
        text-align: right;
        color: #4b5563;
    }
    .weegkaart-blok .tijden strong {
        color: #111;
    }
    .weegkaart-qr {
        padding: 8px;
        text-align: center;
        background: white;
    }
    .weegkaart-qr canvas {
        display: block;
        margin: 0 auto;
    }
    .weegkaart-qr .code {
        font-size: 8px;
        color: #9ca3af;
        font-family: monospace;
        margin-top: 4px;
    }
    .weegkaart-footer {
        background: #f5f5f5;
        border-top: 1px solid #ccc;
        padding: 3px 8px;
        font-size: 8px;
        color: #333;
        display: flex;
        justify-content: space-between;
    }
    /* Band kleuren - leesbare tekst met kleur-bolletje */
    .band-wit, .band-geel, .band-oranje, .band-groen, .band-blauw, .band-bruin, .band-zwart, .band-default {
        color: #111; background: none; border: none;
    }
    /* Geen kleuren voor print - alles zwart */
    .geslacht-m, .geslacht-v, .leeftijd, .gewicht { color: #111; }

    @media print {
        .weegkaart-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .weegkaart-naam { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .weegkaart-blok { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .weegkaart-footer { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .band-wit, .band-geel, .band-oranje, .band-groen, .band-blauw, .band-bruin, .band-zwart, .band-default { }
        .mat-rood, .mat-blauw, .mat-groen, .mat-geel, .mat-oranje, .mat-paars, .mat-roze, .mat-default {
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
    }
</style>
@endpush

@section('content')
<div class="kaart-grid">
    @foreach($judokas as $judoka)
    @php
        $poule = $judoka->poules->first();
        $blok = $poule?->blok;
        $mat = $poule?->mat;
        $aantalBlokken = $toernooi->blokken()->count();

        // Band kleur class via enum
        $bandLabel = \App\Enums\Band::toKleur($judoka->band) ?: '?';
        $bandKleur = match(strtolower($bandLabel)) {
            'wit' => 'band-wit',
            'geel' => 'band-geel',
            'oranje' => 'band-oranje',
            'groen' => 'band-groen',
            'blauw' => 'band-blauw',
            'bruin' => 'band-bruin',
            'zwart' => 'band-zwart',
            default => 'band-default',
        };

        // Gewicht display
        if ($judoka->gewichtsklasse === 'Variabel') {
            $gewichtDisplay = $judoka->gewicht ? $judoka->gewicht . ' kg' : 'Var.';
        } else {
            $gewichtDisplay = $judoka->gewichtsklasse ? '-' . $judoka->gewichtsklasse . ' kg' : '?';
        }

        // Mat kleur class (lichte versies voor print)
        $matKleur = match(strtolower($mat?->kleur ?? '')) {
            'rood' => 'mat-rood',
            'blauw' => 'mat-blauw',
            'groen' => 'mat-groen',
            'geel' => 'mat-geel',
            'oranje' => 'mat-oranje',
            'paars' => 'mat-paars',
            'roze' => 'mat-roze',
            default => 'mat-default',
        };
    @endphp
    <div class="weegkaart no-break">
        {{-- Header met toernooi naam + datum --}}
        <div class="weegkaart-header">
            <span>{{ $toernooi->naam }}</span>
            <span>{{ $toernooi->datum->format('d-m-Y') }}</span>
        </div>

        {{-- Naam + club prominent --}}
        <div class="weegkaart-naam">
            <h3>{{ $judoka->naam }}</h3>
            <p>{{ $judoka->club?->naam ?? 'Onbekend' }}</p>
        </div>

        {{-- Classificatie: Leeftijd | Gewicht | Band | Geslacht --}}
        <div class="weegkaart-classificatie">
            <div>
                <div class="label">Leeftijd</div>
                <div class="value leeftijd">{{ $judoka->leeftijdsklasse ?? '?' }}</div>
            </div>
            <div>
                <div class="label">Gewicht</div>
                <div class="value gewicht">{{ $gewichtDisplay }}</div>
            </div>
            <div>
                <div class="label">Band</div>
                <div class="value">{{ $bandLabel }}</div>
            </div>
            <div>
                <div class="label">Geslacht</div>
                <div class="value geslacht-{{ strtolower($judoka->geslacht ?? 'm') }}">{{ $judoka->geslacht ?? '?' }}</div>
            </div>
        </div>

        {{-- Blok + Mat + Tijden --}}
        @if($poule)
        <div class="weegkaart-blok">
            <div>
                @if($aantalBlokken > 1 && $blok)
                <span class="blok-badge">{{ $blok->naam ?? 'Blok ' . $blok->nummer }}</span>
                @endif
                @if($mat)
                <span class="mat-badge {{ $matKleur }}">Mat {{ $mat->nummer }}</span>
                @endif
            </div>
            <div class="tijden">
                @if($blok?->weging_start && $blok?->weging_einde)
                <div>Weging: <strong>{{ $blok->weging_start->format('H:i') }}-{{ $blok->weging_einde->format('H:i') }}</strong></div>
                @endif
                @if($blok?->starttijd)
                <div>Start: <strong>{{ $blok->starttijd->format('H:i') }}</strong></div>
                @endif
            </div>
        </div>
        @else
        <div class="weegkaart-blok" style="background: #fef3c7; justify-content: center;">
            <span style="color: #92400e; font-size: 10px;">Nog geen poule toegewezen</span>
        </div>
        @endif

        {{-- QR code --}}
        <div class="weegkaart-qr">
            <canvas id="qr-{{ $judoka->id }}" width="120" height="120"></canvas>
            <div class="code">{{ strtoupper(Str::limit($judoka->qr_code, 12, '')) }}</div>
        </div>

        {{-- Footer --}}
        <div class="weegkaart-footer">
            <span>📱 Toon bij weging</span>
            <span>{{ now()->format('d-m H:i') }}</span>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    @foreach($judokas as $judoka)
    QRCode.toCanvas(document.getElementById('qr-{{ $judoka->id }}'), '{{ route('weegkaart.show', $judoka->qr_code) }}', {
        width: 120,
        margin: 1
    });
    @endforeach
});
</script>
@endsection
