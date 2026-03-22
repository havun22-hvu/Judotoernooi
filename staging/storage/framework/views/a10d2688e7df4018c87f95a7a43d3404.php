<?php
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
    // Gebruik poule's gewichtsklasse, niet judoka's eigen klasse
    $overpoulers = $poule->judokas->filter(fn($j) =>
        $j->gewicht_gewogen > 0 && !$j->isGewichtBinnenKlasse(null, $tolerantie, $poule->gewichtsklasse) && $j->isActief($wegingGesloten)
    );
    $verwijderdeTekst = collect();
    foreach ($afwezigeJudokas as $j) {
        $verwijderdeTekst->push($j->naam . ' (' . __('afwezig') . ')');
    }
    foreach ($overpoulers as $j) {
        $verwijderdeTekst->push($j->naam . ' (' . __('afwijkend gewicht') . ')');
    }

    // Poule titel - gebruik model methode die dynamisch range berekent
    $pouleTitel = $poule->getDisplayTitel();
    $pouleIsDynamisch = $poule->isDynamisch() || empty($poule->gewichtsklasse);
?>
<div
    id="poule-<?php echo e($poule->id); ?>"
    class="border rounded-lg bg-white transition-colors poule-card <?php echo e($aantalActief === 0 ? 'opacity-50' : ''); ?> <?php echo e($isProblematisch ? 'border-2 border-red-300' : ''); ?> <?php echo e($heeftGewichtsprobleem && !$isProblematisch ? 'border-2 border-orange-400' : ''); ?>"
    data-poule-id="<?php echo e($poule->id); ?>"
    data-poule-nummer="<?php echo e($poule->nummer); ?>"
    data-poule-leeftijdsklasse="<?php echo e($poule->leeftijdsklasse); ?>"
    data-poule-gewichtsklasse="<?php echo e($poule->gewichtsklasse); ?>"
    data-actief="<?php echo e($aantalActief); ?>"
