<?php $__env->startSection('title', __('Wedstrijddag Poules')); ?>

<?php $__env->startSection('content'); ?>
<div class="select-none">
<?php
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
?>
<div x-data="wedstrijddagPoules()" class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold"><?php echo e(__('Wedstrijddag Poules')); ?></h1>
        <button onclick="verifieerPoules()" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
            <?php echo e(__('Verifieer poules')); ?>

        </button>
    </div>

    
    <?php if($matWaarschuwing ?? false): ?>
        <div class="bg-yellow-50 border border-yellow-300 rounded-lg p-4">
            <h3 class="font-bold text-yellow-800 mb-1"><?php echo e(__('Poules staan nog niet op matten')); ?></h3>
            <p class="text-yellow-700 text-sm">
                <?php echo e(__('Ga naar')); ?> <strong><?php echo e(__('Blokken')); ?></strong> → <strong><?php echo e(__('Verdeel over matten')); ?></strong>
                <?php echo e(__('om de poules eerst aan matten toe te wijzen. Daarna verschijnen ze in het zaaloverzicht.')); ?>

            </p>
        </div>
    <?php endif; ?>

    <!-- Verificatie resultaat -->
    <div id="verificatie-resultaat" class="hidden"></div>

    <!-- Problematische poules (gecombineerd: te weinig/te veel judoka's + gewichtsrange) -->
    <?php
        $totaalProblemen = $teWeinigjudokas->count() + $teVeelJudokas->count() + $problematischeGewichtsPoules->count();
    ?>
    <div id="problematische-poules-container" class="<?php echo e($totaalProblemen > 0 ? '' : 'hidden'); ?>" style="width: fit-content;">
    <div class="bg-red-50 border border-red-300 rounded-lg p-4">
        <h3 class="font-bold text-red-800 mb-2"><?php echo e(__('Problematische poules')); ?> (<span id="problematische-count"><?php echo e($totaalProblemen); ?></span>)</h3>

        
        <?php if($teWeinigjudokas->count() > 0): ?>
        <p class="text-red-700 text-sm mb-1"><?php echo e(__("Te weinig judoka's")); ?> (&lt; 3):</p>
        <p class="text-gray-600 text-xs mb-2"><?php echo e(__('Oplossing: sleep judoka naar andere poule, of bij weging gewicht')); ?> <strong>0</strong> <?php echo e(__('invoeren = afmelden')); ?></p>
        <div id="problematische-links" class="flex flex-wrap gap-2 mb-3">
            <?php $__currentLoopData = $teWeinigjudokas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a href="#poule-<?php echo e($p['id']); ?>" onclick="scrollToPoule(event, <?php echo e($p['id']); ?>)" data-probleem-poule="<?php echo e($p['id']); ?>" class="inline-flex items-center px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm hover:bg-red-200 cursor-pointer transition-colors">
                #<?php echo e($p['nummer']); ?> <?php echo e($p['label']); ?> <?php echo e($p['gewichtsklasse']); ?> (<span data-probleem-count="<?php echo e($p['id']); ?>"><?php echo e($p['actief']); ?></span>)
            </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <?php endif; ?>

        
        <?php if($teVeelJudokas->count() > 0): ?>
        <p class="text-purple-700 text-sm mb-2"><?php echo e(__("Te veel judoka's")); ?> (&ge; 6) - <?php echo e(__('splitsen')); ?>:</p>
        <div id="teveel-links" class="flex flex-wrap gap-2 mb-3">
            <?php $__currentLoopData = $teVeelJudokas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a href="#poule-<?php echo e($p['id']); ?>" onclick="scrollToPoule(event, <?php echo e($p['id']); ?>)" data-teveel-poule="<?php echo e($p['id']); ?>" class="inline-flex items-center px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm hover:bg-purple-200 cursor-pointer transition-colors">
                #<?php echo e($p['nummer']); ?> <?php echo e($p['label']); ?> <?php echo e($p['gewichtsklasse']); ?> (<span data-teveel-count="<?php echo e($p['id']); ?>"><?php echo e($p['actief']); ?></span>)
            </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <?php endif; ?>

        
        <?php if($problematischeGewichtsPoules->count() > 0): ?>
        <p class="text-orange-700 text-sm mb-2"><?php echo e(__('Gewichtsrange overschreden')); ?>:</p>
        <div id="gewichtsrange-items" class="space-y-3">
            <?php $__currentLoopData = $problematischeGewichtsPoules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pouleId => $probleem): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
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
            ?>
            <?php if($pouleInfo): ?>
            <div id="gewichtsrange-poule-<?php echo e($pouleId); ?>" class="bg-white border border-orange-200 rounded-lg p-3" data-gewichtsrange-poule="<?php echo e($pouleId); ?>">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <a href="#poule-<?php echo e($pouleId); ?>" onclick="scrollToPoule(event, <?php echo e($pouleId); ?>)" class="font-bold text-orange-800 hover:underline cursor-pointer">
                            #<?php echo e($pouleInfo->nummer); ?> <?php echo e($pouleInfo->getDisplayTitel()); ?>

                        </a>
                        <span class="text-orange-600 text-sm ml-2">Range: <?php echo e(number_format($probleem['range'], 1)); ?>kg (max: <?php echo e(number_format($probleem['max_toegestaan'], 1)); ?>kg)</span>
                    </div>
                    <span class="text-red-600 font-bold">+<?php echo e(number_format($probleem['overschrijding'], 1)); ?>kg <?php echo e(__('over')); ?></span>
                </div>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <?php if($probleem['lichtste']): ?>
                    <div class="flex items-center justify-between bg-blue-50 rounded px-2 py-1">
                        <span>
                            <span class="text-blue-600 font-medium"><?php echo e(number_format($probleem['min_kg'], 1)); ?>kg</span>
                            - <?php echo e($probleem['lichtste']->naam); ?>

                        </span>
                        <button onclick="openZoekMatchWedstrijddag(<?php echo e($probleem['lichtste']->id); ?>, <?php echo e($pouleId); ?>)" class="text-blue-600 hover:text-blue-800 text-xs font-medium px-2 py-0.5 bg-blue-100 rounded">
                            <?php echo e(__('Zoek match')); ?>

                        </button>
                    </div>
                    <?php endif; ?>
                    <?php if($probleem['zwaarste'] && $probleem['zwaarste']->id !== $probleem['lichtste']?->id): ?>
                    <div class="flex items-center justify-between bg-red-50 rounded px-2 py-1">
                        <span>
                            <span class="text-red-600 font-medium"><?php echo e(number_format($probleem['max_kg'], 1)); ?>kg</span>
                            - <?php echo e($probleem['zwaarste']->naam); ?>

                        </span>
                        <button onclick="openZoekMatchWedstrijddag(<?php echo e($probleem['zwaarste']->id); ?>, <?php echo e($pouleId); ?>)" class="text-red-600 hover:text-red-800 text-xs font-medium px-2 py-0.5 bg-red-100 rounded">
                            <?php echo e(__('Zoek match')); ?>

                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <?php endif; ?>
    </div>
    </div>

    
    <div class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-2 flex items-center gap-6 text-sm">
        <span class="font-medium text-gray-600"><?php echo e(__('Legenda')); ?>:</span>
        <span class="flex items-center gap-1"><span class="text-green-500">●</span> <?php echo e(__('Gewogen')); ?></span>
        <span class="flex items-center gap-1"><span class="text-orange-500">⚠</span> <?php echo e(__('Afwijkend gewicht')); ?></span>
    </div>

    <div id="blokken-container" class="space-y-6">
    <?php $__empty_1 = true; $__currentLoopData = $blokken; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $blok): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <div class="bg-white rounded-lg shadow w-full blok-item" x-data="{
    open: localStorage.getItem('blok-poules-<?php echo e($blok['id']); ?>') !== null
        ? localStorage.getItem('blok-poules-<?php echo e($blok['id']); ?>') === 'true'
        : <?php echo e($loop->first ? 'true' : 'false'); ?>

}" x-init="$watch('open', val => localStorage.setItem('blok-poules-<?php echo e($blok['id']); ?>', val))">
        
        <?php
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
        ?>
        <div class="flex items-center bg-gray-800 text-white rounded-t-lg">
            <button @click="open = !open" class="flex-1 flex justify-between items-center px-4 py-3 hover:bg-gray-700 rounded-tl-lg">
                <div class="flex items-center gap-4">
                    <span class="text-lg font-bold"><?php echo e(__('Blok')); ?> <?php echo e($blok['nummer']); ?></span>
                    <span class="text-gray-300 text-sm"><?php echo e($blokJudokas); ?> <?php echo e(__("judoka's")); ?> | <?php echo e($blokWedstrijden); ?> <?php echo e(__('wedstrijden')); ?> | <?php echo e($blok['categories']->count()); ?> <?php echo e(__('categorieën')); ?></span>
                </div>
                <svg :class="{ 'rotate-180': open }" class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <?php if($heeftVariabeleCategorieen): ?>
            <?php
                $blokPouleOpties = collect();
                foreach ($blok['categories'] as $cat) {
                    if ($cat['is_eliminatie'] ?? false) continue;
                    foreach ($cat['poules'] as $p) {
                        if ($p->type !== 'kruisfinale') {
                            $blokPouleOpties->push(['id' => $p->id, 'nummer' => $p->nummer]);
                        }
                    }
                }
            ?>
            <button onclick="openNieuweJudokaModal('', '', <?php echo e($blokPouleOpties->values()->toJson()); ?>)" class="px-3 py-2 bg-green-600 hover:bg-green-500 text-white text-sm rounded font-medium">+ <?php echo e(__('Laatkomer')); ?></button>
            <?php endif; ?>
            <button onclick="openNieuwePouleModal(<?php echo e($blok['nummer']); ?>)" class="px-3 py-2 mr-2 bg-green-600 hover:bg-green-500 text-white text-sm rounded font-medium">+ <?php echo e(__('Poule')); ?></button>
        </div>

        
        <div x-show="open" x-collapse>
            <?php if($heeftVariabeleCategorieen): ?>
            
            
            <?php
                $allePoules = collect();
                $eliminatiePoules = collect();
                foreach ($blok['categories'] as $cat) {
                    if ($cat['is_eliminatie'] ?? false) {
                        $eliminatiePoules = $eliminatiePoules->merge($cat['poules']);
                    } else {
                        $allePoules = $allePoules->merge($cat['poules']);
                    }
                }
            ?>
            <div class="bg-white p-4">
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    
                    <?php $__currentLoopData = $eliminatiePoules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $elimPoule): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                        // Afwezige judoka's
                        $afwezigeElim = $elimPoule->judokas->filter(fn($j) => !$j->isActief($wegingGesloten));
                        // Overgepoulde judoka's (afwijkend gewicht maar wel actief)
                        // Gebruik poule's gewichtsklasse, niet judoka's eigen klasse
                        $overpoulersElim = $elimPoule->judokas->filter(fn($j) =>
                            $j->gewicht_gewogen > 0 && !$j->isGewichtBinnenKlasse(null, $tolerantie, $elimPoule->gewichtsklasse) && $j->isActief($wegingGesloten)
                        );
                        $aantalActiefElim = $elimPoule->judokas->count() - $afwezigeElim->count();

                        // Info tekst voor tooltip
                        $verwijderdeTekstElim = collect();
                        foreach ($afwezigeElim as $j) {
                            $verwijderdeTekstElim->push($j->naam . ' (' . __('afwezig') . ')');
                        }
                        foreach ($overpoulersElim as $j) {
                            $verwijderdeTekstElim->push($j->naam . ' (' . __('afwijkend gewicht') . ')');
                        }

                        // Titel via model methode
                        $elimTitelFormatted = $elimPoule->getDisplayTitel();

                        $isDoorgestuurdElim = $elimPoule->doorgestuurd_op !== null;
                    ?>
                    <div id="poule-<?php echo e($elimPoule->id); ?>" class="col-span-2 md:col-span-3 lg:col-span-4 border-2 border-orange-300 rounded-lg overflow-hidden bg-white poule-card" data-poule-id="<?php echo e($elimPoule->id); ?>" data-poule-nummer="<?php echo e($elimPoule->nummer); ?>" data-type="eliminatie">
                        <div class="bg-orange-600 text-white px-4 py-2 flex justify-between items-center poule-header">
                            <div>
                                <div class="font-bold">⚔️ #<?php echo e($elimPoule->nummer); ?> <?php echo e($elimTitelFormatted); ?> <span class="font-normal text-orange-200">(<?php echo e(__('Eliminatie')); ?>)</span></div>
                                <div class="text-sm text-orange-200 poule-stats"><span class="poule-actief"><?php echo e($aantalActiefElim); ?></span> <?php echo e(__("judoka's")); ?> ~<span class="poule-wedstrijden"><?php echo e($elimPoule->berekenAantalWedstrijden($aantalActiefElim)); ?></span> <?php echo e(__('wedstrijden')); ?></div>
                            </div>
                            <div class="flex items-center gap-1">
                                <?php if($verwijderdeTekstElim->isNotEmpty()): ?>
                                <div class="relative" x-data="{ show: false }">
                                    <span @click="show = !show" @click.away="show = false" class="info-icon cursor-pointer text-base opacity-80 hover:opacity-100">ⓘ</span>
                                    <div x-show="show" x-transition class="absolute bottom-full right-0 mb-2 bg-gray-900 text-white text-xs rounded px-3 py-2 whitespace-pre-line z-[9999] min-w-[200px] shadow-xl pointer-events-none"><?php echo e($verwijderdeTekstElim->join("\n")); ?></div>
                                </div>
                                <?php endif; ?>
                                <button
                                    onclick="naarZaaloverzichtPoule(<?php echo e($elimPoule->id); ?>, this)"
                                    class="px-2 py-0.5 text-xs rounded transition-all <?php echo e($isDoorgestuurdElim ? 'bg-green-500 hover:bg-green-600' : 'bg-orange-500 hover:bg-orange-400'); ?>"
                                    title="<?php echo e($isDoorgestuurdElim ? __('Doorgestuurd') : __('Naar zaaloverzicht')); ?>"
                                ><?php echo e($isDoorgestuurdElim ? '✓' : '→'); ?></button>
                                <div class="relative" x-data="{ open: false }">
                                    <button @click="open = !open" class="bg-orange-500 hover:bg-orange-400 text-white text-xs px-2 py-0.5 rounded">⚙</button>
                                    <div x-show="open" @click.away="open = false" class="absolute right-0 mt-1 bg-white border rounded-lg shadow-lg z-10 min-w-[160px]">
                                        <button onclick="zetOmNaarPoules(<?php echo e($elimPoule->id); ?>, 'poules')" class="w-full text-left px-3 py-2 hover:bg-gray-100 text-sm text-gray-700"><?php echo e(__('Naar poules')); ?></button>
                                        <button onclick="zetOmNaarPoules(<?php echo e($elimPoule->id); ?>, 'poules_kruisfinale')" class="w-full text-left px-3 py-2 hover:bg-gray-100 text-sm text-gray-700 border-t">+ <?php echo e(__('kruisfinale')); ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="p-3 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-2 sortable-poule" data-poule-id="<?php echo e($elimPoule->id); ?>">
                            <?php $__currentLoopData = $elimPoule->judokas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $judoka): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php if(!$judoka->isActief($wegingGesloten)): ?> <?php continue; ?> <?php endif; ?>
                            <?php
                                $isGewogenElim = $judoka->gewicht_gewogen > 0;
                                $heeftGewichtElim = $isGewogenElim || $judoka->gewicht > 0;
                                $isAfwijkendElim = $heeftGewichtElim && !$judoka->isGewichtBinnenKlasse(null, $tolerantie, $elimPoule->gewichtsklasse);
                            ?>
                            <div class="px-2 py-1.5 rounded text-sm judoka-item cursor-move <?php echo e($isAfwijkendElim ? 'bg-red-50 border border-red-400' : 'bg-orange-50 border border-orange-200'); ?> group relative" data-judoka-id="<?php echo e($judoka->id); ?>" draggable="true" <?php if($isAfwijkendElim): ?> title="<?php echo e(__('Te zwaar voor')); ?> <?php echo e($elimPoule->gewichtsklasse); ?>!" <?php endif; ?>>
                                <div class="flex items-center gap-1">
                                    <?php if($isAfwijkendElim): ?><span class="text-red-600 text-xs">⚠</span>
                                    <?php elseif($isGewogenElim): ?><span class="text-green-500 text-xs">●</span><?php endif; ?>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium text-gray-800 truncate"><?php echo e($judoka->naam); ?></div>
                                        <div class="text-xs text-gray-500 truncate"><?php echo e($judoka->club?->naam ?? '-'); ?></div>
                                    </div>
                                    <button
                                        onclick="event.stopPropagation(); openZoekMatchWedstrijddag(<?php echo e($judoka->id); ?>, <?php echo e($elimPoule->id); ?>)"
                                        class="text-gray-400 hover:text-blue-600 p-0.5 rounded hover:bg-blue-50 transition-colors opacity-0 group-hover:opacity-100"
                                        title="<?php echo e(__('Zoek geschikte poule')); ?>"
                                    >🔍</button>
                                </div>
                            </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                    
                    <?php $__currentLoopData = $allePoules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $poule): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php echo $__env->make('pages.wedstrijddag.partials.poule-card', ['poule' => $poule, 'wegingGesloten' => $wegingGesloten, 'tolerantie' => $tolerantie], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
            <?php else: ?>
            
            
            <?php
                $categoriesPerLabel = $blok['categories']->groupBy('label');
            ?>
            <div class="space-y-6">
            <?php $__empty_2 = true; $__currentLoopData = $categoriesPerLabel; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $labelNaam => $categoriesInLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
            
            <div class="border-l-4 border-blue-500 bg-blue-50 px-4 py-2 mb-2">
                <h2 class="text-xl font-bold text-blue-800"><?php echo e($labelNaam); ?></h2>
            </div>
            <div class="divide-y">
            <?php $__currentLoopData = $categoriesInLabel; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $isEliminatie = $category['is_eliminatie'] ?? false;
                $jsLeeftijd = addslashes($category['leeftijdsklasse']);
                $jsGewicht = addslashes($category['gewichtsklasse']);
            ?>
            <div class="bg-white">
                
                <div class="flex justify-between items-center px-4 py-3 <?php echo e($isEliminatie ? 'bg-orange-100' : 'bg-gray-100'); ?> border-b">
                    <div class="flex items-center gap-3">
                        <h2 class="text-lg font-bold <?php echo e($isEliminatie ? 'text-orange-800' : ''); ?>">
                            <?php if($isEliminatie): ?>⚔️ <?php endif; ?><?php echo e($category['label']); ?> <?php echo e($category['gewichtsklasse']); ?>

                            <?php if($isEliminatie): ?><span class="text-sm font-normal text-orange-600 ml-1">(<?php echo e(__('Eliminatie')); ?>)</span><?php endif; ?>
                        </h2>
                        <?php if(!$isEliminatie): ?>
                        <button onclick="nieuwePoule('<?php echo e($jsLeeftijd); ?>', '<?php echo e($jsGewicht); ?>')" class="text-gray-500 hover:text-gray-700 hover:bg-gray-200 px-2 py-0.5 rounded text-sm font-medium">+ <?php echo e(__('Poule')); ?></button>
                        <?php endif; ?>
                        <button onclick="openNieuweJudokaModal('<?php echo e($jsLeeftijd); ?>', '<?php echo e($jsGewicht); ?>', <?php echo e(json_encode($category['poules']->where('type', '!=', 'kruisfinale')->map(fn($p) => ['id' => $p->id, 'nummer' => $p->nummer])->values())); ?>)" class="text-green-600 hover:text-green-800 hover:bg-green-100 px-2 py-0.5 rounded text-sm font-medium" title="<?php echo e(__('Nieuwe judoka aanmelden (laatkomer)')); ?>">+ <?php echo e(__('Laatkomer')); ?></button>
                    </div>
                    <?php if($isEliminatie): ?>
                    <?php $elimPoule = $category['poules']->first(); ?>
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="bg-gray-500 hover:bg-gray-600 text-white text-sm px-3 py-1.5 rounded"><?php echo e(__('Omzetten naar poules')); ?> ▾</button>
                        <div x-show="open" @click.away="open = false" class="absolute right-0 mt-1 bg-white border rounded-lg shadow-lg z-10 min-w-[200px]">
                            <button onclick="zetOmNaarPoules(<?php echo e($elimPoule->id); ?>, 'poules')" class="w-full text-left px-4 py-2 hover:bg-gray-100 text-sm"><?php echo e(__('Alleen poules')); ?></button>
                            <button onclick="zetOmNaarPoules(<?php echo e($elimPoule->id); ?>, 'poules_kruisfinale')" class="w-full text-left px-4 py-2 hover:bg-gray-100 text-sm border-t"><?php echo e(__('Poules + kruisfinale')); ?></button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="p-4">
                    <?php if($isEliminatie): ?>
                    
                    <?php
                        // Collect removed judokas for info tooltip
                        // Gebruik poule's gewichtsklasse, niet judoka's eigen klasse
                        $verwijderdeElim = $elimPoule->judokas->filter(function($j) use ($tolerantie, $wegingGesloten, $elimPoule) {
                            $isAfwijkend = $j->gewicht_gewogen > 0 && !$j->isGewichtBinnenKlasse(null, $tolerantie, $elimPoule->gewichtsklasse);
                            return !$j->isActief($wegingGesloten) || $isAfwijkend;
                        });

                        // Calculate active count
                        $aantalActiefElim = $elimPoule->judokas->count() - $verwijderdeElim->count();

                        // Format removed for tooltip
                        $verwijderdeTekstElim = $verwijderdeElim->map(function($j) use ($tolerantie, $wegingGesloten) {
                            if (!$j->isActief($wegingGesloten)) return $j->naam . ' (' . __('afwezig') . ')';
                            return $j->naam . ' (' . __('afwijkend gewicht') . ')';
                        });
                    ?>
                    <div class="border-2 border-orange-300 rounded-lg overflow-hidden bg-white poule-card" data-poule-id="<?php echo e($elimPoule->id); ?>" data-poule-nummer="<?php echo e($elimPoule->nummer); ?>" data-type="eliminatie">
                        <div class="bg-orange-500 text-white px-4 py-2 flex justify-between items-center poule-header">
                            <span class="font-bold poule-stats"><span class="poule-actief"><?php echo e($aantalActiefElim); ?></span> <?php echo e(__("judoka's")); ?></span>
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-orange-200">~<span class="poule-wedstrijden"><?php echo e($elimPoule->berekenAantalWedstrijden($aantalActiefElim)); ?></span> <?php echo e(__('wedstrijden')); ?></span>
                                <?php if($verwijderdeTekstElim->isNotEmpty()): ?>
                                <div class="relative" x-data="{ show: false }">
                                    <span @click="show = !show" @click.away="show = false" class="info-icon cursor-pointer text-base opacity-80 hover:opacity-100">ⓘ</span>
                                    <div x-show="show" x-transition class="absolute bottom-full right-0 mb-2 bg-gray-900 text-white text-xs rounded px-3 py-2 whitespace-pre-line z-[9999] min-w-[200px] shadow-xl pointer-events-none"><?php echo e($verwijderdeTekstElim->join("\n")); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="p-3 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-2 sortable-poule" data-poule-id="<?php echo e($elimPoule->id); ?>">
                            <?php $__currentLoopData = $elimPoule->judokas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $judoka): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php if(!$judoka->isActief($wegingGesloten)): ?>
                                <?php continue; ?>
                            <?php endif; ?>
                            <?php $isGewogen = $judoka->gewicht_gewogen > 0; ?>
                            <div class="px-2 py-1.5 rounded text-sm bg-orange-50 border border-orange-200 judoka-item cursor-move group" data-judoka-id="<?php echo e($judoka->id); ?>" draggable="true">
                                <div class="flex items-center gap-1">
                                    <?php if($isGewogen): ?>
                                        <span class="text-green-500 text-xs">●</span>
                                    <?php endif; ?>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium text-gray-800 truncate" title="<?php echo e($judoka->naam); ?>"><?php echo e($judoka->naam); ?></div>
                                        <div class="text-xs text-gray-500 truncate"><?php echo e($judoka->club?->naam ?? '-'); ?></div>
                                    </div>
                                    <button
                                        onclick="event.stopPropagation(); openZoekMatchWedstrijddag(<?php echo e($judoka->id); ?>, <?php echo e($elimPoule->id); ?>)"
                                        class="text-gray-400 hover:text-blue-600 p-0.5 rounded hover:bg-blue-50 transition-colors opacity-0 group-hover:opacity-100"
                                        title="<?php echo e(__('Zoek geschikte poule')); ?>"
                                    >🔍</button>
                                </div>
                            </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    </div>
                    <?php else: ?>
                    
                    <?php
                        // Groepeer poules op gewicht (uit titel of gewichtsklasse)
                        // Groepeer poules per gewichtsklasse
                        $poulesPerGewicht = $category['poules']->groupBy(function($poule) {
                            if ($poule->isDynamisch()) {
                                // Haal gewichtsbereik uit live berekende range
                                $range = $poule->getGewichtsRange();
                                if ($range) {
                                    return round($range['min_kg'], 1) . '-' . round($range['max_kg'], 1) . 'kg';
                                }
                            }
                            return $poule->gewichtsklasse ?: 'default';
                        })->sortKeys();
                    ?>
                    <div class="flex gap-4">
                        
                        <div class="flex-1">
                            <?php $__currentLoopData = $poulesPerGewicht; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gewichtKey => $poulesInGewicht): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
                            <?php $__currentLoopData = $poulesInGewicht; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $poule): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php if($poule->type === 'kruisfinale'): ?>
                            
                            <?php
                                $aantalVoorrondes = $category['poules']->filter(fn($p) => $p->type === 'voorronde')->count();
                            ?>
                            <div
                                id="poule-<?php echo e($poule->id); ?>"
                                class="border-2 border-purple-400 rounded-lg overflow-hidden bg-white kruisfinale-card"
                                data-poule-id="<?php echo e($poule->id); ?>"
                                data-aantal-voorrondes="<?php echo e($aantalVoorrondes); ?>"
                            >
                                <div class="bg-purple-600 text-white px-3 py-2 flex justify-between items-center">
                                    <div>
                                        <div class="font-bold text-sm">🏆 <?php echo e(__('Kruisfinale')); ?></div>
                                        <div class="text-xs text-purple-200 kruisfinale-stats"><?php echo e($poule->aantal_judokas); ?> <?php echo e(__("judoka's")); ?> | <?php echo e($poule->aantal_wedstrijden); ?> <?php echo e(__('wedstrijden')); ?></div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <select
                                            onchange="updateKruisfinale(<?php echo e($poule->id); ?>, this.value)"
                                            class="text-xs bg-purple-500 text-white border border-purple-400 rounded px-1 py-0.5"
                                            title="<?php echo e(__('Aantal plaatsen per poule')); ?>"
                                        >
                                            <?php for($i = 1; $i <= 3; $i++): ?>
                                            <option value="<?php echo e($i); ?>" <?php echo e(($poule->kruisfinale_plaatsen ?? 2) == $i ? 'selected' : ''); ?>>Top <?php echo e($i); ?></option>
                                            <?php endfor; ?>
                                        </select>
                                        <button
                                            onclick="verwijderPoule(<?php echo e($poule->id); ?>, '<?php echo e($poule->nummer); ?>')"
                                            class="w-5 h-5 flex items-center justify-center bg-purple-800 hover:bg-purple-900 text-white rounded-full text-xs font-bold"
                                            title="<?php echo e(__('Verwijder kruisfinale')); ?>"
                                        >×</button>
                                    </div>
                                </div>
                                <div class="p-3 text-sm text-gray-600 kruisfinale-info">
                                    <?php echo e($aantalVoorrondes); ?> <?php echo e(__('poules')); ?> × top <?php echo e($poule->kruisfinale_plaatsen ?? 2); ?> = <?php echo e($poule->aantal_judokas); ?> <?php echo e(__("judoka's door")); ?>

                                </div>
                            </div>
                            <?php continue; ?>
                            <?php endif; ?>
                            <?php if($poule->type === 'eliminatie' && $poule->judokas->count() === 0 && $poule->aantal_judokas > 0): ?>
                            
                            <div
                                id="poule-<?php echo e($poule->id); ?>"
                                class="border-2 border-orange-400 rounded-lg overflow-hidden min-w-[200px] bg-white"
                                data-poule-id="<?php echo e($poule->id); ?>"
                                data-poule-leeftijdsklasse="<?php echo e($poule->leeftijdsklasse); ?>"
                                data-poule-gewichtsklasse="<?php echo e($poule->gewichtsklasse); ?>"
                            >
                                <div class="bg-orange-600 text-white px-3 py-2 flex justify-between items-center">
                                    <div>
                                        <div class="font-bold text-sm">⚔️ <?php echo e(__('Eliminatie Finale')); ?></div>
                                        <div class="text-xs text-orange-200"><?php echo e($poule->aantal_judokas); ?> <?php echo e(__("judoka's")); ?> | <?php echo e($poule->aantal_wedstrijden); ?> <?php echo e(__('wedstrijden')); ?></div>
                                    </div>
                                    <button
                                        onclick="verwijderPoule(<?php echo e($poule->id); ?>, '<?php echo e($poule->nummer); ?>')"
                                        class="w-5 h-5 flex items-center justify-center bg-orange-800 hover:bg-orange-900 text-white rounded-full text-xs font-bold"
                                        title="<?php echo e(__('Verwijder eliminatie finale')); ?>"
                                    >×</button>
                                </div>
                                <div class="p-3 text-sm text-gray-600">
                                    <?php echo e(__('Winnaars uit voorronde poules')); ?>

                                </div>
                            </div>
                            <?php continue; ?>
                            <?php endif; ?>
                            <?php
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
                                    $verwijderdeTekst->push($j->naam . ' (' . __('afwezig') . ')');
                                }
                                foreach ($overpoulers as $j) {
                                    $verwijderdeTekst->push($j->naam . ' (' . __('afwijkend gewicht') . ' → ' . $j->gewichtsklasse . ')');
                                }
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
                                    // Gebruik centrale getDisplayTitel() methode
                                    $pouleTitel = $poule->getDisplayTitel();
                                ?>
                                <div class="<?php echo e($aantalActief === 0 ? 'bg-gray-500' : ($isProblematisch ? 'bg-red-600' : ($heeftGewichtsprobleem ? 'bg-orange-600' : 'bg-blue-700'))); ?> text-white px-3 py-2 poule-header flex justify-between items-start rounded-t-lg">
                                    <div class="pointer-events-none flex-1">
                                        <div class="font-bold text-sm">#<?php echo e($poule->nummer); ?> <?php echo e($pouleTitel); ?></div>
                                        <div class="text-xs <?php echo e($aantalActief === 0 ? 'text-gray-300' : ($isProblematisch ? 'text-red-200' : ($heeftGewichtsprobleem ? 'text-orange-200' : 'text-blue-200'))); ?> poule-stats"><span class="poule-actief"><?php echo e($aantalActief); ?></span> <?php echo e(__("judoka's")); ?> <span class="poule-wedstrijden"><?php echo e($aantalWedstrijden); ?></span> <?php echo e(__('wedstrijden')); ?></div>
                                    </div>
                                    <div class="flex items-center gap-1 flex-shrink-0">
                                        <?php if($verwijderdeTekst->isNotEmpty()): ?>
                                        <div class="relative" x-data="{ show: false }">
                                            <span @click="show = !show" @click.away="show = false" class="info-icon cursor-pointer text-base opacity-80 hover:opacity-100">ⓘ</span>
                                            <div x-show="show" x-transition class="absolute bottom-full right-0 mb-2 bg-gray-900 text-white text-xs rounded px-3 py-2 whitespace-pre-line z-[9999] min-w-[200px] shadow-xl pointer-events-none"><?php echo e($verwijderdeTekst->join("\n")); ?></div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if($aantalActief > 0): ?>
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

                                        // Check of judoka past in DEZE POULE's gewichtsklasse (niet judoka's eigen klasse!)
                                        // Gebruik poule->gewichtsklasse voor de check, want judoka kan verplaatst zijn
                                        $isAfwijkendGewicht = $isGewogen && !$judoka->isGewichtBinnenKlasse(null, $tolerantie, $poule->gewichtsklasse);
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
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </div>
                            </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>

                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
            <div class="p-4 text-gray-500 text-center"><?php echo e(__('Geen categorieën in dit blok')); ?></div>
            <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
        <?php echo e(__('Geen blokken gevonden. Maak eerst blokken aan via de Blokken pagina.')); ?>

    </div>
    <?php endif; ?>
    </div>
