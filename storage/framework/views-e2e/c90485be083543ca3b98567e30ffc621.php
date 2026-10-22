<?php $__env->startSection('title', 'Gestión de pagos'); ?>
<?php $__env->startPush('styles'); ?><link rel="stylesheet" href="<?php echo e(asset('css/toast.css')); ?>?v=1"><?php $__env->stopPush(); ?>
<?php $__env->startSection('workspace-content'); ?>
<section class="management clients-management">
    <nav class="workspace-breadcrumb" aria-label="Ruta de navegación"><a href="<?php echo e(route('dashboard')); ?>">Inicio</a><span aria-hidden="true">/</span><span>Gestión de pagos</span></nav>
    <header class="module-heading">
        <span class="eyebrow">ADMINISTRACIÓN FINANCIERA</span>
        <h1>Gestión de pagos</h1>
        <p>Registra, consulta y administra los pagos de tus contratos en un solo lugar.</p>
    </header>
    <?php if(session('status')): ?>
    <div class="client-toast" data-toast role="status" aria-live="polite" aria-atomic="true">
        <span><?php echo e(session('status')); ?></span><button type="button" data-toast-close aria-label="Cerrar notificación">&times;</button>
    </div>
    <script src="<?php echo e(asset('js/toast.js')); ?>" defer></script>
    <?php endif; ?>
    <?php if(session('error')): ?>
    <div class="notice error" role="alert">
        <strong><?php echo e(session('error')); ?></strong>
    </div>
    <?php endif; ?>
    <?php if($errors->any()): ?>
        <div class="notice error-summary" role="alert"><strong>Revisa los datos indicados:</strong>
            <ul><?php $__currentLoopData = $errors->messages(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $messages): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><a href="#<?php echo e($field); ?>"><?php echo e($messages[0]); ?></a></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul>
        </div>
    <?php endif; ?>
    <?php echo $__env->yieldContent('payments-content'); ?>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.management', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\Capstone\Capstone_Proyecto\resources\views/payments/layout.blade.php ENDPATH**/ ?>