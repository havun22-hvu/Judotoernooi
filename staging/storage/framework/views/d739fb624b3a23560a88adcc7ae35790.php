<?php $__env->startSection('title', __('Mat Interface')); ?>

<?php $__env->startPush('styles'); ?>
<style>
    input[type="number"] { -moz-appearance: textfield; appearance: textfield; }
    input[type="number"]::-webkit-outer-spin-button,
    input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

    .sortable-bracket-ghost { opacity: 0.4; background: #dbeafe !important; }
    .sortable-bracket-chosen { opacity: 0.3; }
    /* Drop target highlight */
    .sortable-drop-highlight { outline: 3px solid #a855f7; outline-offset: -1px; background: #f3e8ff !important; }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="mb-4">
    <h1 class="text-2xl font-bold text-gray-800">🥋 <?php echo e(__('Mat Interface')); ?></h1>
</div>

<?php echo $__env->make('pages.mat.partials._content', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<!-- Pusher for Reverb WebSocket -->
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>

<!-- SortableJS for touch drag & drop in bracket -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<?php echo $__env->make('partials.mat-updates-listener', [
    'toernooi' => $toernooi,
    'matId' => $matNummer ?? null
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/judotoernooi/staging/resources/views/pages/mat/interface-admin.blade.php ENDPATH**/ ?>