</div>

<!-- Modal nieuwe poule -->
<div id="nieuwe-poule-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <h2 class="text-xl font-bold text-gray-800 mb-4"><?php echo e(__('Nieuwe poule aanmaken')); ?></h2>
        <form id="nieuwe-poule-form">
            <input type="hidden" id="nieuwe-poule-blok" value="">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo e(__('Categorie')); ?></label>
                <select id="nieuwe-poule-categorie" class="w-full border rounded px-3 py-2" required>
                    <option value=""><?php echo e(__('Selecteer...')); ?></option>
                    <?php $__currentLoopData = $toernooi->getAlleGewichtsklassen(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $klasse): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                        $catLabel = !empty($klasse['label']) ? $klasse['label'] : $key;
                        $catDisplay = $catLabel;
                        if (!empty($klasse['max_leeftijd']) && $klasse['max_leeftijd'] < 99) {
                            $catDisplay .= ' (t/m ' . $klasse['max_leeftijd'] . ' jr)';
                        }
                    ?>
                    <option value="<?php echo e($catLabel); ?>"><?php echo e($catDisplay); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeNieuwePouleModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                    <?php echo e(__('Annuleren')); ?>

                </button>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    <?php echo e(__('Aanmaken')); ?>

                </button>
            </div>
        </form>
    </div>
