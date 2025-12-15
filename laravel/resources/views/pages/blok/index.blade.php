@extends('layouts.app')

@section('title', 'Blokverdeling')

@section('content')
@php
    $leeftijdVolgorde = ["Mini's", 'A-pupillen', 'B-pupillen', 'C-pupillen', 'Dames -15', 'Heren -15', 'Dames -18', 'Heren -18', 'Dames -21', 'Heren -21', 'Dames', 'Heren'];
    $afkortingen = [
        "Mini's" => "Mini's", 'A-pupillen' => 'A-pup', 'B-pupillen' => 'B-pup', 'C-pupillen' => 'C-pup',
        'Dames -15' => 'D-15', 'Heren -15' => 'H-15', 'Dames -18' => 'D-18', 'Heren -18' => 'H-18',
        'Dames -21' => 'D-21', 'Heren -21' => 'H-21', 'Dames' => 'Dames', 'Heren' => 'Heren',
    ];

    // Check for variant selection mode
    $varianten = session('blok_varianten', []);
    $toonVarianten = request()->has('kies') && !empty($varianten);

    // Get all categories with their block assignment from database
    // reorder() removes default orderBy('nummer') which conflicts with GROUP BY in MySQL
    $alleCatsRaw = $toernooi->poules()
        ->reorder()
        ->select('leeftijdsklasse', 'gewichtsklasse', 'blok_id', 'blok_vast')
        ->selectRaw('SUM(aantal_wedstrijden) as wedstrijden')
        ->selectRaw('MAX(blok_vast) as is_vast')
        ->groupBy('leeftijdsklasse', 'gewichtsklasse', 'blok_id', 'blok_vast')
        ->with('blok:id,nummer')
        ->orderBy('leeftijdsklasse')
        ->orderBy('gewichtsklasse')
        ->get();

    // Group per category (database state)
    $alleCats = $alleCatsRaw
        ->groupBy(fn($p) => $p->leeftijdsklasse . '|' . $p->gewichtsklasse)
        ->map(fn($g) => [
            'leeftijd' => $g->first()->leeftijdsklasse,
            'gewicht' => $g->first()->gewichtsklasse,
            'wedstrijden' => $g->sum('wedstrijden'),
            'blok' => $g->first()->blok->nummer ?? null,
            'vast' => (bool) $g->first()->is_vast,
        ])
        ->filter(fn($c) => $c['wedstrijden'] > 0)
        ->sortBy(function($v) use ($leeftijdVolgorde) {
            $leeftijdPos = ($pos = array_search($v['leeftijd'], $leeftijdVolgorde)) !== false ? $pos * 10000 : 990000;
            $gewicht = (int)preg_replace('/[^0-9]/', '', $v['gewicht']);
            $plusBonus = str_starts_with($v['gewicht'], '+') ? 500 : 0;
            return $leeftijdPos + $gewicht + $plusBonus;
        });

    // If showing variants, apply variant 0 to display (but not to DB yet)
    if ($toonVarianten && isset($varianten[0]['toewijzingen'])) {
        $variantToewijzingen = $varianten[0]['toewijzingen'];
        $alleCats = $alleCats->map(function($cat) use ($variantToewijzingen) {
            $key = $cat['leeftijd'] . '|' . $cat['gewicht'];
            if (!$cat['vast'] && isset($variantToewijzingen[$key])) {
                $cat['blok'] = $variantToewijzingen[$key];
            }
            return $cat;
        });
    }

    $catsPerBlok = $alleCats->groupBy('blok');
    $nietVerdeeldCats = $catsPerBlok->get(null, collect());

    // Calculate totals
    $totaalWedstrijden = $alleCats->sum('wedstrijden');
    $gemiddeldPerBlok = $blokken->count() > 0 ? round($totaalWedstrijden / $blokken->count()) : 0;

    // Group by leeftijd for panels
    $catsPerLeeftijd = $alleCats->groupBy('leeftijd');
    $nietVerdeeldPerLeeftijd = $nietVerdeeldCats->groupBy('leeftijd');
@endphp

