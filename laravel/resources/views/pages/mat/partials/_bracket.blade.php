{{--
    A-bracket rendering partial (server-side).
    Vervangt de JS renderBracket() functie.

    Variabelen:
    - $layout: array uit BracketLayoutService::berekenABracketLayout()
        - rondes: array van rondes met wedstrijden + _layout
        - totale_hoogte: int
        - medaille_data: array met goud/zilver posities
    - $pouleId: poule ID
    - $isLocked: boolean
    - $debugSlots: boolean (optioneel)
--}}
@php
    $rondes = $layout['rondes'] ?? [];
    $totaleHoogte = $layout['totale_hoogte'] ?? 300;
    $medailleData = $layout['medaille_data'] ?? [];
    $debugSlots = $debugSlots ?? false;
@endphp

@if(empty($rondes))
    <div class="text-gray-500">{{ __('Geen wedstrijden') }}</div>
@else
    {{-- Header met ronde namen --}}
    <div class="flex mb-1">
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
    <div class="flex" style="height: {{ $totaleHoogte }}px;" id="bracket-{{ $pouleId }}-A">
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
