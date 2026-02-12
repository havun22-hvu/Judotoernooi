{{--
    A-bracket rendering partial (server-side).
    Vervangt de JS renderBracket() functie.

    Variabelen:
    - $layout: array uit BracketLayoutService::berekenABracketLayout()
        - rondes: array van rondes met wedstrijden + _layout
        - totale_hoogte: int
        - medaille_data: array met goud/zilver posities
        - start_ronde: int (0 = alle rondes)
        - totaal_rondes: int (totaal aantal rondes incl. verborgen)
    - $pouleId: poule ID
    - $isLocked: boolean
    - $debugSlots: boolean (optioneel)
--}}
@php
    $rondes = $layout['rondes'] ?? [];
    $totaleHoogte = $layout['totale_hoogte'] ?? 300;
    $medailleData = $layout['medaille_data'] ?? [];
    $startRonde = $layout['start_ronde'] ?? 0;
    $totaalRondes = $layout['totaal_rondes'] ?? count($rondes);
    $debugSlots = $debugSlots ?? false;
@endphp

@if(empty($rondes))
    <div class="text-gray-500">{{ __('Geen wedstrijden') }}</div>
@else
    {{-- Navigatie pijltjes (alleen als >3 rondes totaal) --}}
    @if($totaalRondes > 3)
        <div class="flex items-center gap-2 mb-1">
            <button type="button"
                    class="px-2 py-0.5 text-sm bg-purple-100 hover:bg-purple-200 text-purple-700 rounded disabled:opacity-30 disabled:cursor-not-allowed"
                    onclick="bracketNavigate({{ $pouleId }}, -1)"
                    {{ $startRonde === 0 ? 'disabled' : '' }}>
                &larr;
            </button>
            <span class="text-xs text-gray-500">
                @if($startRonde === 0)
                    Alle rondes
                @else
                    Vanaf {{ $rondes[0]['naam'] }}
                @endif
            </span>
            <button type="button"
                    class="px-2 py-0.5 text-sm bg-purple-100 hover:bg-purple-200 text-purple-700 rounded disabled:opacity-30 disabled:cursor-not-allowed"
                    onclick="bracketNavigate({{ $pouleId }}, 1)"
                    {{ $startRonde >= $totaalRondes - 2 ? 'disabled' : '' }}>
                &rarr;
            </button>
        </div>
    @endif

    {{-- Header met ronde namen --}}
    <div class="flex mb-1 py-1 relative z-10 bg-white bracket-round-header">
        @foreach($rondes as $rondeIdx => $ronde)
            <div class="w-32 flex-shrink-0 text-center text-xs font-bold text-purple-600">
                {{ $ronde['naam'] }}
            </div>
            @if($rondeIdx < count($rondes) - 1)
                <div class="w-2 flex-shrink-0"></div>
            @endif
        @endforeach
        <div class="w-32 flex-shrink-0 text-center text-xs font-bold text-yellow-600">🏆</div>
    </div>

    {{-- Bracket container --}}
    <div class="flex" style="height: {{ $totaleHoogte }}px;" id="bracket-{{ $pouleId }}-A"
         data-start-ronde="{{ $startRonde }}">
        @foreach($rondes as $rondeIdx => $ronde)
            <div class="relative flex-shrink-0 w-32">
                @foreach($ronde['wedstrijden'] as $wedIdx => $wed)
                    @include('pages.mat.partials._bracket-potje', [
                        'wed' => $wed,
                        'topPos' => $wed['_layout']['top'],
                        'isLastRound' => $wed['_layout']['is_last_round'],
                        'pouleId' => $pouleId,
                        'isLocked' => $isLocked,
                        'groep' => 'A',
                        'debugSlots' => $debugSlots,
                        'visualSlotWit' => $wed['_layout']['visual_slot_wit'],
                        'visualSlotBlauw' => $wed['_layout']['visual_slot_blauw'],
                    ])
                @endforeach
            </div>
            @if($rondeIdx < count($rondes) - 1)
                <div class="w-2 flex-shrink-0"></div>
            @endif
        @endforeach

        {{-- Medaille slots --}}
        @include('pages.mat.partials._bracket-medailles', [
            'groep' => 'A',
            'medailleData' => $medailleData,
            'pouleId' => $pouleId,
        ])
    </div>
@endif