</div>


<div id="nieuwe-judoka-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <h2 class="text-xl font-bold text-gray-800 mb-4">🆕 <?php echo e(__('Laatkomer aanmelden')); ?></h2>
        <form id="nieuwe-judoka-form">
            <input type="hidden" id="nj-leeftijdsklasse" value="">
            <input type="hidden" id="nj-gewichtsklasse" value="">
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo e(__('Naam')); ?> *</label>
                    <input type="text" id="nj-naam" class="w-full border rounded px-3 py-2" required placeholder="<?php echo e(__('Achternaam, Voornaam')); ?>">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo e(__('Geboortejaar')); ?></label>
                        <input type="number" id="nj-geboortejaar" class="w-full border rounded px-3 py-2" min="1990" max="<?php echo e(date('Y')); ?>" placeholder="<?php echo e(date('Y') - 10); ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo e(__('Band')); ?></label>
                        <select id="nj-band" class="w-full border rounded px-3 py-2">
                            <option value="">-</option>
                            <option value="wit"><?php echo e(__('Wit')); ?></option>
                            <option value="geel"><?php echo e(__('Geel')); ?></option>
                            <option value="oranje"><?php echo e(__('Oranje')); ?></option>
                            <option value="groen"><?php echo e(__('Groen')); ?></option>
                            <option value="blauw"><?php echo e(__('Blauw')); ?></option>
                            <option value="bruin"><?php echo e(__('Bruin')); ?></option>
                            <option value="zwart"><?php echo e(__('Zwart')); ?></option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo e(__('Gewicht (kg)')); ?></label>
                        <input type="number" id="nj-gewicht" class="w-full border rounded px-3 py-2" step="0.1" min="10" max="200" placeholder="32.5">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo e(__('Judoschool')); ?></label>
                        <select id="nj-club" class="w-full border rounded px-3 py-2">
                            <option value="">-</option>
                            <?php $__currentLoopData = $clubs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $club): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($club->id); ?>"><?php echo e($club->naam); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo e(__('Poule')); ?> *</label>
                    <select id="nj-poule" class="w-full border rounded px-3 py-2" required>
                        
                    </select>
                </div>
            </div>
            <div class="flex justify-end space-x-3 mt-4">
                <button type="button" onclick="closeNieuweJudokaModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                    <?php echo e(__('Annuleren')); ?>

                </button>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    <?php echo e(__('Toevoegen')); ?>

                </button>
            </div>
        </form>
    </div>