>
    <?php
        // Problematisch krijgt voorrang (rood), dan eliminatie (oranje), dan gewichtsprobleem (oranje), anders blauw
        $headerBg = $aantalActief === 0 ? 'bg-gray-500' : ($isProblematisch ? 'bg-red-600' : ($isEliminatie ? 'bg-orange-600' : ($heeftGewichtsprobleem ? 'bg-orange-600' : 'bg-blue-700')));
        $headerSubtext = $aantalActief === 0 ? 'text-gray-300' : ($isProblematisch ? 'text-red-200' : ($isEliminatie ? 'text-orange-200' : ($heeftGewichtsprobleem ? 'text-orange-200' : 'text-blue-200')));
    ?>
    <div class="<?php echo e($headerBg); ?> text-white px-3 py-2 poule-header flex justify-between items-start rounded-t-lg">
        <div class="flex-1">
            <div class="font-bold text-sm"><?php if($isEliminatie): ?>⚔️ <?php endif; ?>#<?php echo e($poule->nummer); ?> <?php echo e($pouleTitel); ?></div>
            <div class="text-xs <?php echo e($headerSubtext); ?> poule-stats"><span class="poule-actief"><?php echo e($aantalActief); ?></span> <?php echo e(__('judoka\'s')); ?> ~<span class="poule-wedstrijden"><?php echo e($aantalWedstrijden); ?></span> <?php echo e(__('wedstrijden')); ?></div>
        </div>
        <div class="flex items-center gap-1 flex-shrink-0">
            <?php if($verwijderdeTekst->isNotEmpty()): ?>
            <div class="relative" x-data="{ show: false }">
                <span @click="show = !show" @click.away="show = false" class="info-icon cursor-pointer text-base opacity-80 hover:opacity-100">ⓘ</span>
                <div x-show="show" x-transition class="absolute bottom-full right-0 mb-2 bg-gray-900 text-white text-xs rounded px-3 py-2 whitespace-pre-line z-[9999] min-w-[200px] shadow-xl pointer-events-none"><?php echo e($verwijderdeTekst->join("\n")); ?></div>
            </div>
            <?php endif; ?>
            <?php if($isEliminatie): ?>
            
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="bg-orange-500 hover:bg-orange-400 text-white text-xs px-2 py-0.5 rounded">⚙</button>
                <div x-show="open" @click.away="open = false" class="absolute right-0 mt-1 bg-white border rounded-lg shadow-lg z-10 min-w-[160px]">
                    <button onclick="zetOmNaarPoules(<?php echo e($poule->id); ?>, 'poules')" class="w-full text-left px-3 py-2 hover:bg-gray-100 text-sm text-gray-700"><?php echo e(__('Naar poules')); ?></button>
                    <button onclick="zetOmNaarPoules(<?php echo e($poule->id); ?>, 'poules_kruisfinale')" class="w-full text-left px-3 py-2 hover:bg-gray-100 text-sm text-gray-700 border-t"><?php echo e(__('+ kruisfinale')); ?></button>
                </div>
            </div>
            <?php elseif($aantalTotaal > 0): ?>
            <?php $isDoorgestuurd = $poule->doorgestuurd_op !== null; ?>
            <button
                onclick="naarZaaloverzichtPoule(<?php echo e($poule->id); ?>, this)"
                class="px-2 py-0.5 text-xs rounded transition-all <?php echo e($isDoorgestuurd ? 'bg-green-500 hover:bg-green-600' : 'bg-blue-500 hover:bg-blue-600'); ?>"
                title="<?php echo e($isDoorgestuurd ? __('Doorgestuurd') : __('Naar zaaloverzicht')); ?>"
            ><?php echo e($isDoorgestuurd ? '✓' : '→'); ?></button>
            <?php else: ?>
            <button
                onclick="verwijderPoule(<?php echo e($poule->id); ?>, '<?php echo e($poule->nummer); ?>')"
                class="delete-poule-btn w-6 h-6 flex items-center justify-center bg-black hover:bg-gray-800 text-white rounded-full text-sm font-bold"
                title="<?php echo e(__('Verwijder lege poule')); ?>"
            >×</button>
            <?php endif; ?>
        </div>
    </div>
    <div class="divide-y divide-gray-100 sortable-poule min-h-[40px]" data-poule-id="<?php echo e($poule->id); ?>">
        <?php $__currentLoopData = $poule->judokas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $judoka): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php
            $isGewogen = $judoka->gewicht_gewogen > 0;
            $isAfwezig = !$judoka->isActief($wegingGesloten);
            // Gebruik poule's gewichtsklasse, niet judoka's eigen klasse
            // Check ook opgegeven gewicht als niet gewogen (vb: 26.1 kg in -25 poule)
            $heeftGewicht = $isGewogen || $judoka->gewicht > 0;
            $isAfwijkendGewicht = $heeftGewicht && !$judoka->isGewichtBinnenKlasse(null, $tolerantie, $poule->gewichtsklasse);
            // Verdacht gewicht: < 15 kg OF > 5 kg afwijking van opgegeven gewicht
            $isVerdachtGewicht = $isGewogen && (
                $judoka->gewicht_gewogen < 15 ||
                ($judoka->gewicht && abs($judoka->gewicht_gewogen - $judoka->gewicht) > 5)
            );
            $heeftProbleem = $isAfwijkendGewicht || $isVerdachtGewicht;
        ?>
        <?php if($isAfwezig): ?>
            <?php continue; ?>
        <?php endif; ?>
        <div
            class="px-2 py-1.5 text-sm judoka-item hover:bg-blue-50 cursor-move group <?php echo e($isVerdachtGewicht ? 'bg-red-50 border-l-4 border-red-500' : ($heeftProbleem ? 'bg-orange-50 border-l-4 border-orange-400' : '')); ?>"
            data-judoka-id="<?php echo e($judoka->id); ?>"
            draggable="true"
        >
            <div class="flex justify-between items-start">
                <div class="flex items-center gap-1 flex-1 min-w-0">
                    <?php if($isVerdachtGewicht): ?>
                        <span class="text-red-600 text-xs flex-shrink-0" title="<?php echo e(__('Verdacht gewicht!')); ?> <?php echo e($judoka->gewicht_gewogen < 15 ? __('Te laag') : __('Grote afwijking van opgave')); ?>">🚨</span>
                    <?php elseif($heeftProbleem): ?>
                        <span class="text-orange-500 text-xs flex-shrink-0" title="<?php echo e(__('Afwijkend gewicht')); ?>">⚠</span>
                    <?php elseif($isGewogen): ?>
                        <span class="text-green-500 text-xs flex-shrink-0">●</span>
                    <?php endif; ?>
                    <div class="min-w-0">
                        <div class="font-medium <?php echo e($isVerdachtGewicht ? 'text-red-800' : ($heeftProbleem ? 'text-orange-800' : 'text-gray-800')); ?> truncate"><?php echo e($judoka->naam); ?> <span class="text-gray-400 font-normal">(<?php echo e($judoka->leeftijd); ?>j)</span></div>
                        <div class="text-xs text-gray-500 truncate"><?php echo e($judoka->club?->naam ?? '-'); ?></div>
                    </div>
                </div>
                <div class="flex items-center gap-1 flex-shrink-0">
                    <div class="text-right text-xs">
                        <div class="<?php echo e($isVerdachtGewicht ? 'text-red-600 font-bold' : ($heeftProbleem ? 'text-orange-600 font-bold' : 'text-gray-600')); ?> font-medium"><?php echo e($judoka->gewicht_gewogen ? $judoka->gewicht_gewogen . ' kg' : ($judoka->gewicht ? $judoka->gewicht . ' kg' : '-')); ?></div>
                        <div class="text-gray-400"><?php echo e(\App\Enums\Band::toKleur($judoka->band)); ?></div>
                    </div>
                    <button
                        onclick="event.stopPropagation(); openZoekMatchWedstrijddag(<?php echo e($judoka->id); ?>, <?php echo e($poule->id); ?>)"
                        class="text-gray-400 hover:text-blue-600 p-1 rounded hover:bg-blue-50 transition-colors opacity-0 group-hover:opacity-100"
                        title="<?php echo e(__('Zoek geschikte poule')); ?>"
                    >🔍</button>
                    <button
                        onclick="event.stopPropagation(); meldJudokaAf(<?php echo e($judoka->id); ?>, '<?php echo e(addslashes($judoka->naam)); ?>')"
                        class="text-gray-400 hover:text-red-600 p-1 rounded hover:bg-red-50 transition-colors opacity-0 group-hover:opacity-100"
                        title="<?php echo e(__('Afmelden (kan niet deelnemen)')); ?>"
                    >✕</button>
                </div>
            </div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
</div>
<?php /**PATH /var/www/judotoernooi/staging/resources/views/pages/wedstrijddag/partials/poule-card.blade.php ENDPATH**/ ?>