<!-- Header -->
<div class="flex justify-between items-center mb-4">
    <div class="flex items-center gap-4">
        <h1 class="text-3xl font-bold text-gray-800">Blokverdeling</h1>
        <span class="text-sm text-gray-500">{{ $blokken->count() }} blokken | {{ $totaalWedstrijden }} wed. | gem {{ $gemiddeldPerBlok }}/blok</span>
        @if($nietVerdeeldCats->isNotEmpty())
        <span class="text-sm text-red-600 font-bold" id="niet-verdeeld-header">{{ $nietVerdeeldCats->count() }} cat. niet verdeeld</span>
        @endif
    </div>
    <div class="flex items-center gap-2">
        <form action="{{ route('toernooi.blok.genereer-verdeling', $toernooi) }}" method="POST" class="inline flex items-center gap-2" id="bereken-form">
            @csrf
            <input type="hidden" name="balans" id="balans-input" value="{{ session('blok_balans', 50) }}">
            <div class="flex items-center gap-2 text-xs bg-gray-100 px-3 py-1.5 rounded">
                <span class="text-gray-600 whitespace-nowrap">Verdeling</span>
                <input type="range" id="balans-slider-header" min="0" max="100" value="{{ session('blok_balans', 50) }}"
                       class="w-24 h-2 bg-gradient-to-r from-blue-400 to-green-400 rounded appearance-none cursor-pointer"
                       oninput="updateBalansSlider(this.value)">
                <span class="text-gray-600 whitespace-nowrap">Aansluiting</span>
            </div>
            <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded">
                (Her)bereken
            </button>
        </form>
        <form action="{{ route('toernooi.blok.zet-op-mat', $toernooi) }}" method="POST" class="inline" id="zet-op-mat-form">
            @csrf
            <div class="flex flex-col items-end">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    Verdeel over matten →
                </button>
                <span class="text-xs text-gray-500 mt-0.5">(zie zaaloverzicht voor preview)</span>
            </div>
        </form>
    </div>
</div>

@if(session('error'))
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4 text-sm">{{ session('error') }}</div>
@endif

@if($blokken->isEmpty())
<div class="bg-yellow-50 border border-yellow-300 rounded-lg p-6 text-center">
    <p class="text-yellow-800">Nog geen blokken. Maak eerst blokken aan in toernooi instellingen.</p>
</div>
@else

