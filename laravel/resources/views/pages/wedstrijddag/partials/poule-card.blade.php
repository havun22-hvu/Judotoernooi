@php
    // Calculate active judokas and problems
    $isEliminatie = $poule->type === 'eliminatie';
    $aantalActief = $poule->judokas->filter(fn($j) => $j->isActief($wegingGesloten))->count();
    // Total judokas (excluding absent) - for button logic
    $aantalTotaal = $poule->judokas->filter(fn($j) => $j->aanwezigheid !== 'afwezig')->count();
    // Use actual count if poule has wedstrijden, otherwise estimate
    if ($poule->wedstrijden->count() > 0) {
        $aantalWedstrijden = $poule->wedstrijden->count();
    } elseif ($isEliminatie) {
        $aantalWedstrijden = $poule->berekenAantalWedstrijden($aantalActief);
    } else {
        // Estimate based on toernooi settings
        $toernooi = $poule->toernooi;
        $baseWedstrijden = $aantalActief >= 2 ? ($aantalActief * ($aantalActief - 1)) / 2 : 0;
        if ($aantalActief == 2 && $toernooi?->best_of_three_bij_2) {
            $aantalWedstrijden = 3;
        } elseif ($aantalActief == 2 && ($toernooi?->dubbel_bij_2_judokas ?? true)) {
            $aantalWedstrijden = 2;
        } elseif ($aantalActief == 3 && ($toernooi?->dubbel_bij_3_judokas ?? true)) {
            $aantalWedstrijden = 6;
        } else {
            $aantalWedstrijden = $baseWedstrijden;
        }
    }
    // Problematisch: normale poule <3 judoka's OF eliminatie <8 judoka's
    $isProblematisch = $aantalActief > 0 && $aantalActief < ($isEliminatie ? 8 : 3);

    // Check gewichtsprobleem for dynamische poules
    $heeftGewichtsprobleem = false;
    if ($poule->isDynamisch()) {
        $range = $poule->getGewichtsRange();
        if ($range && ($range['max_kg'] - $range['min_kg']) > ($poule->max_kg_verschil ?? 3)) {
            $heeftGewichtsprobleem = true;
        }
    }

    // Collect afwezige judokas for info tooltip
    $afwezigeJudokas = $poule->judokas->filter(fn($j) => !$j->isActief($wegingGesloten));
    $overpoulers = $poule->judokas->filter(fn($j) =>
        $j->gewicht_gewogen !== null && !$j->isGewichtBinnenKlasse(null, $tolerantie) && $j->isActief($wegingGesloten)
    );
    $verwijderdeTekst = collect();
    foreach ($afwezigeJudokas as $j) {
        $verwijderdeTekst->push($j->naam . ' (afwezig)');
    }
    foreach ($overpoulers as $j) {
        $verwijderdeTekst->push($j->naam . ' (afwijkend gewicht)');
    }

    // Poule titel - gebruik model methode die dynamisch range berekent
    $pouleTitel = $poule->getDisplayTitel();
    $pouleIsDynamisch = $poule->isDynamisch() || empty($poule->gewichtsklasse);
@endphp
<div
    id="poule-{{ $poule->id }}"
    class="border rounded-lg bg-white transition-colors poule-card {{ $aantalActief === 0 ? 'opacity-50' : '' }} {{ $isProblematisch ? 'border-2 border-red-300' : '' }} {{ $heeftGewichtsprobleem && !$isProblematisch ? 'border-2 border-orange-400' : '' }}"
    data-poule-id="{{ $poule->id }}"
    data-poule-nummer="{{ $poule->nummer }}"
    data-poule-leeftijdsklasse="{{ $poule->leeftijdsklasse }}"
    data-poule-gewichtsklasse="{{ $poule->gewichtsklasse }}"
    data-actief="{{ $aantalActief }}"
