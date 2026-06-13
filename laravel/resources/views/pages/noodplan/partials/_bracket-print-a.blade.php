@php
    /** @var array $layout — output of BracketLayoutService::berekenABracketLayout(). */
    $rondes = $layout['rondes'] ?? [];
    $totaleHoogte = max((int) ($layout['totale_hoogte'] ?? 300), 100);

    // Layout grid (SVG units).
    $kolomBreedte = 200;
    $kolomGap = 20;
    $headerHoogte = 24;
    $potjeBreedte = 180;
    $potjeHoogte = 80;
    $potjeNaamRegel = 32;

    $aantalKolommen = max(1, count($rondes));
    $svgBreedte = $aantalKolommen * ($kolomBreedte + $kolomGap);
    $svgHoogte = $totaleHoogte + $headerHoogte + 20;
@endphp

@if (empty($rondes))
    <div class="geen-wedstrijden">{{ __('Geen wedstrijden in deze A-bracket') }}</div>
@else
    <svg class="bracket-svg" xmlns="http://www.w3.org/2000/svg"
         viewBox="0 0 {{ $svgBreedte }} {{ $svgHoogte }}"
         preserveAspectRatio="xMidYMid meet">
        {{-- Ronde headers --}}
        @foreach ($rondes as $rondeIdx => $ronde)
            @php $x = $rondeIdx * ($kolomBreedte + $kolomGap) + $potjeBreedte / 2; @endphp
            <text class="ronde-header" x="{{ $x }}" y="{{ $headerHoogte - 8 }}">{{ $ronde['naam'] }}</text>
        @endforeach

        {{-- Potjes per ronde --}}
        @foreach ($rondes as $rondeIdx => $ronde)
            @php $kolomX = $rondeIdx * ($kolomBreedte + $kolomGap); @endphp
            @foreach ($ronde['wedstrijden'] as $wed)
                @php
                    $top = ($wed['_layout']['top'] ?? 0) + $headerHoogte;
                    $witNaam = $wed['wit']['naam'] ?? null;
                    $blauwNaam = $wed['blauw']['naam'] ?? null;
                    $witClub = $wed['wit']['club'] ?? null;
                    $blauwClub = $wed['blauw']['club'] ?? null;

                    $isGespeeld = $wed['is_gespeeld'] ?? false;
                    $winnaarId = $wed['winnaar_id'] ?? null;
                    $witIsWinnaar = $isGespeeld && $winnaarId && $winnaarId === ($wed['wit']['id'] ?? null);
                    $blauwIsWinnaar = $isGespeeld && $winnaarId && $winnaarId === ($wed['blauw']['id'] ?? null);

                    $witClass = $isGespeeld && !$witIsWinnaar ? 'loser' : ($witNaam ? '' : 'empty');
                    $blauwClass = $isGespeeld && !$blauwIsWinnaar ? 'loser' : ($blauwNaam ? '' : 'empty');
                @endphp
                <g class="potje" transform="translate({{ $kolomX }}, {{ $top }})">
                    {{-- Wit (boven) --}}
                    <rect class="potje-vakje" x="0" y="0" width="{{ $potjeBreedte - 30 }}" height="{{ $potjeNaamRegel }}"/>
                    <text class="potje-naam {{ $witClass }}" x="6" y="14">{{ $witNaam ?? '__________________' }}</text>
                    @if ($witClub)
                        <text class="potje-club" x="6" y="26">{{ $witClub }}</text>
                    @endif
                    <rect class="potje-score-vakje" x="{{ $potjeBreedte - 30 }}" y="0" width="30" height="{{ $potjeNaamRegel }}"/>
                    @if (!is_null($wed['uitslag_wit'] ?? null))
                        <text class="potje-score" x="{{ $potjeBreedte - 15 }}" y="20">{{ $wed['uitslag_wit'] }}</text>
                    @endif

                    {{-- Blauw (onder) --}}
                    <rect class="potje-vakje" x="0" y="{{ $potjeHoogte - $potjeNaamRegel }}" width="{{ $potjeBreedte - 30 }}" height="{{ $potjeNaamRegel }}"/>
                    <text class="potje-naam {{ $blauwClass }}" x="6" y="{{ $potjeHoogte - $potjeNaamRegel + 14 }}">{{ $blauwNaam ?? '__________________' }}</text>
                    @if ($blauwClub)
                        <text class="potje-club" x="6" y="{{ $potjeHoogte - $potjeNaamRegel + 26 }}">{{ $blauwClub }}</text>
                    @endif
                    <rect class="potje-score-vakje" x="{{ $potjeBreedte - 30 }}" y="{{ $potjeHoogte - $potjeNaamRegel }}" width="30" height="{{ $potjeNaamRegel }}"/>
                    @if (!is_null($wed['uitslag_blauw'] ?? null))
                        <text class="potje-score" x="{{ $potjeBreedte - 15 }}" y="{{ $potjeHoogte - $potjeNaamRegel + 20 }}">{{ $wed['uitslag_blauw'] }}</text>
                    @endif

                    {{-- Connector to next round (skip last round) --}}
                    @if (! ($wed['_layout']['is_last_round'] ?? false))
                        <line class="potje-lijn" x1="{{ $potjeBreedte }}" y1="{{ $potjeHoogte / 2 }}" x2="{{ $potjeBreedte + $kolomGap / 2 }}" y2="{{ $potjeHoogte / 2 }}"/>
                    @endif
                </g>
            @endforeach
        @endforeach

        {{-- Medaille labels (alleen finale) --}}
        @php $medaille = $layout['medaille_data'] ?? []; @endphp
        @if (!empty($medaille['goud']))
            @php
                $finaleKolom = max(0, count($rondes) - 1) * ($kolomBreedte + $kolomGap) + $potjeBreedte + 4;
                $goudY = ($medaille['goud']['top'] ?? 0) + $headerHoogte + 14;
                $zilverY = ($medaille['zilver']['top'] ?? 40) + $headerHoogte + 14;
            @endphp
            <text class="medaille medaille-goud" x="{{ $finaleKolom }}" y="{{ $goudY }}">🥇 {{ $medaille['goud']['winnaar']['naam'] ?? '' }}</text>
            <text class="medaille medaille-zilver" x="{{ $finaleKolom }}" y="{{ $zilverY }}">🥈 {{ $medaille['zilver']['verliezer']['naam'] ?? '' }}</text>
        @endif
    </svg>
@endif
