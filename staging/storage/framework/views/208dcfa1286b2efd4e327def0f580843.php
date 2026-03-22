<?php $__env->startSection('title', __('Judoka\'s')); ?>

<?php $__env->startSection('content'); ?>
<?php
    $incompleteJudokas = $judokas->filter(fn($j) => $j->is_onvolledig || !$j->club_id || !$j->band || !$j->geboortejaar || !$j->gewicht);
    $nietGecategoriseerdAantal = $toernooi->countNietGecategoriseerd();
?>

<!-- INFO: Judoka's die niet in een categorie passen (alleen via portal te zien) -->
<?php if($nietInCategorie->count() > 0): ?>
<div class="mb-4 p-4 bg-orange-50 border border-orange-300 rounded-lg" x-data="{ open: false }">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="text-2xl">🚫</span>
            <div>
                <p class="font-bold text-orange-800"><?php echo e(__(':count judoka(\'s) niet in deelnemerslijst', ['count' => $nietInCategorie->count()])); ?></p>
                <p class="text-sm text-orange-700"><?php echo e(__('Te oud/jong voor dit toernooi. Alleen zichtbaar in het club portal.')); ?></p>
            </div>
        </div>
        <button @click="open = !open" class="px-4 py-2 bg-orange-500 text-white rounded hover:bg-orange-600 text-sm font-medium">
            <span x-text="open ? '<?php echo e(__('Verbergen')); ?>' : '<?php echo e(__('Tonen')); ?>'"></span>
        </button>
    </div>
    <div x-show="open" x-collapse class="mt-4 border-t border-orange-200 pt-3">
        <table class="w-full text-sm">
            <thead class="text-orange-800">
                <tr>
                    <th class="text-left py-1"><?php echo e(__('Naam')); ?></th>
                    <th class="text-left py-1"><?php echo e(__('Geb.jaar')); ?></th>
                    <th class="text-left py-1"><?php echo e(__('Club')); ?></th>
                    <th class="text-left py-1"><?php echo e(__('Reden')); ?></th>
                    <th class="text-right py-1"><?php echo e(__('Acties')); ?></th>
                </tr>
            </thead>
            <tbody class="text-orange-700">
                <?php $__currentLoopData = $nietInCategorie; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $judoka): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $leeftijd = $judoka->geboortejaar ? (date('Y') - $judoka->geboortejaar) : null;
                    $reden = $judoka->import_warnings;
                    if (!$reden && $leeftijd) {
                        $reden = "Leeftijd {$leeftijd} jaar past niet in categorieën";
                    } elseif (!$reden) {
                        $reden = 'Onbekende categorie';
                    }
                ?>
                <tr class="border-t border-orange-100">
                    <td class="py-1"><?php echo e($judoka->naam); ?></td>
                    <td class="py-1"><?php echo e($judoka->geboortejaar); ?> (<?php echo e($leeftijd ?? '?'); ?> jr)</td>
                    <td class="py-1"><?php echo e($judoka->club?->naam ?? '-'); ?></td>
                    <td class="py-1 text-red-600"><?php echo e($reden); ?></td>
                    <td class="py-1 text-right whitespace-nowrap">
                        <a href="<?php echo e(route('toernooi.judoka.show', $toernooi->routeParamsWith(['judoka' => $judoka]))); ?>"
                           class="text-blue-600 hover:text-blue-800 text-xs mr-2">
                            <?php echo e(__('Bekijken')); ?>

                        </a>
                        <form action="<?php echo e(route('toernooi.judoka.destroy', $toernooi->routeParamsWith(['judoka' => $judoka]))); ?>" method="POST" class="inline"
                              onsubmit="return confirm('<?php echo e(__('Weet je zeker dat je :naam wilt verwijderen?', ['naam' => $judoka->naam])); ?>')">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('DELETE'); ?>
                            <button type="submit" class="text-red-600 hover:text-red-800 text-xs">
                                <?php echo e(__('Verwijderen')); ?>

                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- WAARSCHUWING: Niet-gecategoriseerde judoka's (oude stijl - voor backward compatibility) -->
<?php if($nietGecategoriseerdAantal > 0 && $nietInCategorie->count() === 0): ?>
<?php
    // Haal de niet-gecategoriseerde judoka's op via dezelfde methode als de count
    $nietGecatJudokas = $toernooi->getNietGecategoriseerdeJudokas()->load('club');
