@php
    // Calculate active judokas and problems
    $isEliminatie = $poule->type === 'eliminatie';
    $aantalActief = $poule->judokas->filter(fn($j) => $j->isActief($wegingGesloten))->count();
    $aantalWedstrijden = $isEliminatie
        ? $poule->berekenAantalWedstrijden($aantalActief)
        : ($aantalActief >= 2 ? ($aantalActief * ($aantalActief - 1)) / 2 : 0);
    $isProblematisch = !$isEliminatie && $aantalActief > 0 && $aantalActief < 3;

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

    // Poule titel - formaat: Label / leeftijd / gewicht
    $pouleIsDynamisch = $poule->isDynamisch();
    $pouleRange = $pouleIsDynamisch ? $poule->getGewichtsRange() : null;
    if ($pouleIsDynamisch && $pouleRange) {
        // Haal label en leeftijd uit titel (bijv. "Jeugd 5-7j 16.1-18.3kg" -> "Jeugd" en "5-7j")
        $titelZonderKg = preg_replace('/\s*[\d.]+-[\d.]+kg\s*$/', '', $poule->titel ?? '');
        // Split label en leeftijd
        if (preg_match('/^(.+?)\s+(\d+-\d+j)$/', trim($titelZonderKg), $matches)) {
            $pouleTitel = $matches[1] . ' / ' . $matches[2] . ' / ' . round($pouleRange['min_kg'], 1) . '-' . round($pouleRange['max_kg'], 1) . 'kg';
        } else {
            $pouleTitel = $titelZonderKg . ' / ' . round($pouleRange['min_kg'], 1) . '-' . round($pouleRange['max_kg'], 1) . 'kg';
        }
    } elseif ($poule->titel) {
        // Formatteer bestaande titel met slashes
        $titel = $poule->titel;
        // Probeer "Label Xj gewicht" te parsen en te formatteren
        if (preg_match('/^(.+?)\s+(\d+-?\d*j)\s+(.+)$/', $titel, $matches)) {
            $pouleTitel = $matches[1] . ' / ' . $matches[2] . ' / ' . $matches[3];
        } else {
            $pouleTitel = $titel;
        }
    } else {
        $pouleTitel = $poule->leeftijdsklasse . ' / ' . $poule->gewichtsklasse;
    }
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
        $headerBg = $aantalActief === 0 ? 'bg-gray-500' : ($isEliminatie ? 'bg-orange-600' : ($isProblematisch ? 'bg-red-600' : ($heeftGewichtsprobleem ? 'bg-orange-600' : 'bg-blue-700')));
        $headerSubtext = $aantalActief === 0 ? 'text-gray-300' : ($isEliminatie ? 'text-orange-200' : ($isProblematisch ? 'text-red-200' : ($heeftGewichtsprobleem ? 'text-orange-200' : 'text-blue-200')));
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
            @elseif($aantalActief > 0)
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
            $isVerkeerdePoule = false;
            if (!$pouleIsDynamisch && $poule->gewichtsklasse) {
                $judokaGewicht = $judoka->gewicht_gewogen ?? $judoka->gewicht ?? 0;
                $isPlusKlasse = str_starts_with($poule->gewichtsklasse, '+');
                $pouleLimiet = floatval(preg_replace('/[^0-9.]/', '', $poule->gewichtsklasse));
                if ($isPlusKlasse) {
                    $isVerkeerdePoule = $judokaGewicht < ($pouleLimiet - $tolerantie);
                } else {
                    $isVerkeerdePoule = $judokaGewicht > ($pouleLimiet + $tolerantie);
                }
            }
            $isAfwijkendGewicht = !$pouleIsDynamisch && $isGewogen && !$judoka->isGewichtBinnenKlasse(null, $tolerantie);
            $heeftProbleem = $isVerkeerdePoule || $isAfwijkendGewicht;
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
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
