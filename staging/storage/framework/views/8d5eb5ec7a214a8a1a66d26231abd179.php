<?php $__env->startSection('title', __('Havun Admin - Alle Organisatoren')); ?>
<?php $__env->startSection('main-class', 'max-w-7xl w-full mx-auto'); ?>

<?php $__env->startSection('content'); ?>
<div x-data="{ tab: 'overzicht' }" class="w-full max-w-7xl">


<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-800"><?php echo e(__('Havun Admin Dashboard')); ?></h1>
        <p class="text-gray-500 mt-1 text-sm"><?php echo e(__('Overzicht van alle klanten (organisatoren) en hun toernooien')); ?></p>
    </div>
    <a href="<?php echo e(route('organisator.dashboard', ['organisator' => Auth::guard('organisator')->user()->slug])); ?>" class="text-blue-600 hover:text-blue-800 flex items-center">
        &larr; <?php echo e(__('Terug naar Dashboard')); ?>

    </a>
</div>


<nav class="flex gap-1 mb-6 border-b border-gray-200">
    <button @click="tab = 'overzicht'" :class="tab === 'overzicht' ? 'border-blue-600 text-blue-600 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="px-4 py-2.5 border-b-2 text-sm transition-colors">
        <?php echo e(__('Overzicht')); ?>

    </button>
    <button @click="tab = 'klanten'" :class="tab === 'klanten' ? 'border-blue-600 text-blue-600 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="px-4 py-2.5 border-b-2 text-sm transition-colors">
        <?php echo e(__('Klanten')); ?>

    </button>
    <button @click="tab = 'betalingen'" :class="tab === 'betalingen' ? 'border-blue-600 text-blue-600 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="px-4 py-2.5 border-b-2 text-sm transition-colors">
        <?php echo e(__('Betalingen')); ?>

    </button>
    <button @click="tab = 'activiteit'" :class="tab === 'activiteit' ? 'border-blue-600 text-blue-600 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="px-4 py-2.5 border-b-2 text-sm transition-colors">
        <?php echo e(__('Activiteit')); ?>

    </button>
    <button @click="tab = 'systeem'" :class="tab === 'systeem' ? 'border-blue-600 text-blue-600 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="px-4 py-2.5 border-b-2 text-sm transition-colors">
        <?php echo e(__('Systeem')); ?>

        <?php $afCount = \App\Models\AutofixProposal::where('created_at', '>=', now()->subDay())->count(); ?>
        <?php if($afCount > 0): ?>
            <span class="ml-1 bg-red-100 text-red-700 text-xs font-bold px-1.5 py-0.5 rounded-full"><?php echo e($afCount); ?></span>
        <?php endif; ?>
    </button>
</nav>


<div class="w-full">




