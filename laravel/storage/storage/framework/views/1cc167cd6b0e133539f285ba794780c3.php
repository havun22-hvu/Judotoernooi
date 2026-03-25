<?php $__env->startSection('title', __('Dashboard')); ?>

<?php $__env->startSection('content'); ?>
<div class="mb-8">
    <div class="flex justify-between items-start">
        <div>
            <h1 class="text-3xl font-bold text-gray-800"><?php echo e($toernooi->naam); ?></h1>
            <p class="text-gray-600"><?php echo e($toernooi->datum->format('d-m-Y')); ?><?php echo e($toernooi->organisatie ? ' - ' . $toernooi->organisatie : ''); ?></p>
        </div>
        <div class="flex items-center space-x-2">
            <a href="<?php echo e(route('toernooi.edit', $toernooi->routeParams())); ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <?php echo e(__('Instellingen')); ?>

            </a>
        </div>
    </div>

    <?php if($toernooi->max_judokas): ?>
    <div class="mt-4 flex items-center space-x-4">
        <div class="flex-1 bg-gray-200 rounded-full h-4 max-w-md">
            <div class="h-4 rounded-full <?php echo e($toernooi->bezettings_percentage >= 100 ? 'bg-red-500' : ($toernooi->bezettings_percentage >= 80 ? 'bg-orange-500' : 'bg-green-500')); ?>"
                 style="width: <?php echo e(min($toernooi->bezettings_percentage, 100)); ?>%"></div>
        </div>
        <span class="text-sm font-medium <?php echo e($toernooi->bezettings_percentage >= 100 ? 'text-red-600' : ($toernooi->bezettings_percentage >= 80 ? 'text-orange-600' : 'text-gray-600')); ?>">
            <?php echo e($statistieken['totaal_judokas']); ?> / <?php echo e($toernooi->max_judokas); ?> (<?php echo e($toernooi->bezettings_percentage); ?>%)
        </span>
    </div>
    <?php endif; ?>

    <?php if($toernooi->inschrijving_deadline): ?>
    <p class="text-sm mt-2 <?php echo e($toernooi->isInschrijvingOpen() ? 'text-green-600' : 'text-red-600'); ?>">
        <?php echo e(__('Inschrijving')); ?>: <?php echo e($toernooi->isInschrijvingOpen() ? __('Open tot') : __('Gesloten sinds')); ?> <?php echo e($toernooi->inschrijving_deadline->format('d-m-Y')); ?>

    </p>
    <?php endif; ?>
</div>


<?php if (isset($component)) { $__componentOriginal5181816604fa4f57b90925c6759c48bf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5181816604fa4f57b90925c6759c48bf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.freemium-banner','data' => ['toernooi' => $toernooi]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('freemium-banner'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['toernooi' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($toernooi)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5181816604fa4f57b90925c6759c48bf)): ?>
<?php $attributes = $__attributesOriginal5181816604fa4f57b90925c6759c48bf; ?>
<?php unset($__attributesOriginal5181816604fa4f57b90925c6759c48bf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5181816604fa4f57b90925c6759c48bf)): ?>
<?php $component = $__componentOriginal5181816604fa4f57b90925c6759c48bf; ?>
<?php unset($__componentOriginal5181816604fa4f57b90925c6759c48bf); ?>
<?php endif; ?>


<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-3xl font-bold text-blue-600"><?php echo e($statistieken['totaal_judokas']); ?></div>
        <div class="text-gray-600"><?php echo e(__("Judoka's")); ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-3xl font-bold text-green-600"><?php echo e($statistieken['totaal_poules']); ?></div>
        <div class="text-gray-600"><?php echo e(__('Poules')); ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-3xl font-bold text-orange-600"><?php echo e($statistieken['totaal_wedstrijden']); ?></div>
        <div class="text-gray-600"><?php echo e(__('Wedstrijden')); ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-3xl font-bold text-purple-600"><?php echo e($statistieken['aanwezig']); ?></div>
        <div class="text-gray-600"><?php echo e(__('Aanwezig')); ?></div>
    </div>
</div>