?>
<div id="niet-gecategoriseerd-alert"
     class="mb-4 p-4 bg-red-100 border-2 border-red-500 rounded-lg animate-error-blink"
     x-data="{ show: true, open: false }"
     x-show="show"
     x-init="setTimeout(() => $el.classList.remove('animate-error-blink'), 1500)">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="text-2xl">⚠️</span>
            <div>
                <p class="font-bold text-red-800"><?php echo e(__(':count judoka(\'s) niet gecategoriseerd!', ['count' => $nietGecategoriseerdAantal])); ?></p>
                <p class="text-sm text-red-700"><?php echo e(__('Geen categorie past bij deze judoka(\'s). Pas de categorie-instellingen aan.')); ?></p>
            </div>
        </div>
        <div class="flex gap-2">
            <button @click="open = !open" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 text-sm font-medium">
                <span x-text="open ? '<?php echo e(__('Verbergen')); ?>' : '<?php echo e(__('Details')); ?>'"></span>
            </button>
            <a href="<?php echo e(route('toernooi.edit', $toernooi->routeParams())); ?>?tab=toernooi#categorieen"
               class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 text-sm font-medium">
                <?php echo e(__('Naar Instellingen')); ?>

            </a>
        </div>
    </div>
    <div x-show="open" x-collapse class="mt-4 border-t border-red-200 pt-3">
        <table class="w-full text-sm">
            <thead class="text-red-800">
                <tr>
                    <th class="text-left py-1"><?php echo e(__('Naam')); ?></th>
                    <th class="text-left py-1"><?php echo e(__('Leeftijd')); ?></th>
                    <th class="text-left py-1"><?php echo e(__('Geslacht')); ?></th>
                    <th class="text-left py-1"><?php echo e(__('Club')); ?></th>
                </tr>
            </thead>
            <tbody class="text-red-700">
                <?php $__currentLoopData = $nietGecatJudokas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $judoka): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr class="border-t border-red-100">
                    <td class="py-1">
                        <a href="<?php echo e(route('toernooi.judoka.show', $toernooi->routeParamsWith(['judoka' => $judoka]))); ?>" class="hover:underline">
                            <?php echo e($judoka->naam); ?>

                        </a>
                    </td>
                    <td class="py-1 font-medium"><?php echo e($judoka->geboortejaar ? (date('Y') - $judoka->geboortejaar) . ' jaar' : '?'); ?></td>
                    <td class="py-1"><?php echo e($judoka->geslacht ?? '?'); ?></td>
                    <td class="py-1"><?php echo e($judoka->club?->naam ?? '-'); ?></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if($incompleteJudokas->count() > 0): ?>
<div class="bg-yellow-50 border border-yellow-300 rounded-lg p-4 mb-4" x-data="{ open: false }">
    <div class="flex items-center justify-between">
        <h3 class="font-bold text-yellow-800">
            <span class="cursor-pointer hover:underline" @click="open = !open">
                <?php echo e(__(':count judoka\'s met ontbrekende gegevens', ['count' => $incompleteJudokas->count()])); ?>

                <span class="text-sm font-normal ml-2"><?php echo e(__('(klik voor details)')); ?></span>
            </span>
        </h3>
        <button @click="open = !open" class="text-yellow-600 hover:text-yellow-800">
            <svg x-show="!open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
            <svg x-show="open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
            </svg>
        </button>
    </div>
    <div x-show="open" x-collapse class="mt-3">
        <ul class="text-sm text-yellow-800 space-y-1">
            <?php $__currentLoopData = $incompleteJudokas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $judoka): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $ontbreekt = [];
                if (!$judoka->gewicht) $ontbreekt[] = __('gewicht');
                if (!$judoka->geboortejaar) $ontbreekt[] = __('geboortejaar');
                if (!$judoka->club_id) $ontbreekt[] = __('club');
                if (!$judoka->band) $ontbreekt[] = __('band');
                if ($judoka->is_onvolledig) $ontbreekt[] = __('onvolledig');
            ?>
            <li class="flex justify-between">
                <a href="<?php echo e(route('toernooi.judoka.show', $toernooi->routeParamsWith(['judoka' => $judoka]))); ?>" class="hover:underline hover:text-yellow-900"><?php echo e($judoka->naam); ?></a>
                <span class="text-yellow-600 text-xs"><?php echo e(implode(', ', $ontbreekt)); ?></span>
            </li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<div class="flex justify-between items-center mb-4">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Judoka's (<?php echo e($judokas->count()); ?>)</h1>
    </div>
    <div class="flex space-x-2">
        <?php if($toernooi->organisator->stamJudokas()->actief()->exists()): ?>
        <button onclick="document.getElementById('stambestandModal').classList.remove('hidden'); loadStambestand()"
                class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded">
            <?php echo e(__('Uit database')); ?>

        </button>
        <?php endif; ?>
        <a href="<?php echo e(route('toernooi.judoka.import', $toernooi->routeParams())); ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            <?php echo e(__('Importeren')); ?>

        </a>
        <button onclick="document.getElementById('addJudokaModal').classList.remove('hidden')"
                class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
            + <?php echo e(__('Judoka toevoegen')); ?>

        </button>
        <form action="<?php echo e(route('toernooi.judoka.valideer', $toernooi->routeParams())); ?>" method="POST" class="inline">
            <?php echo csrf_field(); ?>
            <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                <?php echo e(__('Valideren')); ?>

            </button>
        </form>
    </div>