<div x-show="tab === 'overzicht'" x-cloak class="w-full">

    
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-3xl font-bold text-blue-600"><?php echo e($organisatoren->where('is_sitebeheerder', false)->count()); ?></div>
            <div class="text-gray-500 text-sm"><?php echo e(__('Klanten (organisatoren)')); ?></div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-3xl font-bold text-green-600"><?php echo e($organisatoren->sum(fn($o) => $o->toernooien->count()) + $toernooienZonderOrganisator->count()); ?></div>
            <div class="text-gray-500 text-sm"><?php echo e(__('Toernooien totaal')); ?></div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-3xl font-bold text-purple-600"><?php echo e($organisatoren->sum(fn($o) => $o->toernooien->sum('judokas_count')) + $toernooienZonderOrganisator->sum('judokas_count')); ?></div>
            <div class="text-gray-500 text-sm"><?php echo e(__('Judoka\'s verwerkt')); ?></div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <?php
                $afgeslotenCount = $organisatoren->sum(fn($o) => $o->toernooien->whereNotNull('afgesloten_at')->count());
            ?>
            <div class="text-3xl font-bold text-orange-600"><?php echo e($afgeslotenCount); ?></div>
            <div class="text-gray-500 text-sm"><?php echo e(__('Toernooien afgerond')); ?></div>
        </div>
    </div>

    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="bg-blue-50 px-6 py-3 border-b">
                <h2 class="text-lg font-bold text-blue-800"><?php echo e(__('Vandaag & Binnenkort')); ?></h2>
            </div>
            <div class="p-4">
                <?php if($toernooienVandaag->count() > 0): ?>
                    <div class="mb-3">
                        <div class="text-xs font-semibold text-red-600 uppercase mb-1"><?php echo e(__('Vandaag')); ?></div>
                        <?php $__currentLoopData = $toernooienVandaag; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="flex items-center justify-between py-1">
                            <div class="flex items-center gap-2">
                                <span class="px-1.5 py-0.5 bg-red-100 text-red-700 rounded text-xs animate-pulse font-bold">LIVE</span>
                                <span class="font-medium text-sm"><?php echo e($t->naam); ?></span>
                            </div>
                            <div class="text-xs text-gray-500">
                                <?php echo e($t->organisator?->naam); ?> &middot; <?php echo e($t->judokas_count); ?> <?php echo e(__('judoka\'s')); ?>

                            </div>
                        </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                <?php endif; ?>

                <?php if($toernooienDezeWeek->count() > 0): ?>
                    <div class="mb-3">
                        <div class="text-xs font-semibold text-orange-600 uppercase mb-1"><?php echo e(__('Deze week')); ?></div>
                        <?php $__currentLoopData = $toernooienDezeWeek; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="flex items-center justify-between py-1">
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-orange-600 font-medium"><?php echo e($t->datum->format('D d M')); ?></span>
                                <span class="text-sm"><?php echo e($t->naam); ?></span>
                            </div>
                            <div class="text-xs text-gray-500">
                                <?php echo e($t->organisator?->naam); ?> &middot; <?php echo e($t->judokas_count); ?> <?php echo e(__('judoka\'s')); ?>

                            </div>
                        </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                <?php endif; ?>

                <?php if($toernooienKomendeMaand->count() > 0): ?>
                    <div>
                        <div class="text-xs font-semibold text-gray-500 uppercase mb-1"><?php echo e(__('Komende 30 dagen')); ?></div>
                        <?php $__currentLoopData = $toernooienKomendeMaand; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="flex items-center justify-between py-1">
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-500"><?php echo e($t->datum->format('d M')); ?></span>
                                <span class="text-sm"><?php echo e($t->naam); ?></span>
                            </div>
                            <div class="text-xs text-gray-500">
                                <?php echo e($t->organisator?->naam); ?> &middot; <?php echo e($t->judokas_count); ?> <?php echo e(__('judoka\'s')); ?>

                            </div>
                        </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                <?php endif; ?>

                <?php if($toernooienVandaag->count() === 0 && $toernooienDezeWeek->count() === 0 && $toernooienKomendeMaand->count() === 0): ?>
                    <div class="text-sm text-gray-400 italic py-2"><?php echo e(__('Geen toernooien gepland')); ?></div>
                <?php endif; ?>
            </div>
        </div>

        
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="bg-purple-50 px-6 py-3 border-b">
                <h2 class="text-lg font-bold text-purple-800"><?php echo e(__('Klant Gezondheid')); ?></h2>
            </div>
            <div class="grid grid-cols-2 gap-4 p-4">
                <div class="bg-green-50 rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-green-700"><?php echo e($klantenActief); ?></div>
                    <div class="text-xs text-gray-500"><?php echo e(__('Actief')); ?></div>
                    <div class="text-xs text-gray-400"><?php echo e(__('< 7 dagen')); ?></div>
                </div>
                <div class="bg-yellow-50 rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-yellow-700"><?php echo e($klantenInactief); ?></div>
                    <div class="text-xs text-gray-500"><?php echo e(__('Inactief')); ?></div>
                    <div class="text-xs text-gray-400">7-30 <?php echo e(__('dagen')); ?></div>
                </div>
                <div class="bg-red-50 rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-red-700"><?php echo e($klantenRisico); ?></div>
                    <div class="text-xs text-gray-500"><?php echo e(__('Risico')); ?></div>
                    <div class="text-xs text-gray-400">> 30 <?php echo e(__('dagen')); ?></div>
                </div>
                <div class="bg-blue-50 rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-blue-700"><?php echo e($klantenNieuw); ?></div>
                    <div class="text-xs text-gray-500"><?php echo e(__('Nieuw deze maand')); ?></div>
                </div>
            </div>
        </div>
    </div>

    
    <?php
        $actieveOrganisatoren = $organisatoren->where('is_sitebeheerder', false)
            ->filter(fn($o) => $o->laatste_login && $o->laatste_login->diffInHours() < 24)
            ->sortByDesc('laatste_login');
    ?>
    <?php if($actieveOrganisatoren->count() > 0): ?>
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="bg-green-50 px-6 py-3 border-b">
            <h2 class="text-lg font-bold text-green-800"><?php echo e(__('Nu actief')); ?> (<?php echo e(__('laatste 24 uur')); ?>)</h2>
        </div>
        <div class="divide-y divide-gray-100">
            <?php $__currentLoopData = $actieveOrganisatoren; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $org): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $laatsteToernooi = $org->toernooien->whereNull('afgesloten_at')->sortByDesc('updated_at')->first();
            ?>
            <div class="px-6 py-3 flex items-center justify-between hover:bg-gray-50">
                <div class="flex items-center gap-3">
                    <span class="w-2 h-2 bg-green-500 rounded-full <?php echo e($org->laatste_login->diffInMinutes() < 30 ? 'animate-pulse' : ''); ?>"></span>
                    <div>
                        <span class="font-medium text-sm"><?php echo e($org->naam); ?></span>
                        <?php if($laatsteToernooi): ?>
                            <span class="text-gray-400 mx-1">&rarr;</span>
                            <span class="text-sm text-gray-600"><?php echo e($laatsteToernooi->naam); ?></span>
                            <?php if($laatsteToernooi->weegkaarten_gemaakt_op): ?>
                                <span class="ml-1 px-1.5 py-0.5 bg-green-100 text-green-700 rounded text-xs"><?php echo e(__('Wedstrijddag')); ?></span>
                            <?php else: ?>
                                <span class="ml-1 px-1.5 py-0.5 bg-blue-100 text-blue-700 rounded text-xs"><?php echo e(__('Voorbereiding')); ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-xs text-gray-400">
                    <?php echo e($org->laatste_login->diffForHumans()); ?>

                </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>
    <?php endif; ?>