</div>

<!-- SortableJS for drag and drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
const verifieerUrl = '<?php echo e(route('toernooi.poule.verifieer', $toernooi->routeParams())); ?>';
const verwijderPouleUrl = '<?php echo e(route('toernooi.poule.destroy', $toernooi->routeParamsWith(['poule' => ':id']))); ?>';
const zetOmNaarPoulesUrl = '<?php echo e(route('toernooi.wedstrijddag.zetOmNaarPoules', $toernooi->routeParams())); ?>';
const updateKruisfinaleUrl = '<?php echo e(route('toernooi.poule.update-kruisfinale', $toernooi->routeParamsWith(['poule' => ':id']))); ?>';
const meldAfUrl = '<?php echo e(route("toernooi.wedstrijddag.meld-judoka-af", $toernooi->routeParams())); ?>';

// i18n constants
const __foutBijAfmelden = <?php echo json_encode(__('Fout bij afmelden'), 15, 512) ?>;
const __foutBijAanpassenKruisfinale = <?php echo json_encode(__('Fout bij aanpassen kruisfinale'), 15, 512) ?>;
const __foutBijOmzetten = <?php echo json_encode(__('Fout bij omzetten'), 15, 512) ?>;
const __foutBijVerwijderen = <?php echo json_encode(__('Fout bij verwijderen'), 15, 512) ?>;
const __verwijderUitPouleBevestiging = <?php echo json_encode(__('Weet je zeker dat je deze judoka uit de poule wilt verwijderen?'), 15, 512) ?>;
const __selecteerEenCategorie = <?php echo json_encode(__('Selecteer een categorie'), 15, 512) ?>;
const __naamIsVerplicht = <?php echo json_encode(__('Naam is verplicht'), 15, 512) ?>;
const __selecteerEenPoule = <?php echo json_encode(__('Selecteer een poule'), 15, 512) ?>;
const __foutBijVerplaatsen = <?php echo json_encode(__('Fout bij verplaatsen'), 15, 512) ?>;
const __foutBijVerificatie = <?php echo json_encode(__('Fout bij verificatie'), 15, 512) ?>;
const __bezigMetVerificatie = <?php echo json_encode(__('Bezig met verificatie...'), 15, 512) ?>;
const __verificatie = <?php echo json_encode(__('Verificatie'), 15, 512) ?>;
const __probleemenGevonden = <?php echo json_encode(__('probleem(en) gevonden'), 15, 512) ?>;
const __verificatieGeslaagd = <?php echo json_encode(__('Verificatie geslaagd!'), 15, 512) ?>;
const __totaal = <?php echo json_encode(__('Totaal'), 15, 512) ?>;
const __poules = <?php echo json_encode(__('poules'), 15, 512) ?>;
const __wedstrijden = <?php echo json_encode(__('wedstrijden'), 15, 512) ?>;
const __poulesHerberekend = <?php echo json_encode(__('poules herberekend'), 15, 512) ?>;
const __geenGeschiktePoules = <?php echo json_encode(__('Geen geschikte poules gevonden'), 15, 512) ?>;
const __foutBijLaden = <?php echo json_encode(__('Fout bij laden'), 15, 512) ?>;
const __afmelden = <?php echo json_encode(__('Afmelden'), 15, 512) ?>;
const __afmeldenBevestiging = <?php echo json_encode(__('afmelden? Deze judoka kan dan niet meer deelnemen.'), 15, 512) ?>;
const __huidigBlok = <?php echo json_encode(__('Huidig blok'), 15, 512) ?>;
const __eerderBlokWegingGesloten = <?php echo json_encode(__('Eerder blok (weging gesloten)'), 15, 512) ?>;
const __andereCategorie = <?php echo json_encode(__('andere categorie'), 15, 512) ?>;
const __nu = <?php echo json_encode(__('Nu'), 15, 512) ?>;
const __na = <?php echo json_encode(__('Na'), 15, 512) ?>;
const __judokas = <?php echo json_encode(__("judoka's"), 15, 512) ?>;
const __doorgestuurd = <?php echo json_encode(__('Doorgestuurd'), 15, 512) ?>;
const __foutBijDoorsturen = <?php echo json_encode(__('Fout bij doorsturen'), 15, 512) ?>;
const __netwerkFout = <?php echo json_encode(__('Netwerk fout'), 15, 512) ?>;
const __poule = <?php echo json_encode(__('Poule'), 15, 512) ?>;
const __bezig = <?php echo json_encode(__('Bezig...'), 15, 512) ?>;
const __toevoegen = <?php echo json_encode(__('Toevoegen'), 15, 512) ?>;
const __foutBijAanmakenPoule = <?php echo json_encode(__('Fout bij aanmaken poule'), 15, 512) ?>;
const __problematischePoules = <?php echo json_encode(__('Problematische poules'), 15, 512) ?>;
const __dezePoulesMinder3 = <?php echo json_encode(__('Deze poules hebben minder dan 3 actieve judoka\'s:'), 15, 512) ?>;
const __eliminatieOmzetten = <?php echo json_encode(__('Eliminatie omzetten naar'), 15, 512) ?>;
const __poulesKruisfinale = <?php echo json_encode(__('poules + kruisfinale'), 15, 512) ?>;
const __alleenPoules = <?php echo json_encode(__('alleen poules'), 15, 512) ?>;
const __pouleVerwijderen = <?php echo json_encode(__('Poule verwijderen?'), 15, 512) ?>;
const __fout = <?php echo json_encode(__('Fout'), 15, 512) ?>;
const __onbekendeFout = <?php echo json_encode(__('Onbekende fout'), 15, 512) ?>;
const __verkeerdeGewichtsklasse = <?php echo json_encode(__('Verkeerde gewichtsklasse'), 15, 512) ?>;
const __onbekend = <?php echo json_encode(__('Onbekend'), 15, 512) ?>;
const __judokasDoor = <?php echo json_encode(__("judoka's door"), 15, 512) ?>;


