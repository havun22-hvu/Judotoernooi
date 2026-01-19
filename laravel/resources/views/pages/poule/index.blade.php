@extends('layouts.app')

@section('title', 'Poules')

@section('content')
@php
    // Toegestane poule groottes uit instellingen (default [5, 4, 6, 3])
    $toegestaneGroottes = $toernooi->poule_grootte_voorkeur ?? [5, 4, 6, 3];
    // Een poule is problematisch als grootte > 0 EN niet in toegestane groottes
    $isProblematischeGrootte = fn($count) => $count > 0 && !in_array($count, $toegestaneGroottes);
    // Check of inschrijving gesloten is (weegkaarten hebben dan al bloknummers)
    $inschrijvingGesloten = !$toernooi->isInschrijvingOpen();
@endphp

{{-- Knipperende popup als inschrijving gesloten is --}}
@if($inschrijvingGesloten)
<div id="inschrijving-popup" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center no-print">
    <div class="bg-white rounded-lg shadow-2xl p-6 max-w-md mx-4 animate-pulse-warning">
        <div class="text-center">
            <span class="text-6xl">⚠️</span>
            <h2 class="text-2xl font-bold text-orange-600 mt-4">Inschrijving is gesloten!</h2>
            <p class="text-gray-600 mt-3">De weegkaarten zijn al geprint met bloknummers. Wijzigingen hier kunnen problemen veroorzaken.</p>
            <p class="text-gray-700 font-medium mt-4">Gebruik op de wedstrijddag:</p>
            <div class="flex flex-col gap-2 mt-3">
                <a href="{{ route('toernooi.wedstrijddag.poules', $toernooi) }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg">
                    Wedstrijddag Poules
                </a>
                <a href="{{ route('toernooi.blok.zaaloverzicht', $toernooi) }}" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg">
                    Zaaloverzicht
                </a>
            </div>
            <button onclick="sluitInschrijvingPopup()" class="mt-4 text-gray-500 hover:text-gray-700 text-sm underline">
                Ik begrijp het, toch doorgaan
            </button>
        </div>
    </div>
</div>
<style>
    @keyframes pulse-warning {
        0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(234, 88, 12, 0.7); }
        50% { transform: scale(1.02); box-shadow: 0 0 30px 10px rgba(234, 88, 12, 0.4); }
    }
    .animate-pulse-warning { animation: pulse-warning 1s ease-in-out infinite; }
</style>
<script>
    let inschrijvingPopupGetoond = false;
    function toonInschrijvingPopup() {
        if (inschrijvingPopupGetoond) return;
        const popup = document.getElementById('inschrijving-popup');
        popup.classList.remove('hidden');
        popup.classList.add('flex');
    }
    function sluitInschrijvingPopup() {
        inschrijvingPopupGetoond = true;
        const popup = document.getElementById('inschrijving-popup');
        popup.classList.add('hidden');
        popup.classList.remove('flex');
    }
    // Trigger bij drag start, klik op actie knoppen, etc.
    document.addEventListener('DOMContentLoaded', function() {
        // Bij drag start
        document.addEventListener('dragstart', toonInschrijvingPopup);
        // Bij klik op actie knoppen (verplaats, verwijder, etc.)
        document.querySelectorAll('[onclick*="verplaats"], [onclick*="verwijder"], [onclick*="nieuw"], .sortable-poule').forEach(el => {
            el.addEventListener('mousedown', toonInschrijvingPopup);
        });
    });
</script>
@endif
{{-- Statistieken sectie (blijft zichtbaar) --}}
<div id="poule-statistieken" class="bg-white rounded-lg shadow p-4 mb-6 no-print">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
        <div>
            <div class="text-2xl font-bold text-blue-600" id="stat-poules">{{ $poules->count() }}</div>
            <div class="text-sm text-gray-600">Poules</div>
        </div>
        <div>
            <div class="text-2xl font-bold text-green-600" id="stat-wedstrijden">{{ $poules->sum('aantal_wedstrijden') }}</div>
            <div class="text-sm text-gray-600">Wedstrijden</div>
        </div>
        <div>
            <div class="text-2xl font-bold text-purple-600" id="stat-judokas">{{ $poules->sum('judokas_count') }}</div>
            <div class="text-sm text-gray-600">Judoka's</div>
        </div>
        <div>
            <div class="text-2xl font-bold text-orange-600" id="stat-problematisch">{{ $poules->filter(fn($p) => $isProblematischeGrootte($p->judokas_count) && $p->type !== 'eliminatie' && $p->type !== 'kruisfinale')->count() }}</div>
            <div class="text-sm text-gray-600">Problemen</div>
        </div>
    </div>
</div>

<div class="flex justify-between items-center mb-6 no-print">
    <h1 class="text-3xl font-bold text-gray-800">Poules (<span id="poule-count">{{ $poules->count() }}</span>)</h1>
    <div class="flex items-center space-x-4">
        <span class="text-sm text-gray-500">Sleep judoka's tussen poules</span>
        <form action="{{ route('toernooi.poule.genereer', $toernooi) }}" method="POST" class="inline"
              onsubmit="return {{ $poules->count() }} === 0 || confirm('WAARSCHUWING: Dit verwijdert ALLE huidige poules inclusief handmatige wijzigingen en maakt een nieuwe indeling. Weet je het zeker?')">
            @csrf
            <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white font-bold py-2 px-4 rounded">
                (her)Verdelen
            </button>
        </form>
        <button onclick="verifieerPoules()" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
            Verifieer poules
        </button>
        <button onclick="openNieuwePouleModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            + Nieuwe poule
        </button>
    </div>
</div>

<!-- Verificatie resultaat -->
<div id="verificatie-resultaat" class="hidden mb-6"></div>

<!-- Toast notification -->
<div id="toast" class="fixed top-4 right-4 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300 z-50">
    <span id="toast-message"></span>
</div>

@php
    // Poules zijn problematisch als grootte niet in toegestane groottes staat (excl. eliminatie/kruisfinale)
    $problematischePoules = $poules->filter(fn($p) => $isProblematischeGrootte($p->judokas_count) && $p->type !== 'eliminatie' && $p->type !== 'kruisfinale');
@endphp