</div>




<div x-show="tab === 'klanten'" x-cloak class="w-full">

    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold text-gray-800"><?php echo e(__('Klanten & Toernooien')); ?></h2>
        <a href="<?php echo e(route('admin.klanten')); ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
            <?php echo e(__('Klantenbeheer')); ?>

        </a>
    </div>

    <?php $__currentLoopData = $organisatoren->where('is_sitebeheerder', false); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $organisator): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
        
        <div class="bg-gray-50 px-6 py-4 border-b">
            <div class="flex justify-between items-start">
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-bold text-gray-800"><?php echo e($organisator->naam); ?></h2>
                        <a href="<?php echo e(route('organisator.dashboard', ['organisator' => $organisator->slug])); ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium">&rarr; <?php echo e(__('Open dashboard')); ?></a>
                    </div>
                    <div class="text-sm text-gray-500 mt-1">
                        <span class="inline-flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <?php echo e($organisator->email); ?>

                        </span>
                        <?php if($organisator->telefoon): ?>
                        <span class="ml-4 inline-flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            <?php echo e($organisator->telefoon); ?>

                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-right">
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div>
                            <div class="text-2xl font-bold text-blue-600"><?php echo e($organisator->toernooien->count()); ?></div>
                            <div class="text-xs text-gray-500"><?php echo e(__('Toernooien')); ?></div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-green-600"><?php echo e($organisator->clubs_count ?? $organisator->clubs()->count()); ?></div>
                            <div class="text-xs text-gray-500"><?php echo e(__('Clubs')); ?></div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-purple-600"><?php echo e($organisator->toernooi_templates_count ?? $organisator->toernooiTemplates()->count()); ?></div>
                            <div class="text-xs text-gray-500"><?php echo e(__('Templates')); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-between items-center mt-3 pt-3 border-t border-gray-200 text-sm">
                <div class="flex gap-6 text-gray-500">
                    <span><?php echo e(__('Klant sinds')); ?>: <strong><?php echo e($organisator->created_at?->format('d-m-Y') ?? '-'); ?></strong></span>
                    <span><?php echo e(__('Laatste login')); ?>:
                        <?php if($organisator->laatste_login): ?>
                            <strong class="<?php echo e($organisator->laatste_login->diffInDays() > 30 ? 'text-orange-600' : 'text-green-600'); ?>">
                                <?php echo e($organisator->laatste_login->diffForHumans()); ?>

                            </strong>
                        <?php else: ?>
                            <strong class="text-gray-400"><?php echo e(__('Nooit')); ?></strong>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="flex gap-2">
                    <?php
                        $actief = $organisator->toernooien->whereNull('afgesloten_at')->count();
                        $afgerond = $organisator->toernooien->whereNotNull('afgesloten_at')->count();
                    ?>
                    <?php if($actief > 0): ?>
                        <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs"><?php echo e($actief); ?> <?php echo e(__('actief')); ?></span>
                    <?php endif; ?>
                    <?php if($afgerond > 0): ?>
                        <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs"><?php echo e($afgerond); ?> <?php echo e(__('afgerond')); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        
        <?php if($organisator->toernooien->count() > 0): ?>
        <table class="min-w-full">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase"><?php echo e(__('Toernooi')); ?></th>
                    <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase"><?php echo e(__('Datum')); ?></th>
                    <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase"><?php echo e(__('Judoka\'s')); ?></th>
                    <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase"><?php echo e(__('Poules')); ?></th>
                    <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase"><?php echo e(__('Status')); ?></th>
                    <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase"><?php echo e(__('Pakket')); ?></th>
                    <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase"><?php echo e(__('Laatst actief')); ?></th>
                    <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase"><?php echo e(__('Acties')); ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php $__currentLoopData = $organisator->toernooien->sortByDesc('datum'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $toernooi): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr class="hover:bg-gray-50 <?php echo e($toernooi->afgesloten_at ? 'bg-gray-50 opacity-75' : ''); ?>">
                    <td class="px-6 py-3 whitespace-nowrap">
                        <div class="font-medium"><?php echo e($toernooi->naam); ?></div>
                        <?php if($toernooi->organisatie && $toernooi->organisatie !== $organisator->naam): ?>
                            <div class="text-xs text-gray-400"><?php echo e($toernooi->organisatie); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap text-sm">
                        <?php if($toernooi->datum): ?>
                            <span class="<?php echo e($toernooi->datum->isPast() ? 'text-gray-500' : 'text-blue-600 font-medium'); ?>">
                                <?php echo e($toernooi->datum->format('d-m-Y')); ?>

                            </span>
                            <?php if($toernooi->datum->isToday()): ?>
                                <span class="ml-1 px-1.5 py-0.5 bg-red-100 text-red-700 rounded text-xs animate-pulse"><?php echo e(__('VANDAAG')); ?></span>
                            <?php elseif($toernooi->datum->isFuture()): ?>
                                <?php
                                    $totalDagen = (int) now()->diffInDays($toernooi->datum);
                                    $weken = (int) floor($totalDagen / 7);
                                    $dagen = $totalDagen % 7;
                                    $countdown = $weken . 'w ' . $dagen . 'd';
                                    $urgentClass = $totalDagen <= 7
                                        ? 'px-1.5 py-0.5 bg-orange-100 text-orange-700 rounded'
                                        : ($totalDagen <= 30 ? 'text-orange-600' : 'text-gray-500');
                                ?>
                                <span class="ml-1 text-xs <?php echo e($urgentClass); ?>"><?php echo e($countdown); ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap text-center">
                        <span class="font-medium"><?php echo e($toernooi->judokas_count); ?></span>
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap text-center text-sm"><?php echo e($toernooi->poules_count); ?></td>
                    <td class="px-6 py-3 whitespace-nowrap text-center">
                        <?php if($toernooi->afgesloten_at): ?>
                            <span class="px-2 py-1 bg-gray-200 text-gray-600 rounded text-xs"><?php echo e(__('Afgerond')); ?></span>
                        <?php elseif($toernooi->weegkaarten_gemaakt_op): ?>
                            <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs"><?php echo e(__('Wedstrijddag')); ?></span>
                        <?php elseif($toernooi->judokas_count > 0): ?>
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs"><?php echo e(__('Voorbereiding')); ?></span>
                        <?php else: ?>
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs"><?php echo e(__('Nieuw')); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap text-center">
                        <?php if($toernooi->isPaidTier()): ?>
                            <div class="inline-flex flex-col items-center">
                                <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-medium">&euro;<?php echo e($toernooi->toernooiBetaling?->bedrag ?? '?'); ?></span>
                                <span class="text-xs text-gray-500"><?php echo e(__('max')); ?> <?php echo e($toernooi->paid_max_judokas); ?></span>
                            </div>
                        <?php else: ?>
                            <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs"><?php echo e(__('Gratis')); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500">
                        <?php echo e($toernooi->updated_at?->diffForHumans() ?? '-'); ?>

                    </td>
                    <td class="px-6 py-3 whitespace-nowrap space-x-2">
                        <a href="<?php echo e(route('toernooi.show', $toernooi->routeParams())); ?>" class="text-blue-600 hover:text-blue-800 text-sm"><?php echo e(__('Open')); ?></a>
                        <button onclick="confirmDelete('<?php echo e($organisator->slug); ?>', '<?php echo e($toernooi->slug); ?>', '<?php echo e(addslashes($toernooi->naam)); ?>')" class="text-red-500 hover:text-red-700 text-sm"><?php echo e(__('Verwijder')); ?></button>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="px-6 py-4 text-gray-500 text-sm italic"><?php echo e(__('Nog geen toernooien aangemaakt')); ?></div>
        <?php endif; ?>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

    
    <?php if($organisatoren->where('is_sitebeheerder', true)->count() > 0): ?>
    <div class="mt-8 pt-8 border-t border-gray-300">
        <h2 class="text-lg font-semibold text-gray-600 mb-4"><?php echo e(__('Sitebeheerders (Havun)')); ?></h2>
        <?php $__currentLoopData = $organisatoren->where('is_sitebeheerder', true); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $organisator): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="bg-purple-50 rounded-lg shadow mb-4 p-4">
            <div class="flex justify-between items-center">
                <div>
                    <span class="text-purple-600 font-bold"><?php echo e($organisator->naam); ?></span>
                    <span class="text-gray-500 text-sm ml-2"><?php echo e($organisator->email); ?></span>
                </div>
                <div class="text-sm text-gray-500">
                    <?php echo e(__('Laatste login')); ?>: <?php echo e($organisator->laatste_login?->diffForHumans() ?? __('Nooit')); ?>

                </div>
            </div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
    <?php endif; ?>

    
    <?php if($toernooienZonderOrganisator->count() > 0): ?>
    <div class="bg-white rounded-lg shadow mb-6 overflow-hidden mt-8">
        <div class="bg-orange-50 px-6 py-4 border-b">
            <h2 class="text-lg font-bold text-orange-800"><?php echo e(__('Toernooien zonder organisator')); ?> (<?php echo e($toernooienZonderOrganisator->count()); ?>)</h2>
            <div class="text-sm text-orange-600"><?php echo e(__('Deze toernooien hebben geen gekoppelde klant - mogelijk legacy data')); ?></div>
        </div>
        <table class="min-w-full">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase"><?php echo e(__('Naam')); ?></th>
                    <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase"><?php echo e(__('Datum')); ?></th>
                    <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase"><?php echo e(__('Judoka\'s')); ?></th>
                    <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase"><?php echo e(__('Poules')); ?></th>
                    <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase"><?php echo e(__('Laatst gebruikt')); ?></th>
                    <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase"><?php echo e(__('Acties')); ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php $__currentLoopData = $toernooienZonderOrganisator; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $toernooi): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 whitespace-nowrap font-medium"><?php echo e($toernooi->naam); ?></td>
                    <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-600"><?php echo e($toernooi->datum?->format('d-m-Y') ?? '-'); ?></td>
                    <td class="px-6 py-3 whitespace-nowrap text-center text-sm"><?php echo e($toernooi->judokas_count); ?></td>
                    <td class="px-6 py-3 whitespace-nowrap text-center text-sm"><?php echo e($toernooi->poules_count); ?></td>
                    <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500"><?php echo e($toernooi->updated_at?->diffForHumans() ?? '-'); ?></td>
                    <td class="px-6 py-3 whitespace-nowrap space-x-2">
                        <?php if($toernooi->organisator): ?>
                        <a href="<?php echo e(route('toernooi.show', $toernooi->routeParams())); ?>" class="text-blue-600 hover:text-blue-800 text-sm"><?php echo e(__('Open')); ?></a>
                        <button onclick="confirmDelete('<?php echo e($toernooi->organisator->slug); ?>', '<?php echo e($toernooi->slug); ?>', '<?php echo e(addslashes($toernooi->naam)); ?>')" class="text-red-500 hover:text-red-700 text-sm"><?php echo e(__('Verwijder')); ?></button>
                        <?php else: ?>
                        <span class="text-gray-400 text-sm"><?php echo e(__('Geen organisator')); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>




