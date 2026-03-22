<?php $__env->startSection('title', __('Importeer Deelnemers')); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-2xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-8"><?php echo e(__('Deelnemers Importeren')); ?></h1>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-bold mb-4"><?php echo e(__('Bestandsformaat')); ?></h2>
        <p class="text-gray-600 mb-4"><?php echo e(__('Upload een CSV of Excel bestand met de volgende kolommen:')); ?></p>
        <ul class="list-disc list-inside text-gray-600 space-y-1">
            <li><strong><?php echo e(__('Naam')); ?></strong> (<?php echo e(__('verplicht')); ?>)</li>
            <li><strong><?php echo e(__('Geboortejaar')); ?></strong> (<?php echo e(__('verplicht')); ?>)</li>
            <li><strong><?php echo e(__('Geslacht')); ?></strong> (<?php echo e(__('M of V')); ?>)</li>
            <li><strong><?php echo e(__('Band')); ?></strong> (<?php echo e(__('wit, geel, oranje, groen, blauw, bruin, zwart')); ?>)</li>
            <li><strong><?php echo e(__('Club')); ?></strong></li>
            <li><strong><?php echo e(__('Gewicht')); ?></strong></li>
        </ul>
    </div>

    <form action="<?php echo e(route('toernooi.judoka.import.store', $toernooi->routeParams())); ?>" method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow p-6" data-loading="<?php echo e(__('Bestand importeren...')); ?>">
        <?php echo csrf_field(); ?>

        <div class="mb-6">
            <label for="bestand" class="block text-gray-700 font-bold mb-2"><?php echo e(__('Selecteer Bestand')); ?> *</label>
            <input type="file" name="bestand" id="bestand" accept=".csv,.xlsx,.xls"
                   class="w-full border rounded px-3 py-2 <?php $__errorArgs = ['bestand'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" required>
            <?php $__errorArgs = ['bestand'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <p class="text-red-500 text-sm mt-1"><?php echo e($message); ?></p>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            <p class="text-gray-500 text-sm mt-1"><?php echo e(__('Ondersteunde formaten: CSV, XLSX, XLS')); ?></p>
        </div>

        <div class="flex justify-end space-x-4">
            <a href="<?php echo e(route('toernooi.judoka.index', $toernooi->routeParams())); ?>" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                <?php echo e(__('Annuleren')); ?>

            </a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                <?php echo e(__('Importeren')); ?>

            </button>
        </div>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/judotoernooi/staging/resources/views/pages/judoka/import.blade.php ENDPATH**/ ?>