</div>

<!-- Rapportage per leeftijdsklasse -->
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <h3 class="font-bold text-gray-700 mb-3"><?php echo e(__('Overzicht per leeftijdsklasse')); ?></h3>
    <div class="flex flex-wrap gap-3">
        <?php $__currentLoopData = $judokasPerKlasse; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $klasse => $klasseJudokas): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-2">
            <span class="font-medium text-blue-800"><?php echo e($klasse); ?></span>
            <span class="ml-2 bg-blue-600 text-white px-2 py-0.5 rounded-full text-sm"><?php echo e($klasseJudokas->count()); ?></span>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
</div>


<?php if(session('validatie_fouten')): ?>
<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
    <h3 class="font-bold text-yellow-800 mb-2"><?php echo e(__('Ontbrekende gegevens:')); ?></h3>
    <ul class="list-disc list-inside text-yellow-700 text-sm">
        <?php $__currentLoopData = session('validatie_fouten'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fout): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <li><?php echo e($fout); ?></li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ul>
</div>
<?php endif; ?>

<?php if(session('correctie_mails')): ?>
<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
    <p class="text-blue-800"><?php echo e(session('correctie_mails')); ?></p>
</div>
<?php endif; ?>

<?php if(session('zonder_club')): ?>
<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
    <p class="text-yellow-800"><?php echo e(session('zonder_club')); ?></p>
</div>
<?php endif; ?>

<?php if(session('import_fouten')): ?>
<div class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-6" x-data="{ open: false }">
    <div class="flex items-center justify-between">
        <h3 class="font-bold text-orange-800">
            <span class="cursor-pointer hover:underline" @click="open = !open">
                <?php echo e(__(':count import fouten', ['count' => count(session('import_fouten'))])); ?>

                <span class="text-sm font-normal ml-2"><?php echo e(__('(klik voor details)')); ?></span>
            </span>
        </h3>
        <button @click="open = !open" class="text-orange-600 hover:text-orange-800">
            <svg x-show="!open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
            <svg x-show="open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
            </svg>
        </button>
    </div>
    <div x-show="open" x-collapse class="mt-3">
        <ul class="list-disc list-inside text-orange-700 text-sm max-h-64 overflow-y-auto">
            <?php $__currentLoopData = session('import_fouten'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fout): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li><?php echo e($fout); ?></li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<?php if($importWarningsPerClub->count() > 0): ?>
<div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6" x-data="{ open: false }">
    <div class="flex items-center justify-between">
        <h3 class="font-bold text-red-800">
            <span class="cursor-pointer hover:underline" @click="open = !open">
                <?php echo e(__(':count judoka\'s met import waarschuwingen', ['count' => $importWarningsPerClub->flatten()->count()])); ?>

                <span class="text-sm font-normal ml-2"><?php echo e(__('(klik voor details per club)')); ?></span>
            </span>
        </h3>
        <button @click="open = !open" class="text-red-600 hover:text-red-800">
            <svg x-show="!open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
            <svg x-show="open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
            </svg>
        </button>
    </div>
    <div x-show="open" x-collapse class="mt-3 space-y-4">
        <?php $__currentLoopData = $importWarningsPerClub; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $clubNaam => $clubJudokas): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php $club = $clubJudokas->first()->club; ?>
        <div class="bg-white rounded p-3 border border-red-100">
            <div class="flex justify-between items-start mb-2">
                <h4 class="font-semibold text-red-900"><?php echo e($clubNaam); ?> (<?php echo e($clubJudokas->count()); ?>)</h4>
                <?php if($club): ?>
                <div class="text-xs text-gray-600 text-right">
                    <?php if($club->email): ?>
                    <a href="mailto:<?php echo e($club->email); ?>?subject=Judoka%20gegevens%20aanvullen%20-%20<?php echo e(urlencode($toernooi->naam)); ?>" class="text-blue-600 hover:underline"><?php echo e($club->email); ?></a>
                    <?php endif; ?>
                    <?php if($club->telefoon): ?>
                    <span class="ml-2"><?php echo e($club->telefoon); ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <ul class="text-sm text-red-700 space-y-1">
                <?php $__currentLoopData = $clubJudokas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $judoka): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li class="flex justify-between">
                    <a href="<?php echo e(route('toernooi.judoka.show', $toernooi->routeParamsWith(['judoka' => $judoka]))); ?>" class="hover:underline hover:text-red-900"><?php echo e($judoka->naam); ?></a>
                    <span class="text-red-500 text-xs"><?php echo e($judoka->import_warnings); ?></span>
                </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