<div x-show="tab === 'betalingen'" x-cloak class="w-full">

    <div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
        <div class="bg-green-50 px-6 py-3 border-b">
            <h2 class="text-lg font-bold text-green-800"><?php echo e(__('Omzet Overzicht')); ?></h2>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4">
            <div class="bg-green-50 rounded-lg p-3">
                <div class="text-2xl font-bold text-green-700">&euro;<?php echo e(number_format($omzetDezeMaand + $inschrijfgeldDezeMaand, 2, ',', '.')); ?></div>
                <div class="text-xs text-gray-500"><?php echo e(__('Deze maand')); ?></div>
                <?php if($omzetDezeMaand > 0 || $inschrijfgeldDezeMaand > 0): ?>
                <div class="text-xs text-gray-400 mt-1">
                    <?php echo e(__('Upgrades')); ?>: &euro;<?php echo e(number_format($omzetDezeMaand, 2, ',', '.')); ?> &middot;
                    <?php echo e(__('Inschrijfgeld')); ?>: &euro;<?php echo e(number_format($inschrijfgeldDezeMaand, 2, ',', '.')); ?>

                </div>
                <?php endif; ?>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-2xl font-bold text-gray-700">&euro;<?php echo e(number_format($omzetVorigeMaand, 2, ',', '.')); ?></div>
                <div class="text-xs text-gray-500"><?php echo e(__('Vorige maand')); ?></div>
                <div class="text-xs text-gray-400 mt-1"><?php echo e(__('Upgrades')); ?></div>
            </div>
            <div class="bg-blue-50 rounded-lg p-3">
                <div class="text-2xl font-bold text-blue-700">&euro;<?php echo e(number_format($omzetTotaal + $inschrijfgeldTotaal, 2, ',', '.')); ?></div>
                <div class="text-xs text-gray-500"><?php echo e(__('Totaal')); ?></div>
                <div class="text-xs text-gray-400 mt-1">
                    <?php echo e(__('Upgrades')); ?>: &euro;<?php echo e(number_format($omzetTotaal, 2, ',', '.')); ?> &middot;
                    <?php echo e(__('Inschrijfgeld')); ?>: &euro;<?php echo e(number_format($inschrijfgeldTotaal, 2, ',', '.')); ?>

                </div>
            </div>
            <div class="bg-orange-50 rounded-lg p-3">
                <div class="text-2xl font-bold <?php echo e($openBetalingen > 0 ? 'text-orange-700' : 'text-gray-400'); ?>"><?php echo e($openBetalingen); ?></div>
                <div class="text-xs text-gray-500"><?php echo e(__('Open betalingen')); ?></div>
                <?php if($actieveAbos > 0): ?>
                <div class="text-xs text-blue-600 mt-1"><?php echo e($actieveAbos); ?> <?php echo e(__('wimpel abo\'s')); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="flex justify-end">
        <a href="<?php echo e(route('admin.facturen')); ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
            <?php echo e(__('Factuuroverzicht openen')); ?> &rarr;
        </a>
    </div>

