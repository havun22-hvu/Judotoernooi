<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['toernooi']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['toernooi']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<?php if($toernooi->isFreeTier()): ?>
    <?php
        $huidige = $toernooi->judokas()->count();
        $max = $toernooi->getEffectiveMaxJudokas();
        $percentage = $max > 0 ? round(($huidige / $max) * 100) : 0;
    ?>

    <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border border-yellow-200 rounded-lg p-4 mb-6">
        <div class="flex items-start justify-between">
            <div class="flex-1">
                <div class="flex items-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 mr-2">
                        <?php echo e(__('Gratis Tier')); ?>

                    </span>
                    <span class="text-sm text-gray-600"><?php echo e($huidige); ?>/<?php echo e($max); ?> <?php echo e(__("judoka's")); ?></span>
                </div>

                
                <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                    <div class="h-2 rounded-full <?php echo e($percentage >= 90 ? 'bg-red-500' : ($percentage >= 70 ? 'bg-yellow-500' : 'bg-green-500')); ?>"
                         style="width: <?php echo e(min($percentage, 100)); ?>%"></div>
                </div>

                <?php if($percentage >= 80): ?>
                    <p class="mt-2 text-sm text-orange-700">
                        <strong><?php echo e(__('Let op:')); ?></strong> <?php echo e(__("Je nadert de limiet van :max judoka's.", ['max' => $max])); ?>

                        <a href="<?php echo e(route('toernooi.upgrade', $toernooi->routeParams())); ?>" class="underline font-medium"><?php echo e(__('Upgrade nu')); ?></a> <?php echo e(__('voor meer ruimte.')); ?>

                    </p>
                <?php endif; ?>

                <p class="mt-2 text-xs text-gray-500">
                    <?php echo e(__('Printen van poules en wedstrijdschema\'s beschikbaar bij betaald pakket.')); ?>

                    <a href="<?php echo e(route('toernooi.upgrade', $toernooi->routeParams())); ?>" class="text-blue-600 hover:underline"><?php echo e(__('Bekijk upgrade opties')); ?></a>
                </p>
            </div>

            <a href="<?php echo e(route('toernooi.upgrade', $toernooi->routeParams())); ?>"
               class="ml-4 inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                <?php echo e(__('Upgrade')); ?>

            </a>
        </div>
    </div>
<?php elseif($toernooi->isWimpelAbo()): ?>
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mr-2">
                    <?php echo e(__('Wimpel Abonnement')); ?>

                </span>
                <span class="text-sm text-gray-600"><?php echo e(__('Onbeperkt puntencompetitie')); ?></span>
            </div>
            <?php if($toernooi->organisator?->wimpelAboBijnaVerlopen()): ?>
                <span class="text-xs text-orange-600 font-medium">
                    <?php echo e(__('Abo verloopt :datum', ['datum' => $toernooi->organisator->wimpel_abo_einde->format('d-m-Y')])); ?>

                </span>
            <?php else: ?>
                <span class="text-xs text-blue-600"><?php echo e(__('Alle functies actief')); ?></span>
            <?php endif; ?>
        </div>
    </div>
<?php elseif($toernooi->isPaidTier()): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 mr-2">
                    <?php echo e(__('Betaald')); ?>

                </span>
                <span class="text-sm text-gray-600">
                    <?php echo e(__(':tier staffel - max :max judoka\'s', ['tier' => $toernooi->paid_tier, 'max' => $toernooi->paid_max_judokas])); ?>

                </span>
            </div>
            <span class="text-xs text-green-600"><?php echo e(__('Alle functies actief')); ?></span>
        </div>
    </div>
<?php endif; ?>
<?php /**PATH /var/www/judotoernooi/staging/resources/views/components/freemium-banner.blade.php ENDPATH**/ ?>