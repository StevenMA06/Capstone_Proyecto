<div class="table-scroll">
<table class="users-table inventory-table">
    <caption class="sr-only">Existencias actuales de <?php echo e($kind === 'equipment' ? 'equipos' : 'materiales'); ?></caption>
    <thead><tr>
        <th scope="col"><?php echo e($kind === 'equipment' ? 'Equipo' : 'Material'); ?></th>
        <th scope="col" class="stock-column"><?php echo e($kind === 'equipment' ? 'Disponibles' : 'Stock actual'); ?></th>
    </tr></thead>
    <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $records; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr>
            <td><strong><?php echo e($item->nombre); ?></strong><small class="table-subtitle"><?php echo e($kind === 'equipment' ? implode(' · ', array_filter([$item->tipo, $item->marca, $item->modelo])) : $item->codigo); ?></small></td>
            <td class="stock-column">
                <strong><?php echo e($kind === 'equipment' ? number_format($item->stock_actual) : rtrim(rtrim(number_format($item->stock_actual, 3, '.', ','), '0'), '.')); ?> <?php echo e($kind === 'equipment' ? 'unid.' : $item->unidad_medida); ?></strong>
                <?php if($kind === 'equipment'): ?>
                    <?php if($item->stock_actual <= $item->stock_minimo): ?><span class="stock-warning">⚠ Stock bajo</span><?php endif; ?>
                    <small class="table-subtitle">Mínimo recomendado: <?php echo e($item->stock_minimo + 0); ?></small>
                <?php else: ?>
                    <?php if($item->stock_actual <= $item->stock_minimo): ?><span class="stock-warning">⚠ Stock mínimo</span><?php endif; ?>
                    <small class="table-subtitle">Mínimo: <?php echo e($item->stock_minimo + 0); ?></small>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="2" class="empty-state">No hay <?php echo e($kind === 'equipment' ? 'equipos' : 'materiales'); ?> activos registrados.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?php /**PATH C:\xampp\htdocs\Capstone\Capstone_Proyecto\resources\views/operations/inventory-table.blade.php ENDPATH**/ ?>