// Meld judoka af (kan niet deelnemen) - moet vroeg gedefinieerd zijn voor poule-card buttons
async function meldJudokaAf(judokaId, naam) {
    if (!confirm(`${naam} ${__afmeldenBevestiging}`)) return;

    try {
        const response = await fetch(meldAfUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ judoka_id: judokaId })
        });

        const data = await response.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert(__fout + ': ' + (data.message || __onbekendeFout));
        }
    } catch (error) {
        console.error('Error:', error);
        alert(__foutBijAfmelden);
    }
}

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
                    statsDiv.textContent = `${data.aantal_judokas} ${__judokas} | ${data.aantal_wedstrijden} ${__wedstrijden}`;
                }
                const infoDiv = card.querySelector('.kruisfinale-info');
                const aantalVoorrondes = card.dataset.aantalVoorrondes || '?';
                if (infoDiv) {
                    infoDiv.textContent = `${aantalVoorrondes} ${__poules} × top ${plaatsen} = ${data.aantal_judokas} ${__judokasDoor}`;
                }
            }
        } else {
            alert(data.message || __foutBijAanpassenKruisfinale);
        }
    } catch (error) {
        console.error('Error:', error);
        alert(__foutBijAanpassenKruisfinale);
    }
}

async function zetOmNaarPoules(pouleId, systeem) {
    if (!confirm(`${__eliminatieOmzetten} ${systeem === 'poules_kruisfinale' ? __poulesKruisfinale : __alleenPoules}?`)) return;

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
            alert(data.message || __foutBijOmzetten);
        }
    } catch (error) {
        console.error('Error:', error);
        alert(__foutBijOmzetten);
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
    if (!confirm(`${__poule} #${pouleNummer} ${__pouleVerwijderen}`)) return;

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
            alert(data.message || __foutBijVerwijderen);
        }
    } catch (error) {
        console.error('Error:', error);
        alert(__foutBijVerwijderen);
    }
}