</div>
<?php endif; ?>

<!-- Judoka tabel -->
<?php if($judokas->count() > 0): ?>
<div class="bg-white rounded-lg shadow overflow-hidden" x-data="judokaTable()">
    <!-- Zoekbalk -->
    <div class="px-4 py-3 bg-gray-50 border-b">
        <div class="flex gap-2 items-center">
            <div class="relative flex-1">
                <input type="text"
                       x-model="zoekterm"
                       placeholder="<?php echo e(__('Filter: naam, club, -45kg, 20-30kg, +55kg...')); ?>"
                       class="w-full border rounded-lg px-4 py-2 pl-10 focus:border-blue-500 focus:outline-none">
                <svg class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <button @click="fuzzyLevel = fuzzyLevel ? 0 : 1"
                    :class="fuzzyLevel ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
                    class="px-3 py-2 rounded text-sm font-medium whitespace-nowrap">
                <?php echo e(__('Fuzzy')); ?> <span x-text="fuzzyLevel ? '<?php echo e(__('aan')); ?>' : '<?php echo e(__('uit')); ?>'"></span>
            </button>
            <button @click="toonOnvolledig = !toonOnvolledig"
                    :class="toonOnvolledig ? 'bg-red-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
                    class="px-3 py-2 rounded text-sm font-medium whitespace-nowrap">
                <?php echo e(__('Onvolledig')); ?> (<?php echo e($incompleteJudokas->count()); ?>)
            </button>
            <div x-show="zoekterm" class="bg-green-100 border border-green-300 rounded-lg px-4 py-2 flex items-center gap-2">
                <span class="text-green-800 font-bold text-lg" x-text="filteredJudokas.length"></span>
                <span class="text-green-700 text-sm"><?php echo e(__('resultaten')); ?></span>
            </div>
        </div>
    </div>
    <table class="w-full table-fixed">
        <thead class="bg-blue-800 text-white sticky top-0 z-10">
            <tr>
                <th @click="sort('naam')" class="w-[18%] px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                    <span class="flex items-center gap-1"><?php echo e(__('Naam')); ?> <template x-if="sortKey === 'naam'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                </th>
                <th @click="sort('leeftijdsklasse')" class="w-[12%] px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                    <span class="flex items-center gap-1"><?php echo e(__('Categorie')); ?> <template x-if="sortKey === 'leeftijdsklasse'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                </th>
                <th @click="sort('gewicht')" class="w-[10%] px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                    <span class="flex items-center gap-1"><?php echo e(__('Gewicht')); ?> <template x-if="sortKey === 'gewicht'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                </th>
                <th @click="sort('geboortejaar')" class="w-[8%] px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                    <span class="flex items-center gap-1"><?php echo e(__('Geb.jaar')); ?> <template x-if="sortKey === 'geboortejaar'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                </th>
                <th @click="sort('geslacht')" class="w-[8%] px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                    <span class="flex items-center gap-1"><?php echo e(__('M/V')); ?> <template x-if="sortKey === 'geslacht'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                </th>
                <th @click="sort('band')" class="w-[10%] px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                    <span class="flex items-center gap-1"><?php echo e(__('Band')); ?> <template x-if="sortKey === 'band'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                </th>
                <th @click="sort('club')" class="w-[28%] px-4 py-3 text-left text-xs font-medium uppercase cursor-pointer hover:bg-blue-700 select-none">
                    <span class="flex items-center gap-1"><?php echo e(__('Club')); ?> <template x-if="sortKey === 'club'"><span x-text="sortAsc ? '▲' : '▼'"></span></template></span>
                </th>
                <th class="w-[6%] px-4 py-3 text-left text-xs font-medium uppercase"><?php echo e(__('Acties')); ?></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <template x-for="judoka in sortedJudokas" :key="judoka.id">
                <tr :class="judoka.incompleet ? 'bg-red-50 hover:bg-red-100' : 'hover:bg-gray-50'">
                    <td class="px-4 py-2 truncate">
                        <a :href="judoka.url + (toonOnvolledig ? '?filter=onvolledig' : '')" class="text-blue-600 hover:text-blue-800 font-medium" x-text="judoka.naam"></a>
                        <span x-show="judoka.incompleet" class="ml-1 text-red-600 text-xs">⚠</span>
                    </td>
                    <td class="px-4 py-2 text-sm text-gray-600 truncate" x-text="judoka.leeftijdsklasse"></td>
                    <td class="px-4 py-2 text-sm" :class="!judoka.gewicht ? 'text-red-600' : ''" x-text="judoka.gewicht ? judoka.gewicht + ' kg' : '-'"></td>
                    <td class="px-4 py-2 text-sm" :class="!judoka.geboortejaar ? 'text-red-600' : ''" x-text="judoka.geboortejaar || '-'"></td>
                    <td class="px-4 py-2 text-sm" x-text="judoka.geslacht === 'Jongen' ? 'M' : 'V'"></td>
                    <td class="px-4 py-2 text-sm truncate" :class="!judoka.band ? 'text-red-600' : ''" x-text="judoka.band || '-'"></td>
                    <td class="px-4 py-2 text-sm truncate" :class="!judoka.club ? 'text-red-600' : ''" x-text="judoka.club || '-'"></td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <a :href="judoka.editUrl + (toonOnvolledig ? '?filter=onvolledig' : '')" class="text-blue-600 hover:text-blue-800 mr-2" title="<?php echo e(__('Bewerken')); ?>">✏️</a>
                        <form :action="judoka.deleteUrl" method="POST" class="inline" @submit.prevent="if(confirm('<?php echo e(__('Weet je zeker dat je')); ?> ' + judoka.naam + ' <?php echo e(__('wilt verwijderen?')); ?>')) $el.submit()">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('DELETE'); ?>
                            <button type="submit" class="text-red-600 hover:text-red-800 font-bold text-lg" title="<?php echo e(__('Verwijderen')); ?>">×</button>
                        </form>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>