<!-- Main layout -->
<div class="flex gap-4">
    <!-- Links: Sleepvak + Blokken -->
    <div class="flex-1 space-y-3">
        <!-- Sleepvak (niet verdeeld) -->
        <div class="bg-white rounded-lg shadow" id="sleepvak-container">
            <div class="bg-purple-700 text-white px-4 py-2 rounded-t-lg flex justify-between items-center cursor-pointer" onclick="document.getElementById('niet-verdeeld-content').classList.toggle('hidden')">
                <div class="flex items-center gap-3">
                    <span class="toggle-icon">▼</span>
                    <span class="font-bold">Sleepvak</span>
                    <span class="text-purple-200" id="sleepvak-stats">({{ $nietVerdeeldCats->count() }} cat., {{ $nietVerdeeldCats->sum('wedstrijden') }}w)</span>
                </div>
            </div>
            <div id="niet-verdeeld-content" class="p-2 blok-dropzone min-h-[40px]" data-blok="0">
                @if($nietVerdeeldCats->isEmpty())
                <span class="text-gray-400 text-xs italic dropzone-placeholder">Alle categorieën zijn verdeeld</span>
                @else
                <div class="flex flex-wrap gap-x-4 gap-y-1">
                    @foreach($leeftijdVolgorde as $leeftijd)
                        @php $leeftijdCats = $nietVerdeeldPerLeeftijd->get($leeftijd, collect()); @endphp
                        @if($leeftijdCats->isNotEmpty())
                        <div class="leeftijd-groep" data-leeftijd="{{ $leeftijd }}">
                            <div class="font-semibold text-gray-700 text-xs mb-0.5">
                                {{ $afkortingen[$leeftijd] ?? $leeftijd }}
                                <span class="text-gray-400 font-normal">({{ $leeftijdCats->sum('wedstrijden') }}w)</span>
                            </div>
                            <div class="leeftijd-content flex flex-wrap gap-0.5">
                                @foreach($leeftijdCats->sortBy(fn($c) => (int)preg_replace('/[^0-9]/', '', $c['gewicht']) + (str_starts_with($c['gewicht'], '+') ? 500 : 0)) as $cat)
                                @include('pages.blok._category_chip', ['cat' => $cat, 'inSleepvak' => true])
                                @endforeach
                            </div>
                        </div>
                        @endif
                    @endforeach
                </div>
                @endif
            </div>
        </div>

        <!-- Blokken -->
        @foreach($blokken as $blok)
        @php
            $blokCats = $catsPerBlok->get($blok->nummer, collect());
            $blokWedstrijden = $blokCats->sum('wedstrijden');
            $gewenstWedstrijden = $blok->gewenst_wedstrijden ?? $gemiddeldPerBlok;
            $afwijkingPct = $gewenstWedstrijden > 0 ? round(($blokWedstrijden - $gewenstWedstrijden) / $gewenstWedstrijden * 100) : 0;
            $blokCatsSorted = $blokCats->sortBy(function($c) use ($leeftijdVolgorde) {
                $leeftijdPos = ($pos = array_search($c['leeftijd'], $leeftijdVolgorde)) !== false ? $pos * 10000 : 990000;
                return $leeftijdPos + (int)preg_replace('/[^0-9]/', '', $c['gewicht']) + (str_starts_with($c['gewicht'], '+') ? 500 : 0);
            });
        @endphp
        <div class="bg-white rounded-lg shadow blok-container" data-blok="{{ $blok->nummer }}" data-blok-id="{{ $blok->id }}" data-gewenst="{{ $gewenstWedstrijden }}">
            <div class="bg-gray-800 text-white px-4 py-2 rounded-t-lg flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <span class="font-bold">Blok {{ $blok->nummer }}</span>
                    <div class="flex items-center gap-2 text-sm">
                        <span class="text-gray-400">Gewenst:</span>
                        <input type="number" class="gewenst-input w-16 px-2 py-1 rounded text-gray-800 text-center text-sm"
                               value="{{ $blok->gewenst_wedstrijden ?? '' }}"
                               placeholder="{{ $gemiddeldPerBlok }}"
                               data-blok-id="{{ $blok->id }}"
                               onchange="updateGewenst(this)">
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="blok-stats text-sm">Actueel: <strong class="blok-actueel">{{ $blokWedstrijden }}</strong> wed.</span>
                    <span class="afwijking-badge text-xs px-2 py-0.5 rounded {{ abs($afwijkingPct) <= 10 ? 'bg-green-500' : (abs($afwijkingPct) <= 25 ? 'bg-yellow-500' : 'bg-red-500') }}">
                        {{ $afwijkingPct >= 0 ? '+' : '' }}{{ $afwijkingPct }}%
                    </span>
                    @if($blok->weging_gesloten)<span class="text-xs bg-red-500 px-2 py-0.5 rounded">Gesloten</span>@endif
                </div>
            </div>
            <div class="p-3 min-h-[50px] blok-dropzone flex flex-wrap gap-1.5 items-start content-start" data-blok="{{ $blok->nummer }}">
                @foreach($blokCatsSorted as $cat)
                @include('pages.blok._category_chip', ['cat' => $cat, 'inSleepvak' => false])
                @endforeach
                @if($blokCats->isEmpty())<span class="text-gray-400 text-xs italic dropzone-placeholder">Sleep categorieën hierheen</span>@endif
            </div>
        </div>
        @endforeach

        <!-- Varianten panel (altijd zichtbaar) -->
        @php
            $blokStats = session('blok_stats', []);
        @endphp
        <div class="bg-white rounded-lg shadow p-3">
            <!-- Toepassen/Annuleer knoppen rechtsboven -->
            @if($toonVarianten)
            <div class="flex justify-end gap-3 mb-2">
                <button type="button" onclick="pasVariantToe()" class="bg-green-600 hover:bg-green-700 text-white text-xs font-bold px-3 py-1 rounded">
                    ✓ Toepassen
                </button>
                <a href="{{ route('toernooi.blok.index', $toernooi) }}" class="text-gray-400 hover:text-gray-600 text-xs">✕ Annuleer</a>
            </div>
            @endif

            <!-- Variant knoppen -->
            <div class="flex flex-wrap gap-2 mb-2">
                @if($toonVarianten)
                    @foreach($varianten as $idx => $variant)
                    @php
                        $scores = $variant['scores'];
                        $totaal = $variant['totaal_score'] ?? 0;
                        // Score kleur: lager = beter
                        $scoreKleur = $totaal < 100 ? 'text-green-600' : ($totaal < 200 ? 'text-yellow-600' : 'text-red-600');
                    @endphp
                    <button type="button" onclick="toonVariant({{ $idx }})"
                            class="variant-btn px-3 py-2 rounded border text-sm transition-all {{ $idx === 0 ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-200' : 'border-gray-200 bg-white hover:bg-gray-50' }}"
                            data-idx="{{ $idx }}"
                            data-origineel-score="{{ $totaal }}"
                            title="Score: {{ $totaal }} (lager=beter)&#10;Verdeling: {{ $scores['verdeling_score'] ?? 0 }} (Σ%afwijking)&#10;Aansluiting: {{ $scores['aansluiting_score'] ?? 0 }} (0/10/20/30 per overgang)&#10;Weging: {{ $scores['gewichten']['verdeling'] ?? 50 }}% / {{ $scores['gewichten']['aansluiting'] ?? 50 }}%">
                        <span class="font-bold variant-nummer">#{{ $idx + 1 }}</span>
                        <span class="variant-score font-bold {{ $scoreKleur }}">{{ $totaal }}</span>
                        <span class="variant-detail text-gray-400 text-xs">(V{{ $scores['verdeling_score'] ?? 0 }}+A{{ $scores['aansluiting_score'] ?? 0 }})</span>
                    </button>
                    @endforeach
                @else
                    @for($i = 1; $i <= 5; $i++)
                    <button type="button" disabled
                            class="px-3 py-2 rounded border text-sm border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed">
                        <span class="font-bold">#{{ $i }}</span>
                        <span>--</span>
                    </button>
                    @endfor
                @endif
            </div>

            <!-- Stats onderaan -->
            <div class="text-sm text-gray-500">
                @if($toonVarianten && !empty($blokStats))
                    <span class="font-medium">{{ number_format($blokStats['pogingen'] ?? 0) }}</span>
                    <span class="text-gray-400 text-xs">({{ $blokStats['tijd_sec'] ?? 0 }}s, {{ number_format($blokStats['per_seconde'] ?? 0) }}/s)</span>
                    <span class="text-gray-400 mx-1">→</span>
                    <span>{{ $blokStats['geldige_varianten'] ?? 0 }} geldig</span>
                    <span class="text-gray-400 mx-1">→</span>
                    <span class="font-bold text-blue-600">top {{ $blokStats['getoond'] ?? 0 }}</span>
                    <span id="live-score-display" class="ml-3 hidden"></span>
                @else
                    Varianten (klik Bereken)
                @endif
            </div>
        </div>

    </div>

    <!-- Rechts: Overzicht -->
    <div class="w-48 flex-shrink-0">
        <div class="bg-white rounded-lg shadow sticky top-4">
            <div class="bg-gray-700 text-white px-3 py-2 rounded-t-lg flex justify-between items-center text-sm">
                <span class="font-bold">Overzicht</span>
                <span class="text-gray-300">Blok</span>
            </div>
            <div class="p-2 max-h-[calc(100vh-200px)] overflow-y-auto text-xs" id="overzicht-panel">
                @foreach($leeftijdVolgorde as $leeftijd)
                    @if($catsPerLeeftijd->has($leeftijd))
                    <div class="mb-2">
                        <div class="font-bold text-gray-700 border-b border-gray-200 pb-0.5 mb-1">{{ $afkortingen[$leeftijd] ?? $leeftijd }}</div>
                        @foreach($catsPerLeeftijd[$leeftijd]->sortBy(fn($c) => (int)preg_replace('/[^0-9]/', '', $c['gewicht']) + (str_starts_with($c['gewicht'], '+') ? 500 : 0)) as $cat)
                        <div class="flex justify-between items-center py-0.5 hover:bg-gray-50">
                            <span>{{ $cat['gewicht'] }} <span class="text-gray-400">({{ $cat['wedstrijden'] }}w)</span></span>
                            @if($cat['blok'])
                                @if($cat['vast'])
                                <span class="bg-green-100 text-green-800 px-1.5 py-0.5 rounded font-bold blok-badge" data-key="{{ $cat['leeftijd'] }}|{{ $cat['gewicht'] }}">●{{ $cat['blok'] }}</span>
                                @else
                                <span class="bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded font-bold blok-badge" data-key="{{ $cat['leeftijd'] }}|{{ $cat['gewicht'] }}">{{ $cat['blok'] }}</span>
                                @endif
                            @else
                            <span class="bg-red-100 text-red-600 px-1.5 py-0.5 rounded blok-badge" data-key="{{ $cat['leeftijd'] }}|{{ $cat['gewicht'] }}">-</span>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</div>

