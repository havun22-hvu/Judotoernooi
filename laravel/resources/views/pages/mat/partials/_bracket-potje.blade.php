{{--
    Individueel eliminatie potje (wit + blauw slot met connector lijnen).
    Gebruikt voor zowel A-bracket als B-bracket.

    Variabelen:
    - $wed: wedstrijd array met wit/blauw/is_gespeeld/winnaar_id etc.
    - $topPos: verticale positie in pixels
    - $isLastRound: boolean - geen connector lijnen bij laatste ronde
    - $pouleId: poule ID
    - $isLocked: boolean - bracket is locked (wedstrijd gespeeld)
    - $groep: 'A' of 'B'
    - $isRonde2: boolean - toon A-verliezer placeholder op blauw slot (B-bracket)
    - $aRondeNaam: string - naam voor placeholder (bijv. 'A-1/8')
    - $debugSlots: boolean - toon slot nummers
    - $visualSlotWit: int - visueel slot nummer voor wit
    - $visualSlotBlauw: int - visueel slot nummer voor blauw
--}}
@php
    $isBye = ($wed['uitslag_type'] ?? null) === 'bye';
    $isWitWinnaar = ($wed['is_gespeeld'] ?? false) && ($wed['winnaar_id'] ?? null) == ($wed['wit']['id'] ?? null) && !$isBye && $wed['wit'];
    $isBlauwWinnaar = ($wed['is_gespeeld'] ?? false) && ($wed['winnaar_id'] ?? null) == ($wed['blauw']['id'] ?? null) && !$isBye && $wed['blauw'];
    $ringColor = 'ring-purple-400';

    // Drag data voor wit
    $witDragData = json_encode([
        'judokaId' => $wed['wit']['id'] ?? null,
        'wedstrijdId' => $wed['id'],
        'judokaNaam' => $wed['wit']['naam'] ?? '',
        'volgendeWedstrijdId' => $wed['volgende_wedstrijd_id'] ?? null,
        'winnaarNaarSlot' => $wed['winnaar_naar_slot'] ?? null,
        'pouleIsLocked' => $isLocked,
        'pouleId' => $pouleId,
        'positie' => 'wit',
        'isWinnaar' => (bool) $isWitWinnaar,
        'isGespeeld' => (bool) ($wed['is_gespeeld'] ?? false),
    ], JSON_HEX_QUOT | JSON_HEX_APOS);

    // Drag data voor blauw
    $blauwDragData = json_encode([
        'judokaId' => $wed['blauw']['id'] ?? null,
        'wedstrijdId' => $wed['id'],
        'judokaNaam' => $wed['blauw']['naam'] ?? '',
        'volgendeWedstrijdId' => $wed['volgende_wedstrijd_id'] ?? null,
        'winnaarNaarSlot' => $wed['winnaar_naar_slot'] ?? null,
        'pouleIsLocked' => $isLocked,
        'pouleId' => $pouleId,
        'positie' => 'blauw',
        'isWinnaar' => (bool) $isBlauwWinnaar,
        'isGespeeld' => (bool) ($wed['is_gespeeld'] ?? false),
    ], JSON_HEX_QUOT | JSON_HEX_APOS);

    // Bewoner data voor swap
    $witBewoner = $wed['wit'] ? json_encode(['id' => $wed['wit']['id'], 'naam' => $wed['wit']['naam']], JSON_HEX_QUOT | JSON_HEX_APOS) : 'null';
    $blauwBewoner = $wed['blauw'] ? json_encode(['id' => $wed['blauw']['id'], 'naam' => $wed['blauw']['naam']], JSON_HEX_QUOT | JSON_HEX_APOS) : 'null';

    $debugSlots = $debugSlots ?? false;
    $isRonde2 = $isRonde2 ?? false;
    $aRondeNaam = $aRondeNaam ?? '';
    $herkomstWit = $herkomstWit ?? '';
    $herkomstBlauw = $herkomstBlauw ?? '';
    $visualSlotWit = $visualSlotWit ?? ($wed['_layout']['visual_slot_wit'] ?? 0);
    $visualSlotBlauw = $visualSlotBlauw ?? ($wed['_layout']['visual_slot_blauw'] ?? 0);
