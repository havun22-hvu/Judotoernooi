{{--
    B-bracket rendering partial (mirrored layout).
    Vervangt de JS renderBBracketMirrored() functie.

    Variabelen:
    - $layout: array uit BracketLayoutService::berekenBBracketLayout()
        - niveaus: array van niveaus met sub_rondes
        - totale_hoogte: int
        - medaille_data: array met brons posities
        - rondes_flat: platte lijst voor header
        - start_ronde: int (0 = alle niveaus)
        - totaal_niveaus: int (totaal aantal niveaus incl. verborgen)
    - $pouleId: poule ID
    - $isLocked: boolean
    - $debugSlots: boolean (optioneel)
--}}
@php
    $niveaus = $layout['niveaus'] ?? [];
    $totaleHoogte = $layout['totale_hoogte'] ?? 300;
    $medailleData = $layout['medaille_data'] ?? [];
    $rondesFlat = $layout['rondes_flat'] ?? [];
    $startRonde = $layout['start_ronde'] ?? 0;
    $totaalNiveaus = $layout['totaal_niveaus'] ?? count($niveaus);
    $debugSlots = $debugSlots ?? false;
@endphp

@if(empty($niveaus))
    <div class="text-gray-500">{{ __('Geen wedstrijden') }}</div>
@else
    {{-- Header met ronde namen --}}
    <div class="flex mb-4 py-1 relative z-10 bg-white bracket-round-header">
        @foreach($niveaus as $niveauIdx => $niveau)
            @foreach($niveau['sub_rondes'] as $srIdx => $sr)
                <div class="w-32 flex-shrink-0 text-center text-xs font-bold text-purple-600 px-1">
                    {{ $sr['naam'] }}
                </div>
                @if($srIdx < count($niveau['sub_rondes']) - 1 || $niveauIdx < count($niveaus) - 1)
                    <div class="w-4 flex-shrink-0"></div>
                @endif
            @endforeach
        @endforeach
        <div class="w-32 flex-shrink-0 text-center text-xs font-bold text-yellow-600 px-1">🥉</div>
    </div>

    {{-- Main container --}}
    <div class="flex" style="height: {{ $totaleHoogte }}px;" id="bracket-{{ $pouleId }}-B"
         data-start-ronde="{{ $startRonde }}" data-totaal-rondes="{{ $totaalNiveaus }}">
        @foreach($niveaus as $niveauIdx => $niveau)
            @foreach($niveau['sub_rondes'] as $subRondeIdx => $ronde)
                <div class="relative flex-shrink-0 w-32">
                    @foreach($ronde['wedstrijden'] as $wedIdx => $wed)
                        @include('pages.mat.partials._bracket-potje', [
                            'wed' => $wed,
                            'topPos' => $wed['_layout']['top'],
                            'isLastRound' => $wed['_layout']['is_last_column'] ?? false,
                            'pouleId' => $pouleId,
                            'isLocked' => $isLocked,
                            'groep' => 'B',
                            'isRonde2' => $wed['_layout']['is_ronde2'] ?? false,
                            'aRondeNaam' => $wed['_layout']['a_ronde_naam'] ?? '',
                            'herkomstWit' => $wed['_layout']['herkomst_wit'] ?? '',
                            'herkomstBlauw' => $wed['_layout']['herkomst_blauw'] ?? '',
                            'debugSlots' => $debugSlots,
                            'visualSlotWit' => $wed['_layout']['visual_slot_wit'],
                            'visualSlotBlauw' => $wed['_layout']['visual_slot_blauw'],
                            'rondeIdx' => 99,
                        ])
                    @endforeach
                </div>

                {{-- Spacer --}}
                @if(!($ronde['_is_last_column'] ?? false))
                    <div class="w-4 flex-shrink-0"></div>
                @endif
            @endforeach
        @endforeach

        {{-- Brons medaille slots --}}
        @include('pages.mat.partials._bracket-medailles', [
            'groep' => 'B',
            'medailleData' => $medailleData,
            'pouleId' => $pouleId,
        ])
    </div>
@endif