@endif

@if(session('success'))
<div class="text-xs text-green-600 mt-1">✓ {{ session('success') }}</div>
@endif

<!-- Varianten data voor JavaScript -->
@if($toonVarianten)
<script>
const variantenData = @json($varianten);
const afkortingen = @json($afkortingen);
</script>
@endif

<script>
function updateBalansSlider(value) {
    const headerSlider = document.getElementById('balans-slider-header');
    const variantSlider = document.getElementById('balans-slider');
    const hiddenInput = document.getElementById('balans-input');

    // Sync all elements to the same value
    if (hiddenInput) hiddenInput.value = value;
    if (headerSlider) headerSlider.value = value;
    if (variantSlider) variantSlider.value = value;

    // Herbereken score met nieuwe gewichten
    if (typeof berekenLiveScore === 'function') {
        berekenLiveScore();
    }
}

function updateGewenst(input) {
    const blokId = input.dataset.blokId;
    fetch('{{ route('toernooi.blok.update-gewenst', $toernooi) }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ blok_id: blokId, gewenst: input.value || null })
    }).then(() => updateAllStats());
}

@if($toonVarianten)
let huidigeVariant = 0;

function pasVariantToe() {
    // Lees huidige chip posities uit DOM (inclusief handmatige aanpassingen)
    const toewijzingen = {};
    document.querySelectorAll('.category-chip').forEach(chip => {
        const key = chip.dataset.key;
        const blokZone = chip.closest('.blok-dropzone');
        const blokNr = parseInt(blokZone?.dataset?.blok || 0);
        if (blokNr > 0) {
            toewijzingen[key] = blokNr;
        }
    });

    fetch('{{ route('toernooi.blok.kies-variant', $toernooi) }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ toewijzingen: toewijzingen })
    }).then(r => r.json()).then(result => {
        if (result.success) location.reload();
    });
}