@endphp

<div class="absolute w-32 bracket-potje"
     id="potje-{{ $wed['id'] }}"
     style="top: {{ $topPos }}px;"
     data-wedstrijd-id="{{ $wed['id'] }}"
     data-poule-id="{{ $pouleId }}"
     ondblclick="window.dblClickBracket({{ $wed['id'] }}, {{ $pouleId }})">

    {{-- Wit slot (boven) --}}
    <div class="relative">
        <div class="w-32 h-9 bg-white border border-gray-300 rounded-l flex items-center text-xs drop-slot bracket-drop {{ !$isLastRound ? 'border-r-0' : '' }}"
             id="slot-{{ $wed['id'] }}-wit"
             data-drop-handler="dropJudoka"
             data-wedstrijd-id="{{ $wed['id'] }}"
             data-positie="wit"
             data-poule-id="{{ $pouleId }}"
             data-bewoner="{{ $witBewoner }}">
            @if($wed['wit'])
                <div class="w-full h-full px-1 flex items-center cursor-pointer hover:bg-green-50 bracket-judoka"
                     data-drag="{{ $witDragData }}">
                    <span class="truncate">{{ $debugSlots ? "[{$visualSlotWit}] " : '' }}{{ $wed['wit']['naam'] }}</span>
                    @if($isWitWinnaar)
                        <span class="inline-block w-2 h-2 bg-green-500 rounded-full ml-1 flex-shrink-0" title="Winnaar"></span>
                    @endif
                </div>
            @elseif($herkomstWit)
                <span class="px-1 text-gray-400 italic text-xs">&larr; {{ $herkomstWit }}</span>
            @elseif($debugSlots)
                <span class="px-1 text-gray-400">[{{ $visualSlotWit }}]</span>
            @endif
        </div>
        @unless($isLastRound)
            <div class="absolute right-0 top-0 w-4 h-full border-t border-r border-gray-400"></div>
        @endunless
    </div>

    {{-- Blauw slot (onder) --}}
    <div class="relative">
        <div class="w-32 h-9 bg-blue-50 border border-gray-300 rounded-l flex items-center text-xs drop-slot bracket-drop {{ !$isLastRound ? 'border-r-0' : '' }}"
             id="slot-{{ $wed['id'] }}-blauw"
             data-drop-handler="dropJudoka"
             data-wedstrijd-id="{{ $wed['id'] }}"
             data-positie="blauw"
             data-poule-id="{{ $pouleId }}"
             data-bewoner="{{ $blauwBewoner }}">
            @if($wed['blauw'])
                <div class="w-full h-full px-1 flex items-center cursor-pointer hover:bg-green-50 bracket-judoka"
                     data-drag="{{ $blauwDragData }}">
                    <span class="truncate">{{ $debugSlots ? "[{$visualSlotBlauw}] " : '' }}{{ $wed['blauw']['naam'] }}</span>
                    @if($isBlauwWinnaar)
                        <span class="inline-block w-2 h-2 bg-green-500 rounded-full ml-1 flex-shrink-0" title="Winnaar"></span>
                    @endif
                </div>
            @elseif($herkomstBlauw)
                <span class="px-1 text-gray-400 italic text-xs">&larr; {{ $herkomstBlauw }}</span>
            @elseif($debugSlots)
                <span class="px-1 text-gray-400">[{{ $visualSlotBlauw }}]</span>
            @endif
        </div>
        @unless($isLastRound)
            <div class="absolute right-0 top-0 w-4 h-full border-b border-r border-gray-400"></div>
        @endunless
    </div>
</div>
