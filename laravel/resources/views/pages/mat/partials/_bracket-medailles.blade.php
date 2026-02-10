{{--
    Medaille slots voor eliminatie bracket (goud/zilver voor A, brons voor B).

    Variabelen:
    - $groep: 'A' of 'B'
    - $medailleData: array met positie/winnaar/verliezer info
    - $pouleId: poule ID
--}}
@if($groep === 'A')
    <div class="relative flex-shrink-0 w-32">
        {{-- Goud (1e plaats) --}}
        <div class="absolute w-32" style="top: {{ $medailleData['goud']['top'] }}px;">
            <div class="w-32 h-7 bg-yellow-100 border border-yellow-400 rounded flex items-center px-1 text-xs font-bold truncate bracket-drop bracket-medal {{ !($medailleData['goud']['winnaar'] ?? null) ? 'cursor-pointer' : '' }}"
                 id="medaille-{{ $pouleId }}-goud"
                 data-drop-handler="dropOpMedaille"
                 data-finale-id="{{ $medailleData['goud']['finale_id'] ?? 'null' }}"
                 data-medaille="goud"
                 data-poule-id="{{ $pouleId }}">
                @if($medailleData['goud']['winnaar'] ?? null)
                    🥇 {{ $medailleData['goud']['winnaar']['naam'] }}
                @else
                    🥇 {{ __('Sleep winnaar hier') }}
                @endif
            </div>
        </div>
        {{-- Zilver (2e plaats) --}}
        <div class="absolute w-32" style="top: {{ $medailleData['zilver']['top'] }}px;">
            <div class="w-32 h-7 bg-gray-200 border border-gray-400 rounded flex items-center px-1 text-xs truncate bracket-drop bracket-medal {{ !($medailleData['zilver']['verliezer'] ?? null) ? 'cursor-pointer' : '' }}"
                 id="medaille-{{ $pouleId }}-zilver"
                 data-drop-handler="dropOpMedaille"
                 data-finale-id="{{ $medailleData['zilver']['finale_id'] ?? 'null' }}"
                 data-medaille="zilver"
                 data-poule-id="{{ $pouleId }}">
                @if($medailleData['zilver']['verliezer'] ?? null)
                    🥈 {{ $medailleData['zilver']['verliezer']['naam'] }}
                @else
                    🥈 {{ __('Sleep verliezer hier') }}
                @endif
            </div>
        </div>
    </div>
@else
    {{-- B-bracket: brons medailles --}}
    <div class="relative flex-shrink-0 w-32">
        @if(isset($medailleData['brons_1']))
            <div class="absolute w-32" style="top: {{ $medailleData['brons_1']['top'] }}px;">
                <div class="w-32 h-7 bg-amber-100 border border-amber-400 rounded flex items-center px-1 text-xs truncate bracket-drop bracket-medal"
                     id="medaille-{{ $pouleId }}-brons1"
                     data-drop-handler="dropOpMedaille"
                     data-finale-id="{{ $medailleData['brons_1']['wedstrijd_id'] ?? 'null' }}"
                     data-medaille="brons"
                     data-poule-id="{{ $pouleId }}">
                    @if($medailleData['brons_1']['winnaar'] ?? null)
                        🥉 {{ $medailleData['brons_1']['winnaar']['naam'] }}
                    @else
                        🥉 {{ __('Sleep winnaar hier') }}
                    @endif
                </div>
            </div>
        @endif
        @if(isset($medailleData['brons_2']))
            <div class="absolute w-32" style="top: {{ $medailleData['brons_2']['top'] }}px;">
                <div class="w-32 h-7 bg-amber-100 border border-amber-400 rounded flex items-center px-1 text-xs truncate bracket-drop bracket-medal"
                     id="medaille-{{ $pouleId }}-brons2"
                     data-drop-handler="dropOpMedaille"
                     data-finale-id="{{ $medailleData['brons_2']['wedstrijd_id'] ?? 'null' }}"
                     data-medaille="brons"
                     data-poule-id="{{ $pouleId }}">
                    @if($medailleData['brons_2']['winnaar'] ?? null)
                        🥉 {{ $medailleData['brons_2']['winnaar']['naam'] }}
                    @else
                        🥉 {{ __('Sleep winnaar hier') }}
                    @endif
                </div>
            </div>
        @endif
    </div>
@endif
