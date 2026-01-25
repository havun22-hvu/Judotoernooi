@extends('layouts.app')

@section('title', 'Wedstrijddag Poules')

@section('content')
@php
    $tolerantie = $toernooi->gewicht_tolerantie ?? 0.5;
    // Onderscheid vaste gewichtsklassen vs variabele poules
    // gebruik_gewichtsklassen = true  → vaste klassen (-24kg, -27kg, etc.)
    // gebruik_gewichtsklassen = false → variabele poules (dynamisch op gewicht/leeftijd)
    $heeftVasteGewichtsklassen = $toernooi->gebruik_gewichtsklassen ?? false;
    $heeftVariabeleCategorieen = !$heeftVasteGewichtsklassen;
    // Verzamel problematische poules (< 3 of >= 6 actieve judoka's)
    $teWeinigjudokas = collect();
    $teVeelJudokas = collect();
    foreach ($blokken as $blok) {
        $wegingGesloten = $blok['weging_gesloten'] ?? false;
        foreach ($blok['categories'] as $category) {
            foreach ($category['poules'] as $poule) {
                if ($poule->type === 'kruisfinale') continue;
                $actief = $poule->judokas->filter(fn($j) => $j->isActief($wegingGesloten))->count();
                if ($actief > 0 && $actief < 3) {
                    $teWeinigjudokas->push([
                        'id' => $poule->id,
                        'nummer' => $poule->nummer,
                        'label' => $category['label'],
                        'gewichtsklasse' => $poule->gewichtsklasse,
                        'actief' => $actief,
                    ]);
                }
                if ($actief >= 6) {
                    $teVeelJudokas->push([
                        'id' => $poule->id,
                        'nummer' => $poule->nummer,
                        'label' => $category['label'],
                        'gewichtsklasse' => $poule->gewichtsklasse,
                        'actief' => $actief,
                    ]);
                }
            }
        }
    }

    // Problematische poules door gewichtsrange (dynamisch overpoulen)
    $problematischeGewichtsPoules = $problematischeGewichtsPoules ?? collect();