function toonVariant(idx) {
    huidigeVariant = idx;
    const variant = variantenData[idx];
    if (!variant) return;

    // Update button styling
    document.querySelectorAll('.variant-btn').forEach(btn => {
        const btnIdx = parseInt(btn.dataset.idx);
        if (btnIdx === idx) {
            btn.classList.remove('border-gray-300', 'bg-white');
            btn.classList.add('border-blue-500', 'bg-blue-100', 'ring-2', 'ring-blue-300');
        } else {
            btn.classList.remove('border-blue-500', 'bg-blue-100', 'ring-2', 'ring-blue-300');
            btn.classList.add('border-gray-300', 'bg-white');
        }
    });

    // Move ALL non-vast chips according to variant
    const toewijzingen = variant.toewijzingen;

    document.querySelectorAll('.category-chip[data-vast="0"]').forEach(chip => {
        const key = chip.dataset.key;
        const nieuweBlok = toewijzingen[key];

        if (nieuweBlok !== undefined) {
            // Find target dropzone
            const targetZone = document.querySelector(`.blok-dropzone[data-blok="${nieuweBlok}"]`);
            if (targetZone) {
                // Remove placeholder if present
                const placeholder = targetZone.querySelector('.dropzone-placeholder');
                if (placeholder) placeholder.remove();

                // Always move chip to ensure correct placement
                insertChipSorted(chip, targetZone);

                // Update chip styling to blue (solver placed, not vast)
                chip.className = 'category-chip px-2 py-1 text-sm rounded cursor-move bg-gradient-to-b from-blue-50 to-blue-100 text-blue-800 border border-blue-300 shadow-sm hover:shadow transition-all inline-flex items-center gap-1';
                chip.dataset.vast = '0';

                // Add red 📌 pin icon (not vast)
                let pinIcon = chip.querySelector('.pin-icon');
                if (!pinIcon) {
                    pinIcon = document.createElement('span');
                    pinIcon.className = 'pin-icon text-red-400 cursor-pointer ml-1';
                    chip.appendChild(pinIcon);
                }
                pinIcon.textContent = '📌';
                pinIcon.className = 'pin-icon text-red-400 cursor-pointer ml-1';
                pinIcon.title = 'Niet vast - klik om vast te zetten';
            }
        }
    });

    // Force update all stats and overzicht panel
    updateAllStats();
}

// Intercept form submit to apply current variant first
document.getElementById('zet-op-mat-form')?.addEventListener('submit', function(e) {
    if (variantenData && variantenData[huidigeVariant]) {
        e.preventDefault();
        // Apply variant to DB then submit
        fetch('{{ route('toernooi.blok.kies-variant', $toernooi) }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ variant: huidigeVariant })
        }).then(() => {
            // Clear session and submit
            this.submit();
        });
    }
});
@endif

