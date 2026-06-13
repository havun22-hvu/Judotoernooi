@extends('layouts.print')

@section('title', $titel)

@push('styles')
<style @nonce>
    @page {
        size: A4 portrait;
        margin: 1cm 0.5cm 0.7cm 0.5cm;

        @bottom-right {
            content: "{{ $titel }} — pagina " counter(page) " / " counter(pages);
            font-family: -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 8pt;
            color: #555;
        }
    }

    .bracket-header {
        font-family: -apple-system, BlinkMacSystemFont, sans-serif;
        font-size: 11pt;
        margin-bottom: 6pt;
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        border-bottom: 1px solid #333;
        padding-bottom: 3pt;
    }
    .bracket-header h1 { font-size: 14pt; font-weight: bold; margin: 0; }
    .bracket-header .stempel { font-size: 9pt; color: #555; }

    .bracket-page {
        width: 100%;
        page-break-after: always;
    }
    .bracket-page:last-child { page-break-after: auto; }

    .bracket-svg {
        width: 100%;
        height: auto;
        display: block;
    }

    /* Keep each potje intact across page breaks. */
    .potje {
        break-inside: avoid;
        page-break-inside: avoid;
    }

    .ronde-header {
        font-size: 7pt;
        font-weight: bold;
        text-anchor: middle;
        fill: #4c1d95;
    }

    .potje-naam {
        font-size: 7pt;
        font-weight: bold;
        fill: #111;
    }
    .potje-naam.empty { fill: #999; font-weight: normal; font-style: italic; }
    .potje-naam.loser { fill: #999; text-decoration: line-through; }

    .potje-club {
        font-size: 5pt;
        fill: #555;
    }

    .potje-vakje { fill: #fff; stroke: #333; stroke-width: 1; }
    .potje-score-vakje { fill: #f3f4f6; stroke: #333; stroke-width: 1; }
    .potje-score { font-size: 8pt; font-weight: bold; text-anchor: middle; fill: #b91c1c; }
    .potje-lijn { stroke: #333; stroke-width: 1; fill: none; }

    .medaille { font-size: 11pt; font-weight: bold; }
    .medaille-goud { fill: #b45309; }
    .medaille-zilver { fill: #6b7280; }
    .medaille-brons { fill: #92400e; }

    .geen-wedstrijden {
        text-align: center;
        font-size: 11pt;
        color: #555;
        padding: 30pt;
        border: 1px dashed #aaa;
    }
</style>
@endpush

@section('content')
<div class="bracket-page">
    <div class="bracket-header">
        <h1>{{ $titel }} <span style="font-weight:normal; font-size:11pt;">— A bracket</span></h1>
        <span class="stempel">{{ $data['meta']['stempel'] }} · {{ $data['meta']['aantal_deelnemers'] }} judoka's · {{ now()->format('d-m-Y H:i') }}</span>
    </div>
    @include('pages.noodplan.partials._bracket-print-a', ['layout' => $data['a_bracket']])
</div>

<div class="bracket-page">
    <div class="bracket-header">
        <h1>{{ $titel }} <span style="font-weight:normal; font-size:11pt;">— B (herkansing)</span></h1>
        <span class="stempel">{{ $data['meta']['stempel'] }} · {{ $data['meta']['aantal_deelnemers'] }} judoka's · {{ now()->format('d-m-Y H:i') }}</span>
    </div>
    @include('pages.noodplan.partials._bracket-print-b', ['layout' => $data['b_bracket']])
</div>

<script @nonce>
    window.addEventListener('load', () => setTimeout(() => window.print(), 300));
</script>
@endsection
