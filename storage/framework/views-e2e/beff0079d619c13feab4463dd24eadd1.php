<?php $__env->startSection('title', 'Gestión de equipos'); ?>
<?php $__env->startPush('styles'); ?><link rel="stylesheet" href="<?php echo e(asset('css/toast.css')); ?>?v=1"><?php $__env->stopPush(); ?>
<?php $__env->startPush('styles'); ?><link rel="stylesheet" href="<?php echo e(asset('css/inventory.css')); ?>?v=2"><script src="<?php echo e(asset('js/inventory.js')); ?>?v=1" defer></script><?php $__env->stopPush(); ?>
<?php $__env->startSection('workspace-content'); ?>
<section class="management record-tables clients-management inventory-management">
    <nav class="workspace-breadcrumb" aria-label="Ruta de navegación"><a href="<?php echo e(route('dashboard')); ?>">Inicio</a><span aria-hidden="true">/</span><span>Gestión de equipos</span></nav>
    <header class="module-heading">
        <span class="eyebrow">INVENTARIO DE EQUIPOS</span>
        <h1>Gestión de equipos</h1>
        <p>Registra, consulta y administra todos los equipos de tu inventario con trazabilidad completa.</p>
    </header>
    <?php if(session('status')): ?>
    <div class="client-toast" data-toast role="status" aria-live="polite" aria-atomic="true">
        <span><?php echo e(session('status')); ?></span><button type="button" data-toast-close aria-label="Cerrar notificación">&times;</button>
    </div>
    <script src="<?php echo e(asset('js/toast.js')); ?>" defer></script>
    <?php endif; ?>
    <?php if($errors->any()): ?>
        <div class="notice error-summary" role="alert"><strong>Revisa los datos indicados:</strong>
            <ul><?php $__currentLoopData = $errors->messages(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $messages): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><a href="#<?php echo e($field); ?>"><?php echo e($messages[0]); ?></a></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul>
        </div>
    <?php endif; ?>
    <?php echo $__env->yieldContent('equipments-content'); ?>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.management', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\Capstone\Capstone_Proyecto\resources\views/equipments/layout.blade.php ENDPATH**/ ?>