function insertChipSorted(chip, targetZone) {
    const sortValue = parseInt(chip.dataset.sort) || 0;
    const chips = Array.from(targetZone.querySelectorAll('.category-chip'));

    for (const existingChip of chips) {
        if (existingChip === chip) continue;
        if (sortValue < (parseInt(existingChip.dataset.sort) || 0)) {
            targetZone.insertBefore(chip, existingChip);
            return;
        }
    }
    targetZone.appendChild(chip);
}

function updateAllStats() {
    // Update each blok's total and afwijking (in percentage)
    document.querySelectorAll('.blok-container').forEach(container => {
        const dropzone = container.querySelector('.blok-dropzone');
        if (!dropzone) return;

        let total = 0;
        dropzone.querySelectorAll('.category-chip').forEach(chip => {
            total += parseInt(chip.dataset.wedstrijden) || 0;
        });

        const actueel = container.querySelector('.blok-actueel');
        if (actueel) actueel.textContent = total;

        const gewenst = Math.max(1, parseInt(container.dataset.gewenst) || 1);
        const afwijkingPct = Math.round((total - gewenst) / gewenst * 100);
        const badge = container.querySelector('.afwijking-badge');
        if (badge) {
            badge.textContent = (afwijkingPct >= 0 ? '+' : '') + afwijkingPct + '%';
            // Green <= 10%, Yellow <= 25%, Red > 25% (HARD LIMIT)
            badge.className = 'afwijking-badge text-xs px-2 py-0.5 rounded ' +
                (Math.abs(afwijkingPct) <= 10 ? 'bg-green-500' : (Math.abs(afwijkingPct) <= 25 ? 'bg-yellow-500' : 'bg-red-500'));
        }
    });

    // Update sleepvak stats
    const sleepvak = document.getElementById('niet-verdeeld-content');
    if (sleepvak) {
        let count = 0, total = 0;
        sleepvak.querySelectorAll('.category-chip').forEach(chip => {
            count++;
            total += parseInt(chip.dataset.wedstrijden) || 0;
        });
        const stats = document.getElementById('sleepvak-stats');
        if (stats) stats.textContent = `(${count} cat., ${total}w)`;

        const header = document.getElementById('niet-verdeeld-header');
        if (header) {
            header.textContent = count > 0 ? `${count} cat. niet verdeeld` : '';
        }
    }

    // Update overzicht panel badges
    document.querySelectorAll('.category-chip').forEach(chip => {
        const key = chip.dataset.key;
        const badge = document.querySelector(`.blok-badge[data-key="${key}"]`);
        if (!badge) return;

        const blokZone = chip.closest('.blok-dropzone');
        const blokNr = blokZone?.dataset?.blok;
        const isVast = chip.dataset.vast === '1';

        if (blokNr === '0' || !blokNr) {
            badge.textContent = '-';
            badge.className = 'bg-red-100 text-red-600 px-1.5 py-0.5 rounded blok-badge';
        } else if (isVast) {
            badge.textContent = '●' + blokNr;
            badge.className = 'bg-green-100 text-green-800 px-1.5 py-0.5 rounded font-bold blok-badge';
        } else {
            badge.textContent = blokNr;
            badge.className = 'bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded font-bold blok-badge';
        }
    });

    // Bereken en update live score
    berekenLiveScore();
}

// Leeftijd volgorde voor aansluiting berekening
const leeftijdVolgorde = @json($leeftijdVolgorde);

let heeftWijzigingen = false;

