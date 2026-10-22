<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $__env->yieldContent('title', 'Mi panel'); ?> · LUNNY’S PERU R.2.D.</title>
    <link rel="stylesheet" href="<?php echo e(asset('css/app.css')); ?>?v=5">
    <script src="<?php echo e(asset('js/login.js')); ?>" defer></script>
    <?php echo $__env->make('layouts.session-monitor', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->yieldPushContent('styles'); ?>
    <script src="<?php echo e(asset('js/validation-es.js')); ?>?v=1" defer></script>
</head>
<body class="<?php echo $__env->yieldContent('body-class'); ?>">
    <div class="page-decoration" aria-hidden="true"></div>
    <main><?php echo $__env->yieldContent('content'); ?></main>
    <footer><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/></svg> © <?php echo e(date('Y')); ?> LUNNY’S PERU R.2.D. Todos los derechos reservados.</footer>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\Capstone\Capstone_Proyecto\resources\views/layouts/app.blade.php ENDPATH**/ ?>