</div>

<script>
function judokaTable() {
    // Levenshtein distance for fuzzy matching
    function levenshtein(a, b) {
        if (a.length === 0) return b.length;
        if (b.length === 0) return a.length;
        const matrix = [];
        for (let i = 0; i <= b.length; i++) matrix[i] = [i];
        for (let j = 0; j <= a.length; j++) matrix[0][j] = j;
        for (let i = 1; i <= b.length; i++) {
            for (let j = 1; j <= a.length; j++) {
                matrix[i][j] = b[i-1] === a[j-1]
                    ? matrix[i-1][j-1]
                    : Math.min(matrix[i-1][j-1] + 1, matrix[i][j-1] + 1, matrix[i-1][j] + 1);
            }
        }
        return matrix[b.length][a.length];
    }

    // Check if term fuzzy-matches any word in text
    function fuzzyMatch(term, text, maxDist) {
        if (maxDist === 0) return text.includes(term);
        const words = text.split(/\s+/);
        for (const word of words) {
            if (word.includes(term)) return true;
            if (term.length >= 3 && levenshtein(term, word.substring(0, term.length + maxDist)) <= maxDist) return true;
        }
        // Also check substring match with tolerance
        for (let i = 0; i <= text.length - term.length + maxDist; i++) {
            const sub = text.substring(i, i + term.length);
            if (levenshtein(term, sub) <= maxDist) return true;
        }
        return false;
    }

    return {
        sortKey: null,
        sortAsc: true,
        zoekterm: '',
        fuzzyLevel: 0,
        toonOnvolledig: sessionStorage.getItem('toonOnvolledig') === 'true' || new URLSearchParams(window.location.search).get('filter') === 'onvolledig',

        init() {
            // Clear sessionStorage after reading - it's only for back navigation
            sessionStorage.removeItem('toonOnvolledig');
        },

        judokas: [
            <?php $__currentLoopData = $judokas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $judoka): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            {
                id: <?php echo e($judoka->id); ?>,
                naam: <?php echo json_encode($judoka->naam, 15, 512) ?>,
                leeftijdsklasse: <?php echo json_encode($judoka->leeftijdsklasse, 15, 512) ?>,
                leeftijdsklasseOrder: <?php echo e($judoka->sort_categorie ?? 99); ?>,
                gewicht: <?php echo e($judoka->gewicht ?? 'null'); ?>,
                geboortejaar: <?php echo e($judoka->geboortejaar ?? 'null'); ?>,
                geslacht: '<?php echo e($judoka->geslacht == "M" ? "Jongen" : "Meisje"); ?>',
                band: <?php echo json_encode(\App\Enums\Band::toKleur($judoka->band) ?: null, 15, 512) ?>,
                bandOrder: <?php echo e(\App\Enums\Band::getSortNiveau(\App\Enums\Band::toKleur($judoka->band))); ?>,
                club: <?php echo json_encode($judoka->club?->naam, 15, 512) ?>,
                incompleet: <?php echo e(($judoka->is_onvolledig || !$judoka->club_id || !$judoka->band || !$judoka->geboortejaar || !$judoka->gewicht) ? 'true' : 'false'); ?>,
                url: '<?php echo e(route("toernooi.judoka.show", $toernooi->routeParamsWith(["judoka" => $judoka]))); ?>',
                editUrl: '<?php echo e(route("toernooi.judoka.edit", $toernooi->routeParamsWith(["judoka" => $judoka]))); ?>',
                deleteUrl: '<?php echo e(route("toernooi.judoka.destroy", $toernooi->routeParamsWith(["judoka" => $judoka]))); ?>'
            },
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        ],

        get filteredJudokas() {
            let result = this.judokas;

            // Filter on incomplete if enabled
            if (this.toonOnvolledig) {
                result = result.filter(j => j.incompleet);
            }

            // Filter on search term (with weight filter support)
            if (this.zoekterm) {
                const terms = this.zoekterm.toLowerCase().split(/\s+/).filter(t => t.length > 0);
                const maxDist = this.fuzzyLevel;

                // Check for weight filter patterns and separate them
                const gewichtFilters = [];
                const tekstTerms = [];

                terms.forEach(term => {
                    // Pattern: -45, +55, 20-30
                    if (/^[+-]?\d+(\.\d+)?$/.test(term) || /^\d+(\.\d+)?-\d+(\.\d+)?$/.test(term)) {
                        gewichtFilters.push(term);
                    } else {
                        tekstTerms.push(term);
                    }
                });

                // Apply weight filters
                gewichtFilters.forEach(filter => {
                    let minGewicht = null;
                    let maxGewicht = null;

                    if (filter.startsWith('-')) {
                        // -45 = onder 45 kg
                        maxGewicht = parseFloat(filter.substring(1));
                    } else if (filter.startsWith('+')) {
                        // +55 = boven 55 kg
                        minGewicht = parseFloat(filter.substring(1));
                    } else if (filter.includes('-')) {
                        // 20-30 = tussen 20 en 30 kg
                        const parts = filter.split('-');
                        minGewicht = parseFloat(parts[0]);
                        maxGewicht = parseFloat(parts[1]);
                    } else {
                        // 20 = exact 20 kg (eigenlijk 20-20)
                        minGewicht = parseFloat(filter);
                        maxGewicht = parseFloat(filter);
                    }

                    if (minGewicht !== null || maxGewicht !== null) {
                        result = result.filter(j => {
                            if (!j.gewicht) return false;
                            if (minGewicht !== null && j.gewicht < minGewicht) return false;
                            if (maxGewicht !== null && j.gewicht > maxGewicht) return false;
                            return true;
                        });
                    }
                });

                // Apply text search on remaining terms
                if (tekstTerms.length > 0) {
                    result = result.filter(j => {
                        const searchText = [
                            j.naam, j.club, j.leeftijdsklasse, j.gewicht ? j.gewicht + 'kg' : '', j.geboortejaar, j.geslacht, j.band
                        ].filter(Boolean).join(' ').toLowerCase();
                        return tekstTerms.every(term => fuzzyMatch(term, searchText, maxDist));
                    });
                }
            }

            return result;
        },

        get sortedJudokas() {
            const list = this.filteredJudokas;
            if (!this.sortKey) {
                // Default: incomplete judokas first
                return [...list].sort((a, b) => {
                    if (a.incompleet !== b.incompleet) return a.incompleet ? -1 : 1;
                    return 0;
                });
            }
            return [...list].sort((a, b) => {
                // Incomplete judokas always first (unless filtering on incomplete)
                if (!this.toonOnvolledig && a.incompleet !== b.incompleet) {
                    return a.incompleet ? -1 : 1;
                }
                let aVal, bVal;
                if (this.sortKey === 'leeftijdsklasse') {
                    aVal = a.leeftijdsklasseOrder;
                    bVal = b.leeftijdsklasseOrder;
                } else if (this.sortKey === 'gewicht') {
                    aVal = a.gewicht ?? 999;
                    bVal = b.gewicht ?? 999;
                } else if (this.sortKey === 'geboortejaar') {
                    aVal = a.geboortejaar ?? 0;
                    bVal = b.geboortejaar ?? 0;
                } else if (this.sortKey === 'band') {
                    aVal = a.bandOrder;
                    bVal = b.bandOrder;
                } else if (this.sortKey === 'club') {
                    aVal = (a.club || 'zzz').toLowerCase();
                    bVal = (b.club || 'zzz').toLowerCase();
                } else {
                    aVal = (a[this.sortKey] || '').toString().toLowerCase();
                    bVal = (b[this.sortKey] || '').toString().toLowerCase();
                }
                if (aVal < bVal) return this.sortAsc ? -1 : 1;
                if (aVal > bVal) return this.sortAsc ? 1 : -1;
                return 0;
            });
        },

        sort(key) {
            if (this.sortKey === key) {
                this.sortAsc = !this.sortAsc;
            } else {
                this.sortKey = key;
                this.sortAsc = true;
            }
        }
    }
}
</script>
<?php else: ?>
<div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
    <?php echo e(__('Nog geen judoka\'s.')); ?> <a href="<?php echo e(route('toernooi.judoka.import', $toernooi->routeParams())); ?>" class="text-blue-600"><?php echo e(__('Importeer deelnemers')); ?></a>.
