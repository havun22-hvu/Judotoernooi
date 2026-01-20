@php
    // $leeftijdVolgorde en $afkortingen worden doorgegeven vanuit parent view
    $leeftijdVolgorde = $leeftijdVolgorde ?? [];
    $afkortingen = $afkortingen ?? [];
    $pos = array_search($cat['leeftijd'], $leeftijdVolgorde);

    // is_poule = true voor individuele poules (variabele categorieën)
    // is_poule = false voor gegroepeerde categorieën (vaste gewichtsklassen)
    $isPoule = $cat['is_poule'] ?? false;

    // Sort value voor correcte volgorde
    $sortValue = ($pos !== false ? $pos : 99) * 100000
        + ($cat['min_lft'] ?? 99) * 1000
        + ($cat['min_kg'] ?? 999);

    // In sleepvak = purple, in blok vast = green, in blok niet vast = blue
    if ($inSleepvak) {
        $chipClass = 'bg-gradient-to-b from-purple-50 to-purple-100 text-purple-800 border border-purple-300 hover:from-purple-100 hover:to-purple-200';
        $textClass = 'text-purple-600';
        $subClass = 'text-purple-400';
    } elseif ($cat['vast']) {
        $chipClass = 'bg-gradient-to-b from-green-50 to-green-100 text-green-800 border border-green-400';
        $textClass = 'text-green-600';
        $subClass = 'text-green-400';
    } else {
        $chipClass = 'bg-gradient-to-b from-blue-50 to-blue-100 text-blue-800 border border-blue-300';
        $textClass = 'text-blue-600';
        $subClass = 'text-blue-400';
    }

    $titel = $cat['titel'] ?? '';

    if ($isPoule) {
        // Individuele poule: toon "P{nr} {label} {lft}j {kg}kg (Xw)"
        // Extract label prefix (Mini's → M, Jeugd → J)
        $labelPrefix = '';
        if (preg_match('/^([A-Za-z])/', $cat['leeftijd'], $m)) {
            $labelPrefix = strtoupper($m[1]);
        }
        // Extract leeftijd uit titel "Jeugd 7-9j -30kg" → "7-9j"
        preg_match('/(\d+-?\d*j)/', $titel, $lftMatch);
        $leeftijdStr = $lftMatch[1] ?? '';

        $chipLabel = '#' . ($cat['nummer'] ?? '?') . ' ' . $labelPrefix;
        $chipGewicht = $leeftijdStr . ' ' . $cat['gewicht'];

        // Key voor poules: gebruik poule_id als beschikbaar, anders leeftijd|gewicht|nummer
        $dataKey = $cat['poule_id'] ? 'poule_' . $cat['poule_id'] : $cat['leeftijd'] . '|' . $cat['gewicht'] . '|' . $cat['nummer'];
    } else {
        // Vaste categorie: bestaande weergave
        $chipLabel = $afkortingen[$cat['leeftijd']] ?? $cat['leeftijd'];
        $chipGewicht = $cat['gewicht'];
        $dataKey = $cat['leeftijd'] . '|' . $cat['gewicht'];
    }
@endphp
<div class="category-chip px-2 py-1 text-sm rounded cursor-move {{ $chipClass }} shadow-sm hover:shadow transition-all inline-flex items-center gap-1"
     draggable="true"
     data-key="{{ $dataKey }}"
     data-leeftijd="{{ $cat['leeftijd'] }}"
     data-is-poule="{{ $isPoule ? '1' : '0' }}"
     data-wedstrijden="{{ $cat['wedstrijden'] }}"
     data-vast="{{ $cat['vast'] ? '1' : '0' }}"
     data-sort="{{ $sortValue }}"
     title="{{ $cat['titel'] ?? ($cat['leeftijd'] . ' ' . $cat['gewicht']) }}">
    <span class="font-semibold">{{ $chipLabel }}</span>
    <span class="{{ $textClass }}">{{ $chipGewicht }}</span>
    <span class="{{ $subClass }}">({{ $cat['wedstrijden'] }}w)</span>
    @if(!$inSleepvak)
        @if($cat['vast'])
            <span class="pin-icon text-green-600 cursor-pointer ml-1" title="Vast - klik om los te maken">●</span>
        @else
            <span class="pin-icon text-red-400 cursor-pointer ml-1" title="Niet vast - klik om vast te zetten">📌</span>
        @endif
    @endif
</div>
