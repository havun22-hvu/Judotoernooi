@php
    /** @var array $layout — output of BracketLayoutService::berekenBBracketLayout(). */
    $niveaus = $layout['niveaus'] ?? [];
    $totaleHoogte = max((int) ($layout['totale_hoogte'] ?? 300), 100);

    // Flatten niveaus → kolommen (één sub_ronde per kolom).
    $kolommen = [];
    foreach ($niveaus as $niveau) {
        foreach ($niveau['sub_rondes'] ?? [] as $subRonde) {
            $kolommen[] = $subRonde;
        }
    }

    $kolomBreedte = 200;
    $kolomGap = 20;
    $headerHoogte = 24;
    $potjeBreedte = 180;
    $potjeHoogte = 80;
    $potjeGap = 8;
    $potjeNaamRegel = 32;

    $aantalKolommen = max(1, count($kolommen));
    $svgBreedte = $aantalKolommen * ($kolomBreedte + $kolomGap);
    $svgHoogte = $totaleHoogte + $headerHoogte + 20;
@endphp

@if (empty($kolommen))
    <div class="geen-wedstrijden">{{ __('Geen B-wedstrijden voor deze bracket') }}</div>
@else
    <svg class="bracket-svg" xmlns="http://www.w3.org/2000/svg"
         viewBox="0 0 {{ $svgBreedte }} {{ $svgHoogte }}"
         preserveAspectRatio="xMidYMid meet">
        {{-- Headers per sub-ronde --}}
        @foreach ($kolommen as $kolomIdx => $kolom)
            @php $x = $kolomIdx * ($kolomBreedte + $kolomGap) + $potjeBreedte / 2; @endphp
            <text class="ronde-header" x="{{ $x }}" y="{{ $headerHoogte - 8 }}">{{ $kolom['naam'] ?? $kolom['ronde'] ?? '' }}</text>
        @endforeach

        {{-- Potjes per kolom: stack van boven naar beneden, equal spacing. --}}
        @foreach ($kolommen as $kolomIdx => $kolom)
            @php
                $kolomX = $kolomIdx * ($kolomBreedte + $kolomGap);
                $weds = $kolom['wedstrijden'] ?? [];
                $count = max(1, count($weds));
                $stapHoogte = ($svgHoogte - $headerHoogte - 20) / $count;
            @endphp
            @foreach ($weds as $wedIdx => $wed)
                @php
                    $top = $headerHoogte + $wedIdx * $stapHoogte + ($stapHoogte - $potjeHoogte) / 2;

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
                    <rect class="potje-vakje" x="0" y="0" width="{{ $potjeBreedte - 30 }}" height="{{ $potjeNaamRegel }}"/>
                    <text class="potje-naam {{ $witClass }}" x="6" y="14">{{ $witNaam ?? '__________________' }}</text>
                    @if ($witClub)
                        <text class="potje-club" x="6" y="26">{{ $witClub }}</text>
                    @endif
                    <rect class="potje-score-vakje" x="{{ $potjeBreedte - 30 }}" y="0" width="30" height="{{ $potjeNaamRegel }}"/>
                    @if (!is_null($wed['uitslag_wit'] ?? null))
                        <text class="potje-score" x="{{ $potjeBreedte - 15 }}" y="20">{{ $wed['uitslag_wit'] }}</text>
                    @endif

                    <rect class="potje-vakje" x="0" y="{{ $potjeHoogte - $potjeNaamRegel }}" width="{{ $potjeBreedte - 30 }}" height="{{ $potjeNaamRegel }}"/>
                    <text class="potje-naam {{ $blauwClass }}" x="6" y="{{ $potjeHoogte - $potjeNaamRegel + 14 }}">{{ $blauwNaam ?? '__________________' }}</text>
                    @if ($blauwClub)
                        <text class="potje-club" x="6" y="{{ $potjeHoogte - $potjeNaamRegel + 26 }}">{{ $blauwClub }}</text>
                    @endif
                    <rect class="potje-score-vakje" x="{{ $potjeBreedte - 30 }}" y="{{ $potjeHoogte - $potjeNaamRegel }}" width="30" height="{{ $potjeNaamRegel }}"/>
                    @if (!is_null($wed['uitslag_blauw'] ?? null))
                        <text class="potje-score" x="{{ $potjeBreedte - 15 }}" y="{{ $potjeHoogte - $potjeNaamRegel + 20 }}">{{ $wed['uitslag_blauw'] }}</text>
                    @endif

                    @if ($kolomIdx < count($kolommen) - 1)
                        <line class="potje-lijn" x1="{{ $potjeBreedte }}" y1="{{ $potjeHoogte / 2 }}" x2="{{ $potjeBreedte + $kolomGap / 2 }}" y2="{{ $potjeHoogte / 2 }}"/>
                    @endif
                </g>
            @endforeach
        @endforeach

        {{-- Brons-medailles (laatste niveau winnaars) --}}
        @php $medailles = $layout['medaille_data'] ?? []; @endphp
        @if (!empty($medailles['brons_1']))
            @php $x = (count($kolommen) - 1) * ($kolomBreedte + $kolomGap) + $potjeBreedte + 4; @endphp
            <text class="medaille medaille-brons" x="{{ $x }}" y="{{ ($medailles['brons_1']['top'] ?? 0) + $headerHoogte + 14 }}">🥉 {{ $medailles['brons_1']['winnaar']['naam'] ?? '' }}</text>
            @if (!empty($medailles['brons_2']))
                <text class="medaille medaille-brons" x="{{ $x }}" y="{{ ($medailles['brons_2']['top'] ?? 60) + $headerHoogte + 14 }}">🥉 {{ $medailles['brons_2']['winnaar']['naam'] ?? '' }}</text>
            @endif
        @endif
    </svg>
@endif