>
    @php
        // Problematisch krijgt voorrang (rood), dan eliminatie (oranje), dan gewichtsprobleem (oranje), anders blauw
        $headerBg = $aantalActief === 0 ? 'bg-gray-500' : ($isProblematisch ? 'bg-red-600' : ($isEliminatie ? 'bg-orange-600' : ($heeftGewichtsprobleem ? 'bg-orange-600' : 'bg-blue-700')));
        $headerSubtext = $aantalActief === 0 ? 'text-gray-300' : ($isProblematisch ? 'text-red-200' : ($isEliminatie ? 'text-orange-200' : ($heeftGewichtsprobleem ? 'text-orange-200' : 'text-blue-200')));
    @endphp
    <div class="{{ $headerBg }} text-white px-3 py-2 poule-header flex justify-between items-start rounded-t-lg">
        <div class="flex-1">
            <div class="font-bold text-sm">@if($isEliminatie)⚔️ @endif#{{ $poule->nummer }} {{ $pouleTitel }}</div>
            <div class="text-xs {{ $headerSubtext }} poule-stats"><span class="poule-actief">{{ $aantalActief }}</span> judoka's ~<span class="poule-wedstrijden">{{ $aantalWedstrijden }}</span> wedstrijden</div>
        </div>
        <div class="flex items-center gap-1 flex-shrink-0">
            @if($verwijderdeTekst->isNotEmpty())
            <div class="relative" x-data="{ show: false }">
                <span @click="show = !show" @click.away="show = false" class="info-icon cursor-pointer text-base opacity-80 hover:opacity-100">ⓘ</span>
                <div x-show="show" x-transition class="absolute bottom-full right-0 mb-2 bg-gray-900 text-white text-xs rounded px-3 py-2 whitespace-pre-line z-[9999] min-w-[200px] shadow-xl pointer-events-none">{{ $verwijderdeTekst->join("\n") }}</div>
            </div>
            @endif
            @if($isEliminatie)
            {{-- Eliminatie: omzetten dropdown --}}
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="bg-orange-500 hover:bg-orange-400 text-white text-xs px-2 py-0.5 rounded">⚙</button>
                <div x-show="open" @click.away="open = false" class="absolute right-0 mt-1 bg-white border rounded-lg shadow-lg z-10 min-w-[160px]">
                    <button onclick="zetOmNaarPoules({{ $poule->id }}, 'poules')" class="w-full text-left px-3 py-2 hover:bg-gray-100 text-sm text-gray-700">Naar poules</button>
                    <button onclick="zetOmNaarPoules({{ $poule->id }}, 'poules_kruisfinale')" class="w-full text-left px-3 py-2 hover:bg-gray-100 text-sm text-gray-700 border-t">+ kruisfinale</button>
                </div>
            </div>
            @elseif($aantalTotaal > 0)
            @php $isDoorgestuurd = $poule->doorgestuurd_op !== null; @endphp
            <button
                onclick="naarZaaloverzichtPoule({{ $poule->id }}, this)"
                class="px-2 py-0.5 text-xs rounded transition-all {{ $isDoorgestuurd ? 'bg-green-500 hover:bg-green-600' : 'bg-blue-500 hover:bg-blue-600' }}"
                title="{{ $isDoorgestuurd ? 'Doorgestuurd' : 'Naar zaaloverzicht' }}"
            >{{ $isDoorgestuurd ? '✓' : '→' }}</button>
            @else
            <button
                onclick="verwijderPoule({{ $poule->id }}, '{{ $poule->nummer }}')"
                class="delete-poule-btn w-6 h-6 flex items-center justify-center bg-black hover:bg-gray-800 text-white rounded-full text-sm font-bold"
                title="Verwijder lege poule"
            >×</button>
            @endif
        </div>
    </div>
    <div class="divide-y divide-gray-100 sortable-poule min-h-[40px]" data-poule-id="{{ $poule->id }}">
        @foreach($poule->judokas as $judoka)
        @php
            $isGewogen = $judoka->gewicht_gewogen !== null;
            $isAfwezig = !$judoka->isActief($wegingGesloten);
            // Gebruik centrale methode voor gewicht check (werkt voor vaste klassen)
            $isAfwijkendGewicht = $isGewogen && !$judoka->isGewichtBinnenKlasse(null, $tolerantie);
            $heeftProbleem = $isAfwijkendGewicht;
        @endphp
        @if($isAfwezig)
            @continue
        @endif
        <div
            class="px-2 py-1.5 text-sm judoka-item hover:bg-blue-50 cursor-move group {{ $heeftProbleem ? 'bg-orange-50 border-l-4 border-orange-400' : '' }}"
            data-judoka-id="{{ $judoka->id }}"
            draggable="true"
        >
            <div class="flex justify-between items-start">
                <div class="flex items-center gap-1 flex-1 min-w-0">
                    @if($heeftProbleem)
                        <span class="text-orange-500 text-xs flex-shrink-0" title="{{ $isVerkeerdePoule ? 'Verkeerde gewichtsklasse' : 'Afwijkend gewicht' }}">⚠</span>
                    @elseif($isGewogen)
                        <span class="text-green-500 text-xs flex-shrink-0">●</span>
                    @endif
                    <div class="min-w-0">
                        <div class="font-medium {{ $heeftProbleem ? 'text-orange-800' : 'text-gray-800' }} truncate">{{ $judoka->naam }} <span class="text-gray-400 font-normal">({{ $judoka->leeftijd }}j)</span></div>
                        <div class="text-xs text-gray-500 truncate">{{ $judoka->club?->naam ?? '-' }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-1 flex-shrink-0">
                    <div class="text-right text-xs">
                        <div class="{{ $heeftProbleem ? 'text-orange-600 font-bold' : 'text-gray-600' }} font-medium">{{ $judoka->gewicht_gewogen ? $judoka->gewicht_gewogen . ' kg' : ($judoka->gewicht ? $judoka->gewicht . ' kg' : '-') }}</div>
                        <div class="text-gray-400">{{ \App\Enums\Band::stripKyu($judoka->band ?? '') }}</div>
                    </div>
                    <button
                        onclick="event.stopPropagation(); openZoekMatchWedstrijddag({{ $judoka->id }}, {{ $poule->id }})"
                        class="text-gray-400 hover:text-blue-600 p-1 rounded hover:bg-blue-50 transition-colors opacity-0 group-hover:opacity-100"
                        title="Zoek geschikte poule"
                    >🔍</button>
                    <button
                        onclick="event.stopPropagation(); meldJudokaAf({{ $judoka->id }}, '{{ addslashes($judoka->naam) }}')"
                        class="text-gray-400 hover:text-red-600 p-1 rounded hover:bg-red-50 transition-colors opacity-0 group-hover:opacity-100"
                        title="Afmelden (kan niet deelnemen)"
                    >✕</button>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