<?php if($toernooi->betaling_actief): ?>
<div class="bg-white rounded-lg shadow p-6 mb-8">
    <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <span class="text-green-600">€</span> <?php echo e(__('Betalingsoverzicht')); ?>

    </h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div>
            <div class="text-3xl font-bold text-green-600">&euro;<?php echo e(number_format($statistieken['totaal_ontvangen'] ?? 0, 2, ',', '.')); ?></div>
            <div class="text-gray-600"><?php echo e(__('Totaal ontvangen')); ?></div>
        </div>
        <div>
            <div class="text-3xl font-bold text-blue-600"><?php echo e($statistieken['betaald_judokas'] ?? 0); ?></div>
            <div class="text-gray-600"><?php echo e(__("Betaalde judoka's")); ?></div>
        </div>
        <div>
            <div class="text-3xl font-bold text-gray-600"><?php echo e($statistieken['aantal_betalingen'] ?? 0); ?></div>
            <div class="text-gray-600"><?php echo e(__('Transacties')); ?></div>
        </div>
    </div>
    <?php if(($statistieken['totaal_judokas'] - ($statistieken['betaald_judokas'] ?? 0)) > 0): ?>
    <p class="text-sm text-orange-600 mt-4">
        <?php echo e(__(':aantal judoka(\'s) nog niet betaald', ['aantal' => $statistieken['totaal_judokas'] - ($statistieken['betaald_judokas'] ?? 0)])); ?>

    </p>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4"><?php echo e(__('Voorbereiding')); ?></h2>
        <div class="space-y-3">
            <a href="<?php echo e(route('toernooi.edit', $toernooi->routeParams())); ?>" class="block bg-gray-100 hover:bg-gray-200 p-3 rounded">
                ⚙️ <?php echo e(__('Toernooi Instellingen')); ?>

            </a>
            <a href="<?php echo e(route('toernooi.club.index', $toernooi->routeParams())); ?>" class="block bg-blue-100 hover:bg-blue-200 p-3 rounded">
                🏢 <?php echo e(__('Clubs & Uitnodigingen')); ?>

            </a>
            <a href="<?php echo e(route('toernooi.judoka.import', $toernooi->routeParams())); ?>" class="block bg-blue-100 hover:bg-blue-200 p-3 rounded">
                📥 <?php echo e(__('Deelnemers Importeren')); ?>

            </a>
            <a href="<?php echo e(route('toernooi.judoka.index', $toernooi->routeParams())); ?>" class="block bg-blue-100 hover:bg-blue-200 p-3 rounded">
                👥 <?php echo e(__('Deelnemerslijst')); ?> (<?php echo e($statistieken['totaal_judokas']); ?>)
            </a>
            <?php if($statistieken['totaal_judokas'] > 0): ?>
                <?php
                    $nietGecategoriseerd = $toernooi->countNietGecategoriseerd();
                    $heeftOverlap = false;
                    if (!empty($toernooi->gewichtsklassen)) {
                        $classifier = new \App\Services\CategorieClassifier($toernooi->gewichtsklassen);
                        $heeftOverlap = !empty($classifier->detectOverlap());
                    }
                    $heeftCategorieProbleem = $nietGecategoriseerd > 0 || $heeftOverlap;
                ?>
                <?php if($heeftCategorieProbleem): ?>
                <div class="w-full bg-gray-200 p-3 rounded opacity-60 cursor-not-allowed">
                    <span class="text-gray-500">🎯 <?php echo e(__('Genereer Poule-indeling')); ?></span>
                    <p class="text-xs text-red-600 mt-1">
                        ⚠️ <?php echo e(__('Los eerst de categorie-problemen op')); ?>

                    </p>
                </div>
                <?php else: ?>
                <form action="<?php echo e(route('toernooi.poule.genereer', $toernooi->routeParams())); ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="w-full text-left bg-green-100 hover:bg-green-200 p-3 rounded">
                        🎯 <?php echo e(__('Genereer Poule-indeling')); ?>

                    </button>
                </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4"><?php echo e(__('Blok/Mat Indeling')); ?></h2>
        <div class="space-y-3">
            <?php if($statistieken['totaal_poules'] > 0): ?>
            <form action="<?php echo e(route('toernooi.blok.genereer-verdeling', $toernooi->routeParams())); ?>" method="POST" class="inline">
                <?php echo csrf_field(); ?>
                <button type="submit" class="w-full text-left bg-yellow-100 hover:bg-yellow-200 p-3 rounded">
                    📋 <?php echo e(__('Genereer Blok/Mat Verdeling')); ?>

                </button>
            </form>
            <?php endif; ?>
            <a href="<?php echo e(route('toernooi.blok.zaaloverzicht', $toernooi->routeParams())); ?>" class="block bg-yellow-100 hover:bg-yellow-200 p-3 rounded">
                🏟️ <?php echo e(__('Zaaloverzicht')); ?>

            </a>
            <a href="<?php echo e(route('toernooi.blok.index', $toernooi->routeParams())); ?>" class="block bg-yellow-100 hover:bg-yellow-200 p-3 rounded">
                ⏱️ <?php echo e(__('Blokken Beheer')); ?>

            </a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4"><?php echo e(__('Toernooidag')); ?></h2>
        <div class="space-y-3">
            <a href="<?php echo e(route('toernooi.weging.interface', $toernooi->routeParams())); ?>" class="block bg-purple-100 hover:bg-purple-200 p-3 rounded">
                ⚖️ <?php echo e(__('Weging Interface')); ?>

            </a>
            <a href="<?php echo e(route('toernooi.mat.interface', $toernooi->routeParams())); ?>" class="block bg-purple-100 hover:bg-purple-200 p-3 rounded">
                🥋 <?php echo e(__('Mat Interface')); ?>

            </a>
            <a href="<?php echo e(route('toernooi.afsluiten', $toernooi->routeParams())); ?>"
               class="block <?php echo e($toernooi->isAfgesloten() ? 'bg-green-100 hover:bg-green-200' : 'bg-red-100 hover:bg-red-200'); ?> p-3 rounded">
                <?php echo e($toernooi->isAfgesloten() ? '🏆 ' . __('Afgesloten - Bekijk Resultaten') : '🔒 ' . __('Toernooi Afsluiten')); ?>

            </a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4"><?php echo e(__('Per Leeftijdsklasse')); ?></h2>
        <div class="space-y-2">
            <?php $__empty_1 = true; $__currentLoopData = $statistieken['per_leeftijdsklasse']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $klasse => $aantal): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="flex justify-between">
                <span><?php echo e($klasse); ?></span>
                <span class="font-bold"><?php echo e($aantal); ?></span>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <p class="text-gray-500"><?php echo e(__('Nog geen deelnemers')); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/judotoernooi/staging/resources/views/pages/toernooi/dashboard.blade.php ENDPATH**/ ?>