</div>
<?php endif; ?>

<!-- Add Judoka Modal -->
<div id="addJudokaModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-xl shadow-lg rounded-lg bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800"><?php echo e(__('Judoka toevoegen')); ?></h3>
            <button onclick="document.getElementById('addJudokaModal').classList.add('hidden')"
                    class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>

        <form action="<?php echo e(route('toernooi.judoka.store', $toernooi->routeParams())); ?>" method="POST">
            <?php echo csrf_field(); ?>
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 font-medium mb-1"><?php echo e(__('Naam')); ?> *</label>
                        <input type="text" name="naam" required
                               class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-1"><?php echo e(__('Club')); ?></label>
                        <select name="club_id" class="w-full border rounded px-3 py-2">
                            <option value="">-- <?php echo e(__('Geen club')); ?> --</option>
                            <?php $__currentLoopData = $toernooi->clubs()->orderBy('naam')->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $club): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($club->id); ?>"><?php echo e($club->naam); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-gray-700 font-medium mb-1"><?php echo e(__('Geboortejaar')); ?></label>
                        <input type="number" name="geboortejaar" min="1990" max="<?php echo e(date('Y')); ?>"
                               class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-1"><?php echo e(__('Geslacht')); ?></label>
                        <select name="geslacht" class="w-full border rounded px-3 py-2">
                            <option value="">--</option>
                            <option value="M"><?php echo e(__('Man')); ?></option>
                            <option value="V"><?php echo e(__('Vrouw')); ?></option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium mb-1"><?php echo e(__('Band')); ?></label>
                        <select name="band" class="w-full border rounded px-3 py-2">
                            <option value="">--</option>
                            <?php $__currentLoopData = ['wit', 'geel', 'oranje', 'groen', 'blauw', 'bruin', 'zwart']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $band): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($band); ?>"><?php echo e(ucfirst($band)); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 font-medium mb-1"><?php echo e(__('Gewicht (kg)')); ?></label>
                        <input type="number" name="gewicht" step="0.1" min="10" max="200"
                               class="w-full border rounded px-3 py-2">
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <button type="button"
                        onclick="document.getElementById('addJudokaModal').classList.add('hidden')"
                        class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                    <?php echo e(__('Annuleren')); ?>

                </button>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    <?php echo e(__('Toevoegen')); ?>

                </button>
            </div>
        </form>
    </div>
