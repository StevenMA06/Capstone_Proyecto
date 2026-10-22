<?php $__env->startSection('title', 'Panel de Call Center'); ?>
<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/call-center.css')); ?>?v=1">
<?php $__env->stopPush(); ?>
<?php $__env->startSection('workspace-content'); ?>
<?php
    $commercial = [
        ['office.coordination.index', 'Coordinar visitas', 'tool', 'Registra la fecha acordada con el cliente y da inicio al pedido de instalación.'],
        ['clients.index', 'Clientes', 'users', 'Consulta las fichas de clientes y sus datos de contacto.'],
        ['contracts.index', 'Contratos', 'file', 'Revisa los servicios contratados y la información de cada contrato.'],
        ['sales.index', 'Ventas', 'cart', 'Consulta las ventas y da seguimiento a la atención comercial.'],
        ['orders.index', 'Pedidos', 'tool', 'Revisa los pedidos y el avance de su atención.'],
        ['plans.index', 'Planes de servicio', 'file', 'Consulta las velocidades, tarifas y condiciones de los planes.'],
        ['payments.index', 'Pagos', 'money', 'Consulta los pagos y el estado de las obligaciones del cliente.'],
    ];
    $support = [
        ['zones.index', 'Zonas de cobertura', 'pin', 'Consulta las zonas donde se ofrece el servicio.'],
        ['equipments.availability', 'Disponibilidad de equipos', 'router', 'Consulta los equipos disponibles para la atención.'],
        ['materials.availability', 'Disponibilidad de materiales', 'box', 'Consulta las existencias de materiales.'],
        ['executions.index', 'Instalaciones', 'tool', 'Consulta el avance de las instalaciones.'],
    ];
    $allowed = fn ($item) => auth()->user()->hasPermission(\App\Support\AccessCatalog::forRoute($item[0]) ?? '');
    $commercial = array_values(array_filter($commercial, $allowed));
    $support = array_values(array_filter($support, $allowed));
?>
<div class="call-center-home">
    <nav class="cc-breadcrumb" aria-label="Ruta de navegación"><span>Inicio</span><span aria-hidden="true">/</span><span aria-current="page">Call Center</span></nav>
    <header class="cc-heading">
        <p class="cc-eyebrow">Atención comercial</p>
        <h1>Panel de Call Center</h1>
        <p>Hola, <?php echo e(auth()->user()->name); ?>. ¿Qué necesitas gestionar hoy?</p>
    </header>
    <?php if(count($commercial)): ?>
    <section class="cc-section" aria-labelledby="cc-commercial-title">
        <div class="cc-section-heading"><h2 id="cc-commercial-title">Gestión comercial</h2><p>Accede directamente a tus herramientas de trabajo.</p></div>
        <div class="cc-card-grid">
            <?php $__currentLoopData = $commercial; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$destination, $label, $icon, $description]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a class="cc-module-card" href="<?php echo e(route($destination)); ?>">
                <span class="cc-card-icon"><?php echo $__env->make('layouts.module-icon', ['icon' => $icon], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span>
                <span class="cc-card-copy"><strong><?php echo e($label); ?></strong><span><?php echo e($description); ?></span></span>
                <span class="cc-card-link">Abrir <?php echo e(mb_strtolower($label)); ?> <span aria-hidden="true">→</span></span>
            </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </section>
    <?php endif; ?>
    <?php if(count($support)): ?>
    <section class="cc-section cc-support" aria-labelledby="cc-support-title">
        <div class="cc-section-heading"><h2 id="cc-support-title">Consultas de apoyo</h2><p>Verifica la cobertura y los recursos para atender al cliente.</p></div>
        <div class="cc-support-grid">
            <?php $__currentLoopData = $support; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$destination, $label, $icon, $description]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a class="cc-support-link" href="<?php echo e(route($destination)); ?>">
                <span class="cc-support-icon"><?php echo $__env->make('layouts.module-icon', ['icon' => $icon], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span>
                <span><strong><?php echo e($label); ?></strong><small><?php echo e($description); ?></small></span>
                <span class="cc-arrow" aria-hidden="true">→</span>
            </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </section>
    <?php endif; ?>
    <?php if(!count($commercial) && !count($support)): ?>
    <div class="cc-empty"><h2>Aún no tienes herramientas asignadas</h2><p>Solicita al administrador los accesos necesarios para tu trabajo.</p></div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.management', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\Capstone\Capstone_Proyecto\resources\views/access/office.blade.php ENDPATH**/ ?>