<div id="problematische-poules-container">
@if($problematischePoules->count() > 0)
<div class="bg-red-50 border border-red-300 rounded-lg p-4 mb-6">
    <h3 class="font-bold text-red-800 mb-2">Problematische poules (<span id="problematische-count">{{ $problematischePoules->count() }}</span>)</h3>
    <p class="text-red-700 text-sm mb-3">Deze poules hebben een niet-toegestane grootte (toegestaan: {{ implode(', ', $toegestaneGroottes) }}). Klik om naar de poule te gaan:</p>
    <div id="problematische-links" class="flex flex-wrap gap-2">
        @foreach($problematischePoules as $p)
        <a href="#poule-{{ $p->id }}" data-probleem-poule="{{ $p->id }}" class="inline-flex items-center px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm hover:bg-red-200 cursor-pointer transition-colors">
            #{{ $p->nummer }} {{ $leeftijdsklasseLabels[$p->leeftijdsklasse] ?? $p->leeftijdsklasse }} / {{ $p->gewichtsklasse }} kg (<span data-probleem-count="{{ $p->id }}">{{ $p->judokas_count }}</span>)
        </a>
        @endforeach
    </div>
</div>
@endif
</div>

<!-- Per leeftijdsklasse -->
@forelse($poulesPerKlasse as $leeftijdsklasse => $klassePoules)
<div class="mb-8 w-full" x-data="{ open: true }">
    <button @click="open = !open" class="w-full flex justify-between items-center bg-blue-800 text-white px-4 py-3 rounded-t-lg hover:bg-blue-700">
        <span class="text-lg font-bold">{{ $leeftijdsklasseLabels[$leeftijdsklasse] ?? $leeftijdsklasse }} ({{ $klassePoules->count() }} poules, {{ $klassePoules->sum('judokas_count') }} judoka's)</span>
        <svg :class="{ 'rotate-180': open }" class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div x-show="open" x-collapse class="bg-gray-50 rounded-b-lg shadow p-4">
        @php
            // Groepeer per 5kg blok (20-24, 25-29, 30-34, etc.)
            $poulesPerGewichtBlok = $klassePoules->groupBy(function($poule) {
                // Haal het eerste getal uit gewichtsklasse (bijv. "30-35" -> 30, "-38" -> 38)
                if (preg_match('/(\d+)/', $poule->gewichtsklasse, $m)) {
                    $kg = (int) $m[1];
                    return floor($kg / 5) * 5; // Rond af naar 5kg blokken
                }
                return 0;
            })->sortKeys();
        @endphp
        @foreach($poulesPerGewichtBlok as $gewichtBlok => $gewichtPoules)
        <div class="mb-4 last:mb-0">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
            @foreach($gewichtPoules as $poule)
            @php
                $isEliminatie = $poule->type === 'eliminatie';
                $isKruisfinale = $poule->isKruisfinale();
                $isProbleem = $isProblematischeGrootte($poule->judokas_count) && !$isKruisfinale && !$isEliminatie;

                // Bereken leeftijd en gewicht ranges uit judoka's
                $leeftijdRange = '';
                $gewichtRange = '';
                if ($poule->judokas->count() > 0) {
                    $huidigJaar = now()->year;
                    $leeftijden = $poule->judokas->map(fn($j) => $huidigJaar - $j->geboortejaar)->filter();

                    // VOORBEREIDING: Toon altijd INGESCHREVEN gewicht voor range berekening
                    $gewichten = $poule->judokas->map(function($j) {
                        if ($j->gewicht !== null) return $j->gewicht;
                        // Gewichtsklasse is bijv. "-38" of "+73" - extract getal
                        if ($j->gewichtsklasse && preg_match('/(\d+)/', $j->gewichtsklasse, $m)) {
                            return (float) $m[1];
                        }
                        return null;
                    })->filter();

                    if ($leeftijden->count() > 0) {
                        $minL = $leeftijden->min();
                        $maxL = $leeftijden->max();
                        $leeftijdRange = $minL === $maxL ? "{$minL}j" : "{$minL}-{$maxL}j";
                    }
                    if ($gewichten->count() > 0) {
                        $minG = $gewichten->min();
                        $maxG = $gewichten->max();
                        $gewichtRange = $minG === $maxG ? "{$minG}kg" : "{$minG}-{$maxG}kg";
                    }
                }
            @endphp
            <div id="poule-{{ $poule->id }}" class="bg-white rounded-lg shadow {{ $isEliminatie ? 'border-2 border-orange-400 col-span-full' : '' }} {{ $isProbleem ? 'border-2 border-red-300' : '' }} {{ $isKruisfinale ? 'border-2 border-purple-300' : '' }}" data-poule-id="{{ $poule->id }}" data-poule-nummer="{{ $poule->nummer }}" data-poule-leeftijdsklasse="{{ $poule->leeftijdsklasse }}" data-poule-gewichtsklasse="{{ $poule->gewichtsklasse }}" data-poule-is-kruisfinale="{{ $isKruisfinale ? '1' : '0' }}" data-poule-is-eliminatie="{{ $isEliminatie ? '1' : '0' }}">
                <!-- Poule header -->
                <div class="px-3 py-2 border-b {{ $isEliminatie ? 'bg-orange-100' : ($isKruisfinale ? 'bg-purple-100' : ($isProbleem ? 'bg-red-100' : 'bg-blue-100')) }}">
                    <div class="flex justify-between items-center">
                        <div class="font-bold text-sm {{ $isEliminatie ? 'text-orange-800' : ($isKruisfinale ? 'text-purple-800' : ($isProbleem ? 'text-red-800' : 'text-blue-800')) }}">
                            @if($isEliminatie)
                                #{{ $poule->nummer }} ⚔️ {{ $poule->gewichtsklasse }} kg - Eliminatie
                            @elseif($isKruisfinale)
                                #{{ $poule->nummer }} Kruisfinale {{ $poule->gewichtsklasse }} kg
                            @else
                                {{-- Gebruik opgeslagen titel (bevat label, leeftijd range en gewicht range) --}}
                                <span class="text-gray-900" data-poule-titel="{{ $poule->id }}">#{{ $poule->nummer }} {{ $poule->titel }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            {{-- Wedstrijdsysteem dropdown voor alle poule types --}}
                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open" class="bg-gray-500 hover:bg-gray-600 text-white text-xs px-2 py-1 rounded">
                                    Omzetten ▾
                                </button>
                                <div x-show="open" @click.away="open = false" class="absolute right-0 mt-1 bg-white border rounded-lg shadow-lg z-10 min-w-[180px]">
                                    @if($isEliminatie)
                                    <button onclick="zetOmNaarPoules({{ $poule->id }}, 'poules')" class="w-full text-left px-3 py-2 hover:bg-gray-100 text-sm">
                                        Alleen poules
                                    </button>
                                    <button onclick="zetOmNaarPoules({{ $poule->id }}, 'poules_kruisfinale')" class="w-full text-left px-3 py-2 hover:bg-gray-100 text-sm border-t">
                                        Poules + kruisfinale
                                    </button>
                                    @elseif($isKruisfinale)
                                    <button onclick="zetOmNaar({{ $poule->id }}, 'poules')" class="w-full text-left px-3 py-2 hover:bg-gray-100 text-sm">
                                        Alleen poules
                                    </button>
                                    <button onclick="zetOmNaar({{ $poule->id }}, 'eliminatie')" class="w-full text-left px-3 py-2 hover:bg-gray-100 text-sm border-t">
                                        Eliminatie
                                    </button>
                                    @else
                                    <button onclick="zetOmNaar({{ $poule->id }}, 'eliminatie')" class="w-full text-left px-3 py-2 hover:bg-gray-100 text-sm">
                                        Eliminatie
                                    </button>
                                    <button onclick="zetOmNaar({{ $poule->id }}, 'poules_kruisfinale')" class="w-full text-left px-3 py-2 hover:bg-gray-100 text-sm border-t">
                                        Poules + kruisfinale
                                    </button>
                                    @endif
                                </div>
                            </div>
                            @if($poule->judokas_count === 0 && !$isEliminatie)
                            <button onclick="verwijderPoule({{ $poule->id }}, '{{ $poule->nummer }}')" class="delete-empty-btn text-red-500 hover:text-red-700 font-bold text-lg leading-none" title="Verwijder poule">&minus;</button>
                            @endif
                        </div>
                    </div>
                    <div class="flex justify-between items-center text-xs text-gray-500">
                        @if($poule->isKruisfinale())
                        <span class="flex items-center gap-1">
                            Top
                            <select onchange="updateKruisfinalesPlaatsen({{ $poule->id }}, this.value)" class="border rounded px-1 py-0.5 text-xs bg-white">
                                @for($i = 1; $i <= 3; $i++)
                                <option value="{{ $i }}" {{ $poule->kruisfinale_plaatsen == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                            door → <span data-poule-count="{{ $poule->id }}">{{ $poule->aantal_judokas }}</span> judoka's
                        </span>
                        @else
                        <span><span data-poule-count="{{ $poule->id }}">{{ $poule->judokas_count }}</span> judoka's</span>
                        @endif
                        <span><span data-poule-wedstrijden="{{ $poule->id }}">{{ $poule->judokas_count < 2 && !$poule->isKruisfinale() ? '-' : $poule->aantal_wedstrijden }}</span> wedstrijden</span>
                    </div>
                </div>

                <!-- Judoka's in poule (sortable) -->
                <div class="{{ $isEliminatie ? 'grid grid-cols-5 gap-1 p-2' : 'divide-y divide-gray-100' }} min-h-[60px] sortable-poule" data-poule-id="{{ $poule->id }}">
                    @foreach($poule->judokas as $judoka)
                    @php
                        if ($judoka->gewicht) {
                            $toonGewicht = $judoka->gewicht . 'kg';
                        } elseif ($judoka->gewichtsklasse) {
                            $toonGewicht = str_replace('-', '≤', $judoka->gewichtsklasse) . 'kg';
                        } else {
                            $toonGewicht = null;
                        }
                        $isGewogen = $judoka->gewicht_gewogen !== null;
                    @endphp
                    <div class="px-3 py-2 hover:bg-blue-50 cursor-move text-sm judoka-item group {{ $isEliminatie ? 'border border-gray-200 rounded' : '' }}"
                         data-judoka-id="{{ $judoka->id }}"
                         data-poule-id="{{ $poule->id }}"
                         data-judoka-naam="{{ $judoka->naam }}"
                         data-judoka-leeftijd="{{ $judoka->leeftijd }}"
                         data-judoka-gewicht="{{ $judoka->gewicht ?? '' }}">
                        <div class="flex justify-between items-start">
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-gray-800 truncate">{{ $judoka->naam }} <span class="text-gray-400 font-normal">({{ $judoka->leeftijd }}j)</span></div>
                                <div class="text-xs text-gray-500 truncate">{{ $judoka->club?->naam ?? '-' }}</div>
                            </div>
                            <div class="text-right text-xs">
                                <div class="{{ $isGewogen ? 'text-green-600' : 'text-gray-600' }} font-medium">{{ $toonGewicht ?? '-' }}</div>
                                <div class="text-gray-400">{{ ucfirst($judoka->band) }}</div>
                            </div>
                        </div>
                    </div>
                    @endforeach

                    @if($poule->judokas->isEmpty())
                    <div class="px-3 py-4 text-gray-400 text-sm italic text-center empty-placeholder {{ $isEliminatie ? 'col-span-5' : '' }}">Leeg</div>
                    @endif
                </div>
            </div>
            @endforeach
            </div>
        </div>
        @endforeach
    </div>
</div>
@empty
<div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
    Nog geen poules. Genereer eerst de poule-indeling.
</div>
@endforelse

<!-- Modal nieuwe poule -->
<div id="nieuwe-poule-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Nieuwe poule aanmaken</h2>
        <form id="nieuwe-poule-form">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Leeftijdsklasse</label>
                <select id="leeftijdsklasse" class="w-full border rounded px-3 py-2" required>
                    <option value="">Selecteer...</option>
                    @foreach($toernooi->getAlleGewichtsklassen() as $key => $klasse)
                    <option value="{{ $key }}" data-label="{{ $klasse['label'] }}">{{ $klasse['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Gewichtsklasse</label>
                <select id="gewichtsklasse" class="w-full border rounded px-3 py-2" required disabled>
                    <option value="">Selecteer eerst leeftijdsklasse</option>
                </select>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeNieuwePouleModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                    Annuleren
                </button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Aanmaken
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Context menu voor judoka -->
<div id="judoka-context-menu" class="fixed hidden bg-white rounded-lg shadow-xl border border-gray-200 py-1 z-50 min-w-[160px]">
    <button onclick="openZoekMatch()" class="w-full px-4 py-2 text-left text-sm hover:bg-blue-50 flex items-center gap-2">
        <span>🔍</span> Zoek match
    </button>
</div>

<!-- Zoek Match Modal (draggable) -->
<div id="zoek-match-modal" class="fixed inset-0 bg-black bg-opacity-30 hidden z-50 pointer-events-none">
    <div id="zoek-match-modal-content" class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[80vh] flex flex-col absolute pointer-events-auto" style="top: 50%; left: 50%; transform: translate(-50%, -50%);">
        <div id="zoek-match-modal-header" class="p-4 border-b flex justify-between items-center cursor-move select-none bg-gray-50 rounded-t-lg">
            <h2 class="text-lg font-bold text-gray-800">
                Match voor: <span id="zoek-match-judoka-naam"></span>
                <span class="text-gray-500 font-normal" id="zoek-match-judoka-info"></span>
            </h2>
            <button onclick="closeZoekMatchModal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <div class="p-4 overflow-y-auto flex-1" id="zoek-match-results">
            <p class="text-gray-500 text-center py-8">Laden...</p>
        </div>
    </div>
</div>

<!-- SortableJS for drag and drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const verifieerUrl = '{{ route('toernooi.poule.verifieer', $toernooi) }}';
const verplaatsUrl = '{{ route('toernooi.poule.verplaats-judoka-api', $toernooi) }}';
const zoekMatchUrl = '{{ route('toernooi.poule.zoek-match', [$toernooi, '__JUDOKA_ID__']) }}';
const nieuwePouleUrl = '{{ route('toernooi.poule.store', $toernooi) }}';
const verwijderPouleUrl = '{{ route('toernooi.poule.destroy', [$toernooi, ':id']) }}';
const updateKruisfinaleUrl = '{{ route('toernooi.poule.update-kruisfinale', [$toernooi, ':id']) }}';
const zetOmNaarPoulesUrl = '{{ route('toernooi.wedstrijddag.zetOmNaarPoules', $toernooi) }}';
const wijzigPouleTypeUrl = '{{ route('toernooi.wedstrijddag.wijzigPouleType', $toernooi) }}';

// Gewichtsklassen per leeftijdsklasse
const gewichtsklassen = @json($toernooi->getAlleGewichtsklassen());

// Toegestane poule groottes uit instellingen
const toegestaneGroottes = @json($toegestaneGroottes);
const isProblematischeGrootte = (count) => count > 0 && !toegestaneGroottes.includes(count);

// Smooth scroll naar problematische poules met offset
document.addEventListener('click', function(e) {
    const link = e.target.closest('[data-probleem-poule]');
    if (link) {
        e.preventDefault();
        const pouleId = link.dataset.probleemPoule;
        const pouleEl = document.getElementById('poule-' + pouleId);
        if (pouleEl) {
            const headerOffset = 120; // Ruimte voor sticky header
            const elementPosition = pouleEl.getBoundingClientRect().top;
            const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
            window.scrollTo({ top: offsetPosition, behavior: 'smooth' });
        }
    }
});

function openNieuwePouleModal() {
    document.getElementById('nieuwe-poule-modal').classList.remove('hidden');
    document.getElementById('leeftijdsklasse').value = '';
    document.getElementById('gewichtsklasse').innerHTML = '<option value="">Selecteer eerst leeftijdsklasse</option>';
    document.getElementById('gewichtsklasse').disabled = true;
}

function closeNieuwePouleModal() {
    document.getElementById('nieuwe-poule-modal').classList.add('hidden');
}

function showToast(message, isError = false) {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-message');

    toastMessage.textContent = message;
    toast.classList.remove('translate-x-full', 'bg-green-600', 'bg-red-600');
    toast.classList.add(isError ? 'bg-red-600' : 'bg-green-600');

    setTimeout(() => toast.classList.add('translate-x-full'), 2000);
}

function updateTotaalStats() {
    // Tel alle wedstrijden op
    let totaalWedstrijden = 0;
    document.querySelectorAll('[data-poule-wedstrijden]').forEach(el => {
        const val = parseInt(el.textContent) || 0;
        totaalWedstrijden += val;
    });
    document.getElementById('stat-wedstrijden').textContent = totaalWedstrijden;

    // Tel alle judoka's op
    let totaalJudokas = 0;
    document.querySelectorAll('[data-poule-count]').forEach(el => {
        totaalJudokas += parseInt(el.textContent) || 0;
    });
    document.getElementById('stat-judokas').textContent = totaalJudokas;

    // Tel problematische poules (grootte niet in toegestane groottes)
    let problematisch = 0;
    document.querySelectorAll('[data-poule-count]').forEach(el => {
        const count = parseInt(el.textContent) || 0;
        if (isProblematischeGrootte(count)) problematisch++;
    });
    document.getElementById('stat-problematisch').textContent = problematisch;
}

async function verwijderPoule(pouleId, pouleNummer) {
    if (!confirm(`Poule #${pouleNummer} verwijderen?`)) return;

    try {
        const response = await fetch(verwijderPouleUrl.replace(':id', pouleId), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (data.success) {
            showToast(data.message);
            document.getElementById('poule-' + pouleId)?.remove();
            updateTotaalStats();
            // Update poule count in header
            const pouleCount = document.querySelectorAll('[id^="poule-"]').length;
            document.getElementById('stat-poules').textContent = pouleCount;
            document.getElementById('poule-count').textContent = pouleCount;
        } else {
            showToast(data.message || 'Fout bij verwijderen', true);
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Fout bij verwijderen', true);
    }
}

async function updateKruisfinalesPlaatsen(pouleId, plaatsen) {
    try {
        const response = await fetch(updateKruisfinaleUrl.replace(':id', pouleId), {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ kruisfinale_plaatsen: plaatsen })
        });

        const data = await response.json();

        if (data.success) {
            // Update displayed values
            const countEl = document.querySelector(`[data-poule-count="${pouleId}"]`);
            const wedstrijdenEl = document.querySelector(`[data-poule-wedstrijden="${pouleId}"]`);
            if (countEl) countEl.textContent = data.aantal_judokas;
            if (wedstrijdenEl) wedstrijdenEl.textContent = data.aantal_wedstrijden;
            showToast(data.message);
        } else {
            showToast(data.message || 'Fout bij opslaan', true);
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Fout bij opslaan', true);
    }
}

async function zetOmNaarPoules(pouleId, systeem) {
    if (!confirm(`Eliminatie omzetten naar ${systeem === 'poules_kruisfinale' ? 'poules + kruisfinale' : 'alleen poules'}?`)) return;

    try {
        const response = await fetch(zetOmNaarPoulesUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ poule_id: pouleId, systeem: systeem })
        });

        const data = await response.json();

        if (data.success) {
            showToast(data.message || 'Omgezet naar poules');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Fout bij omzetten', true);
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Fout bij omzetten', true);
    }
}

async function zetOmNaar(pouleId, type) {
    const typeLabels = {
        'eliminatie': 'eliminatie',
        'voorronde': 'poules',
        'kruisfinale': 'kruisfinale'
    };
    if (!confirm(`Omzetten naar ${typeLabels[type] || type}?`)) return;

    try {
        const response = await fetch(wijzigPouleTypeUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ poule_id: pouleId, type: type })
        });

        const data = await response.json();

        if (data.success) {
            showToast(data.message || 'Omgezet');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Fout bij omzetten', true);
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Fout bij omzetten', true);
    }
}

// Modal event listeners - direct na DOM ready
document.addEventListener('DOMContentLoaded', function() {
    const leeftijdsSelect = document.getElementById('leeftijdsklasse');
    const gewichtsSelect = document.getElementById('gewichtsklasse');
    const form = document.getElementById('nieuwe-poule-form');

    if (leeftijdsSelect) {
        leeftijdsSelect.addEventListener('change', function() {
            const key = this.value;

            if (!key || !gewichtsklassen[key]) {
                gewichtsSelect.innerHTML = '<option value="">Selecteer eerst leeftijdsklasse</option>';
                gewichtsSelect.disabled = true;
                return;
            }

            const gewichten = gewichtsklassen[key].gewichten;
            gewichtsSelect.innerHTML = '<option value="">Selecteer...</option>' +
                gewichten.map(g => `<option value="${g}">${g} kg</option>`).join('');
            gewichtsSelect.disabled = false;
        });
    }

    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const leeftijdsklasseKey = leeftijdsSelect.value;
            const leeftijdsklasseLabel = leeftijdsSelect.selectedOptions[0]?.dataset?.label;
            const gewichtsklasse = gewichtsSelect.value;

            if (!leeftijdsklasseKey || !gewichtsklasse) return;

            try {
                const response = await fetch(nieuwePouleUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        leeftijdsklasse: leeftijdsklasseLabel,
                        gewichtsklasse: gewichtsklasse
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showToast(data.message);
                    closeNieuwePouleModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message || 'Fout bij aanmaken', true);
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Fout bij aanmaken', true);
            }
        });
    }
});