</div>

<?php if($toernooi->organisator->stamJudokas()->actief()->exists()): ?>
<div id="stambestandModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[80vh] flex flex-col mx-4">
        <div class="p-6 border-b">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-800"><?php echo e(__('Importeer uit database')); ?></h2>
                <button onclick="document.getElementById('stambestandModal').classList.add('hidden')"
                        class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            <div class="mt-3">
                <input type="text" id="stamZoek" placeholder="<?php echo e(__('Zoek op naam...')); ?>"
                       class="w-full border rounded-lg px-4 py-2 text-sm focus:border-purple-500 focus:outline-none"
                       oninput="filterStambestand()">
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6" id="stambestandLijst">
            <p class="text-gray-500 text-center"><?php echo e(__('Laden...')); ?></p>
        </div>

        <div class="p-4 border-t bg-gray-50 flex justify-between items-center">
            <span class="text-sm text-gray-600">
                <span id="stamGeselecteerd">0</span> <?php echo e(__('geselecteerd')); ?>

            </span>
            <div class="flex gap-3">
                <button onclick="document.getElementById('stambestandModal').classList.add('hidden')"
                        class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                    <?php echo e(__('Annuleren')); ?>

                </button>
                <button onclick="importUitDatabase()" id="stamImportBtn" disabled
                        class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded disabled:opacity-50 disabled:cursor-not-allowed">
                    <?php echo e(__('Importeer geselecteerde')); ?>

                </button>
            </div>
        </div>
    </div>
</div>

<script>
let _stamData = [];
const _stamUrl = <?php echo json_encode(route('toernooi.judoka.stambestand', $toernooi->routeParams()), 512) ?>;
const _importUrl = <?php echo json_encode(route('toernooi.judoka.import-database', $toernooi->routeParams()), 512) ?>;
const _csrfToken = '<?php echo e(csrf_token()); ?>';