@endphp
<div x-data="wedstrijddagPoules()" class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold">Wedstrijddag Poules</h1>
        <button onclick="verifieerPoules()" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
            Verifieer poules
        </button>
    </div>

    <!-- Verificatie resultaat -->
    <div id="verificatie-resultaat" class="hidden"></div>

    <!-- Problematische poules (gecombineerd: te weinig/te veel judoka's + gewichtsrange) -->
    @php
        $totaalProblemen = $teWeinigjudokas->count() + $teVeelJudokas->count() + $problematischeGewichtsPoules->count();
    @endphp
    <div id="problematische-poules-container" class="{{ $totaalProblemen > 0 ? '' : 'hidden' }}" style="width: fit-content;">
    <div class="bg-red-50 border border-red-300 rounded-lg p-4">
        <h3 class="font-bold text-red-800 mb-2">Problematische poules (<span id="problematische-count">{{ $totaalProblemen }}</span>)</h3>

        {{-- Te weinig judoka's --}}
        @if($teWeinigjudokas->count() > 0)
        <p class="text-red-700 text-sm mb-2">Te weinig judoka's (&lt; 3):</p>
        <div id="problematische-links" class="flex flex-wrap gap-2 mb-3">
            @foreach($teWeinigjudokas as $p)
            <a href="#poule-{{ $p['id'] }}" onclick="scrollToPoule(event, {{ $p['id'] }})" data-probleem-poule="{{ $p['id'] }}" class="inline-flex items-center px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm hover:bg-red-200 cursor-pointer transition-colors">
                #{{ $p['nummer'] }} {{ $p['label'] }} {{ $p['gewichtsklasse'] }} (<span data-probleem-count="{{ $p['id'] }}">{{ $p['actief'] }}</span>)
            </a>
            @endforeach
        </div>
        @endif

        {{-- Te veel judoka's --}}
        @if($teVeelJudokas->count() > 0)
        <p class="text-purple-700 text-sm mb-2">Te veel judoka's (&ge; 6) - splitsen:</p>
        <div id="teveel-links" class="flex flex-wrap gap-2 mb-3">
            @foreach($teVeelJudokas as $p)
            <a href="#poule-{{ $p['id'] }}" onclick="scrollToPoule(event, {{ $p['id'] }})" data-teveel-poule="{{ $p['id'] }}" class="inline-flex items-center px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm hover:bg-purple-200 cursor-pointer transition-colors">
                #{{ $p['nummer'] }} {{ $p['label'] }} {{ $p['gewichtsklasse'] }} (<span data-teveel-count="{{ $p['id'] }}">{{ $p['actief'] }}</span>)
            </a>
            @endforeach
        </div>
        @endif

        {{-- Gewichtsrange overschreden --}}
        @if($problematischeGewichtsPoules->count() > 0)
        <p class="text-orange-700 text-sm mb-2">Gewichtsrange overschreden:</p>
        <div id="gewichtsrange-items" class="space-y-3">
            @foreach($problematischeGewichtsPoules as $pouleId => $probleem)
            @php
                $pouleInfo = null;
                foreach ($blokken as $blok) {
                    foreach ($blok['categories'] as $cat) {
                        foreach ($cat['poules'] as $p) {
                            if ($p->id == $pouleId) {
                                $pouleInfo = $p;
                                break 3;
                            }
                        }
                    }
                }
            @endphp
            @if($pouleInfo)
            <div id="gewichtsrange-poule-{{ $pouleId }}" class="bg-white border border-orange-200 rounded-lg p-3" data-gewichtsrange-poule="{{ $pouleId }}">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <a href="#poule-{{ $pouleId }}" onclick="scrollToPoule(event, {{ $pouleId }})" class="font-bold text-orange-800 hover:underline cursor-pointer">
                            #{{ $pouleInfo->nummer }} {{ $pouleInfo->titel ?? ($pouleInfo->leeftijdsklasse . ' ' . $pouleInfo->gewichtsklasse) }}
                        </a>
                        <span class="text-orange-600 text-sm ml-2">Range: {{ number_format($probleem['range'], 1) }}kg (max: {{ number_format($probleem['max_toegestaan'], 1) }}kg)</span>
                    </div>
                    <span class="text-red-600 font-bold">+{{ number_format($probleem['overschrijding'], 1) }}kg over</span>
                </div>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    @if($probleem['lichtste'])
                    <div class="flex items-center justify-between bg-blue-50 rounded px-2 py-1">
                        <span>
                            <span class="text-blue-600 font-medium">{{ number_format($probleem['min_kg'], 1) }}kg</span>
                            - {{ $probleem['lichtste']->naam }}
                        </span>
                        <button onclick="openZoekMatchWedstrijddag({{ $probleem['lichtste']->id }}, {{ $pouleId }})" class="text-blue-600 hover:text-blue-800 text-xs font-medium px-2 py-0.5 bg-blue-100 rounded">
                            Zoek match
                        </button>
                    </div>
                    @endif
                    @if($probleem['zwaarste'] && $probleem['zwaarste']->id !== $probleem['lichtste']?->id)
                    <div class="flex items-center justify-between bg-red-50 rounded px-2 py-1">
                        <span>
                            <span class="text-red-600 font-medium">{{ number_format($probleem['max_kg'], 1) }}kg</span>
                            - {{ $probleem['zwaarste']->naam }}
                        </span>
                        <button onclick="openZoekMatchWedstrijddag({{ $probleem['zwaarste']->id }}, {{ $pouleId }})" class="text-red-600 hover:text-red-800 text-xs font-medium px-2 py-0.5 bg-red-100 rounded">
                            Zoek match
                        </button>
                    </div>
                    @endif
                </div>
            </div>
            @endif
            @endforeach
        </div>
        @endif
    </div>
    </div>

    {{-- Legenda --}}
    <div class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-2 flex items-center gap-6 text-sm">
        <span class="font-medium text-gray-600">Legenda:</span>
        <span class="flex items-center gap-1"><span class="text-green-500">●</span> Gewogen</span>
        <span class="flex items-center gap-1"><span class="text-orange-500">⚠</span> Afwijkend gewicht</span>
    </div>

    <div id="blokken-container" class="space-y-6">
    @forelse($blokken as $blok)
    <div class="bg-white rounded-lg shadow w-full blok-item" x-data="{
    open: localStorage.getItem('blok-poules-{{ $blok['id'] }}') !== null
        ? localStorage.getItem('blok-poules-{{ $blok['id'] }}') === 'true'
        : {{ $loop->first ? 'true' : 'false' }}
}" x-init="$watch('open', val => localStorage.setItem('blok-poules-{{ $blok['id'] }}', val))">
        {{-- Blok header (inklapbaar) --}}
        @php
            // Tel totaal actieve judoka's en wedstrijden in dit blok
            // BELANGRIJK: Na weging sluiting zijn niet-gewogen judoka's ook afwezig
            // UITZONDERING: Kruisfinales gebruiken geplande aantallen (nog geen judokas gekoppeld)
            $wegingGesloten = $blok['weging_gesloten'] ?? false;
            $blokJudokas = 0;
            $blokWedstrijden = 0;
            foreach ($blok['categories'] as $cat) {
                foreach ($cat['poules'] as $p) {
                    if ($p->type === 'kruisfinale') {
                        $blokJudokas += $p->aantal_judokas;
                        $blokWedstrijden += $p->aantal_wedstrijden;
                    } else {
                        $actief = $p->judokas->filter(fn($j) => $j->isActief($wegingGesloten))->count();
                        $blokJudokas += $actief;
                        $blokWedstrijden += $p->berekenAantalWedstrijden($actief);
                    }
                }
            }
        @endphp
        <div class="flex items-center bg-gray-800 text-white rounded-t-lg">
            <button @click="open = !open" class="flex-1 flex justify-between items-center px-4 py-3 hover:bg-gray-700 rounded-tl-lg">
                <div class="flex items-center gap-4">
                    <span class="text-lg font-bold">Blok {{ $blok['nummer'] }}</span>
                    <span class="text-gray-300 text-sm">{{ $blokJudokas }} judoka's | {{ $blokWedstrijden }} wedstrijden | {{ $blok['categories']->count() }} categorieën</span>
                </div>
                <svg :class="{ 'rotate-180': open }" class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <button onclick="openNieuwePouleModal({{ $blok['nummer'] }})" class="px-3 py-2 mr-2 bg-green-600 hover:bg-green-500 text-white text-sm rounded font-medium">+ Poule</button>
        </div>

        {{-- Categories within blok --}}
        <div x-show="open" x-collapse>
            @if($heeftVariabeleCategorieen)
            {{-- ==================== VARIABEL TOERNOOI ==================== --}}
            {{-- Geen headers, geen wachtruimte, alles in 4-kolom grid --}}
            @php
                $allePoules = collect();
                $eliminatiePoules = collect();
                foreach ($blok['categories'] as $cat) {
                    if ($cat['is_eliminatie'] ?? false) {
                        $eliminatiePoules = $eliminatiePoules->merge($cat['poules']);
                    } else {
                        $allePoules = $allePoules->merge($cat['poules']);
                    }
                }
            @endphp
            <div class="bg-white p-4">
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    {{-- Eliminatie poules eerst: volle breedte --}}
                    @foreach($eliminatiePoules as $elimPoule)
                    @php
                        // Afwezige judoka's
                        $afwezigeElim = $elimPoule->judokas->filter(fn($j) => !$j->isActief($wegingGesloten));
                        // Overgepoulde judoka's (afwijkend gewicht maar wel actief)
                        $overpoulersElim = $elimPoule->judokas->filter(fn($j) =>
                            $j->gewicht_gewogen !== null && !$j->isGewichtBinnenKlasse(null, $tolerantie) && $j->isActief($wegingGesloten)
                        );
                        $aantalActiefElim = $elimPoule->judokas->count() - $afwezigeElim->count();

                        // Info tekst voor tooltip
                        $verwijderdeTekstElim = collect();
                        foreach ($afwezigeElim as $j) {
                            $verwijderdeTekstElim->push($j->naam . ' (afwezig)');
                        }
                        foreach ($overpoulersElim as $j) {
                            $verwijderdeTekstElim->push($j->naam . ' (afwijkend gewicht)');
                        }

                        // Titel formaat met slashes
                        $elimTitel = $elimPoule->titel ?? ($elimPoule->leeftijdsklasse . ' ' . $elimPoule->gewichtsklasse);
                        if (preg_match('/^(.+?)\s+(\d+-?\d*j)\s+(.+)$/', $elimTitel, $matches)) {
                            $elimTitelFormatted = $matches[1] . ' / ' . $matches[2] . ' / ' . $matches[3];
                        } else {
                            $elimTitelFormatted = $elimTitel;
                        }

                        $isDoorgestuurdElim = $elimPoule->doorgestuurd_op !== null;
                    @endphp
                    <div id="poule-{{ $elimPoule->id }}" class="col-span-2 md:col-span-3 lg:col-span-4 border-2 border-orange-300 rounded-lg overflow-hidden bg-white poule-card" data-poule-id="{{ $elimPoule->id }}">
                        <div class="bg-orange-600 text-white px-4 py-2 flex justify-between items-center">
                            <div>
                                <div class="font-bold">⚔️ #{{ $elimPoule->nummer }} {{ $elimTitelFormatted }} <span class="font-normal text-orange-200">(Eliminatie)</span></div>
                                <div class="text-sm text-orange-200">{{ $aantalActiefElim }} judoka's ~{{ $elimPoule->berekenAantalWedstrijden($aantalActiefElim) }} wedstrijden</div>
                            </div>
                            <div class="flex items-center gap-1">
                                @if($verwijderdeTekstElim->isNotEmpty())
                                <div class="relative" x-data="{ show: false }">
                                    <span @click="show = !show" @click.away="show = false" class="info-icon cursor-pointer text-base opacity-80 hover:opacity-100">ⓘ</span>
                                    <div x-show="show" x-transition class="absolute bottom-full right-0 mb-2 bg-gray-900 text-white text-xs rounded px-3 py-2 whitespace-pre-line z-[9999] min-w-[200px] shadow-xl pointer-events-none">{{ $verwijderdeTekstElim->join("\n") }}</div>
                                </div>
                                @endif
                                <button
                                    onclick="naarZaaloverzichtPoule({{ $elimPoule->id }}, this)"
                                    class="px-2 py-0.5 text-xs rounded transition-all {{ $isDoorgestuurdElim ? 'bg-green-500 hover:bg-green-600' : 'bg-orange-500 hover:bg-orange-400' }}"
                                    title="{{ $isDoorgestuurdElim ? 'Doorgestuurd' : 'Naar zaaloverzicht' }}"
                                >{{ $isDoorgestuurdElim ? '✓' : '→' }}</button>
                                <div class="relative" x-data="{ open: false }">
                                    <button @click="open = !open" class="bg-orange-500 hover:bg-orange-400 text-white text-xs px-2 py-0.5 rounded">⚙</button>
                                    <div x-show="open" @click.away="open = false" class="absolute right-0 mt-1 bg-white border rounded-lg shadow-lg z-10 min-w-[160px]">
                                        <button onclick="zetOmNaarPoules({{ $elimPoule->id }}, 'poules')" class="w-full text-left px-3 py-2 hover:bg-gray-100 text-sm text-gray-700">Naar poules</button>
                                        <button onclick="zetOmNaarPoules({{ $elimPoule->id }}, 'poules_kruisfinale')" class="w-full text-left px-3 py-2 hover:bg-gray-100 text-sm text-gray-700 border-t">+ kruisfinale</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="p-3 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-2">
                            @foreach($elimPoule->judokas as $judoka)
                            @if(!$judoka->isActief($wegingGesloten)) @continue @endif
                            @php $isGewogenElim = $judoka->gewicht_gewogen !== null; @endphp
                            <div class="px-2 py-1.5 rounded text-sm bg-orange-50 border border-orange-200 group relative">
                                <div class="flex items-center gap-1">
                                    @if($isGewogenElim)<span class="text-green-500 text-xs">●</span>@endif
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium text-gray-800 truncate">{{ $judoka->naam }}</div>
                                        <div class="text-xs text-gray-500 truncate">{{ $judoka->club?->naam ?? '-' }}</div>
                                    </div>
                                    <button
                                        onclick="event.stopPropagation(); openZoekMatchWedstrijddag({{ $judoka->id }}, {{ $elimPoule->id }})"
                                        class="text-gray-400 hover:text-blue-600 p-0.5 rounded hover:bg-blue-50 transition-colors opacity-0 group-hover:opacity-100"
                                        title="Zoek geschikte poule"
                                    >🔍</button>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach

                    {{-- Normale poules: 1 kolom elk --}}
                    @foreach($allePoules as $poule)
                        @include('pages.wedstrijddag.partials.poule-card', ['poule' => $poule, 'wegingGesloten' => $wegingGesloten, 'tolerantie' => $tolerantie])
                    @endforeach
                </div>
            </div>
            @else
            {{-- ==================== VAST TOERNOOI ==================== --}}
            {{-- Per categorie: header + poules + wachtruimte --}}
            <div class="divide-y">
            @forelse($blok['categories'] as $category)
            @php
                $isEliminatie = $category['is_eliminatie'] ?? false;
                $jsLeeftijd = addslashes($category['leeftijdsklasse']);
                $jsGewicht = addslashes($category['gewichtsklasse']);
            @endphp
            <div class="bg-white">
                {{-- Category header --}}
                <div class="flex justify-between items-center px-4 py-3 {{ $isEliminatie ? 'bg-orange-100' : 'bg-gray-100' }} border-b">
                    <div class="flex items-center gap-3">
                        <h2 class="text-lg font-bold {{ $isEliminatie ? 'text-orange-800' : '' }}">
                            @if($isEliminatie)⚔️ @endif{{ $category['label'] }} {{ $category['gewichtsklasse'] }}
                            @if($isEliminatie)<span class="text-sm font-normal text-orange-600 ml-1">(Eliminatie)</span>@endif
                        </h2>
                        @if(!$isEliminatie)
                        <button onclick="nieuwePoule('{{ $jsLeeftijd }}', '{{ $jsGewicht }}')" class="text-gray-500 hover:text-gray-700 hover:bg-gray-200 px-2 py-0.5 rounded text-sm font-medium">+ Poule</button>
                        @endif
                    </div>
                    @if($isEliminatie)
                    @php $elimPoule = $category['poules']->first(); @endphp
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="bg-gray-500 hover:bg-gray-600 text-white text-sm px-3 py-1.5 rounded">Omzetten naar poules ▾</button>
                        <div x-show="open" @click.away="open = false" class="absolute right-0 mt-1 bg-white border rounded-lg shadow-lg z-10 min-w-[200px]">
                            <button onclick="zetOmNaarPoules({{ $elimPoule->id }}, 'poules')" class="w-full text-left px-4 py-2 hover:bg-gray-100 text-sm">Alleen poules</button>
                            <button onclick="zetOmNaarPoules({{ $elimPoule->id }}, 'poules_kruisfinale')" class="w-full text-left px-4 py-2 hover:bg-gray-100 text-sm border-t">Poules + kruisfinale</button>
                        </div>
                        @endif
                    </div>
                </div>

                <div class="p-4">
                    @if($isEliminatie)
                    {{-- Eliminatie: één grote box met alle judoka's in grid --}}
                    @php
                        // Collect removed judokas for info tooltip
                        $verwijderdeElim = $elimPoule->judokas->filter(function($j) use ($tolerantie, $wegingGesloten) {
                            $isAfwijkend = $j->gewicht_gewogen !== null && !$j->isGewichtBinnenKlasse(null, $tolerantie);
                            return !$j->isActief($wegingGesloten) || $isAfwijkend;
                        });

                        // Calculate active count
                        $aantalActiefElim = $elimPoule->judokas->count() - $verwijderdeElim->count();

                        // Format removed for tooltip
                        $verwijderdeTekstElim = $verwijderdeElim->map(function($j) use ($tolerantie, $wegingGesloten) {
                            if (!$j->isActief($wegingGesloten)) return $j->naam . ' (afwezig)';
                            return $j->naam . ' (afwijkend gewicht)';
                        });
                    @endphp
                    <div class="border-2 border-orange-300 rounded-lg overflow-hidden bg-white">
                        <div class="bg-orange-500 text-white px-4 py-2 flex justify-between items-center">
                            <span class="font-bold">{{ $aantalActiefElim }} judoka's</span>
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-orange-200">~{{ $elimPoule->berekenAantalWedstrijden($aantalActiefElim) }} wedstrijden</span>
                                @if($verwijderdeTekstElim->isNotEmpty())
                                <div class="relative" x-data="{ show: false }">
                                    <span @click="show = !show" @click.away="show = false" class="info-icon cursor-pointer text-base opacity-80 hover:opacity-100">ⓘ</span>
                                    <div x-show="show" x-transition class="absolute bottom-full right-0 mb-2 bg-gray-900 text-white text-xs rounded px-3 py-2 whitespace-pre-line z-[9999] min-w-[200px] shadow-xl pointer-events-none">{{ $verwijderdeTekstElim->join("\n") }}</div>
                                </div>
                                @endif
                            </div>
                        </div>
                        <div class="p-3 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-2">
                            @foreach($elimPoule->judokas as $judoka)
                            @if(!$judoka->isActief($wegingGesloten))
                                @continue
                            @endif
                            @php $isGewogen = $judoka->gewicht_gewogen !== null; @endphp
                            <div class="px-2 py-1.5 rounded text-sm bg-orange-50 border border-orange-200 group">
                                <div class="flex items-center gap-1">
                                    @if($isGewogen)
                                        <span class="text-green-500 text-xs">●</span>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium text-gray-800 truncate" title="{{ $judoka->naam }}">{{ $judoka->naam }}</div>
                                        <div class="text-xs text-gray-500 truncate">{{ $judoka->club?->naam ?? '-' }}</div>
                                    </div>
                                    <button
                                        onclick="event.stopPropagation(); openZoekMatchWedstrijddag({{ $judoka->id }}, {{ $elimPoule->id }})"
                                        class="text-gray-400 hover:text-blue-600 p-0.5 rounded hover:bg-blue-50 transition-colors opacity-0 group-hover:opacity-100"
                                        title="Zoek geschikte poule"
                                    >🔍</button>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @else
                    {{-- Normale poules - groepeer op gewichtsklasse voor aparte rijen --}}
                    @php
                        // Groepeer poules op gewicht (uit titel of gewichtsklasse)
                        // Gebruik per-poule check of het dynamisch is
                        $poulesPerGewicht = $category['poules']->groupBy(function($poule) {
                            if ($poule->isDynamisch() && $poule->titel) {
                                // Haal gewichtsbereik uit titel (bijv. "21.5-24.4kg" uit "Jeugd 7-9j 21.5-24.4kg")
                                if (preg_match('/([\d.]+)-([\d.]+)kg/', $poule->titel, $m)) {
                                    return $m[1] . '-' . $m[2] . 'kg';
                                }
                            }
                            return $poule->gewichtsklasse ?: 'default';
                        })->sortKeys();
                    @endphp
                    <div class="flex gap-4">
                        {{-- Poules in 3-kolommen grid --}}
                        <div class="flex-1">
                            @foreach($poulesPerGewicht as $gewichtKey => $poulesInGewicht)
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
                            @foreach($poulesInGewicht as $poule)
                            @if($poule->type === 'kruisfinale')
                            {{-- KRUISFINALE: aparte weergave --}}
                            @php
                                $aantalVoorrondes = $category['poules']->filter(fn($p) => $p->type === 'voorronde')->count();
                            @endphp
                            <div
                                id="poule-{{ $poule->id }}"
                                class="border-2 border-purple-400 rounded-lg overflow-hidden bg-white kruisfinale-card"
                                data-poule-id="{{ $poule->id }}"
                                data-aantal-voorrondes="{{ $aantalVoorrondes }}"
                            >
                                <div class="bg-purple-600 text-white px-3 py-2 flex justify-between items-center">
                                    <div>
                                        <div class="font-bold text-sm">🏆 Kruisfinale</div>
                                        <div class="text-xs text-purple-200 kruisfinale-stats">{{ $poule->aantal_judokas }} judoka's | {{ $poule->aantal_wedstrijden }} wedstrijden</div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <select
                                            onchange="updateKruisfinale({{ $poule->id }}, this.value)"
                                            class="text-xs bg-purple-500 text-white border border-purple-400 rounded px-1 py-0.5"
                                            title="Aantal plaatsen per poule"
                                        >
                                            @for($i = 1; $i <= 3; $i++)
                                            <option value="{{ $i }}" {{ ($poule->kruisfinale_plaatsen ?? 2) == $i ? 'selected' : '' }}>Top {{ $i }}</option>
                                            @endfor
                                        </select>
                                        <button
                                            onclick="verwijderPoule({{ $poule->id }}, '{{ $poule->nummer }}')"
                                            class="w-5 h-5 flex items-center justify-center bg-purple-800 hover:bg-purple-900 text-white rounded-full text-xs font-bold"
                                            title="Verwijder kruisfinale"
                                        >×</button>
                                    </div>
                                </div>
                                <div class="p-3 text-sm text-gray-600 kruisfinale-info">
                                    {{ $aantalVoorrondes }} poules × top {{ $poule->kruisfinale_plaatsen ?? 2 }} = {{ $poule->aantal_judokas }} judoka's door
                                </div>
                            </div>
                            @continue
                            @endif
                            @if($poule->type === 'eliminatie' && $poule->judokas->count() === 0 && $poule->aantal_judokas > 0)
                            {{-- ELIMINATIE FINALE: geen fysieke judokas maar wel berekend aantal --}}
                            <div
                                id="poule-{{ $poule->id }}"
                                class="border-2 border-orange-400 rounded-lg overflow-hidden min-w-[200px] bg-white"
                                data-poule-id="{{ $poule->id }}"
                                data-poule-leeftijdsklasse="{{ $poule->leeftijdsklasse }}"
                                data-poule-gewichtsklasse="{{ $poule->gewichtsklasse }}"
                            >
                                <div class="bg-orange-600 text-white px-3 py-2 flex justify-between items-center">
                                    <div>
                                        <div class="font-bold text-sm">⚔️ Eliminatie Finale</div>
                                        <div class="text-xs text-orange-200">{{ $poule->aantal_judokas }} judoka's | {{ $poule->aantal_wedstrijden }} wedstrijden</div>
                                    </div>
                                    <button
                                        onclick="verwijderPoule({{ $poule->id }}, '{{ $poule->nummer }}')"
                                        class="w-5 h-5 flex items-center justify-center bg-orange-800 hover:bg-orange-900 text-white rounded-full text-xs font-bold"
                                        title="Verwijder eliminatie finale"
                                    >×</button>
                                </div>
                                <div class="p-3 text-sm text-gray-600">
                                    Winnaars uit voorronde poules
                                </div>
                            </div>
                            @continue
                            @endif
                            @php
                                // Collect afwezige judokas for info tooltip
                                $afwezigeJudokas = $poule->judokas->filter(fn($j) => !$j->isActief($wegingGesloten));

                                // Collect overpoulers (judokas die uit DEZE poule overpouled zijn)
                                $overpoulers = \App\Models\Judoka::where('overpouled_van_poule_id', $poule->id)->get();

                                // Calculate active count (total minus afwezigen)
                                $aantalActief = $poule->judokas->count() - $afwezigeJudokas->count();
                                $aantalWedstrijden = $poule->berekenAantalWedstrijden($aantalActief);
                                $isProblematisch = $aantalActief > 0 && $aantalActief < 3;

                                // Check gewichtsrange probleem (dynamische categorie)
                                $heeftGewichtsprobleem = $problematischeGewichtsPoules->has($poule->id);

                                // Format afwezigen + overpoulers for tooltip
                                $verwijderdeTekst = collect();
                                foreach ($afwezigeJudokas as $j) {
                                    $verwijderdeTekst->push($j->naam . ' (afwezig)');
                                }
                                foreach ($overpoulers as $j) {
                                    $verwijderdeTekst->push($j->naam . ' (afwijkend gewicht → ' . $j->gewichtsklasse . ')');
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
                                    // Bij dynamische categorieën: toon actuele gewichtsrange (niet de oorspronkelijke)
                                    $pouleIsDynamischTitel = $poule->isDynamisch();
                                    $pouleRange = $pouleIsDynamischTitel ? $poule->getGewichtsRange() : null;
                                    if ($pouleIsDynamischTitel && $pouleRange) {
                                        // Haal leeftijdsdeel uit titel (bijv. "Jeugd 7-9j" uit "Jeugd 7-9j 21.5-24.4kg")
                                        $titelZonderKg = preg_replace('/\s*[\d.]+-[\d.]+kg\s*$/', '', $poule->titel ?? '');
                                        $pouleTitel = $titelZonderKg . ' (' . round($pouleRange['min_kg'], 1) . '-' . round($pouleRange['max_kg'], 1) . 'kg)';
                                    } elseif ($pouleIsDynamischTitel && $poule->titel) {
                                        $pouleTitel = $poule->titel;
                                    } else {
                                        $pouleTitel = $category['label'] . ' / ' . $poule->gewichtsklasse;
                                    }
                                @endphp
                                <div class="{{ $aantalActief === 0 ? 'bg-gray-500' : ($isProblematisch ? 'bg-red-600' : ($heeftGewichtsprobleem ? 'bg-orange-600' : 'bg-blue-700')) }} text-white px-3 py-2 poule-header flex justify-between items-start rounded-t-lg">
                                    <div class="pointer-events-none flex-1">
                                        <div class="font-bold text-sm">#{{ $poule->nummer }} {{ $pouleTitel }}</div>
                                        <div class="text-xs {{ $aantalActief === 0 ? 'text-gray-300' : ($isProblematisch ? 'text-red-200' : ($heeftGewichtsprobleem ? 'text-orange-200' : 'text-blue-200')) }} poule-stats"><span class="poule-actief">{{ $aantalActief }}</span> judoka's <span class="poule-wedstrijden">{{ $aantalWedstrijden }}</span> wedstrijden</div>
                                    </div>
                                    <div class="flex items-center gap-1 flex-shrink-0">
                                        @if($verwijderdeTekst->isNotEmpty())
                                        <div class="relative" x-data="{ show: false }">
                                            <span @click="show = !show" @click.away="show = false" class="info-icon cursor-pointer text-base opacity-80 hover:opacity-100">ⓘ</span>
                                            <div x-show="show" x-transition class="absolute bottom-full right-0 mb-2 bg-gray-900 text-white text-xs rounded px-3 py-2 whitespace-pre-line z-[9999] min-w-[200px] shadow-xl pointer-events-none">{{ $verwijderdeTekst->join("\n") }}</div>
                                        </div>
                                        @endif
                                        @if($aantalActief > 0)
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

                                        // Check of judoka past in DEZE POULE's gewichtsklasse
                                        // ALLEEN voor VASTE categorieën (niet dynamisch)
                                        // Dynamische categorieën: poule-level check via $problematischeGewichtsPoules
                                        $pouleIsDynamisch = $poule->isDynamisch();
                                        $isVerkeerdePoule = false;
                                        if (!$pouleIsDynamisch && $poule->gewichtsklasse) {
                                            $judokaGewicht = $judoka->gewicht_gewogen ?? $judoka->gewicht ?? 0;
                                            $isPlusKlasse = str_starts_with($poule->gewichtsklasse, '+');
                                            $pouleLimiet = floatval(preg_replace('/[^0-9.]/', '', $poule->gewichtsklasse));

                                            if ($isPlusKlasse) {
                                                // +70 = minimaal 70kg (- tolerantie)
                                                $isVerkeerdePoule = $judokaGewicht < ($pouleLimiet - $tolerantie);
                                            } else {
                                                // -24 = maximaal 24kg (+ tolerantie)
                                                $isVerkeerdePoule = $judokaGewicht > ($pouleLimiet + $tolerantie);
                                            }
                                        }

                                        // Afwijkend = eigen gewichtsklasse niet gehaald (overpouler)
                                        // Alleen voor VASTE categorieën - dynamische gebruiken poule-level check
                                        $isAfwijkendGewicht = !$pouleIsDynamisch && $isGewogen && !$judoka->isGewichtBinnenKlasse(null, $tolerantie);

                                        // Combineer: verkeerde poule OF afwijkend gewicht
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
                                                {{-- Status marker: green = gewogen, orange = probleem --}}
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
                            @endforeach
                            </div>
                            @endforeach
                        </div>

                        {{-- Wachtruimte (rechts) - alleen voor VASTE gewichtscategorieën (niet dynamisch) --}}
                        @php
                            // Check of deze categorie dynamisch is via de eerste poule
                            $eerstePouleInCategorie = $category['poules']->first();
                            $categorieIsDynamisch = $eerstePouleInCategorie && $eerstePouleInCategorie->isDynamisch();
                        @endphp
                        @if(!$categorieIsDynamisch)
                        <div class="border-2 border-dashed border-orange-300 rounded-lg p-3 min-w-[200px] bg-orange-50 flex-shrink-0 wachtruimte-container" data-category="{{ $category['key'] }}">
                            <div class="font-medium text-sm text-orange-600 mb-2 flex justify-between">
                                <span>Wachtruimte</span>
                                <span class="text-xs text-orange-400 wachtruimte-count">{{ count($category['wachtruimte']) }}</span>
                            </div>
                            <div class="divide-y divide-orange-200 sortable-wachtruimte min-h-[40px]" data-category="{{ $category['key'] }}">
                                @forelse($category['wachtruimte'] as $judoka)
                                <div
                                    class="px-2 py-1.5 hover:bg-orange-100 cursor-move text-sm judoka-item group"
                                    data-judoka-id="{{ $judoka->id }}"
                                >
                                    <div class="flex justify-between items-start">
                                        <div class="flex items-center gap-1 flex-1 min-w-0">
                                            <span class="text-red-500 text-xs flex-shrink-0">●</span>
                                            <div class="min-w-0">
                                                <div class="font-medium text-gray-800 truncate">{{ $judoka->naam }} <span class="text-gray-400 font-normal">({{ $judoka->leeftijd }}j)</span></div>
                                                <div class="text-xs text-gray-500 truncate">{{ $judoka->club?->naam ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1 flex-shrink-0">
                                            <div class="text-right text-xs">
                                                <div class="text-orange-600 font-medium">{{ $judoka->gewicht_gewogen ?? $judoka->gewicht }} kg</div>
                                                <div class="text-gray-400">{{ \App\Enums\Band::stripKyu($judoka->band ?? '') }}</div>
                                            </div>
                                            <button
                                                onclick="event.stopPropagation(); openZoekMatchWedstrijddag({{ $judoka->id }}, null)"
                                                class="text-gray-400 hover:text-blue-600 p-1 rounded hover:bg-blue-50 transition-colors opacity-0 group-hover:opacity-100"
                                                title="Zoek geschikte poule"
                                            >🔍</button>
                                        </div>
                                    </div>
                                </div>
                                @empty
                                <div class="text-sm text-gray-400 italic py-2 text-center">Leeg</div>
                                @endforelse
                            </div>
                        </div>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
            @empty
            <div class="p-4 text-gray-500 text-center">Geen categorieën in dit blok</div>
            @endforelse
            </div>
            @endif
        </div>
    </div>
    @empty
    <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
        Geen blokken gevonden. Maak eerst blokken aan via de Blokken pagina.
    </div>
    @endforelse
    </div>
</div>

<!-- Modal nieuwe poule -->
<div id="nieuwe-poule-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Nieuwe poule aanmaken</h2>
        <form id="nieuwe-poule-form">
            <input type="hidden" id="nieuwe-poule-blok" value="">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Categorie</label>
                <select id="nieuwe-poule-categorie" class="w-full border rounded px-3 py-2" required>
                    <option value="">Selecteer...</option>
                    @foreach($toernooi->getAlleGewichtsklassen() as $key => $klasse)
                    <option value="{{ $klasse['label'] }}">{{ $klasse['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeNieuwePouleModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                    Annuleren
                </button>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    Aanmaken
                </button>
            </div>
        </form>
    </div>
</div>

<!-- SortableJS for drag and drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
const verifieerUrl = '{{ route('toernooi.poule.verifieer', $toernooi) }}';
const verwijderPouleUrl = '{{ route('toernooi.poule.destroy', [$toernooi, ':id']) }}';
const zetOmNaarPoulesUrl = '{{ route('toernooi.wedstrijddag.zetOmNaarPoules', $toernooi) }}';
const updateKruisfinaleUrl = '{{ route('toernooi.poule.update-kruisfinale', [$toernooi, ':id']) }}';

async function updateKruisfinale(pouleId, plaatsen) {
    try {
        const response = await fetch(updateKruisfinaleUrl.replace(':id', pouleId), {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ kruisfinale_plaatsen: parseInt(plaatsen) })
        });

        const data = await response.json();

        if (data.success) {
            // Update de weergave
            const card = document.getElementById('poule-' + pouleId);
            if (card) {
                const statsDiv = card.querySelector('.kruisfinale-stats');
                if (statsDiv) {
                    statsDiv.textContent = `${data.aantal_judokas} judoka's | ${data.aantal_wedstrijden} wedstrijden`;
                }
                const infoDiv = card.querySelector('.kruisfinale-info');
                const aantalVoorrondes = card.dataset.aantalVoorrondes || '?';
                if (infoDiv) {
                    infoDiv.textContent = `${aantalVoorrondes} poules × top ${plaatsen} = ${data.aantal_judokas} judoka's door`;
                }
            }
        } else {
            alert(data.message || 'Fout bij aanpassen kruisfinale');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Fout bij aanpassen kruisfinale');
    }
}

async function zetOmNaarPoules(pouleId, systeem) {
    if (!confirm(`Eliminatie omzetten naar ${systeem === 'poules_kruisfinale' ? 'poules + kruisfinale' : 'alleen poules'}?`)) return;

    try {
        const response = await fetch(zetOmNaarPoulesUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ poule_id: pouleId, systeem: systeem })
        });

        const data = await response.json();

        if (data.success) {
            // Refresh page to show new poules
            window.location.reload();
        } else {
            alert(data.message || 'Fout bij omzetten');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Fout bij omzetten');
    }
}

function scrollToPoule(event, pouleId) {
    event.preventDefault();
    const pouleCard = document.getElementById('poule-' + pouleId);
    if (pouleCard) {
        // Find parent blok div with Alpine.js x-data and open it
        const blokDiv = pouleCard.closest('[x-data]');
        if (blokDiv && blokDiv._x_dataStack) {
            blokDiv._x_dataStack[0].open = true;
        }

        // Wait for collapse animation, then scroll
        setTimeout(() => {
            const offset = 100;
            const elementPosition = pouleCard.getBoundingClientRect().top + window.pageYOffset;
            window.scrollTo({
                top: elementPosition - offset,
                behavior: 'smooth'
            });

            // Flash effect to highlight the poule
            pouleCard.classList.add('ring-4', 'ring-yellow-400');
            setTimeout(() => {
                pouleCard.classList.remove('ring-4', 'ring-yellow-400');
            }, 2000);
        }, 100);
    }
}

async function verwijderPoule(pouleId, pouleNummer) {
    if (!confirm(`Poule #${pouleNummer} verwijderen?`)) return;

    try {
        const response = await fetch(verwijderPouleUrl.replace(':id', pouleId), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (data.success) {
            // Remove the poule card from DOM
            const pouleCard = document.getElementById('poule-' + pouleId);
            if (pouleCard) {
                pouleCard.remove();
            }
        } else {
            alert(data.message || 'Fout bij verwijderen');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Fout bij verwijderen');
    }
}

async function verifieerPoules() {
    const resultaatDiv = document.getElementById('verificatie-resultaat');
    resultaatDiv.className = 'bg-blue-50 border border-blue-300 rounded-lg p-4';
    resultaatDiv.innerHTML = '<p class="text-blue-700">Bezig met verificatie...</p>';

    try {
        const response = await fetch(verifieerUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (data.success) {
            let html = '';
            const hasProblems = data.problemen.length > 0;

            if (hasProblems) {
                html = `<div class="bg-yellow-50 border border-yellow-300 rounded-lg p-4">
                    <h3 class="font-bold text-yellow-800 mb-2">Verificatie: ${data.problemen.length} probleem(en) gevonden</h3>
                    <ul class="list-disc list-inside text-yellow-700 text-sm mb-3">
                        ${data.problemen.map(p => `<li>${p.message}</li>`).join('')}
                    </ul>
                    <p class="text-yellow-600 text-sm">Totaal: ${data.totaal_poules} poules, ${data.totaal_wedstrijden} wedstrijden${data.herberekend > 0 ? `, ${data.herberekend} poules herberekend` : ''}</p>
                </div>`;
            } else {
                html = `<div class="bg-green-50 border border-green-300 rounded-lg p-4">
                    <h3 class="font-bold text-green-800 mb-2">Verificatie geslaagd!</h3>
                    <p class="text-green-700 text-sm">Totaal: ${data.totaal_poules} poules, ${data.totaal_wedstrijden} wedstrijden${data.herberekend > 0 ? `, ${data.herberekend} poules herberekend` : ''}</p>
                </div>`;
            }

            resultaatDiv.className = '';
            resultaatDiv.innerHTML = html;

            if (data.herberekend > 0) {
                setTimeout(() => location.reload(), 2000);
            }
        }
    } catch (error) {
        console.error('Error:', error);
        resultaatDiv.className = 'bg-red-50 border border-red-300 rounded-lg p-4';
        resultaatDiv.innerHTML = '<p class="text-red-700">Fout bij verificatie</p>';
    }
}

function wedstrijddagPoules() {
    return {}
}

async function verwijderUitPoule(judokaId, pouleId) {
    if (!confirm('Weet je zeker dat je deze judoka uit de poule wilt verwijderen?')) return;

    try {
        const response = await fetch('{{ route("toernooi.wedstrijddag.verwijder-uit-poule", $toernooi) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ judoka_id: judokaId, poule_id: pouleId }),
        });

        if (response.ok) {
            // Verwijder het element uit de DOM
            const judokaEl = document.querySelector(`.judoka-item[data-judoka-id="${judokaId}"]`);
            if (judokaEl) {
                judokaEl.remove();
            }
            // Update stats
            const data = await response.json();
            if (data.poule) {
                updatePouleStats(data.poule);
            }
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Fout bij verwijderen');
    }
}

async function naarZaaloverzichtPoule(pouleId, btn) {
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳';

    try {
        const response = await fetch('{{ route("toernooi.wedstrijddag.naar-zaaloverzicht-poule", $toernooi) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ poule_id: pouleId }),
        });

        if (response.ok) {
            btn.classList.remove('bg-blue-500', 'hover:bg-blue-600');
            btn.classList.add('bg-green-500', 'hover:bg-green-600');
            btn.innerHTML = '✓';
            btn.title = 'Doorgestuurd';
        } else {
            const data = await response.json().catch(() => ({}));
            alert('Fout bij doorsturen: ' + (data.message || response.status));
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    } catch (error) {
        alert('Netwerk fout: ' + error.message);
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

// Modal functies voor nieuwe poule
function openNieuwePouleModal(blokNummer) {
    document.getElementById('nieuwe-poule-blok').value = blokNummer;
    document.getElementById('nieuwe-poule-categorie').value = '';
    document.getElementById('nieuwe-poule-modal').classList.remove('hidden');
}

function closeNieuwePouleModal() {
    document.getElementById('nieuwe-poule-modal').classList.add('hidden');
}

document.getElementById('nieuwe-poule-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const categorie = document.getElementById('nieuwe-poule-categorie').value;
    const blokNummer = document.getElementById('nieuwe-poule-blok').value;

    if (!categorie) {
        alert('Selecteer een categorie');
        return;
    }

    closeNieuwePouleModal();
    await nieuwePoule(categorie, '', blokNummer);
});

async function nieuwePoule(leeftijdsklasse, gewichtsklasse, blokNummer) {
    try {
        const response = await fetch('{{ route("toernooi.wedstrijddag.nieuwe-poule", $toernooi) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ leeftijdsklasse, gewichtsklasse, blok_nummer: blokNummer }),
        });

        if (response.ok) {
            window.location.reload();
        } else {
            const data = await response.json().catch(() => ({}));
            console.error('Server error:', response.status, data);
            alert('Fout bij aanmaken poule: ' + (data.message || response.status));
        }
    } catch (error) {
        console.error('Error creating poule:', error);
        alert('Fout bij aanmaken poule: ' + error.message);
    }
}

const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const verplaatsUrl = '{{ route("toernooi.wedstrijddag.verplaats-judoka", $toernooi) }}';

document.addEventListener('DOMContentLoaded', function() {
    // Helper: bereken wedstrijden voor round-robin (n*(n-1)/2)
    function berekenWedstrijden(aantalJudokas) {
        if (aantalJudokas < 2) return 0;
        return (aantalJudokas * (aantalJudokas - 1)) / 2;
    }

    // Helper: update poule titelbalk direct vanuit DOM
    function updatePouleFromDOM(pouleId) {
        const pouleCard = document.querySelector(`.poule-card[data-poule-id="${pouleId}"]`);
        if (!pouleCard) return;

        const container = pouleCard.querySelector('.sortable-poule');
        if (!container) return;

        // Tel judoka's in de DOM
        const aantalJudokas = container.querySelectorAll('.judoka-item').length;
        const aantalWedstrijden = berekenWedstrijden(aantalJudokas);

        // Update data attribute
        pouleCard.dataset.actief = aantalJudokas;

        // Update tekst in header
        const actiefSpan = pouleCard.querySelector('.poule-actief');
        const wedstrijdenSpan = pouleCard.querySelector('.poule-wedstrijden');
        if (actiefSpan) actiefSpan.textContent = aantalJudokas;
        if (wedstrijdenSpan) wedstrijdenSpan.textContent = aantalWedstrijden;

        // Update styling
        const isLeeg = aantalJudokas === 0;
        const isProblematisch = aantalJudokas > 0 && aantalJudokas < 3;
        const header = pouleCard.querySelector('.poule-header');
        const statsDiv = pouleCard.querySelector('.poule-stats');

        pouleCard.classList.remove('border-2', 'border-red-300', 'opacity-50');
        if (header) header.classList.remove('bg-blue-700', 'bg-red-600', 'bg-gray-500');
        if (statsDiv) statsDiv.classList.remove('text-blue-200', 'text-red-200', 'text-gray-300');

        if (isLeeg) {
            pouleCard.classList.add('opacity-50');
            if (header) header.classList.add('bg-gray-500');
            if (statsDiv) statsDiv.classList.add('text-gray-300');
        } else if (isProblematisch) {
            pouleCard.classList.add('border-2', 'border-red-300');
            if (header) header.classList.add('bg-red-600');
            if (statsDiv) statsDiv.classList.add('text-red-200');
        } else {
            if (header) header.classList.add('bg-blue-700');
            if (statsDiv) statsDiv.classList.add('text-blue-200');
        }

        // Update category status
        const categoryKey = pouleCard.dataset.pouleLeeftijdsklasse + '|' + pouleCard.dataset.pouleGewichtsklasse;
        updateCategoryStatus(categoryKey);

        // Update problematische poules lijsten bovenaan
        updateProblematischePoules(
            { id: pouleId, aantal_judokas: aantalJudokas },
            isProblematisch
        );
        updateTeVeelJudokas(
            { id: pouleId, aantal_judokas: aantalJudokas },
            aantalJudokas >= 6
        );
    }

    // Initialize sortable on all poule containers (voor drag TUSSEN poules EN naar wachtruimte)
    document.querySelectorAll('.sortable-poule').forEach(container => {
        new Sortable(container, {
            group: 'wedstrijddag-poules',
            animation: 150,
            ghostClass: 'bg-blue-100',
            chosenClass: 'bg-blue-200',
            dragClass: 'shadow-lg',
            onEnd: async function(evt) {
                const judokaId = evt.item.dataset.judokaId;
                const vanPouleId = evt.from.dataset.pouleId;
                const naarPouleId = evt.to.dataset.pouleId;
                const naarWachtruimte = evt.to.classList.contains('sortable-wachtruimte');

                // Direct DOM update
                if (vanPouleId) updatePouleFromDOM(vanPouleId);
                if (naarPouleId) updatePouleFromDOM(naarPouleId);

                // Van poule naar wachtruimte
                if (naarWachtruimte && vanPouleId) {
                    // Update wachtruimte count
                    const countEl = evt.to.closest('.wachtruimte-container')?.querySelector('.wachtruimte-count');
                    if (countEl) countEl.textContent = parseInt(countEl.textContent || 0) + 1;

                    try {
                        const response = await fetch(naarWachtruimteUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: JSON.stringify({ judoka_id: judokaId, from_poule_id: vanPouleId })
                        });
                        const data = await response.json();
                        if (!data.success) {
                            console.error('Server error:', data);
                            alert('Fout: ' + (data.error || data.message));
                            return; // Niet herladen zodat we kunnen debuggen
                        }

                        // Update poule markering op basis van hervalidatie
                        if (data.van_poule) {
                            const pouleCard = document.getElementById('poule-' + data.van_poule.id);
                            if (pouleCard) {
                                // Update titel met nieuwe gewichtsrange
                                updatePouleTitel(pouleCard, data.van_poule);

                                // Update problematische markering (gewichtsrange)
                                const pouleHeader = pouleCard.querySelector('.poule-header');
                                if (data.van_poule.is_problematisch) {
                                    pouleCard.classList.add('border-2', 'border-orange-400');
                                    if (pouleHeader) {
                                        pouleHeader.classList.remove('bg-blue-700');
                                        pouleHeader.classList.add('bg-orange-600');
                                        // Update subtitle kleur
                                        const subtitle = pouleHeader.querySelector('.poule-stats');
                                        if (subtitle) {
                                            subtitle.classList.remove('text-blue-200');
                                            subtitle.classList.add('text-orange-200');
                                        }
                                    }
                                } else {
                                    // Verwijder alle probleem styling
                                    pouleCard.classList.remove('border-2', 'border-orange-400', 'border-red-300');
                                    if (pouleHeader) {
                                        pouleHeader.classList.remove('bg-orange-600', 'bg-red-600');
                                        pouleHeader.classList.add('bg-blue-700');
                                        // Update subtitle kleur
                                        const subtitle = pouleHeader.querySelector('.poule-stats');
                                        if (subtitle) {
                                            subtitle.classList.remove('text-orange-200', 'text-red-200');
                                            subtitle.classList.add('text-blue-200');
                                        }
                                    }
                                }
                            }
                            // Update gewichtsrange box bovenaan
                            updateGewichtsrangeBox(data.van_poule.id, data.van_poule.is_problematisch);
                            // Update problematische poules (< 3 of >= 6 judokas)
                            updateProblematischePoules(data.van_poule, data.van_poule.aantal_judokas > 0 && data.van_poule.aantal_judokas < 3);
                            updateTeVeelJudokas(data.van_poule, data.van_poule.aantal_judokas >= 6);
                        }
                    } catch (error) {
                        console.error('Fetch error naar wachtruimte:', error);
                        alert('Fout bij verplaatsen naar wachtruimte: ' + error.message);
                    }
                    return;
                }

                // Van poule naar poule
                const positions = Array.from(evt.to.querySelectorAll('.judoka-item'))
                    .map((el, idx) => ({ id: parseInt(el.dataset.judokaId), positie: idx + 1 }));

                try {
                    const response = await fetch(verplaatsUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            judoka_id: judokaId,
                            poule_id: naarPouleId,
                            from_poule_id: vanPouleId !== naarPouleId ? vanPouleId : null,
                            positions: positions
                        })
                    });

                    const data = await response.json();
                    if (!data.success) {
                        alert('Fout: ' + (data.error || data.message || 'Onbekende fout'));
                        window.location.reload();
                    } else {
                        // Update gewichtsrange en problematische poules voor beide poules
                        if (data.van_poule) {
                            const vanPouleCard = document.querySelector(`.poule-card[data-poule-id="${data.van_poule.id}"]`);
                            if (vanPouleCard) {
                                updatePouleTitel(vanPouleCard, data.van_poule);
                                updatePouleGewichtsStyling(vanPouleCard, data.van_poule.is_gewicht_problematisch);
                            }
                            updateGewichtsrangeBox(data.van_poule.id, data.van_poule.is_gewicht_problematisch);
                            updateProblematischePoules(data.van_poule, data.van_poule.aantal_judokas > 0 && data.van_poule.aantal_judokas < 3);
                            updateTeVeelJudokas(data.van_poule, data.van_poule.aantal_judokas >= 6);
                        }
                        if (data.naar_poule) {
                            const naarPouleCard = document.querySelector(`.poule-card[data-poule-id="${data.naar_poule.id}"]`);
                            if (naarPouleCard) {
                                updatePouleTitel(naarPouleCard, data.naar_poule);
                                updatePouleGewichtsStyling(naarPouleCard, data.naar_poule.is_gewicht_problematisch);
                            }
                            updateGewichtsrangeBox(data.naar_poule.id, data.naar_poule.is_gewicht_problematisch);
                            updateProblematischePoules(data.naar_poule, data.naar_poule.aantal_judokas > 0 && data.naar_poule.aantal_judokas < 3);
                            updateTeVeelJudokas(data.naar_poule, data.naar_poule.aantal_judokas >= 6);
                        }
                        // Update judoka styling (verkeerde poule markering)
                        if (data.judoka_id !== undefined) {
                            updateJudokaStyling(data.judoka_id, !data.judoka_past_in_poule);
                        }
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Fout bij verplaatsen: ' + error.message);
                    window.location.reload();
                }
            }
        });
    });

    // Update judoka styling (verkeerde poule markering)
    function updateJudokaStyling(judokaId, heeftProbleem) {
        const judokaEl = document.querySelector(`.judoka-item[data-judoka-id="${judokaId}"]`);
        if (!judokaEl) return;

        // Update container styling
        if (heeftProbleem) {
            judokaEl.classList.add('bg-orange-50', 'border-l-4', 'border-orange-400');
        } else {
            judokaEl.classList.remove('bg-orange-50', 'border-l-4', 'border-orange-400');
        }

        // Update status icon
        const iconContainer = judokaEl.querySelector('.flex.items-center.gap-1');
        if (iconContainer) {
            const existingIcon = iconContainer.querySelector('span:first-child');
            if (existingIcon) {
                if (heeftProbleem) {
                    existingIcon.className = 'text-orange-500 text-xs flex-shrink-0';
                    existingIcon.textContent = '⚠';
                    existingIcon.title = 'Verkeerde gewichtsklasse';
                } else {
                    existingIcon.className = 'text-green-500 text-xs flex-shrink-0';
                    existingIcon.textContent = '●';
                    existingIcon.title = '';
                }
            }
        }

        // Update naam en gewicht kleuren
        const naamEl = judokaEl.querySelector('.font-medium');
        const gewichtEl = judokaEl.querySelector('.text-right .font-medium');
        if (naamEl) {
            naamEl.classList.toggle('text-orange-800', heeftProbleem);
            naamEl.classList.toggle('text-gray-800', !heeftProbleem);
        }
        if (gewichtEl) {
            gewichtEl.classList.toggle('text-orange-600', heeftProbleem);
            gewichtEl.classList.toggle('font-bold', heeftProbleem);
            gewichtEl.classList.toggle('text-gray-600', !heeftProbleem);
        }
    }

    // Update poule styling voor gewichtsprobleem (oranje header)
    function updatePouleGewichtsStyling(pouleCard, isGewichtProblematisch) {
        const pouleHeader = pouleCard.querySelector('.poule-header');
        const aantalActief = parseInt(pouleCard.dataset.actief || pouleCard.querySelector('.poule-actief')?.textContent || 0);
        const isProblematisch = aantalActief > 0 && aantalActief < 3;
        const isLeeg = aantalActief === 0;

        // Reset border styling
        pouleCard.classList.remove('border-2', 'border-orange-400', 'border-red-300');

        if (isProblematisch) {
            pouleCard.classList.add('border-2', 'border-red-300');
        } else if (isGewichtProblematisch) {
            pouleCard.classList.add('border-2', 'border-orange-400');
        }

        // Update header kleur (alleen als niet al rood door < 3 judokas)
        if (pouleHeader && !isProblematisch && !isLeeg) {
            const subtitle = pouleHeader.querySelector('.poule-stats');
            if (isGewichtProblematisch) {
                pouleHeader.classList.remove('bg-blue-700');
                pouleHeader.classList.add('bg-orange-600');
                if (subtitle) {
                    subtitle.classList.remove('text-blue-200');
                    subtitle.classList.add('text-orange-200');
                }
            } else {
                pouleHeader.classList.remove('bg-orange-600');
                pouleHeader.classList.add('bg-blue-700');
                if (subtitle) {
                    subtitle.classList.remove('text-orange-200');
                    subtitle.classList.add('text-blue-200');
                }
            }
        }
    }

    // Initialize sortable on wachtruimte (bidirectioneel: van EN naar wachtruimte)
    document.querySelectorAll('.sortable-wachtruimte').forEach(container => {
        new Sortable(container, {
            group: {
                name: 'wedstrijddag-poules',
                pull: true,
                put: true  // Bidirectioneel: ook NAAR wachtruimte kunnen slepen
            },
            animation: 150,
            ghostClass: 'bg-orange-200',
            sort: false,
            onEnd: async function(evt) {
                const judokaId = evt.item.dataset.judokaId;
                const naarPouleId = evt.to.dataset.pouleId;
                const naarWachtruimte = evt.to.classList.contains('sortable-wachtruimte');
                const vanWachtruimte = evt.from.classList.contains('sortable-wachtruimte');
                const vanPouleId = evt.from.dataset.pouleId;

                // Van wachtruimte naar poule
                if (vanWachtruimte && naarPouleId) {
                    updatePouleFromDOM(naarPouleId);

                    // Update wachtruimte count
                    const countEl = evt.from.closest('.wachtruimte-container')?.querySelector('.wachtruimte-count');
                    if (countEl) countEl.textContent = Math.max(0, parseInt(countEl.textContent) - 1);

                    const positions = Array.from(evt.to.querySelectorAll('.judoka-item'))
                        .map((el, idx) => ({ id: parseInt(el.dataset.judokaId), positie: idx + 1 }));

                    try {
                        const response = await fetch(verplaatsUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: JSON.stringify({ judoka_id: judokaId, poule_id: naarPouleId, from_poule_id: null, positions: positions })
                        });
                        const data = await response.json();
                        if (!data.success) {
                            alert('Fout: ' + (data.error || data.message));
                            window.location.reload();
                        } else if (data.naar_poule) {
                            // Update gewichtsrange en problematische poules
                            const naarPouleCard = document.querySelector(`.poule-card[data-poule-id="${data.naar_poule.id}"]`);
                            if (naarPouleCard) {
                                updatePouleTitel(naarPouleCard, data.naar_poule);
                                updatePouleGewichtsStyling(naarPouleCard, data.naar_poule.is_gewicht_problematisch);
                            }
                            updateGewichtsrangeBox(data.naar_poule.id, data.naar_poule.is_gewicht_problematisch);
                            updateProblematischePoules(data.naar_poule, data.naar_poule.aantal_judokas > 0 && data.naar_poule.aantal_judokas < 3);
                            updateTeVeelJudokas(data.naar_poule, data.naar_poule.aantal_judokas >= 6);
                        }
                    } catch (error) { console.error('Error:', error); alert('Fout bij verplaatsen'); window.location.reload(); }
                }
                // Note: "van poule naar wachtruimte" wordt afgehandeld door sortable-poule handler (onEnd triggert op FROM container)
            }
        });
    });

    // Update poule titel met nieuwe gewichtsrange (variabele categorieën)
    function updatePouleTitel(pouleCard, pouleData) {
        if (!pouleData.gewichts_range) return;

        const titelEl = pouleCard.querySelector('.poule-header .font-bold');
        if (!titelEl) return;

        // Haal leeftijdsdeel uit titel (verwijder oude kg range)
        const titel = pouleData.titel || '';
        const titelZonderKg = titel.replace(/\s*\([\d.]+-[\d.]+kg\)\s*$/, '').replace(/\s*[\d.]+-[\d.]+kg\s*$/, '');

        // Bouw nieuwe titel met actuele range (null-safe)
        const minKg = pouleData.gewichts_range.min_kg;
        const maxKg = pouleData.gewichts_range.max_kg;

        let nieuweTitel = `#${pouleCard.dataset.pouleNummer} ${titelZonderKg}`;
        if (minKg !== null && maxKg !== null) {
            nieuweTitel += ` (${parseFloat(minKg).toFixed(1)}-${parseFloat(maxKg).toFixed(1)}kg)`;
        }

        titelEl.textContent = nieuweTitel;
    }

    // Update gewichtsrange box (oranje warning bovenaan)
    function updateGewichtsrangeBox(pouleId, isProblematisch) {
        const container = document.getElementById('gewichtsrange-problemen-container');
        const itemsContainer = document.getElementById('gewichtsrange-items');
        const countEl = document.getElementById('gewichtsrange-count');
        const pouleItem = document.getElementById('gewichtsrange-poule-' + pouleId);

        if (!isProblematisch && pouleItem) {
            // Poule is niet meer problematisch - verwijder uit lijst
            pouleItem.remove();

            // Update count
            const remaining = itemsContainer ? itemsContainer.querySelectorAll('[data-gewichtsrange-poule]').length : 0;
            if (countEl) countEl.textContent = remaining;

            // Verberg hele container als leeg
            if (remaining === 0 && container) {
                container.classList.add('hidden');
            }
        }
        // Note: als poule WEL problematisch wordt, doen we een page reload (zeldzaam scenario)
    }

    function updateProblematischePoules(pouleData, isProblematisch) {
        const container = document.getElementById('problematische-poules-container');
        const linksContainer = document.getElementById('problematische-links');
        const countEl = document.getElementById('problematische-count');
        // Ensure we match both string and number IDs
        const pouleId = String(pouleData.id);
        const existingLink = document.querySelector(`[data-probleem-poule="${pouleId}"]`);
        const pouleCard = document.querySelector(`.poule-card[data-poule-id="${pouleId}"]`);

        if (isProblematisch) {
            if (existingLink) {
                // Update count in existing link
                const linkCount = existingLink.querySelector(`[data-probleem-count="${pouleData.id}"]`);
                if (linkCount) linkCount.textContent = pouleData.aantal_judokas;
            } else if (linksContainer) {
                // Add new link
                const nummer = pouleCard?.dataset.pouleNummer || '';
                const leeftijd = pouleCard?.dataset.pouleLeeftijdsklasse || '';
                const gewicht = pouleCard?.dataset.pouleGewichtsklasse || '';

                const newLink = document.createElement('a');
                newLink.href = `#poule-${pouleData.id}`;
                newLink.dataset.probleemPoule = pouleData.id;
                newLink.className = 'inline-flex items-center px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm hover:bg-red-200 cursor-pointer transition-colors';
                newLink.innerHTML = `#${nummer} ${leeftijd} ${gewicht} (<span data-probleem-count="${pouleData.id}">${pouleData.aantal_judokas}</span>)`;
                linksContainer.appendChild(newLink);

                if (countEl) countEl.textContent = parseInt(countEl.textContent || 0) + 1;
            } else {
                // Create entire section
                const nummer = pouleCard?.dataset.pouleNummer || '';
                const leeftijd = pouleCard?.dataset.pouleLeeftijdsklasse || '';
                const gewicht = pouleCard?.dataset.pouleGewichtsklasse || '';

                container.innerHTML = `
                    <div class="bg-red-50 border border-red-300 rounded-lg p-4">
                        <h3 class="font-bold text-red-800 mb-2">Problematische poules (<span id="problematische-count">1</span>)</h3>
                        <p class="text-red-700 text-sm mb-3">Deze poules hebben minder dan 3 actieve judoka's:</p>
                        <div id="problematische-links" class="flex flex-wrap gap-2">
                            <a href="#poule-${pouleData.id}" data-probleem-poule="${pouleData.id}" class="inline-flex items-center px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm hover:bg-red-200 cursor-pointer transition-colors">
                                #${nummer} ${leeftijd} ${gewicht} (<span data-probleem-count="${pouleData.id}">${pouleData.aantal_judokas}</span>)
                            </a>
                        </div>
                    </div>
                `;
            }
        } else {
            // Niet problematisch - verwijder uit lijst als aanwezig
            if (existingLink) {
                existingLink.remove();

                // Update count en verwijder hele section als leeg
                const updatedLinksContainer = document.getElementById('problematische-links');
                if (updatedLinksContainer) {
                    const remaining = updatedLinksContainer.querySelectorAll('[data-probleem-poule]').length;
                    if (countEl) countEl.textContent = remaining;
                    if (remaining === 0 && container) {
                        container.innerHTML = '';
                    }
                }
            }
        }
    }

    // Update "te veel judoka's" lijst (>= 6)
    function updateTeVeelJudokas(pouleData, isTeVeel) {
        const container = document.getElementById('problematische-poules-container');
        const linksContainer = document.getElementById('teveel-links');
        const countEl = document.getElementById('problematische-count');
        const pouleId = String(pouleData.id);
        const existingLink = document.querySelector(`[data-teveel-poule="${pouleId}"]`);
        const pouleCard = document.querySelector(`.poule-card[data-poule-id="${pouleId}"]`);

        if (isTeVeel) {
            if (existingLink) {
                // Update count in existing link
                const linkCount = existingLink.querySelector(`[data-teveel-count="${pouleData.id}"]`);
                if (linkCount) linkCount.textContent = pouleData.aantal_judokas;
            } else {
                // Add new link - maak container als die niet bestaat
                let targetContainer = linksContainer;
                if (!targetContainer) {
                    // Maak "te veel" sectie aan
                    const problemSection = container.querySelector('.bg-red-50');
                    if (problemSection) {
                        const newSection = document.createElement('div');
                        newSection.innerHTML = `
                            <p class="text-purple-700 text-sm mb-2">Te veel judoka's (&ge; 6) - splitsen:</p>
                            <div id="teveel-links" class="flex flex-wrap gap-2 mb-3"></div>
                        `;
                        // Voeg toe na "te weinig" sectie of aan begin
                        const teWeinigSection = problemSection.querySelector('#problematische-links');
                        if (teWeinigSection) {
                            teWeinigSection.parentNode.insertBefore(newSection.firstElementChild, teWeinigSection.nextSibling);
                            teWeinigSection.parentNode.insertBefore(newSection.lastElementChild, teWeinigSection.nextSibling.nextSibling);
                        } else {
                            const heading = problemSection.querySelector('h3');
                            if (heading) heading.insertAdjacentElement('afterend', newSection);
                        }
                        targetContainer = document.getElementById('teveel-links');
                    }
                }

                if (targetContainer) {
                    const nummer = pouleCard?.dataset.pouleNummer || '';
                    const leeftijd = pouleCard?.dataset.pouleLeeftijdsklasse || '';
                    const gewicht = pouleCard?.dataset.pouleGewichtsklasse || '';

                    const newLink = document.createElement('a');
                    newLink.href = `#poule-${pouleData.id}`;
                    newLink.onclick = (e) => scrollToPoule(e, pouleData.id);
                    newLink.dataset.teveelPoule = pouleData.id;
                    newLink.className = 'inline-flex items-center px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm hover:bg-purple-200 cursor-pointer transition-colors';
                    newLink.innerHTML = `#${nummer} ${leeftijd} ${gewicht} (<span data-teveel-count="${pouleData.id}">${pouleData.aantal_judokas}</span>)`;
                    targetContainer.appendChild(newLink);

                    if (countEl) countEl.textContent = parseInt(countEl.textContent || 0) + 1;
                    container.classList.remove('hidden');
                }
            }
        } else {
            // Niet meer te veel - verwijder uit lijst
            if (existingLink) {
                existingLink.remove();
                if (countEl) countEl.textContent = Math.max(0, parseInt(countEl.textContent || 0) - 1);

                // Verwijder sectie als leeg
                const updatedContainer = document.getElementById('teveel-links');
                if (updatedContainer && updatedContainer.children.length === 0) {
                    updatedContainer.previousElementSibling?.remove(); // Label
                    updatedContainer.remove();
                }
            }
        }
    }
});

// Breedte vastzetten: open tijdelijk een blok om de juiste breedte te meten
function fixBlokBreedte() {
    const blokItems = document.querySelectorAll('.blok-item');
    const container = document.getElementById('blokken-container');
    if (!blokItems.length || !container) return;

    // Vind een blok dat we kunnen openen om te meten
    const eersteBlok = blokItems[0];
    const collapseDiv = eersteBlok.querySelector('[x-show="open"]');

    if (collapseDiv) {
        // Sla originele state op
        const wasHidden = collapseDiv.style.display === 'none' || !collapseDiv.offsetHeight;

        // Forceer open voor meting (zonder animatie)
        collapseDiv.style.display = 'block';
        collapseDiv.style.height = 'auto';
        collapseDiv.style.overflow = 'visible';

        // Meet de breedte
        const breedte = eersteBlok.offsetWidth;

        // Herstel originele state als het gesloten was
        if (wasHidden) {
            collapseDiv.style.display = '';
            collapseDiv.style.height = '';
            collapseDiv.style.overflow = '';
        }

        // Zet min-width op container en alle blokken
        if (breedte > 0) {
            container.style.minWidth = breedte + 'px';
            blokItems.forEach(item => {
                item.style.minWidth = breedte + 'px';
            });
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(fixBlokBreedte, 200);
});

// Zoek Match voor wedstrijddag (dynamisch overpoulen)
const zoekMatchUrl = '{{ route("toernooi.poule.zoek-match", [$toernooi, "__JUDOKA_ID__"]) }}';
const naarWachtruimteUrl = '{{ route("toernooi.wedstrijddag.naar-wachtruimte", $toernooi) }}';

async function openZoekMatchWedstrijddag(judokaId, fromPouleId) {
    const modal = document.getElementById('zoek-match-modal');
    const content = document.getElementById('zoek-match-content');
    const loading = document.getElementById('zoek-match-loading');

    modal.classList.remove('hidden');
    loading.classList.remove('hidden');
    content.innerHTML = '';

    try {
        let url = zoekMatchUrl.replace('__JUDOKA_ID__', judokaId) + '?wedstrijddag=1&_t=' + Date.now();
        if (fromPouleId) url += '&from_poule_id=' + fromPouleId;
        const response = await fetch(url, {
            headers: { 'Accept': 'application/json' },
            cache: 'no-store'
        });
        const data = await response.json();

        loading.classList.add('hidden');

        if (!data.success || !data.matches.length) {
            content.innerHTML = '<p class="text-gray-500 text-center py-4">Geen geschikte poules gevonden</p>';
            return;
        }

        // Groepeer per blok
        const blokGroepen = {};
        data.matches.forEach(match => {
            const blokKey = match.blok_nummer || 0;
            if (!blokGroepen[blokKey]) {
                blokGroepen[blokKey] = {
                    naam: match.blok_naam || 'Onbekend',
                    status: match.blok_status,
                    matches: []
                };
            }
            blokGroepen[blokKey].matches.push(match);
        });

        // Sorteer blokken: same, later, earlier_open
        const blokOrder = { 'same': 0, 'later': 1, 'earlier_open': 2 };
        const sortedBlokken = Object.entries(blokGroepen).sort((a, b) => {
            return (blokOrder[a[1].status] ?? 3) - (blokOrder[b[1].status] ?? 3);
        });

        let html = `<div class="mb-3 pb-2 border-b">
            <span class="font-bold">${data.judoka.naam}</span>
            <span class="text-gray-500">(${data.judoka.leeftijd}j, ${data.judoka.gewicht}kg)</span>
        </div>`;

        const blokColors = {
            'same': 'bg-green-100 text-green-800',
            'earlier_closed': 'bg-yellow-100 text-yellow-800'
        };
        const blokLabels = {
            'same': 'Huidig blok',
            'earlier_closed': 'Eerder blok (weging gesloten)'
        };

        for (const [blokNummer, blok] of sortedBlokken) {
            const color = blokColors[blok.status] || 'bg-gray-100 text-gray-800';
            const label = blokLabels[blok.status] || blok.naam;

            html += `<div class="mb-4">
                <div class="text-sm font-medium ${color} px-2 py-1 rounded mb-2">${blok.naam} - ${label}</div>
                <div class="space-y-2">`;

            for (const match of blok.matches) {
                const statusIcon = match.status === 'ok' ? '✅' : match.status === 'warning' ? '⚠️' : '❌';
                // Alleen kg overschrijding tonen bij variabele poules (geen vaste gewichtsklassen)
                const isVariabel = !data.gebruik_gewichtsklassen;
                const overschrijding = isVariabel && match.kg_overschrijding > 0 ? `<span class="text-orange-600 text-sm ml-2">+${match.kg_overschrijding}kg</span>` : '';

                html += `<div class="border rounded p-2 hover:bg-gray-50 cursor-pointer transition-colors"
                    onclick="selecteerPouleWedstrijddag(${judokaId}, ${fromPouleId}, ${match.poule_id})">
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="font-medium">${statusIcon} #${match.poule_nummer} ${match.leeftijdsklasse}${match.gewichtsklasse ? ' ' + match.gewichtsklasse + ' kg' : ''}</span>
                            ${match.categorie_overschrijding ? '<span class="text-orange-500 text-xs ml-1">(andere categorie)</span>' : ''}
                            ${overschrijding}
                        </div>
                    </div>
                    <div class="text-sm text-gray-600 mt-1">
                        <div>Nu: ${match.huidige_judokas} judoka's | ${match.huidige_leeftijd} | ${match.huidige_gewicht}</div>
                        <div>Na: ${match.nieuwe_judokas} judoka's | ${match.nieuwe_leeftijd} | ${match.nieuwe_gewicht}</div>
                    </div>
                </div>`;
            }

            html += '</div></div>';
        }

        content.innerHTML = html;
    } catch (error) {
        console.error('Error:', error);
        loading.classList.add('hidden');
        content.innerHTML = '<p class="text-red-500 text-center py-4">Fout bij laden</p>';
    }
}

async function selecteerPouleWedstrijddag(judokaId, vanPouleId, naarPouleId) {
    try {
        const response = await fetch(verplaatsUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                judoka_id: judokaId,
                poule_id: naarPouleId,
                from_poule_id: vanPouleId
            })
        });

        const data = await response.json();
        if (data.success) {
            // Sluit modal en refresh pagina
            document.getElementById('zoek-match-modal').classList.add('hidden');
            window.location.reload();
        } else {
            alert('Fout: ' + (data.error || data.message || 'Onbekende fout'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Fout bij verplaatsen');
    }
}

function closeZoekMatchModal() {
    document.getElementById('zoek-match-modal').classList.add('hidden');
}
</script>

<!-- Zoek Match Modal (draggable) -->
<div id="zoek-match-modal" class="hidden fixed inset-0 bg-black bg-opacity-30 z-50" onclick="if(event.target === this) closeZoekMatchModal()">
    <div id="zoek-match-dialog" class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[80vh] overflow-hidden absolute" style="top: 50%; left: 50%; transform: translate(-50%, -50%);">
        <div class="flex justify-between items-center px-4 py-3 border-b bg-blue-700 text-white rounded-t-lg cursor-move" id="zoek-match-header">
            <h3 class="font-bold text-lg select-none">Zoek match (wedstrijddag)</h3>
            <button onclick="closeZoekMatchModal()" class="text-white hover:text-gray-200 text-xl">&times;</button>
        </div>
        <div class="p-4 overflow-y-auto max-h-[60vh]">
            <div id="zoek-match-loading" class="text-center py-4">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                <p class="text-gray-500 mt-2">Laden...</p>
            </div>
            <div id="zoek-match-content"></div>
        </div>
    </div>
</div>

<script>
// Make Zoek Match modal draggable
(function() {
    const dialog = document.getElementById('zoek-match-dialog');
    const header = document.getElementById('zoek-match-header');
    let isDragging = false;
    let offsetX, offsetY;

    header.addEventListener('mousedown', function(e) {
        if (e.target.tagName === 'BUTTON') return;
        isDragging = true;
        const rect = dialog.getBoundingClientRect();
        offsetX = e.clientX - rect.left;
        offsetY = e.clientY - rect.top;
        dialog.style.transform = 'none';
        dialog.style.left = rect.left + 'px';
        dialog.style.top = rect.top + 'px';
    });

    document.addEventListener('mousemove', function(e) {
        if (!isDragging) return;
        e.preventDefault();
        dialog.style.left = (e.clientX - offsetX) + 'px';
        dialog.style.top = (e.clientY - offsetY) + 'px';
    });

    document.addEventListener('mouseup', function() {
        isDragging = false;
    });
})();
</script>

<style>
.sortable-ghost {
    opacity: 0.4;
}
</style>
@endsection
