@extends('layouts.app')

@section('title', 'Wedstrijddag Poules')

@section('content')
@php
    $tolerantie = $toernooi->gewicht_tolerantie ?? 0.5;
    // Verzamel alle poules met te weinig actieve judoka's (1 of 2)
    $problematischePoules = collect();
    foreach ($blokken as $blok) {
        foreach ($blok['categories'] as $category) {
            foreach ($category['poules'] as $poule) {
                $actief = $poule->judokas->filter(fn($j) => !$j->moetUitPouleVerwijderd($tolerantie))->count();
                if ($actief > 0 && $actief < 3) {
                    $problematischePoules->push([
                        'id' => $poule->id,
                        'nummer' => $poule->nummer,
                        'leeftijdsklasse' => $poule->leeftijdsklasse,
                        'gewichtsklasse' => $poule->gewichtsklasse,
                        'actief' => $actief,
                    ]);
                }
            }
        }
    }
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

    <!-- Problematische poules -->
    <div id="problematische-poules-container">
    @if($problematischePoules->count() > 0)
    <div class="bg-red-50 border border-red-300 rounded-lg p-4">
        <h3 class="font-bold text-red-800 mb-2">Problematische poules (<span id="problematische-count">{{ $problematischePoules->count() }}</span>)</h3>
        <p class="text-red-700 text-sm mb-3">Deze poules hebben minder dan 3 actieve judoka's:</p>
        <div id="problematische-links" class="flex flex-wrap gap-2">
            @foreach($problematischePoules as $p)
            <a href="#poule-{{ $p['id'] }}" onclick="scrollToPoule(event, {{ $p['id'] }})" data-probleem-poule="{{ $p['id'] }}" class="inline-flex items-center px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm hover:bg-red-200 cursor-pointer transition-colors">
                #{{ $p['nummer'] }} {{ $p['leeftijdsklasse'] }} {{ $p['gewichtsklasse'] }} (<span data-probleem-count="{{ $p['id'] }}">{{ $p['actief'] }}</span>)
            </a>
            @endforeach
        </div>
    </div>
    @endif
    </div>

    @forelse($blokken as $blok)
    <div class="bg-white rounded-lg shadow" x-data="{ open: true }">
        {{-- Blok header (inklapbaar) --}}
        <button @click="open = !open" class="w-full flex justify-between items-center px-4 py-3 bg-gray-800 text-white rounded-t-lg hover:bg-gray-700">
            <div class="flex items-center gap-4">
                <span class="text-lg font-bold">Blok {{ $blok['nummer'] }}</span>
                <span class="text-gray-300 text-sm">{{ $blok['categories']->count() }} categorieën</span>
            </div>
            <svg :class="{ 'rotate-180': open }" class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        {{-- Categories within blok --}}
        <div x-show="open" x-collapse class="divide-y">
            @forelse($blok['categories'] as $category)
            @php
                $isEliminatie = $category['is_eliminatie'] ?? false;
            @endphp
            <div class="bg-white">
                {{-- Category header --}}
                <div class="flex justify-between items-center px-4 py-3 {{ $isEliminatie ? 'bg-orange-100' : 'bg-gray-100' }} border-b">
                    <div class="flex items-center gap-3">
                        <h2 class="text-lg font-bold {{ $isEliminatie ? 'text-orange-800' : '' }}">
                            @if($isEliminatie)⚔️ @endif{{ $category['leeftijdsklasse'] }} {{ $category['gewichtsklasse'] }}
                            @if($isEliminatie)<span class="text-sm font-normal text-orange-600 ml-1">(Eliminatie)</span>@endif
                        </h2>
                        @php
                            $jsLeeftijd = addslashes($category['leeftijdsklasse']);
                            $jsGewicht = addslashes($category['gewichtsklasse']);
                            $jsKey = addslashes($category['key']);
                            // Calculate active judokas per category (excluding afwezig/afwijkend gewicht)
                            $totaalActiefInCategorie = 0;
                            $aantalLegePoules = 0;
                            foreach ($category['poules'] as $p) {
                                $actief = $p->judokas->filter(function($j) use ($tolerantie) {
                                    $isAfwezig = $j->aanwezigheid === 'afwezig';
                                    $isAfwijkend = $j->gewicht_gewogen !== null && !$j->isGewichtBinnenKlasse(null, $tolerantie);
                                    return !$isAfwezig && !$isAfwijkend;
                                })->count();
                                $totaalActiefInCategorie += $actief;
                                if ($actief === 0) $aantalLegePoules++;
                            }
                        @endphp
                        @if(!$isEliminatie)
                        <button
                            onclick="nieuwePoule('{{ $jsLeeftijd }}', '{{ $jsGewicht }}')"
                            class="text-gray-500 hover:text-gray-700 hover:bg-gray-200 px-2 py-0.5 rounded text-sm font-medium"
                            title="Nieuwe poule toevoegen"
                        >
                            + Poule
                        </button>
                        @endif
                    </div>
                    @if($totaalActiefInCategorie > 0 && $aantalLegePoules === 0)
                    @php $isSent = isset($sentToZaaloverzicht[$category['key']]) && $sentToZaaloverzicht[$category['key']]; @endphp
                    <button
                        onclick="naarZaaloverzicht('{{ $jsKey }}')"
                        class="text-white px-3 py-1.5 text-sm rounded transition-all naar-zaaloverzicht-btn {{ $isSent ? 'bg-green-600 hover:bg-green-700' : 'bg-blue-600 hover:bg-blue-700' }}"
                        data-category="{{ $jsKey }}"
                    >
                        {{ $isSent ? '✓ Doorgestuurd' : 'Naar zaaloverzicht' }}
                    </button>
                    @elseif($aantalLegePoules > 0 && !$isEliminatie)
                    <span class="text-orange-600 text-sm italic px-3 py-1.5">{{ $aantalLegePoules }} lege poule(s) - verwijder eerst</span>
                    @elseif($totaalActiefInCategorie === 0)
                    <span class="text-gray-400 text-sm italic px-3 py-1.5">Geen actieve judoka's</span>
                    @endif
                </div>

                <div class="p-4">
                    @if($isEliminatie)
                    {{-- Eliminatie: één grote box met alle judoka's in grid --}}
                    @php
                        $elimPoule = $category['poules']->first();

                        // Collect removed judokas for info tooltip
                        $verwijderdeElim = $elimPoule->judokas->filter(function($j) use ($tolerantie) {
                            $isAfwezig = $j->aanwezigheid === 'afwezig';
                            $isAfwijkend = $j->gewicht_gewogen !== null && !$j->isGewichtBinnenKlasse(null, $tolerantie);
                            return $isAfwezig || $isAfwijkend;
                        });

                        // Calculate active count
                        $aantalActiefElim = $elimPoule->judokas->count() - $verwijderdeElim->count();

                        // Format removed for tooltip
                        $verwijderdeTekstElim = $verwijderdeElim->map(function($j) use ($tolerantie) {
                            $reden = $j->aanwezigheid === 'afwezig' ? 'afwezig' : 'afwijkend gewicht';
                            return $j->naam . ' (' . $reden . ')';
                        });
                    @endphp
                    <div class="mb-3 flex justify-end" x-data="{ open: false }">
                        <div class="relative">
                            <button @click="open = !open" class="bg-gray-500 hover:bg-gray-600 text-white text-sm px-3 py-1.5 rounded">
                                Omzetten naar poules ▾
                            </button>
                            <div x-show="open" @click.away="open = false" class="absolute right-0 mt-1 bg-white border rounded-lg shadow-lg z-10 min-w-[200px]">
                                <button onclick="zetOmNaarPoules({{ $elimPoule->id }}, 'poules')" class="w-full text-left px-4 py-2 hover:bg-gray-100 text-sm">
                                    Alleen poules
                                </button>
                                <button onclick="zetOmNaarPoules({{ $elimPoule->id }}, 'poules_kruisfinale')" class="w-full text-left px-4 py-2 hover:bg-gray-100 text-sm border-t">
                                    Poules + kruisfinale
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="border-2 border-orange-300 rounded-lg overflow-hidden bg-white">
                        <div class="bg-orange-500 text-white px-4 py-2 flex justify-between items-center">
                            <span class="font-bold">{{ $aantalActiefElim }} judoka's</span>
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-orange-200">~{{ $elimPoule->aantal_wedstrijden }} wedstrijden</span>
                                @if($verwijderdeTekstElim->isNotEmpty())
                                <span class="info-icon cursor-pointer text-base opacity-80 hover:opacity-100" title="{{ $verwijderdeTekstElim->join("\n") }}" onclick="alert(this.title)">ⓘ</span>
                                @endif
                            </div>
                        </div>
                        <div class="p-3 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-2">
                            @foreach($elimPoule->judokas as $judoka)
                            @php
                                $isGewogen = $judoka->gewicht_gewogen !== null;
                                $isAfwezig = $judoka->aanwezigheid === 'afwezig';
                                $isAfwijkendGewicht = $isGewogen && !$judoka->isGewichtBinnenKlasse(null, $tolerantie);
                                $isDoorgestreept = $isAfwezig || $isAfwijkendGewicht;
                            @endphp
                            @if($isDoorgestreept)
                                @continue
                            @endif
                            <div class="px-2 py-1.5 rounded text-sm bg-orange-50 border border-orange-200">
                                <div class="flex items-center gap-1">
                                    @if($isGewogen)
                                        <span class="text-green-500 text-xs">●</span>
                                    @endif
                                    <div class="min-w-0">
                                        <div class="font-medium text-gray-800 truncate" title="{{ $judoka->naam }}">{{ $judoka->naam }}</div>
                                        <div class="text-xs text-gray-500 truncate">{{ $judoka->club?->naam ?? '-' }}</div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @else
                    {{-- Normale poules --}}
                    <div class="flex gap-4">
                        {{-- Existing poules --}}
                        <div class="flex flex-wrap gap-4 flex-1">
                            @foreach($category['poules'] as $poule)
                            @php
                                // Collect removed judokas for info tooltip
                                $verwijderdeJudokas = $poule->judokas->filter(function($j) use ($tolerantie) {
                                    $isAfwezig = $j->aanwezigheid === 'afwezig';
                                    $isAfwijkend = $j->gewicht_gewogen !== null && !$j->isGewichtBinnenKlasse(null, $tolerantie);
                                    return $isAfwezig || $isAfwijkend;
                                });

                                // Calculate active count (total minus removed)
                                $aantalActief = $poule->judokas->count() - $verwijderdeJudokas->count();
                                $aantalWedstrijden = $poule->aantal_wedstrijden;
                                $isProblematisch = $aantalActief > 0 && $aantalActief < 3;

                                // Format removed for tooltip
                                $verwijderdeTekst = $verwijderdeJudokas->map(function($j) use ($tolerantie) {
                                    $reden = $j->aanwezigheid === 'afwezig' ? 'afwezig' : 'afwijkend gewicht';
                                    return $j->naam . ' (' . $reden . ')';
                                });
                            @endphp
                            <div
                                id="poule-{{ $poule->id }}"
                                class="border rounded-lg overflow-hidden min-w-[200px] bg-white transition-colors poule-card {{ $aantalActief === 0 ? 'opacity-50' : '' }} {{ $isProblematisch ? 'border-2 border-red-300' : '' }}"
                                data-poule-id="{{ $poule->id }}"
                                data-poule-nummer="{{ $poule->nummer }}"
                                data-poule-leeftijdsklasse="{{ $poule->leeftijdsklasse }}"
                                data-poule-gewichtsklasse="{{ $poule->gewichtsklasse }}"
                                data-actief="{{ $aantalActief }}"
                            >
                                <div class="{{ $aantalActief === 0 ? 'bg-gray-500' : ($isProblematisch ? 'bg-red-600' : 'bg-blue-700') }} text-white px-3 py-2 poule-header flex justify-between items-start">
                                    <div class="pointer-events-none flex-1">
                                        <div class="font-bold text-sm">#{{ $poule->nummer }} {{ $poule->leeftijdsklasse }} / {{ $poule->gewichtsklasse }}</div>
                                        <div class="text-xs {{ $aantalActief === 0 ? 'text-gray-300' : ($isProblematisch ? 'text-red-200' : 'text-blue-200') }} poule-stats"><span class="poule-actief">{{ $aantalActief }}</span> judoka's <span class="poule-wedstrijden">{{ $aantalWedstrijden }}</span> wedstrijden</div>
                                    </div>
                                    <div class="flex items-center gap-1 flex-shrink-0">
                                        @if($verwijderdeTekst->isNotEmpty())
                                        <span class="info-icon cursor-pointer text-base opacity-80 hover:opacity-100" title="{{ $verwijderdeTekst->join("\n") }}" onclick="alert(this.title)">ⓘ</span>
                                        @endif
                                        @if($aantalActief === 0)
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
                                        $isAfwezig = $judoka->aanwezigheid === 'afwezig';
                                        $isAfwijkendGewicht = $isGewogen && !$judoka->isGewichtBinnenKlasse(null, $tolerantie);
                                        $isDoorgestreept = $isAfwezig || $isAfwijkendGewicht;
                                    @endphp
                                    @if($isDoorgestreept)
                                        @continue
                                    @endif
                                    <div
                                        class="px-2 py-1.5 text-sm judoka-item hover:bg-blue-50 cursor-move"
                                        data-judoka-id="{{ $judoka->id }}"
                                        draggable="true"
                                    >
                                        <div class="flex justify-between items-start">
                                            <div class="flex items-center gap-1 flex-1 min-w-0">
                                                {{-- Status marker: green = gewogen --}}
                                                @if($isGewogen)
                                                    <span class="text-green-500 text-xs flex-shrink-0">●</span>
                                                @endif
                                                <div class="min-w-0">
                                                    <div class="font-medium text-gray-800 truncate">{{ $judoka->naam }} <span class="text-gray-400 font-normal">({{ $judoka->gewichtsklasse }})</span></div>
                                                    <div class="text-xs text-gray-500 truncate">{{ $judoka->club?->naam ?? '-' }}</div>
                                                </div>
                                            </div>
                                            <div class="text-right text-xs flex-shrink-0">
                                                <div class="text-gray-600 font-medium">{{ $judoka->gewicht_gewogen ? $judoka->gewicht_gewogen . ' kg' : ($judoka->gewicht ? $judoka->gewicht . ' kg' : '-') }}</div>
                                                <div class="text-gray-400">{{ ucfirst($judoka->band) }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endforeach
                        </div>

                        {{-- Wachtruimte (rechts) --}}
                        <div class="border-2 border-dashed border-orange-300 rounded-lg p-3 min-w-[200px] bg-orange-50 flex-shrink-0 wachtruimte-container" data-category="{{ $category['key'] }}">
                            <div class="font-medium text-sm text-orange-600 mb-2 flex justify-between">
                                <span>Wachtruimte</span>
                                <span class="text-xs text-orange-400 wachtruimte-count">{{ count($category['wachtruimte']) }}</span>
                            </div>
                            <div class="divide-y divide-orange-200 sortable-wachtruimte min-h-[40px]" data-category="{{ $category['key'] }}">
                                @forelse($category['wachtruimte'] as $judoka)
                                <div
                                    class="px-2 py-1.5 hover:bg-orange-100 cursor-move text-sm judoka-item"
                                    data-judoka-id="{{ $judoka->id }}"
                                >
                                    <div class="flex justify-between items-start">
                                        <div class="flex items-center gap-1 flex-1 min-w-0">
                                            <span class="text-red-500 text-xs flex-shrink-0">●</span>
                                            <div class="min-w-0">
                                                <div class="font-medium text-gray-800 truncate">{{ $judoka->naam }} <span class="text-gray-400 font-normal">({{ $judoka->gewichtsklasse }})</span></div>
                                                <div class="text-xs text-gray-500 truncate">{{ $judoka->club?->naam ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <div class="text-right text-xs ml-2 flex-shrink-0">
                                            <div class="text-orange-600 font-medium">{{ $judoka->gewicht_gewogen ?? $judoka->gewicht }} kg</div>
                                            <div class="text-gray-400">{{ ucfirst($judoka->band) }}</div>
                                        </div>
                                    </div>
                                </div>
                                @empty
                                <div class="text-sm text-gray-400 italic py-2 text-center">Leeg</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @empty
            <div class="p-4 text-gray-500 text-center">Geen categorieën in dit blok</div>
            @endforelse
        </div>
    </div>
    @empty
    <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
        Geen blokken gevonden. Maak eerst blokken aan via de Blokken pagina.
    </div>
    @endforelse
</div>

<!-- SortableJS for drag and drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
const verifieerUrl = '{{ route('toernooi.poule.verifieer', $toernooi) }}';
const verwijderPouleUrl = '{{ route('toernooi.poule.destroy', [$toernooi, ':id']) }}';
const zetOmNaarPoulesUrl = '{{ route('toernooi.wedstrijddag.zetOmNaarPoules', $toernooi) }}';

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
        // Scroll with offset so title is clearly visible (100px from top)
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
                const categoryKey = pouleCard.dataset.pouleLeeftijdsklasse + '|' + pouleCard.dataset.pouleGewichtsklasse;
                pouleCard.remove();

                // Check if category now has no empty poules - show "Naar zaaloverzicht" button
                updateCategoryStatus(categoryKey);
            }
        } else {
            alert(data.message || 'Fout bij verwijderen');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Fout bij verwijderen');
    }
}

function updateCategoryStatus(categoryKey) {
    // Find all poules in this category
    const [leeftijd, gewicht] = categoryKey.split('|');
    const poules = document.querySelectorAll(`.poule-card[data-poule-leeftijdsklasse="${leeftijd}"][data-poule-gewichtsklasse="${gewicht}"]`);

    let totaalActief = 0;
    let aantalLeeg = 0;

    poules.forEach(poule => {
        const actief = parseInt(poule.dataset.actief) || 0;
        totaalActief += actief;
        if (actief === 0) aantalLeeg++;
    });

    // Find the category header
    const categoryHeader = Array.from(document.querySelectorAll('.bg-gray-100.border-b')).find(header => {
        const title = header.querySelector('h2');
        return title && title.textContent.trim() === `${leeftijd} ${gewicht}`;
    });

    if (!categoryHeader) return;

    // Find the right side of the header (where button/message goes)
    const rightSide = categoryHeader.querySelector('.flex.items-center.gap-3')?.parentElement;
    if (!rightSide) return;

    // Remove existing button/message
    const existingBtn = rightSide.querySelector('.naar-zaaloverzicht-btn');
    const existingMsg = rightSide.querySelector('span.text-orange-600, span.text-gray-400');
    if (existingBtn) existingBtn.remove();
    if (existingMsg) existingMsg.remove();

    // Add appropriate element
    if (totaalActief > 0 && aantalLeeg === 0) {
        const btn = document.createElement('button');
        btn.className = 'text-white px-3 py-1.5 text-sm rounded transition-all naar-zaaloverzicht-btn bg-blue-600 hover:bg-blue-700';
        btn.dataset.category = categoryKey;
        btn.innerHTML = 'Naar zaaloverzicht';
        btn.onclick = function() {
            Alpine.evaluate(this, `naarZaaloverzicht('${categoryKey}')`);
        };
        rightSide.appendChild(btn);
    } else if (aantalLeeg > 0) {
        const msg = document.createElement('span');
        msg.className = 'text-orange-600 text-sm italic px-3 py-1.5';
        msg.textContent = `${aantalLeeg} lege poule(s) - verwijder eerst`;
        rightSide.appendChild(msg);
    } else {
        const msg = document.createElement('span');
        msg.className = 'text-gray-400 text-sm italic px-3 py-1.5';
        msg.textContent = 'Geen actieve judoka\'s';
        rightSide.appendChild(msg);
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

// Global state for sent categories
const sentCategories = @json($sentToZaaloverzicht ?? []);

function wedstrijddagPoules() {
    return {
        sentCategories: sentCategories,
    }
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

async function naarZaaloverzicht(categoryKey) {
    try {
        const response = await fetch('{{ route("toernooi.wedstrijddag.naar-zaaloverzicht", $toernooi) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ category: categoryKey }),
        });

        if (response.ok) {
            sentCategories[categoryKey] = true;
            // Update button appearance
            const btn = document.querySelector(`.naar-zaaloverzicht-btn[data-category="${categoryKey}"]`);
            if (btn) {
                btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                btn.classList.add('bg-green-600', 'hover:bg-green-700');
                btn.innerHTML = '✓ Doorgestuurd';
            }
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

async function nieuwePoule(leeftijdsklasse, gewichtsklasse) {
    try {
        const response = await fetch('{{ route("toernooi.wedstrijddag.nieuwe-poule", $toernooi) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ leeftijdsklasse, gewichtsklasse }),
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
        console.log('updatePouleFromDOM called with:', pouleId);
        const pouleCard = document.querySelector(`.poule-card[data-poule-id="${pouleId}"]`);
        if (!pouleCard) {
            console.log('pouleCard NOT found for id:', pouleId);
            return;
        }
        console.log('pouleCard found:', pouleCard);

        const container = pouleCard.querySelector('.sortable-poule');
        if (!container) {
            console.log('sortable-poule container NOT found');
            return;
        }

        // Tel judoka's in de DOM
        const judokaItems = container.querySelectorAll('.judoka-item');
        const aantalJudokas = judokaItems.length;
        const aantalWedstrijden = berekenWedstrijden(aantalJudokas);
        console.log('Counted:', aantalJudokas, 'judokas,', aantalWedstrijden, 'wedstrijden');

        // Update data attribute
        pouleCard.dataset.actief = aantalJudokas;

        // Update tekst in header
        const actiefSpan = pouleCard.querySelector('.poule-actief');
        const wedstrijdenSpan = pouleCard.querySelector('.poule-wedstrijden');
        console.log('Spans:', actiefSpan, wedstrijdenSpan);
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
    }

    // Initialize sortable on all poule containers
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

                console.log('=== DRAG END ===');
                console.log('vanPouleId:', vanPouleId, 'naarPouleId:', naarPouleId);
                console.log('from wachtruimte?', evt.from.classList.contains('sortable-wachtruimte'));

                // Direct DOM update - geen wachten op API
                if (vanPouleId) {
                    console.log('Updating van poule:', vanPouleId);
                    updatePouleFromDOM(vanPouleId);
                }
                if (naarPouleId) {
                    console.log('Updating naar poule:', naarPouleId);
                    updatePouleFromDOM(naarPouleId);
                }

                // Update wachtruimte count
                if (evt.from.classList.contains('sortable-wachtruimte')) {
                    const countEl = evt.from.closest('.wachtruimte-container')?.querySelector('.wachtruimte-count');
                    console.log('Wachtruimte countEl:', countEl, 'current:', countEl?.textContent);
                    if (countEl) countEl.textContent = Math.max(0, parseInt(countEl.textContent) - 1);
                }

                // API call voor database sync (op achtergrond)
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
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Fout bij verplaatsen: ' + error.message);
                    window.location.reload();
                }
            }
        });
    });

    // Initialize sortable on wachtruimte (only as source, not drop target)
    document.querySelectorAll('.sortable-wachtruimte').forEach(container => {
        new Sortable(container, {
            group: {
                name: 'wedstrijddag-poules',
                pull: true,
                put: false
            },
            animation: 150,
            ghostClass: 'bg-orange-200',
            sort: false
        });
    });

    function updateProblematischePoules(pouleData, isProblematisch) {
        const container = document.getElementById('problematische-poules-container');
        const linksContainer = document.getElementById('problematische-links');
        const countEl = document.getElementById('problematische-count');
        const existingLink = document.querySelector(`[data-probleem-poule="${pouleData.id}"]`);
        const pouleCard = document.querySelector(`.poule-card[data-poule-id="${pouleData.id}"]`);

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
        } else if (existingLink) {
            // Remove from problematic list
            existingLink.remove();

            const newLinksContainer = document.getElementById('problematische-links');
            if (countEl && newLinksContainer) {
                const remaining = newLinksContainer.querySelectorAll('[data-probleem-poule]').length;
                countEl.textContent = remaining;

                if (remaining === 0) {
                    container.innerHTML = '';
                }
            }
        }
    }
});
</script>

<style>
.sortable-ghost {
    opacity: 0.4;
}
</style>
@endsection
