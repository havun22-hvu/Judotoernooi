@extends('layouts.print')

@section('title', __('Poule-indeling') . ($blok ? ' ' . __('Blok') . " {$blok->nummer}" : ''))

@push('styles')
<style>
    @media print {
        .mat-page.print-exclude { display: none !important; }
        .mat-page:not(.print-exclude) + .mat-page:not(.print-exclude) { page-break-before: always; }
    }
    @media screen {
        .mat-page.print-exclude { opacity: 0.3; }
        .mat-toolbar { background: #eff6ff; border-bottom: 1px solid #bfdbfe; padding: 8px 16px; }
        .mat-checkbox { display: inline-flex; align-items: center; gap: 6px; margin-right: 16px; cursor: pointer; font-size: 14px; }
        .mat-checkbox input { width: 18px; height: 18px; cursor: pointer; }
    }
</style>
@endpush

@section('toolbar')
<div class="mat-toolbar no-print">
    <div class="max-w-7xl mx-auto flex items-center flex-wrap gap-2">
        <span class="text-sm font-medium text-blue-800 mr-2">{{ __('Matten:') }}</span>
        @foreach($matten as $mat)
            @php
                $heeftPoules = $blokken->flatMap(fn($b) => $b->poules->where('mat_id', $mat->id))->isNotEmpty();
            @endphp
            @if($heeftPoules)
            <label class="mat-checkbox">
                <input type="checkbox" checked onchange="toggleMat({{ $mat->id }}, this.checked)">
                <span>{{ __('Mat') }} {{ $mat->nummer }}{{ $mat->label ? " ({$mat->label})" : '' }}</span>
            </label>
            @endif
        @endforeach
        <span class="text-gray-300 mx-1">|</span>
        <button onclick="selectAllMats(true)" class="text-xs text-blue-600 hover:text-blue-800 underline">{{ __('Alles') }}</button>
        <button onclick="selectAllMats(false)" class="text-xs text-blue-600 hover:text-blue-800 underline">{{ __('Geen') }}</button>
        <span class="ml-auto text-xs text-gray-500" id="mat-count"></span>
    </div>
</div>
@endsection

@section('content')
@php
    $enkelBlok = $blokken->count() === 1;
@endphp

@foreach($blokken as $blok)
    @foreach($matten as $mat)
        @php
            $matPoules = $blok->poules->where('mat_id', $mat->id)->sortBy(fn($p) => $p->nummer);
        @endphp
        @if($matPoules->isNotEmpty())
        <div class="mat-page" data-mat-id="{{ $mat->id }}">
            <h2 class="text-xl font-bold text-blue-800 mb-3 border-b-2 border-blue-300 pb-2">
                {{ !$enkelBlok ? __('Blok') . " {$blok->nummer} - " : '' }}{{ __('Mat') }} {{ $mat->nummer }}{{ $mat->label ? " ({$mat->label})" : '' }}
            </h2>

            @foreach($matPoules as $poule)
            <div class="mb-4">
                <h3 class="font-medium text-gray-700 mb-1">
                    Poule #{{ $poule->nummer }} - {{ $poule->getDisplayTitel() }}
                </h3>
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="px-2 py-1 text-left w-8">#</th>
                            <th class="px-2 py-1 text-left">{{ __('Naam') }}</th>
                            <th class="px-2 py-1 text-left">{{ __('Club') }}</th>
                            <th class="px-2 py-1 text-center w-16">{{ __('Gewicht') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($poule->judokas as $idx => $judoka)
                        <tr class="border-b {{ $idx % 2 == 0 ? 'bg-white' : 'bg-gray-50' }}">
                            <td class="px-2 py-1 text-gray-500">{{ $idx + 1 }}</td>
                            <td class="px-2 py-1 font-medium">{{ $judoka->naam }}</td>
                            <td class="px-2 py-1 text-gray-600 text-xs">{{ $judoka->club?->naam ?? '-' }}</td>
                            <td class="px-2 py-1 text-center text-xs">
                                {{ $judoka->gewicht_gewogen ? number_format($judoka->gewicht_gewogen, 1) : ($judoka->gewicht ? number_format($judoka->gewicht, 1) : '-') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endforeach
        </div>
        @endif
    @endforeach
@endforeach

@if($blokken->flatMap(fn($b) => $b->poules)->isEmpty())
<p class="text-gray-500 text-center py-8">{{ __('Geen poules gevonden') }}</p>
@endif
@endsection

@section('scripts')
<script>
    function toggleMat(matId, checked) {
        document.querySelectorAll('.mat-page[data-mat-id="' + matId + '"]').forEach(function(el) {
            if (checked) {
                el.classList.remove('print-exclude');
            } else {
                el.classList.add('print-exclude');
            }
        });
        updateMatCount();
    }

    function selectAllMats(checked) {
        document.querySelectorAll('.mat-toolbar input[type="checkbox"]').forEach(function(cb) {
            cb.checked = checked;
            cb.dispatchEvent(new Event('change'));
        });
    }

    function updateMatCount() {
        var total = document.querySelectorAll('.mat-page').length;
        var selected = document.querySelectorAll('.mat-page:not(.print-exclude)').length;
        var el = document.getElementById('mat-count');
        if (el) el.textContent = selected + ' / ' + total + ' pagina\'s';
    }

    updateMatCount();
</script>
@endsection