async function verifieerPoules() {
    const resultaatDiv = document.getElementById('verificatie-resultaat');
    resultaatDiv.className = 'bg-blue-50 border border-blue-300 rounded-lg p-4';
    resultaatDiv.innerHTML = `<p class="text-blue-700">${__bezigMetVerificatie}</p>`;

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
                    <h3 class="font-bold text-yellow-800 mb-2">${__verificatie}: ${data.problemen.length} ${__probleemenGevonden}</h3>
                    <ul class="list-disc list-inside text-yellow-700 text-sm mb-3">
                        ${data.problemen.map(p => `<li>${p.message}</li>`).join('')}
                    </ul>
                    <p class="text-yellow-600 text-sm">${__totaal}: ${data.totaal_poules} ${__poules}, ${data.totaal_wedstrijden} ${__wedstrijden}${data.herberekend > 0 ? `, ${data.herberekend} ${__poulesHerberekend}` : ''}</p>
                </div>`;
            } else {
                html = `<div class="bg-green-50 border border-green-300 rounded-lg p-4">
                    <h3 class="font-bold text-green-800 mb-2">${__verificatieGeslaagd}</h3>
                    <p class="text-green-700 text-sm">${__totaal}: ${data.totaal_poules} ${__poules}, ${data.totaal_wedstrijden} ${__wedstrijden}${data.herberekend > 0 ? `, ${data.herberekend} ${__poulesHerberekend}` : ''}</p>
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
        resultaatDiv.innerHTML = '<p class="text-red-700">' + __foutBijVerificatie + '</p>';
    }
}

