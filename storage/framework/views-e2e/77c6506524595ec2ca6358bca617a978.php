<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $__env->yieldContent('title', 'Gestión de clientes'); ?> · Lunnys Perú</title>
    <link rel="stylesheet" href="<?php echo e(asset('css/app.css')); ?>?v=4">
    <link rel="stylesheet" href="<?php echo e(asset('css/management.css')); ?>?v=15">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php echo $__env->make('layouts.session-monitor', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->yieldPushContent('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/record-tables.css')); ?>?v=1">
    <script src="<?php echo e(asset('js/validation-es.js')); ?>?v=1" defer></script>
    <script src="<?php echo e(asset('js/sidebar.js')); ?>?v=1" defer></script>
</head>
<body class="workspace-shell">
<a class="skip-content" href="#module-content">Ir al contenido</a>
<header class="workspace-topbar">
    <a class="workspace-brand" href="<?php echo e(route('dashboard')); ?>"><img class="brand-logo" src="<?php echo e(asset('images/lunnys-peru.png')); ?>" alt="Logo de Lunnys Perú"><span>Lunnys <strong>Perú</strong><small>PLATAFORMA DE GESTIÓN</small></span></a>
    <div class="workspace-account">
        <?php if(auth()->guard()->check()): ?>
            <?php ($account = auth()->user()); ?>
            <div class="workspace-account-summary" aria-label="Cuenta <?php echo e($account->nombre_usuario); ?> · <?php echo e($account->role_names ?: 'Sin rol asignado'); ?>">
                <span class="workspace-account-avatar" aria-hidden="true"><?php echo e(mb_strtoupper(mb_substr($account->nombre_usuario ?: '?', 0, 1))); ?></span>
                <span class="workspace-account-identity"><strong><?php echo e($account->nombre_usuario); ?></strong><small><?php echo e($account->role_names ?: 'Sin rol asignado'); ?></small></span>
            </div>
        <?php endif; ?>
        <form method="POST" action="<?php echo e(route('logout')); ?>"><?php echo csrf_field(); ?><button class="secondary" type="submit">Cerrar sesión</button></form>
    </div>
</header>
<div class="workspace-body">
    <aside class="workspace-sidebar" aria-label="Menú de módulos">
        <details class="sidebar-disclosure" open>
            <summary class="sidebar-disclosure-toggle"><span class="sidebar-disclosure-label"><i class="bi bi-list" aria-hidden="true"></i> Menú de módulos</span><span class="nav-chevron" aria-hidden="true"></span></summary>
            <div class="module-menu">
                <nav aria-label="Navegación principal">
                    <?php echo $__env->make('access.navigation', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                </nav>
            </div>
        </details>

    </aside>
    <main class="workspace-main" id="module-content" tabindex="-1"><?php echo $__env->yieldContent('workspace-content'); ?></main>
</div>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\Capstone\Capstone_Proyecto\resources\views/layouts/management.blade.php ENDPATH**/ ?>