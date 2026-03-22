<?php $__env->startSection('title', __('Upgrade Succesvol') . ' - ' . $toernooi->naam); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-2xl mx-auto text-center">
    <div class="bg-white rounded-lg shadow-lg p-8">
        
        <div class="mx-auto w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mb-6">
            <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        <h1 class="text-3xl font-bold text-gray-800 mb-4"><?php echo e(__('Upgrade Succesvol!')); ?></h1>

        <?php if($betaling->isBetaald()): ?>
            <p class="text-gray-600 mb-6">
                <?php echo e(__('Je toernooi :naam is succesvol geupgrade naar de :tier staffel.', ['naam' => $toernooi->naam, 'tier' => $betaling->tier])); ?>

            </p>

            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <h3 class="font-semibold text-green-800 mb-2"><?php echo e(__('Wat is er ontgrendeld?')); ?></h3>
                <ul class="text-sm text-green-700 space-y-1">
                    <li><?php echo e(__('Maximaal :aantal judoka\'s', ['aantal' => $betaling->max_judokas])); ?></li>
                    <li><?php echo e(__('Toegang tot Print/Noodplan functies')); ?></li>
                    <li><?php echo e(__('Alle premium functies')); ?></li>
                </ul>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 mb-6 text-sm text-gray-600">
                <p><strong><?php echo e(__('Betalingskenmerk:')); ?></strong> <?php echo e($betaling->stripe_payment_id ?? $betaling->mollie_payment_id); ?></p>
                <p><strong><?php echo e(__('Bedrag:')); ?></strong> &euro;<?php echo e(number_format($betaling->bedrag, 2, ',', '.')); ?></p>
                <p><strong><?php echo e(__('Betaald op:')); ?></strong> <?php echo e($betaling->betaald_op?->format('d-m-Y H:i')); ?></p>
            </div>
        <?php else: ?>
            <p class="text-gray-600 mb-6">
                <?php echo e(__('Je betaling wordt verwerkt. Je toernooi wordt automatisch geupgrade zodra de betaling is bevestigd.')); ?>

            </p>

            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                <p class="text-yellow-700 text-sm">
                    <?php echo e(__('Dit kan enkele seconden tot minuten duren. Vernieuw deze pagina om de status te controleren.')); ?>

                </p>
            </div>
        <?php endif; ?>

        <div class="flex justify-center">
            <a href="<?php echo e(route('toernooi.edit', $toernooi->routeParams())); ?>?tab=organisatie" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg text-lg">
                OK
            </a>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/judotoernooi/staging/resources/views/pages/toernooi/upgrade-succes.blade.php ENDPATH**/ ?>