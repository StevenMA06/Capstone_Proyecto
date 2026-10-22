<?php $__env->startSection('title', 'Dashboard'); ?>
<?php $__env->startPush('styles'); ?><link rel="stylesheet" href="<?php echo e(asset('css/dashboard.css')); ?>?v=7"><script src="<?php echo e(asset('js/dashboard.js')); ?>?v=1" defer></script><?php $__env->stopPush(); ?>
<?php $__env->startSection('workspace-content'); ?>
<section class="overview">
    <header class="overview-heading">
        <div><span class="eyebrow">RESUMEN DE TU OPERACIÓN</span><h1><?php echo e(auth()->user()->isAdministrator() ? 'Panel de Administración' : 'Panel de Gerencia'); ?></h1>
        <nav class="workspace-breadcrumb" aria-label="Ruta de navegación"><span>Inicio</span><span aria-hidden="true">/</span><span aria-current="page">Dashboard</span></nav></div>
        <p class="overview-greeting">Hola, <strong><?php echo e(auth()->user()->name); ?></strong><br><span>Bienvenido a Lunnys Perú</span></p>
    </header>
    <div class="overview-metrics">
        <?php if($clientCount !== null): ?>
        <a class="metric-card summary-metric" <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('clients.view')): ?> href="<?php echo e(route('clients.index')); ?>" <?php endif; ?>><h2>Clientes registrados</h2><p class="metric-value"><?php echo e(number_format($clientCount)); ?></p><?php echo $__env->make('layouts.module-icon', ['icon' => 'users'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></a>
        <?php endif; ?>
        <?php if($summary !== null): ?>
        <?php $__currentLoopData = [
            ['Contratos activos', 'contracts', 'file', []],
            ['Pagos vencidos', 'payments', 'money', ['status' => 'VENCIDO']],
            ['Instalaciones en proceso', 'installations', 'tool', []],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $key, $icon, $filters]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a class="metric-card summary-metric" <?php if(auth()->user()->hasPermission(\App\Support\AccessCatalog::forRoute($key.'.index') ?? '')): ?> href="<?php echo e(route($key.'.index', $filters)); ?>" <?php endif; ?>><h2><?php echo e($label); ?></h2><p class="metric-value"><?php echo e(number_format($summary[$key])); ?></p><?php echo $__env->make('layouts.module-icon', ['icon' => $icon], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php $__currentLoopData = ['equipment' => 'Equipos', 'materials' => 'Materiales']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <button type="button" class="metric-card inventory-trigger <?php echo e($summary[$key.'Low'] ? 'metric-alert' : ''); ?>" data-inventory-open="<?php echo e($key); ?>-dialog" aria-haspopup="dialog" aria-controls="<?php echo e($key); ?>-dialog">
            <span class="inventory-title"><?php echo e($label); ?></span>
            <?php echo $__env->make('layouts.module-icon', ['icon' => $key === 'equipment' ? 'router' : 'box'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <span class="metric-note">Haz clic para ver stock</span>
            <?php if($key === 'equipment'): ?>
                <?php if($summary[$key.'Low']): ?><span class="stock-warning">⚠ Stock bajo</span><?php endif; ?>
            <?php else: ?>
                <?php if($summary[$key.'Low']): ?><span class="stock-warning">⚠ Stock mínimo</span><?php endif; ?>
            <?php endif; ?>
        </button>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php endif; ?>
    </div>
    <?php if($summary !== null): ?>
    <section class="overview-chart" aria-labelledby="sales-heading">
        <header class="chart-heading"><div><h2 id="sales-heading">Resumen de ventas</h2><p>Últimos 6 meses · Ventas confirmadas · Mes actual en curso</p></div><div class="sales-total"><span>Total del período</span><strong>S/ <?php echo e(number_format($summary['salesTotal'], 2)); ?></strong></div></header>
        <?php if($summary['salesTotal'] == 0): ?><p class="chart-empty">Aún no hay ventas confirmadas en este período.</p><?php endif; ?>
        <ol class="sales-bars" aria-label="Ventas confirmadas por mes, en soles">
            <?php $__currentLoopData = $summary['months']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $month): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li><span class="bar-amount">S/ <?php echo e(number_format($month['total'], 2)); ?></span><div class="bar-track" aria-hidden="true"><div class="bar-fill" style="height: <?php echo e(round($month['total'] / $summary['salesMax'] * 100, 2)); ?>%"></div></div><span class="bar-month"><?php echo e($month['label']); ?></span></li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ol>
        <p class="chart-note">Indicadores calculados con los registros existentes.</p>
    </section>
    <?php endif; ?>
    <?php if($summary !== null): ?>
    <section class="overview-chart" aria-labelledby="top-plans-heading">
        <header class="chart-heading"><div><h2 id="top-plans-heading">Planes más vendidos</h2><p>Top 5 · Ventas confirmadas en los últimos 6 meses · Cantidad de ventas</p></div></header>
        <?php if($summary['topPlans']->isEmpty()): ?>
        <p class="chart-empty">Aún no hay ventas confirmadas de planes en este período.</p>
        <?php else: ?>
        <ol class="plan-ranking" aria-label="Planes ordenados por cantidad de ventas">
            <?php $__currentLoopData = $summary['topPlans']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $plan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li>
                <div class="plan-ranking-label"><strong><?php echo e($plan->nombre); ?></strong><span><?php echo e($plan->numero_plan); ?></span></div>
                <div class="plan-ranking-track" aria-hidden="true"><span style="width: <?php echo e(round($plan->total / $summary['topPlansMax'] * 100, 2)); ?>%"></span></div>
                <span class="plan-ranking-count"><?php echo e(number_format($plan->total)); ?> <?php echo e($plan->total == 1 ? 'venta' : 'ventas'); ?></span>
            </li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ol>
        <?php endif; ?>
    </section>
    <?php endif; ?>
    <?php if($summary !== null): ?>
        <?php $__currentLoopData = ['equipment' => 'Equipos', 'materials' => 'Materiales']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <dialog class="inventory-dialog" id="<?php echo e($key); ?>-dialog" aria-labelledby="<?php echo e($key); ?>-title">
            <header class="inventory-heading"><h2 id="<?php echo e($key); ?>-title">Inventario de <?php echo e(mb_strtolower($label)); ?></h2><form method="dialog"><button type="submit" class="secondary" autofocus aria-label="Cerrar inventario de <?php echo e(mb_strtolower($label)); ?>">Cerrar ×</button></form></header>
            <?php if($key === 'equipment'): ?>
                <p class="muted">Unidades disponibles para asignación, agrupadas por nombre, tipo, marca y modelo. Mínimo recomendado: <?php echo e(config('inventory.equipment_minimum')); ?> por grupo.</p>
            <?php else: ?>
                <p class="muted">Materiales activos con su stock actual y mínimo recomendado.</p>
            <?php endif; ?>
            <?php if($summary[$key.'Low']): ?>
                <?php if($key === 'equipment'): ?>
                    <p class="stock-warning" role="status">⚠ Stock bajo: <?php echo e($summary[$key.'Low']); ?> <?php echo e($summary[$key.'Low'] === 1 ? 'grupo de equipos' : 'grupos de equipos'); ?> con menos unidades disponibles de lo recomendado.</p>
                <?php else: ?>
                    <p class="stock-warning" role="status">⚠ Stock igual o inferior al mínimo: <?php echo e($summary[$key.'Low']); ?> <?php echo e($summary[$key.'Low'] === 1 ? 'material' : 'materiales'); ?>.</p>
                <?php endif; ?>
            <?php endif; ?>
            <?php echo $__env->make('operations.inventory-table', ['records' => $summary[$key], 'kind' => $key], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <footer class="inventory-footer"><a class="action-link" <?php if(auth()->user()->hasPermission(\App\Support\AccessCatalog::forRoute($key.'.index') ?? '')): ?> href="<?php echo e(route($key.'.index')); ?>" <?php endif; ?>>Abrir <?php echo e(mb_strtolower($label)); ?> →</a></footer>
        </dialog>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endif; ?>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.management', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\Capstone\Capstone_Proyecto\resources\views/dashboard.blade.php ENDPATH**/ ?>