function wedstrijddagPoules() {
    return {}
}

async function verwijderUitPoule(judokaId, pouleId) {
    if (!confirm(__verwijderUitPouleBevestiging)) return;

    try {
        const response = await fetch('<?php echo e(route("toernooi.wedstrijddag.verwijder-uit-poule", $toernooi->routeParams())); ?>', {
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
        alert(__foutBijVerwijderen);
    }
}

async function naarZaaloverzichtPoule(pouleId, btn) {
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳';

    try {
        const response = await fetch('<?php echo e(route("toernooi.wedstrijddag.naar-zaaloverzicht-poule", $toernooi->routeParams())); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ poule_id: pouleId }),
        });

        if (response.ok) {
            btn.classList.remove('bg-blue-500', 'hover:bg-blue-600', 'bg-orange-500', 'hover:bg-orange-400');
            btn.classList.add('bg-green-500', 'hover:bg-green-600');
            btn.innerHTML = '✓';
            btn.title = __doorgestuurd;
        } else {
            const data = await response.json().catch(() => ({}));
            alert(__foutBijDoorsturen + ': ' + (data.message || response.status));
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    } catch (error) {
        alert(__netwerkFout + ': ' + error.message);
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
        alert(__selecteerEenCategorie);
        return;
    }

    closeNieuwePouleModal();
    await nieuwePoule(categorie, '', blokNummer);
});

async function nieuwePoule(leeftijdsklasse, gewichtsklasse, blokNummer) {
    try {
        const response = await fetch('<?php echo e(route("toernooi.wedstrijddag.nieuwe-poule", $toernooi->routeParams())); ?>', {
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
            alert(__foutBijAanmakenPoule + ': ' + (data.message || response.status));
        }
    } catch (error) {
        console.error('Error creating poule:', error);
        alert(__foutBijAanmakenPoule + ': ' + error.message);
    }
}

// Nieuwe judoka modal functies
function openNieuweJudokaModal(leeftijdsklasse, gewichtsklasse, poules) {
    document.getElementById('nj-leeftijdsklasse').value = leeftijdsklasse;
    document.getElementById('nj-gewichtsklasse').value = gewichtsklasse;
    document.getElementById('nj-naam').value = '';
    document.getElementById('nj-geboortejaar').value = '';
    document.getElementById('nj-band').value = '';
    document.getElementById('nj-gewicht').value = '';
    document.getElementById('nj-club').value = '';

    const pouleSelect = document.getElementById('nj-poule');
    pouleSelect.innerHTML = '';
    poules.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = __poule + ' ' + p.nummer;
        pouleSelect.appendChild(opt);
    });

    document.getElementById('nieuwe-judoka-modal').classList.remove('hidden');
    document.getElementById('nj-naam').focus();
}

function closeNieuweJudokaModal() {
    document.getElementById('nieuwe-judoka-modal').classList.add('hidden');
}

document.getElementById('nieuwe-judoka-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const naam = document.getElementById('nj-naam').value.trim();
    if (!naam) { alert(__naamIsVerplicht); return; }

    const pouleId = document.getElementById('nj-poule').value;
    if (!pouleId) { alert(__selecteerEenPoule); return; }

    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = __bezig;

    try {
        const response = await fetch('<?php echo e(route("toernooi.wedstrijddag.nieuwe-judoka", $toernooi->routeParams())); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                naam: naam,
                band: document.getElementById('nj-band').value || null,
                gewicht: document.getElementById('nj-gewicht').value || null,
                geboortejaar: document.getElementById('nj-geboortejaar').value || null,
                club_id: document.getElementById('nj-club').value || null,
                poule_id: pouleId,
            }),
        });

        const data = await response.json().catch(() => ({}));

        if (response.ok && data.success) {
            closeNieuweJudokaModal();
            window.location.reload();
        } else {
            alert(__fout + ': ' + (data.message || __onbekendeFout));
            btn.disabled = false;
            btn.textContent = __toevoegen;
        }
    } catch (error) {
        alert(__netwerkFout + ': ' + error.message);
        btn.disabled = false;
        btn.textContent = __toevoegen;
    }
});

const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const verplaatsUrl = '<?php echo e(route("toernooi.wedstrijddag.verplaats-judoka", $toernooi->routeParams())); ?>';