async function verifieerPoules() {
    const resultaatDiv = document.getElementById('verificatie-resultaat');
    resultaatDiv.className = 'mb-6 bg-blue-50 border border-blue-300 rounded-lg p-4';
    resultaatDiv.innerHTML = '<p class="text-blue-700">Bezig met verificatie...</p>';

    try {
        const response = await fetch(verifieerUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (data.success) {
            // Update statistieken bovenaan
            document.getElementById('stat-poules').textContent = data.totaal_poules;
            document.getElementById('stat-wedstrijden').textContent = data.totaal_wedstrijden;
            document.getElementById('stat-problematisch').textContent = data.problemen.length;
            document.getElementById('poule-count').textContent = data.totaal_poules;

            let html = '';
            const hasProblems = data.problemen.length > 0;
            const refreshNeeded = data.herberekend > 0;

            if (hasProblems) {
                html = `<div class="bg-yellow-50 border border-yellow-300 rounded-lg p-4">
                    <h3 class="font-bold text-yellow-800 mb-2">⚠️ Verificatie: ${data.problemen.length} probleem(en) gevonden</h3>
                    <ul class="list-disc list-inside text-yellow-700 text-sm mb-3">
                        ${data.problemen.map(p => `<li>${p.message}</li>`).join('')}
                    </ul>
                    ${refreshNeeded ? `<p class="text-yellow-600 text-sm font-medium">${data.herberekend} poules herberekend - <button onclick="location.reload()" class="underline hover:no-underline">Pagina vernieuwen</button> om wijzigingen te zien</p>` : ''}
                </div>`;
            } else {
                html = `<div class="bg-green-50 border border-green-300 rounded-lg p-4">
                    <h3 class="font-bold text-green-800 mb-2">✅ Verificatie geslaagd!</h3>
                    <p class="text-green-700 text-sm">Alle ${data.totaal_poules} poules zijn correct. ${data.totaal_wedstrijden} wedstrijden gepland.</p>
                    ${refreshNeeded ? `<p class="text-green-600 text-sm mt-2">${data.herberekend} poules herberekend - <button onclick="location.reload()" class="underline hover:no-underline">Pagina vernieuwen</button> om wijzigingen te zien</p>` : ''}
                </div>`;
            }

            resultaatDiv.className = 'mb-6';
            resultaatDiv.innerHTML = html;
            resultaatDiv.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error:', error);
        resultaatDiv.className = 'mb-6 bg-red-50 border border-red-300 rounded-lg p-4';
        resultaatDiv.innerHTML = '<p class="text-red-700">Fout bij verificatie</p>';
    }
}

document.addEventListener('DOMContentLoaded', function() {

    // Initialize sortable on all poule containers
    document.querySelectorAll('.sortable-poule').forEach(container => {
        new Sortable(container, {
            group: 'poules',
            animation: 150,
            ghostClass: 'bg-blue-100',
            chosenClass: 'bg-blue-200',
            dragClass: 'shadow-lg',
            filter: '.zoek-match-btn',  // Exclude button from drag
            preventOnFilter: false,      // Allow click on filtered elements
            onEnd: async function(evt) {
                const judokaId = evt.item.dataset.judokaId;
                const vanPouleId = evt.from.dataset.pouleId;
                const naarPouleId = evt.to.dataset.pouleId;

                if (vanPouleId === naarPouleId) return;

                // Remove "Leeg" placeholder and delete button from target poule
                evt.to.querySelector('.empty-placeholder')?.remove();
                const pouleCard = evt.to.closest('[data-poule-id]');
                pouleCard?.querySelector('.delete-empty-btn')?.remove();

                // Update data attribute
                evt.item.dataset.pouleId = naarPouleId;

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
                            van_poule_id: vanPouleId,
                            naar_poule_id: naarPouleId
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Update poule statistieken en titels
                        updatePouleStats(data.van_poule);
                        updatePouleStats(data.naar_poule);

                        // Update totaal statistieken bovenaan
                        updateTotaalStats();

                        // Show toast
                        showToast(data.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showToast('Fout bij verplaatsen', true);
                    // Revert the move
                    evt.from.appendChild(evt.item);
                }
            }
        });
    });

    function updatePouleStats(pouleData) {
        const pouleCard = document.getElementById(`poule-${pouleData.id}`);
        if (!pouleCard) return;

        const sortableContainer = pouleCard.querySelector('.sortable-poule');
        const header = pouleCard.querySelector('.border-b');
        const headerTop = header?.querySelector('.flex.justify-between');
        const isKruisfinale = pouleCard.dataset.pouleIsKruisfinale === '1';
        const isEliminatie = pouleCard.dataset.pouleIsEliminatie === '1';

        // Update count
        const countEl = document.querySelector(`[data-poule-count="${pouleData.id}"]`);
        if (countEl) countEl.textContent = pouleData.judokas_count;

        // Update wedstrijden (show "-" for 0 or 1 judokas)
        const wedstrijdenEl = document.querySelector(`[data-poule-wedstrijden="${pouleData.id}"]`);
        if (wedstrijdenEl) {
            wedstrijdenEl.textContent = pouleData.judokas_count < 2 ? '-' : pouleData.aantal_wedstrijden;
        }

        // Update poule titel (ranges zitten in de titel zelf, bijv. "Jeugd 9-10j 28-32kg")
        if (!isKruisfinale && !isEliminatie) {
            const titelEl = pouleCard.querySelector(`[data-poule-titel="${pouleData.id}"]`);
            if (titelEl && pouleData.titel) {
                titelEl.textContent = `#${pouleData.nummer} ${pouleData.titel}`;
            }
        }

        // Handle empty poule: add placeholder and delete button
        if (pouleData.judokas_count === 0) {
            // Add "Leeg" placeholder if not present
            if (!sortableContainer.querySelector('.empty-placeholder')) {
                const placeholder = document.createElement('div');
                placeholder.className = 'px-3 py-4 text-gray-400 text-sm italic text-center empty-placeholder';
                placeholder.textContent = 'Leeg';
                sortableContainer.appendChild(placeholder);
            }

            // Add delete button if not present
            if (headerTop && !headerTop.querySelector('.delete-empty-btn')) {
                const deleteBtn = document.createElement('button');
                deleteBtn.className = 'delete-empty-btn text-red-500 hover:text-red-700 font-bold text-lg leading-none';
                deleteBtn.title = 'Verwijder poule';
                deleteBtn.innerHTML = '&minus;';
                deleteBtn.onclick = () => verwijderPoule(pouleData.id, pouleData.nummer);
                headerTop.appendChild(deleteBtn);
            }

            // Remove red border for empty poules (they're ok)
            pouleCard.classList.remove('border-2', 'border-red-300');
            header?.classList.remove('bg-red-100');
            header?.classList.add('bg-blue-100');
        } else {
            // Remove "Leeg" placeholder
            sortableContainer.querySelector('.empty-placeholder')?.remove();

            // Remove delete button
            headerTop?.querySelector('.delete-empty-btn')?.remove();

            // Update problematic styling (grootte niet in toegestane groottes = problematic, skip kruisfinale/eliminatie)
            if (isProblematischeGrootte(pouleData.judokas_count) && !isKruisfinale && !isEliminatie) {
                pouleCard.classList.add('border-2', 'border-red-300');
                header?.classList.add('bg-red-100');
                header?.classList.remove('bg-blue-100');
            } else if (!isKruisfinale && !isEliminatie) {
                pouleCard.classList.remove('border-2', 'border-red-300');
                header?.classList.remove('bg-red-100');
                header?.classList.add('bg-blue-100');
            }
        }

        // Update problematic poules section at the top (skip kruisfinale/eliminatie)
        if (!isKruisfinale && !isEliminatie) {
            updateProblematischePoules(pouleData);
        }
    }

    function updateProblematischePoules(pouleData) {
        const container = document.getElementById('problematische-poules-container');
        const linksContainer = document.getElementById('problematische-links');
        const countEl = document.getElementById('problematische-count');
        const existingLink = document.querySelector(`[data-probleem-poule="${pouleData.id}"]`);

        const isProblematic = isProblematischeGrootte(pouleData.judokas_count);

        if (isProblematic) {
            // Update or add the link
            if (existingLink) {
                // Update count in existing link
                const linkCount = existingLink.querySelector(`[data-probleem-count="${pouleData.id}"]`);
                if (linkCount) linkCount.textContent = pouleData.judokas_count;
            } else {
                // Need to add new link - ensure container exists
                if (!linksContainer) {
                    // Create the entire problematic section
                    const pouleCard = document.getElementById(`poule-${pouleData.id}`);
                    const nummer = pouleCard?.dataset.pouleNummer || pouleData.nummer;
                    const leeftijd = pouleCard?.dataset.pouleLeeftijdsklasse || '';
                    const gewicht = pouleCard?.dataset.pouleGewichtsklasse || '';

                    container.innerHTML = `
                        <div class="bg-red-50 border border-red-300 rounded-lg p-4 mb-6">
                            <h3 class="font-bold text-red-800 mb-2">Problematische poules (<span id="problematische-count">1</span>)</h3>
                            <p class="text-red-700 text-sm mb-3">Deze poules hebben minder dan 3 judoka's. Klik om naar de poule te gaan:</p>
                            <div id="problematische-links" class="flex flex-wrap gap-2">
                                <a href="#poule-${pouleData.id}" data-probleem-poule="${pouleData.id}" class="inline-flex items-center px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm hover:bg-red-200 cursor-pointer transition-colors">
                                    #${nummer} ${leeftijd} / ${gewicht} kg (<span data-probleem-count="${pouleData.id}">${pouleData.judokas_count}</span>)
                                </a>
                            </div>
                        </div>
                    `;
                } else {
                    // Add new link to existing container
                    const pouleCard = document.getElementById(`poule-${pouleData.id}`);
                    const nummer = pouleCard?.dataset.pouleNummer || pouleData.nummer;
                    const leeftijd = pouleCard?.dataset.pouleLeeftijdsklasse || '';
                    const gewicht = pouleCard?.dataset.pouleGewichtsklasse || '';

                    const newLink = document.createElement('a');
                    newLink.href = `#poule-${pouleData.id}`;
                    newLink.dataset.probleemPoule = pouleData.id;
                    newLink.className = 'inline-flex items-center px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm hover:bg-red-200 cursor-pointer transition-colors';
                    newLink.innerHTML = `#${nummer} ${leeftijd} / ${gewicht} kg (<span data-probleem-count="${pouleData.id}">${pouleData.judokas_count}</span>)`;
                    linksContainer.appendChild(newLink);

                    // Update count
                    if (countEl) {
                        countEl.textContent = parseInt(countEl.textContent) + 1;
                    }
                }
            }
        } else {
            // Remove from problematic list if present
            if (existingLink) {
                existingLink.remove();

                // Update count
                const newLinksContainer = document.getElementById('problematische-links');
                if (countEl && newLinksContainer) {
                    const remaining = newLinksContainer.querySelectorAll('[data-probleem-poule]').length;
                    countEl.textContent = remaining;

                    // Hide entire section if no more problematic poules
                    if (remaining === 0) {
                        container.innerHTML = '';
                    }
                }
            }
        }
    }

});

// Breedte vastzetten: meet de breedste leeftijdsklasse container
function fixBlokBreedte() {
    const blokContainers = document.querySelectorAll('.mb-8.w-full[x-data]');
    let maxBreedte = 0;

    blokContainers.forEach(container => {
        const breedte = container.offsetWidth;
        if (breedte > maxBreedte) maxBreedte = breedte;
    });

    if (maxBreedte > 0) {
        blokContainers.forEach(container => {
            container.style.minWidth = maxBreedte + 'px';
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(fixBlokBreedte, 100);
});

// ============================================
// Zoek Match functionaliteit
// ============================================
let selectedJudokaId = null;
let selectedJudokaElement = null;
const contextMenu = document.getElementById('judoka-context-menu');

// Right-click handler op judoka items
document.addEventListener('contextmenu', function(e) {
    const judokaItem = e.target.closest('.judoka-item');
    if (!judokaItem) return;

    // Check of dit een eliminatie poule is - geen zoek match voor eliminatie
    const pouleCard = judokaItem.closest('[data-poule-id]');
    if (pouleCard?.dataset.pouleIsEliminatie === '1') return;

    e.preventDefault();

    selectedJudokaId = judokaItem.dataset.judokaId;
    selectedJudokaElement = judokaItem;

    // Positioneer context menu bij cursor
    contextMenu.style.left = e.pageX + 'px';
    contextMenu.style.top = e.pageY + 'px';
    contextMenu.classList.remove('hidden');
});

// Sluit context menu bij klik ergens anders
document.addEventListener('click', function(e) {
    if (!contextMenu.contains(e.target)) {
        contextMenu.classList.add('hidden');
    }
});

// Sluit context menu bij scroll
document.addEventListener('scroll', function() {
    contextMenu.classList.add('hidden');
});

function openZoekMatchFor(judokaId, element) {
    selectedJudokaId = judokaId;
    selectedJudokaElement = element;
    openZoekMatch();
}

async function openZoekMatch() {
    contextMenu.classList.add('hidden');

    if (!selectedJudokaId) return;

    const modal = document.getElementById('zoek-match-modal');
    const resultsDiv = document.getElementById('zoek-match-results');
    const naamSpan = document.getElementById('zoek-match-judoka-naam');
    const infoSpan = document.getElementById('zoek-match-judoka-info');

    // Haal judoka info uit data attributes
    const naam = selectedJudokaElement?.dataset.judokaNaam || 'Judoka';
    const leeftijd = selectedJudokaElement?.dataset.judokaLeeftijd || '';
    const gewicht = selectedJudokaElement?.dataset.judokaGewicht || '';
    naamSpan.textContent = naam;
    infoSpan.textContent = leeftijd ? `(${leeftijd}j${gewicht ? ', ' + gewicht + 'kg' : ''})` : '';

    modal.classList.remove('hidden');
    resultsDiv.innerHTML = '<p class="text-gray-500 text-center py-8">Laden...</p>';

    try {
        const url = zoekMatchUrl.replace('__JUDOKA_ID__', selectedJudokaId);
        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        });

        const data = await response.json();

        if (!data.success) {
            resultsDiv.innerHTML = `<p class="text-red-500 text-center py-8">${data.message || 'Fout bij ophalen'}</p>`;
            return;
        }

        // Update judoka info
        infoSpan.textContent = `(${data.judoka.leeftijd}j, ${data.judoka.gewicht}kg)`;

        if (data.matches.length === 0) {
            resultsDiv.innerHTML = '<p class="text-gray-500 text-center py-8">Geen passende poules gevonden</p>';
            return;
        }

        // Render matches
        let html = '<div class="space-y-2">';

        for (const match of data.matches) {
            // Status kleuren
            const statusColors = {
                'ok': 'border-green-200 bg-green-50 hover:bg-green-100',
                'warning': 'border-yellow-200 bg-yellow-50 hover:bg-yellow-100',
                'error': 'border-red-200 bg-red-50 hover:bg-red-100'
            };
            const statusIcons = {
                'ok': '✅',
                'warning': '⚠️',
                'error': '❌'
            };

            const colorClass = statusColors[match.status] || statusColors['warning'];
            const icon = statusIcons[match.status] || '❓';

            // Overschrijding tekst
            let overschrijdingTekst = '';
            if (match.kg_overschrijding > 0 || match.lft_overschrijding > 0) {
                const parts = [];
                if (match.kg_overschrijding > 0) parts.push(`+${match.kg_overschrijding}kg`);
                if (match.lft_overschrijding > 0) parts.push(`+${match.lft_overschrijding}j`);
                overschrijdingTekst = parts.join(', ');
            }

            // Categorie overschrijding indicator
            const catOverschrijding = match.categorie_overschrijding;
            const catBadge = catOverschrijding
                ? `<span class="ml-2 px-2 py-0.5 bg-orange-100 text-orange-700 text-xs rounded-full font-medium">⚠️ ${match.leeftijdsklasse}</span>`
                : '';

            html += `
                <div class="p-3 rounded-lg border cursor-pointer transition-colors ${colorClass} ${catOverschrijding ? 'border-l-4 border-l-orange-400' : ''}"
                     onclick="verplaatsNaarPoule(${selectedJudokaId}, ${match.poule_id})">
                    <div class="flex justify-between items-start flex-wrap gap-1">
                        <div class="flex items-center flex-wrap">
                            <span class="font-medium">${icon} #${match.poule_nummer} ${match.poule_titel || ''}</span>
                            ${catBadge}
                            ${overschrijdingTekst ? `<span class="text-xs text-gray-500 ml-2">(${overschrijdingTekst})</span>` : ''}
                        </div>
                    </div>
                    <div class="mt-2 grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <div class="text-gray-500 text-xs mb-1">Nu:</div>
                            <div>${match.huidige_judokas} judoka's</div>
                            <div class="text-xs text-gray-600">${match.huidige_leeftijd} / ${match.huidige_gewicht}</div>
                        </div>
                        <div>
                            <div class="text-gray-500 text-xs mb-1">Na plaatsing:</div>
                            <div>${match.nieuwe_judokas} judoka's</div>
                            <div class="text-xs text-gray-600">${match.nieuwe_leeftijd} / ${match.nieuwe_gewicht}</div>
                        </div>
                    </div>
                </div>
            `;
        }

        html += '</div>';
        resultsDiv.innerHTML = html;

    } catch (error) {
        console.error('Error:', error);
        resultsDiv.innerHTML = '<p class="text-red-500 text-center py-8">Fout bij ophalen matches</p>';
    }
}

function closeZoekMatchModal() {
    document.getElementById('zoek-match-modal').classList.add('hidden');
    // Reset positie naar midden
    const content = document.getElementById('zoek-match-modal-content');
    content.style.top = '50%';
    content.style.left = '50%';
    content.style.transform = 'translate(-50%, -50%)';
    selectedJudokaId = null;
    selectedJudokaElement = null;
}

// Draggable modal
(function() {
    const modal = document.getElementById('zoek-match-modal-content');
    const header = document.getElementById('zoek-match-modal-header');
    let isDragging = false;
    let offsetX, offsetY;

    header.addEventListener('mousedown', function(e) {
        if (e.target.tagName === 'BUTTON') return;
        isDragging = true;
        const rect = modal.getBoundingClientRect();
        offsetX = e.clientX - rect.left;
        offsetY = e.clientY - rect.top;
        modal.style.transform = 'none';
    });

    document.addEventListener('mousemove', function(e) {
        if (!isDragging) return;
        e.preventDefault();
        let newX = e.clientX - offsetX;
        let newY = e.clientY - offsetY;
        // Keep within viewport
        newX = Math.max(0, Math.min(newX, window.innerWidth - modal.offsetWidth));
        newY = Math.max(0, Math.min(newY, window.innerHeight - modal.offsetHeight));
        modal.style.left = newX + 'px';
        modal.style.top = newY + 'px';
    });

    document.addEventListener('mouseup', function() {
        isDragging = false;
    });
})();

async function verplaatsNaarPoule(judokaId, naarPouleId) {
    // Vind de huidige poule
    const judokaElement = document.querySelector(`[data-judoka-id="${judokaId}"]`);
    const vanPouleId = judokaElement?.dataset.pouleId;

    if (!vanPouleId || vanPouleId === String(naarPouleId)) {
        closeZoekMatchModal();
        return;
    }

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
                van_poule_id: vanPouleId,
                naar_poule_id: naarPouleId
            })
        });

        const data = await response.json();

        if (data.success) {
            closeZoekMatchModal();
            showToast(data.message);
            // Herlaad pagina om alle wijzigingen te tonen
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(data.message || 'Fout bij verplaatsen', true);
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Fout bij verplaatsen', true);
    }
}