</div>




<div x-show="tab === 'activiteit'" x-cloak class="w-full">

    <div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
        <div class="bg-gray-50 px-6 py-3 border-b">
            <h2 class="text-lg font-bold text-gray-800"><?php echo e(__('Recente Activiteit')); ?></h2>
        </div>
        <div class="divide-y divide-gray-100">
            <?php $__empty_1 = true; $__currentLoopData = $recenteActiviteit; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="px-6 py-2 flex items-center justify-between hover:bg-gray-50">
                <div class="flex items-center gap-3">
                    <?php switch($log->interface):
                        case ('weging'): ?>
                            <span class="w-6 h-6 flex items-center justify-center bg-blue-100 text-blue-600 rounded text-xs" title="Weging">W</span>
                            <?php break; ?>
                        <?php case ('mat'): ?>
                            <span class="w-6 h-6 flex items-center justify-center bg-green-100 text-green-600 rounded text-xs" title="Mat">M</span>
                            <?php break; ?>
                        <?php case ('dashboard'): ?>
                            <span class="w-6 h-6 flex items-center justify-center bg-purple-100 text-purple-600 rounded text-xs" title="Dashboard">D</span>
                            <?php break; ?>
                        <?php case ('portaal'): ?>
                            <span class="w-6 h-6 flex items-center justify-center bg-orange-100 text-orange-600 rounded text-xs" title="Portaal">P</span>
                            <?php break; ?>
                        <?php default: ?>
                            <span class="w-6 h-6 flex items-center justify-center bg-gray-100 text-gray-600 rounded text-xs">-</span>
                    <?php endswitch; ?>
                    <div>
                        <span class="text-sm font-medium"><?php echo e($log->actor_naam ?? '-'); ?></span>
                        <span class="text-sm text-gray-500"><?php echo e($log->beschrijving); ?></span>
                    </div>
                </div>
                <div class="text-xs text-gray-400 whitespace-nowrap">
                    <?php if($log->toernooi): ?>
                        <span class="text-gray-500 mr-2"><?php echo e($log->toernooi->naam); ?></span>
                    <?php endif; ?>
                    <?php echo e($log->created_at?->diffForHumans()); ?>

                </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="px-6 py-4 text-sm text-gray-400 italic"><?php echo e(__('Geen recente activiteit')); ?></div>
            <?php endif; ?>
        </div>
    </div>