async function loadStambestand() {
    const lijst = document.getElementById('stambestandLijst');
    lijst.innerHTML = '<p class="text-gray-500 text-center"><?php echo e(__("Laden...")); ?></p>';

    try {
        const res = await fetch(_stamUrl, { headers: { 'Accept': 'application/json' } });
        _stamData = await res.json();
        renderStambestand(_stamData);
    } catch (e) {
        lijst.innerHTML = '<p class="text-red-500 text-center"><?php echo e(__("Fout bij laden")); ?></p>';
    }
}

function filterStambestand() {
    const zoek = document.getElementById('stamZoek').value.toLowerCase().trim();
    if (!zoek) return renderStambestand(_stamData);
    renderStambestand(_stamData.filter(j => j.naam.toLowerCase().includes(zoek)));
}

function renderStambestand(data) {
    const lijst = document.getElementById('stambestandLijst');

    if (data.length === 0) {
        lijst.innerHTML = '<p class="text-gray-500 text-center"><?php echo e(__("Geen judoka\'s gevonden")); ?></p>';
        return;
    }

    lijst.innerHTML = '<table class="w-full text-sm"><thead class="bg-gray-50 sticky top-0"><tr>' +
        '<th class="px-3 py-2 text-left w-10"></th>' +
        '<th class="px-3 py-2 text-left"><?php echo e(__("Naam")); ?></th>' +
        '<th class="px-3 py-2 text-center"><?php echo e(__("Geb.jaar")); ?></th>' +
        '<th class="px-3 py-2 text-center"><?php echo e(__("M/V")); ?></th>' +
        '<th class="px-3 py-2 text-center"><?php echo e(__("Band")); ?></th>' +
        '<th class="px-3 py-2 text-center"><?php echo e(__("Gewicht")); ?></th>' +
        '</tr></thead><tbody class="divide-y">' +
        data.map(j => {
            const disabled = j.al_aangemeld;
            const bandLabel = j.band ? j.band.charAt(0).toUpperCase() + j.band.slice(1) : '-';
            return '<tr class="' + (disabled ? 'opacity-40' : 'hover:bg-purple-50 cursor-pointer') + '"' +
                (disabled ? '' : ' onclick="toggleStamCheckbox(' + j.id + ')"') + '>' +
                '<td class="px-3 py-2">' +
                    (disabled
                        ? '<span class="text-xs text-gray-400">&#10003;</span>'
                        : '<input type="checkbox" id="stam_' + j.id + '" value="' + j.id + '" class="stam-cb rounded" onchange="updateStamCount()" onclick="event.stopPropagation()">') +
                '</td>' +
                '<td class="px-3 py-2 font-medium">' + j.naam + (disabled ? ' <span class="text-xs text-gray-400">(<?php echo e(__("al aangemeld")); ?>)</span>' : '') + '</td>' +
                '<td class="px-3 py-2 text-center text-gray-600">' + (j.geboortejaar || '-') + '</td>' +
                '<td class="px-3 py-2 text-center text-gray-600">' + (j.geslacht || '-') + '</td>' +
                '<td class="px-3 py-2 text-center text-gray-600">' + bandLabel + '</td>' +
                '<td class="px-3 py-2 text-center text-gray-600">' + (j.gewicht ? j.gewicht + ' kg' : '-') + '</td>' +
                '</tr>';
        }).join('') +
        '</tbody></table>';
}

function toggleStamCheckbox(id) {
    const cb = document.getElementById('stam_' + id);
    if (cb) { cb.checked = !cb.checked; updateStamCount(); }
}

function updateStamCount() {
    const checked = document.querySelectorAll('.stam-cb:checked').length;
    document.getElementById('stamGeselecteerd').textContent = checked;
    document.getElementById('stamImportBtn').disabled = checked === 0;
}

async function importUitDatabase() {
    const ids = [...document.querySelectorAll('.stam-cb:checked')].map(cb => parseInt(cb.value));
    if (ids.length === 0) return;

    document.getElementById('stamImportBtn').disabled = true;
    document.getElementById('stamImportBtn').textContent = '<?php echo e(__("Importeren...")); ?>';

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = _importUrl;
    form.innerHTML = '<input type="hidden" name="_token" value="' + _csrfToken + '">';
    ids.forEach(id => {
        form.innerHTML += '<input type="hidden" name="stam_judoka_ids[]" value="' + id + '">';
    });
    document.body.appendChild(form);
    form.submit();
}
</script>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/judotoernooi/staging/resources/views/pages/judoka/index.blade.php ENDPATH**/ ?>