function berekenLiveScore() {
    const balans = parseInt(document.getElementById('balans-slider-header')?.value || 50);
    const verdelingGewicht = (100 - balans) / 100;
    const aansluitingGewicht = balans / 100;

    // 1. Verzamel alle chips per blok
    const blokken = {};
    const categoriePerBlok = {};  // key -> blokNr

    // Zoek in blok-containers (bevat data-gewenst)
    document.querySelectorAll('.blok-container').forEach(container => {
        const blokNr = parseInt(container.dataset.blok);
        if (!blokNr || blokNr === 0) return;  // Skip sleepvak

        const gewenst = parseInt(container.dataset.gewenst) || 100;
        let actueel = 0;

        // Chips zitten in .blok-dropzone BINNEN de container
        const dropzone = container.querySelector('.blok-dropzone');
        if (dropzone) {
            dropzone.querySelectorAll('.category-chip').forEach(chip => {
                actueel += parseInt(chip.dataset.wedstrijden) || 0;
                categoriePerBlok[chip.dataset.key] = blokNr;
            });
        }

        blokken[blokNr] = { gewenst, actueel };
    });

    // Debug: log wat we vinden
    console.log('Live score berekening:', { blokken, categoriePerBlok, verdelingGewicht, aansluitingGewicht });

    // 2. Bereken verdeling score: som van absolute % afwijkingen
    let verdelingScore = 0;
    for (const blokNr in blokken) {
        const { gewenst, actueel } = blokken[blokNr];
        const afwijkingPct = Math.abs((actueel - gewenst) / gewenst * 100);
        verdelingScore += afwijkingPct;
    }

    // 3. Bereken aansluiting score per leeftijdsklasse
    // Groepeer chips per leeftijd
    const chipsPerLeeftijd = {};
    document.querySelectorAll('.category-chip').forEach(chip => {
        const leeftijd = chip.dataset.leeftijd;
        if (!leeftijd) return;
        if (!chipsPerLeeftijd[leeftijd]) chipsPerLeeftijd[leeftijd] = [];
        chipsPerLeeftijd[leeftijd].push({
            key: chip.dataset.key,
            gewicht: parseInt(chip.dataset.sort) || 0,
            blok: categoriePerBlok[chip.dataset.key]
        });
    });

    let aansluitingScore = 0;
    for (const leeftijd in chipsPerLeeftijd) {
        const cats = chipsPerLeeftijd[leeftijd].sort((a, b) => a.gewicht - b.gewicht);
        let vorigBlok = null;
        let eersteBlok = null;
        let laatsteBlok = null;

        for (const cat of cats) {
            // Skip chips in sleepvak (geen blok toegewezen)
            if (!cat.blok) continue;

            if (eersteBlok === null) eersteBlok = cat.blok;
            laatsteBlok = cat.blok;

            if (vorigBlok !== null) {
                const verschil = cat.blok - vorigBlok;
                // Score per overgang: 0=0, +1=10, -1=20, +2=30, anders=50+
                let punten = 0;
                if (verschil === 0) punten = 0;
                else if (verschil === 1) punten = 10;
                else if (verschil === -1) punten = 20;
                else if (verschil === 2) punten = 30;
                else punten = 50 + Math.abs(verschil) * 10;
                aansluitingScore += punten;
            }
            vorigBlok = cat.blok;
        }

        // Penalty voor aflopende leeftijdsklasse
        if (eersteBlok !== null && laatsteBlok !== null && laatsteBlok < eersteBlok) {
            aansluitingScore += 200;
        }
    }

    // 4. Totaal score
    const totaalScore = Math.round(verdelingGewicht * verdelingScore + aansluitingGewicht * aansluitingScore);

    // 5. Toon live score achter statistieken (alleen als er wijzigingen zijn)
    const liveScoreDisplay = document.getElementById('live-score-display');
    if (liveScoreDisplay) {
        if (!heeftWijzigingen) {
            liveScoreDisplay.classList.add('hidden');
            return;
        }

        // Haal originele score van actieve variant
        const actieveBtn = document.querySelector('.variant-btn.ring-2');
        const origineelScore = actieveBtn ? parseFloat(actieveBtn.dataset.origineelScore) : null;

        if (origineelScore !== null) {
            const verschil = totaalScore - origineelScore;
            const verschilText = verschil > 0 ? `+${verschil.toFixed(1)}` : verschil.toFixed(1);
            const kleur = verschil < 0 ? 'text-green-600' : (verschil > 0 ? 'text-red-600' : 'text-gray-600');
            const icon = verschil < 0 ? '↓' : (verschil > 0 ? '↑' : '=');

            liveScoreDisplay.innerHTML = `| Huidige score: <span class="font-bold ${kleur}">${totaalScore}</span> <span class="${kleur}">(${icon}${verschilText})</span>`;
            liveScoreDisplay.classList.remove('hidden');
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Initial score calculation
    berekenLiveScore();
    let draggedChip = null;

    // Make all chips draggable
    document.querySelectorAll('.category-chip').forEach(chip => {
        chip.addEventListener('dragstart', function(e) {
            draggedChip = this;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', this.dataset.key);
            setTimeout(() => this.classList.add('opacity-50'), 0);
        });
        chip.addEventListener('dragend', function() {
            this.classList.remove('opacity-50');
            draggedChip = null;
            document.querySelectorAll('.blok-dropzone').forEach(z => z.classList.remove('bg-yellow-50', 'ring-2', 'ring-yellow-400'));
        });
    });

    // Drop zones
    document.querySelectorAll('.blok-dropzone').forEach(zone => {
        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            this.classList.add('bg-yellow-50', 'ring-2', 'ring-yellow-400');
        });
        zone.addEventListener('dragleave', function(e) {
            if (!this.contains(e.relatedTarget)) {
                this.classList.remove('bg-yellow-50', 'ring-2', 'ring-yellow-400');
            }
        });
        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('bg-yellow-50', 'ring-2', 'ring-yellow-400');
            if (!draggedChip) return;

            const blok = this.dataset.blok;
            const currentBlok = draggedChip.closest('.blok-dropzone')?.dataset?.blok;
            if (blok === currentBlok) return;

            // Markeer dat er wijzigingen zijn voor live score
            heeftWijzigingen = true;

            const placeholder = this.querySelector('.dropzone-placeholder');
            if (placeholder) placeholder.remove();

            // Move to sleepvak
            if (blok === '0') {
                const leeftijd = draggedChip.dataset.leeftijd;
                let target = document.querySelector(`#niet-verdeeld-content .leeftijd-groep[data-leeftijd="${leeftijd}"] .leeftijd-content`);
                if (!target) target = this;
                target.appendChild(draggedChip);

                // Purple styling, no pin
                draggedChip.className = 'category-chip px-2 py-1 text-sm rounded cursor-move bg-gradient-to-b from-purple-50 to-purple-100 text-purple-800 border border-purple-300 shadow-sm hover:shadow transition-all inline-flex items-center gap-1';
                draggedChip.dataset.vast = '0';
                const pinIcon = draggedChip.querySelector('.pin-icon');
                if (pinIcon) pinIcon.remove();
            } else {
                // Move to blok - blue with red 📌 (NOT vast yet)
                insertChipSorted(draggedChip, this);

                draggedChip.className = 'category-chip px-2 py-1 text-sm rounded cursor-move bg-gradient-to-b from-blue-50 to-blue-100 text-blue-800 border border-blue-300 shadow-sm hover:shadow transition-all inline-flex items-center gap-1';
                draggedChip.dataset.vast = '0';

                // Add or update pin icon
                let pinIcon = draggedChip.querySelector('.pin-icon');
                if (!pinIcon) {
                    pinIcon = document.createElement('span');
                    pinIcon.className = 'pin-icon text-red-400 cursor-pointer ml-1';
                    pinIcon.title = 'Niet vast - klik om vast te zetten';
                    draggedChip.appendChild(pinIcon);
                }
                pinIcon.textContent = '📌';
                pinIcon.className = 'pin-icon text-red-400 cursor-pointer ml-1';
                pinIcon.title = 'Niet vast - klik om vast te zetten';
            }

            // Save to server (vast: false for drag, true only when pinned)
            fetch('{{ route('toernooi.blok.verplaats-categorie', $toernooi) }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ key: draggedChip.dataset.key, blok: blok, vast: false })
            });

            updateAllStats();
        });
    });

    // Pin icon click handler - toggle vast status
    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('pin-icon')) return;
        e.stopPropagation();

        const chip = e.target.closest('.category-chip');
        if (!chip) return;

        const blokZone = chip.closest('.blok-dropzone');
        if (!blokZone || blokZone.dataset.blok === '0') return; // Not in a blok

        const isVast = chip.dataset.vast === '1';
        const newVast = !isVast;

        chip.dataset.vast = newVast ? '1' : '0';

        if (newVast) {
            // Vast: green chip, green ●
            chip.className = 'category-chip px-2 py-1 text-sm rounded cursor-move bg-gradient-to-b from-green-50 to-green-100 text-green-800 border border-green-400 shadow-sm hover:shadow transition-all inline-flex items-center gap-1';
            e.target.textContent = '●';
            e.target.className = 'pin-icon text-green-600 cursor-pointer ml-1';
            e.target.title = 'Vast - klik om los te maken';
        } else {
            // Niet vast: blue chip, red 📌
            chip.className = 'category-chip px-2 py-1 text-sm rounded cursor-move bg-gradient-to-b from-blue-50 to-blue-100 text-blue-800 border border-blue-300 shadow-sm hover:shadow transition-all inline-flex items-center gap-1';
            e.target.textContent = '📌';
            e.target.className = 'pin-icon text-red-400 cursor-pointer ml-1';
            e.target.title = 'Niet vast - klik om vast te zetten';
        }

        // Save to server
        fetch('{{ route('toernooi.blok.verplaats-categorie', $toernooi) }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ key: chip.dataset.key, blok: blokZone.dataset.blok, vast: newVast })
        });

        updateAllStats();
    });
});
</script>
@endsection