</div>




<div x-show="tab === 'systeem'" x-cloak class="w-full">

    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold text-gray-800"><?php echo e(__('Systeem Status')); ?></h2>
        <a href="<?php echo e(route('admin.autofix')); ?>" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 text-sm">
            <?php echo e(__('AutoFix openen')); ?> &rarr;
        </a>
    </div>

    <div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
        <div class="px-6 py-3 border-b <?php echo e($autofixVandaag > 5 ? 'bg-red-50' : ($autofixVandaag > 0 ? 'bg-orange-50' : 'bg-green-50')); ?>">
            <h2 class="text-lg font-bold <?php echo e($autofixVandaag > 5 ? 'text-red-800' : ($autofixVandaag > 0 ? 'text-orange-800' : 'text-green-800')); ?>">
                <?php echo e(__('AutoFix & Errors')); ?>

            </h2>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4">
            <div class="text-center">
                <div class="text-2xl font-bold <?php echo e($autofixVandaag > 5 ? 'text-red-600' : ($autofixVandaag > 0 ? 'text-orange-600' : 'text-green-600')); ?>"><?php echo e($autofixVandaag); ?></div>
                <div class="text-xs text-gray-500"><?php echo e(__('Errors vandaag')); ?></div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold <?php echo e($autofixPending > 0 ? 'text-orange-600' : 'text-gray-400'); ?>"><?php echo e($autofixPending); ?></div>
                <div class="text-xs text-gray-500"><?php echo e(__('Pending fixes')); ?></div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-green-600"><?php echo e($autofixApplied); ?></div>
                <div class="text-xs text-gray-500"><?php echo e(__('Gefixt vandaag')); ?></div>
            </div>
            <div class="text-center">
                <div class="text-sm font-medium text-gray-600">
                    <?php echo e($laatsteError ? $laatsteError->diffForHumans() : __('Geen errors')); ?>

                </div>
                <div class="text-xs text-gray-500"><?php echo e(__('Laatste error')); ?></div>
            </div>
        </div>
        <div class="px-4 pb-3 flex gap-4 text-xs text-gray-400">
            <span>PHP <?php echo e(PHP_VERSION); ?></span>
            <span>Laravel <?php echo e(app()->version()); ?></span>
        </div>
    </div>

</div>

</div>

</div>

<!-- Hidden form for delete -->
<form id="delete-form" method="POST" style="display:none;">
    <?php echo csrf_field(); ?>
    <?php echo method_field('DELETE'); ?>
    <input type="hidden" name="bewaar_presets" id="bewaar-presets" value="0">
</form>

<script>
function confirmDelete(orgSlug, slug, naam) {
    if (confirm(`VERWIJDER "${naam}" PERMANENT?\n\nDit verwijdert:\n- Alle judoka's\n- Alle poules en wedstrijden\n- Alle instellingen\n\nDIT KAN NIET ONGEDAAN WORDEN!`)) {
        const form = document.getElementById('delete-form');
        form.action = `/${orgSlug}/toernooi/${slug}`;
        form.submit();
    }
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/judotoernooi/staging/resources/views/pages/toernooi/index.blade.php ENDPATH**/ ?>