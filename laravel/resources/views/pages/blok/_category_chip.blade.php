@php
    // $leeftijdVolgorde en $afkortingen worden doorgegeven vanuit parent view
    // Fallback: gebruik lege arrays (parent view moet deze altijd meegeven)
    $leeftijdVolgorde = $leeftijdVolgorde ?? [];
    $afkortingen = $afkortingen ?? [];
    $pos = array_search($cat['leeftijd'], $leeftijdVolgorde);
    $sortValue = ($pos !== false ? $pos : 99) * 10000 + (int)preg_replace('/[^0-9]/', '', $cat['gewicht']) + (str_starts_with($cat['gewicht'], '+') ? 500 : 0);

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

    // Check of dit een variabele categorie is
    // Variabel = titel bevat leeftijdsrange (bijv. "Mini's 7-8j 20-23kg")
    $titel = $cat['titel'] ?? '';
    $isVariabel = preg_match('/\d+-?\d*j/', $titel) && preg_match('/[\d.]+-[\d.]+kg/', $titel);

    if ($isVariabel) {
        // Variabele categorie: toon "M 7j 20kg (10w)" format uit titel
        // Extract label prefix (Mini's → M, A-pupillen → A, Jeugd → J)
        $labelPrefix = '';
        if (preg_match('/^([A-Za-z])/', $titel, $m)) {
            $labelPrefix = strtoupper($m[1]);
        }
        // Extract min leeftijd uit "7-8j" of "7j"
        preg_match('/(\d+)-?\d*j/', $titel, $lftMatch);
        $minLeeftijd = $lftMatch[1] ?? '';
        // Extract min gewicht uit "20.0-23.5kg"
        preg_match('/([\d.]+)-[\d.]+kg/', $titel, $kgMatch);
        $minGewicht = $kgMatch[1] ?? '';

        $chipLabel = trim($labelPrefix . ' ' . $minLeeftijd . 'j');
        $chipGewicht = $minGewicht ? round((float)$minGewicht) . 'kg' : '';
    } else {
        // Vaste categorie: bestaande weergave
        $chipLabel = $afkortingen[$cat['leeftijd']] ?? $cat['leeftijd'];
        $chipGewicht = $cat['gewicht'];
    }
@endphp
<div class="category-chip px-2 py-1 text-sm rounded cursor-move {{ $chipClass }} shadow-sm hover:shadow transition-all inline-flex items-center gap-1"
     draggable="true"
     data-key="{{ $cat['leeftijd'] }}|{{ $cat['gewicht'] }}"
     data-leeftijd="{{ $cat['leeftijd'] }}"
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