document.addEventListener('DOMContentLoaded', function() {
    // Helper: bereken wedstrijden voor round-robin (n*(n-1)/2)
    function berekenWedstrijden(aantalJudokas) {
        if (aantalJudokas < 2) return 0;
        return (aantalJudokas * (aantalJudokas - 1)) / 2;
    }

    // Helper: bereken wedstrijden voor eliminatie (2N-5 bij 2 brons)
    function berekenEliminatieWedstrijden(aantalJudokas) {
        if (aantalJudokas < 2) return 0;
        return Math.max(0, (2 * aantalJudokas) - 5);
    }

    // Helper: update poule counts vanuit server response (exacte waarden)
    function updatePouleCountsFromServer(pouleCard, pouleData) {
        if (!pouleCard) return;
        const actiefSpan = pouleCard.querySelector('.poule-actief');
        const wedstrijdenSpan = pouleCard.querySelector('.poule-wedstrijden');
        if (actiefSpan && pouleData.aantal_judokas !== undefined) {
            actiefSpan.textContent = pouleData.aantal_judokas;
            pouleCard.dataset.actief = pouleData.aantal_judokas;
        }
        if (wedstrijdenSpan && pouleData.aantal_wedstrijden !== undefined) {
            wedstrijdenSpan.textContent = pouleData.aantal_wedstrijden;
        }
    }

    // Helper: update poule titelbalk direct vanuit DOM
    function updatePouleFromDOM(pouleId) {
        const pouleCard = document.querySelector(`.poule-card[data-poule-id="${pouleId}"]`);
        if (!pouleCard) return;

        const container = pouleCard.querySelector('.sortable-poule');
        if (!container) return;

        // Tel judoka's in de DOM
        const aantalJudokas = container.querySelectorAll('.judoka-item').length;
        const isEliminatie = pouleCard.dataset.type === 'eliminatie';
        const aantalWedstrijden = isEliminatie ? berekenEliminatieWedstrijden(aantalJudokas) : berekenWedstrijden(aantalJudokas);

        // Update data attribute
        pouleCard.dataset.actief = aantalJudokas;

        // Update tekst in header
        const actiefSpan = pouleCard.querySelector('.poule-actief');
        const wedstrijdenSpan = pouleCard.querySelector('.poule-wedstrijden');
        if (actiefSpan) actiefSpan.textContent = aantalJudokas;
        if (wedstrijdenSpan) wedstrijdenSpan.textContent = aantalWedstrijden;

        // Update styling (eliminatie poules behouden oranje header)
        const isLeeg = aantalJudokas === 0;
        const isProblematisch = isEliminatie ? (aantalJudokas > 0 && aantalJudokas < 8) : (aantalJudokas > 0 && aantalJudokas < 3);
        const header = pouleCard.querySelector('.poule-header');
        const statsDiv = pouleCard.querySelector('.poule-stats');

        if (!isEliminatie) {
            pouleCard.classList.remove('border-2', 'border-red-300', 'border-orange-400', 'opacity-50');
            if (header) header.classList.remove('bg-blue-700', 'bg-red-600', 'bg-orange-600', 'bg-gray-500');
            if (statsDiv) statsDiv.classList.remove('text-blue-200', 'text-red-200', 'text-orange-200', 'text-gray-300');

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
        }

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

    // Initialize sortable on all poule containers (voor drag TUSSEN poules)
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

                // Direct DOM update
                if (vanPouleId) updatePouleFromDOM(vanPouleId);
                if (naarPouleId) updatePouleFromDOM(naarPouleId);

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
                        alert(__fout + ': ' + (data.error || data.message || __onbekendeFout));
                        window.location.reload();
                    } else {
                        // Update gewichtsrange en problematische poules voor beide poules
                        if (data.van_poule) {
                            const vanPouleCard = document.querySelector(`.poule-card[data-poule-id="${data.van_poule.id}"]`);
                            if (vanPouleCard) {
                                updatePouleTitel(vanPouleCard, data.van_poule);
                                updatePouleCountsFromServer(vanPouleCard, data.van_poule);
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
                                updatePouleCountsFromServer(naarPouleCard, data.naar_poule);
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
                    alert(__foutBijVerplaatsen + ': ' + error.message);
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

        // Update status icon based on problem state
        const iconContainer = judokaEl.querySelector('.flex.items-center.gap-1');
        if (iconContainer) {
            let icon = iconContainer.querySelector('.flex-shrink-0');
            if (heeftProbleem) {
                if (!icon) {
                    icon = document.createElement('span');
                    iconContainer.insertBefore(icon, iconContainer.querySelector('.min-w-0'));
                }
                icon.className = 'text-orange-500 text-xs flex-shrink-0';
                icon.textContent = '⚠';
                icon.title = __verkeerdeGewichtsklasse;
            } else if (icon && icon.textContent === '⚠') {
                // Only revert icons we changed (⚠), leave original icons (●, 🚨) alone
                icon.remove();
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

    // Update poule titel vanuit server response (getDisplayTitel())
    function updatePouleTitel(pouleCard, pouleData) {
        const titelEl = pouleCard.querySelector('.poule-header .font-bold');
        if (!titelEl) return;

        // Server stuurt correcte titel via getDisplayTitel():
        // - Variabele categorie: "Label / 25.5-30.2kg" (range uit judoka's)
        // - Vaste categorie: "Label / -30kg" (gewichtsklasse naam)
        titelEl.textContent = `#${pouleCard.dataset.pouleNummer} ${pouleData.titel || ''}`;
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
                        <h3 class="font-bold text-red-800 mb-2">${__problematischePoules} (<span id="problematische-count">1</span>)</h3>
                        <p class="text-red-700 text-sm mb-3">${__dezePoulesMinder3}</p>
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
                            <p class="text-purple-700 text-sm mb-2"><?php echo e(__("Te veel judoka's")); ?> (&ge; 6) - <?php echo e(__('splitsen')); ?>:</p>
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
const zoekMatchUrl = '<?php echo e(route("toernooi.poule.zoek-match", $toernooi->routeParamsWith(["judoka" => "__JUDOKA_ID__"]))); ?>';


// Huidige judoka in modal (voor afmelden)
let zoekMatchJudokaId = null;
let zoekMatchFromPouleId = null;

async function openZoekMatchWedstrijddag(judokaId, fromPouleId) {
    zoekMatchJudokaId = judokaId;
    zoekMatchFromPouleId = fromPouleId;
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
            content.innerHTML = '<p class="text-gray-500 text-center py-4">' + __geenGeschiktePoules + '</p>';
            return;
        }

        // Groepeer per blok
        const blokGroepen = {};
        data.matches.forEach(match => {
            const blokKey = match.blok_nummer || 0;
            if (!blokGroepen[blokKey]) {
                blokGroepen[blokKey] = {
                    naam: match.blok_naam || __onbekend,
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

        let html = `<div class="mb-3 pb-2 border-b flex justify-between items-center">
            <div>
                <span class="font-bold">${data.judoka.naam}</span>
                <span class="text-gray-500">(${data.judoka.leeftijd}j, ${data.judoka.gewicht}kg)</span>
            </div>
            <button onclick="meldJudokaAf(${judokaId}, '${data.judoka.naam.replace(/'/g, "\\'")}')"
                class="bg-red-600 hover:bg-red-700 text-white text-xs px-2 py-1 rounded">
                ✕ ${__afmelden}
            </button>
        </div>`;

        const blokColors = {
            'same': 'bg-green-100 text-green-800',
            'earlier_closed': 'bg-yellow-100 text-yellow-800'
        };
        const blokLabels = {
            'same': __huidigBlok,
            'earlier_closed': __eerderBlokWegingGesloten
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
                            ${match.categorie_overschrijding ? '<span class="text-orange-500 text-xs ml-1">(' + __andereCategorie + ')</span>' : ''}
                            ${overschrijding}
                        </div>
                    </div>
                    <div class="text-sm text-gray-600 mt-1">
                        <div>${__nu}: ${match.huidige_judokas} ${__judokas} | ${match.huidige_leeftijd} | ${match.huidige_gewicht}</div>
                        <div>${__na}: ${match.nieuwe_judokas} ${__judokas} | ${match.nieuwe_leeftijd} | ${match.nieuwe_gewicht}</div>
                    </div>
                </div>`;
            }

            html += '</div></div>';
        }

        content.innerHTML = html;
    } catch (error) {
        console.error('Error:', error);
        loading.classList.add('hidden');
        content.innerHTML = '<p class="text-red-500 text-center py-4">' + __foutBijLaden + '</p>';
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
            alert(__fout + ': ' + (data.error || data.message || __onbekendeFout));
        }
    } catch (error) {
        console.error('Error:', error);
        alert(__foutBijVerplaatsen);
    }
}

function closeZoekMatchModal() {
    document.getElementById('zoek-match-modal').classList.add('hidden');
    zoekMatchJudokaId = null;
    zoekMatchFromPouleId = null;
}
</script>

<!-- Zoek Match Modal (draggable) -->
<div id="zoek-match-modal" class="hidden fixed inset-0 bg-black bg-opacity-30 z-50" onclick="if(event.target === this) closeZoekMatchModal()">
    <div id="zoek-match-dialog" class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[80vh] overflow-hidden absolute" style="top: 50%; left: 50%; transform: translate(-50%, -50%);">
        <div class="flex justify-between items-center px-4 py-3 border-b bg-blue-700 text-white rounded-t-lg cursor-move" id="zoek-match-header">
            <h3 class="font-bold text-lg select-none"><?php echo e(__('Zoek match (wedstrijddag)')); ?></h3>
            <button onclick="closeZoekMatchModal()" class="text-white hover:text-gray-200 text-xl">&times;</button>
        </div>
        <div class="p-4 overflow-y-auto max-h-[60vh]">
            <div id="zoek-match-loading" class="text-center py-4">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                <p class="text-gray-500 mt-2"><?php echo e(__('Laden...')); ?></p>
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
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/judotoernooi/staging/resources/views/pages/wedstrijddag/poules.blade.php ENDPATH**/ ?>