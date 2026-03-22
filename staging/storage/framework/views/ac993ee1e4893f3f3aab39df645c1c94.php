<?php $__env->startSection('title', $toernooi->naam); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('pages.toernooi.dashboard', ['toernooi' => $toernooi, 'statistieken' => $statistieken], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/judotoernooi/staging/resources/views/pages/toernooi/show.blade.php ENDPATH**/ ?>