// Sluit modal met Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeZoekMatchModal();
        contextMenu.classList.add('hidden');
    }
});
</script>

<style>
.sortable-ghost {
    opacity: 0.4;
}

/* Print styles */
@media print {
    /* Hide interactive elements */
    .no-print,
    #verificatie-resultaat,
    #toast,
    #nieuwe-poule-modal,
    #problematische-poules-container,
    button,
    form,
    .delete-empty-btn,
    select {
        display: none !important;
    }

    /* Reset page margins */
    @page {
        margin: 1cm;
    }

    /* Remove shadows and make backgrounds printable */
    * {
        box-shadow: none !important;
    }

    .bg-blue-800 {
        background-color: #1e40af !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .bg-blue-100 {
        background-color: #dbeafe !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Prevent page breaks inside weight class sections */
    .mb-4.last\\:mb-0 {
        page-break-inside: avoid;
    }

    /* Prevent page breaks inside poule cards */
    [data-poule-id] {
        page-break-inside: avoid;
    }

    /* Allow page breaks between age classes */
    .mb-8 {
        page-break-after: auto;
    }

    /* Ensure accordion content is visible */
    [x-show] {
        display: block !important;
    }

    /* Full width layout */
    .grid {
        display: block !important;
    }

    .grid > div {
        display: inline-block;
        width: 48%;
        vertical-align: top;
        margin-bottom: 0.5rem;
    }

    /* Smaller text for print */
    .text-3xl {
        font-size: 1.5rem !important;
    }

    .text-lg {
        font-size: 0.9rem !important;
    }

    .text-sm {
        font-size: 0.75rem !important;
    }

    .text-xs {
        font-size: 0.65rem !important;
    }

    /* Compact padding */
    .px-3, .py-2, .p-4, .px-4, .py-3 {
        padding: 0.25rem 0.5rem !important;
    }

    /* Page title */
    h1::after {
        content: " - Print " attr(data-print-date);
    }
}
</style>
@endsection
