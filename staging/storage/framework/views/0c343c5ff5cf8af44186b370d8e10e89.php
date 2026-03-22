<?php
    // $leeftijdVolgorde en $afkortingen worden doorgegeven vanuit parent view
    $leeftijdVolgorde = $leeftijdVolgorde ?? [];
    $afkortingen = $afkortingen ?? [];
    $pos = array_search($cat['leeftijd'], $leeftijdVolgorde);

    // is_poule = true voor individuele poules (variabele categorieën)
    // is_poule = false voor gegroepeerde categorieën (vaste gewichtsklassen)
    $isPoule = $cat['is_poule'] ?? false;
    $isKruisfinale = ($cat['type'] ?? '') === 'kruisfinale';
    $isEliminatie = ($cat['type'] ?? '') === 'eliminatie';

    // Sort value voor correcte volgorde (kruisfinale na voorronde: +50000)
    $sortValue = ($pos !== false ? $pos : 99) * 100000
        + ($cat['min_lft'] ?? 99) * 1000
        + ($cat['min_kg'] ?? 999)
        + ($isKruisfinale ? 50000 : 0)
        + ($isEliminatie ? 40000 : 0);

    // Kruisfinale = orange, eliminatie = orange, sleepvak = purple, vast = green, normaal = blue
    if ($isKruisfinale) {
        $chipClass = 'bg-gradient-to-b from-orange-50 to-orange-100 text-orange-800 border border-orange-400';
        $textClass = 'text-orange-600';
        $subClass = 'text-orange-400';
    } elseif ($isEliminatie) {
        $chipClass = 'bg-gradient-to-b from-orange-50 to-orange-100 text-orange-800 border border-orange-300';
        $textClass = 'text-orange-600';
        $subClass = 'text-orange-400';
    } elseif ($inSleepvak) {
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

    if ($isKruisfinale) {
        // Kruisfinale poule: toon "KF #{nr} {gewicht}kg (Xw)"
        $chipLabel = 'KF #' . ($cat['nummer'] ?? '?');
        $chipGewicht = $cat['gewicht'];
        $dataKey = 'poule_' . $cat['poule_id'];
    } elseif ($isPoule) {
        // Individuele poule: toon "P{nr} {label} {lft}j {kg}kg (Xw)"
        // Extract label prefix (Mini's → M, Jeugd → J)
        // Skip prefix if leeftijd is a config key fallback (e.g. "standaard", "u11_jongens")
        $labelPrefix = '';
        $leeftijdLabel = $cat['leeftijd'] ?? '';
        $looksLikeKey = preg_match('/^[a-z0-9_]+$/', $leeftijdLabel);
        if (!$looksLikeKey && preg_match('/^([A-Za-z])/', $leeftijdLabel, $m)) {
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
?>
<div class="category-chip px-2 py-1 text-sm rounded <?php echo e(($isKruisfinale || $isEliminatie) ? 'cursor-default' : 'cursor-move'); ?> <?php echo e($chipClass); ?> shadow-sm hover:shadow transition-all inline-flex items-center gap-1"
     draggable="<?php echo e(($isKruisfinale || $isEliminatie) ? 'false' : 'true'); ?>"
     data-key="<?php echo e($dataKey); ?>"
     data-leeftijd="<?php echo e($cat['leeftijd']); ?>"
     data-is-poule="<?php echo e($isPoule ? '1' : '0'); ?>"
     data-wedstrijden="<?php echo e($cat['wedstrijden']); ?>"
     data-vast="<?php echo e($cat['vast'] ? '1' : '0'); ?>"
     data-sort="<?php echo e($sortValue); ?>"
     title="<?php echo e($cat['titel'] ?? ($cat['leeftijd'] . ' ' . $cat['gewicht'])); ?>">
    <span class="font-semibold"><?php echo e($chipLabel); ?></span>
    <span class="<?php echo e($textClass); ?>"><?php echo e($chipGewicht); ?></span>
    <span class="<?php echo e($subClass); ?>">(<?php echo e($cat['wedstrijden']); ?>w)</span>
    <?php if(!$inSleepvak): ?>
        <?php if($cat['vast']): ?>
            <span class="pin-icon text-green-600 cursor-pointer ml-1" title="<?php echo e(__('Vast - klik om los te maken')); ?>">●</span>
        <?php else: ?>
            <span class="pin-icon text-red-400 cursor-pointer ml-1" title="<?php echo e(__('Niet vast - klik om vast te zetten')); ?>">📌</span>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php /**PATH /var/www/judotoernooi/staging/resources/views/pages/blok/_category_chip.blade.php ENDPATH**/ ?>