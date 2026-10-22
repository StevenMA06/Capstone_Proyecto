<?php $__env->startSection('equipments-content'); ?>
<div class="client-detail">
    <div class="detail-header">
        <div><h2><?php echo e($equipment->nombre); ?></h2><p class="muted">Código: <?php echo e($equipment->codigo); ?> <?php if($equipment->numero_serie): ?> | Serie: <?php echo e($equipment->numero_serie); ?><?php endif; ?></p></div>
        <div class="detail-actions">
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'equipments.index')): ?><a class="clear-link" href="<?php echo e(route('equipments.index')); ?>">Volver al listado</a><?php endif; ?>

            <?php if($equipment->estado !== 'ASIGNADO'): ?><?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('equipments.manage')): ?>
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'equipments.edit')): ?><a href="<?php echo e(route('equipments.edit', $equipment)); ?>" class="action-link">Editar</a><?php endif; ?>
<?php endif; ?>
<?php endif; ?>
            <?php if($equipment->estado !== 'INACTIVO' && $equipment->estado !== 'ASIGNADO'): ?><?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('equipments.manage')): ?><form method="POST" action="<?php echo e(route('equipments.deactivate', $equipment)); ?>" style="display:inline;" onsubmit="return confirm('¿Inhabilitar este equipo?');"> <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?> <button type="submit" class="clear-link" style="cursor:pointer;">Inhabilitar</button></form><?php endif; ?>
<?php endif; ?>
            
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'equipments.kardex')): ?><a href="<?php echo e(route('equipments.kardex', $equipment)); ?>" class="action-link">Ver Kardex</a><?php endif; ?>

        </div>
    </div>
    
    <div class="detail-grid">
        <div class="detail-card"><h3>Información del equipo</h3><dl><dt>Tipo</dt><dd><?php echo e($equipment->tipo); ?></dd><dt>Marca</dt><dd><?php echo e($equipment->marca ?? '—'); ?></dd><dt>Modelo</dt><dd><?php echo e($equipment->modelo ?? '—'); ?></dd><dt>Condición</dt><dd><span class="state <?php echo e(in_array($equipment->condicion, ['NUEVO', 'BUENO']) ? 'active' : (in_array($equipment->condicion, ['MALO', 'MALOGRADO']) ? 'inactive' : '')); ?>"><?php echo e(ucfirst(strtolower($equipment->condicion))); ?></span></dd><dt>Estado</dt><dd><span class="state <?php echo e($equipment->estado === 'DISPONIBLE' ? 'active' : ($equipment->estado === 'INACTIVO' ? 'inactive' : '')); ?>"><?php echo e(ucfirst(strtolower($equipment->estado))); ?></span></dd><?php if($equipment->observaciones): ?><dt>Observaciones</dt><dd><?php echo e($equipment->observaciones); ?></dd><?php endif; ?></dl></div>
        
        <div class="detail-card"><h3>Registro</h3><dl><dt>Registrado por</dt><dd><?php echo e($equipment->creator->name ?? 'Usuario eliminado'); ?></dd><dt>Fecha de registro</dt><dd><?php echo e($equipment->creado_en->format('d/m/Y H:i')); ?></dd><dt>Última actualización</dt><dd><?php echo e($equipment->actualizado_en->format('d/m/Y H:i')); ?></dd></dl></div>
    </div>
    
    <div class="detail-card"><h3>Historial de movimientos Kardex (<?php echo e($movements->total()); ?>)</h3>
        <?php if($movements->count() > 0): ?>
        <div class="table-scroll"><table class="users-table"><thead><tr><th>Fecha</th><th>Movimiento</th><th>Cantidad</th><th>Responsable</th><th>Observación</th></tr></thead><tbody>
        <?php $__currentLoopData = $movements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $movement): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><?php echo e($movement->fecha_movimiento->format('d/m/Y H:i')); ?></td><td><strong><?php echo e($movement->tipo_movimiento); ?></strong></td><td><?php echo e(\App\Support\InventoryQuantity::format($movement->cantidad)); ?></td><td><?php echo e($movement->responsible->name ?? '—'); ?></td><td><?php echo e($movement->observacion ?? '—'); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody></table></div>
        <?php if($movements->hasPages()): ?> <?php echo $__env->make('clients.pagination', ['records' => $movements, 'label' => 'Paginación de movimientos'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php endif; ?>
        <?php else: ?>
        <p class="muted">No hay movimientos registrados para este equipo.</p>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('equipments.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\Capstone\Capstone_Proyecto\resources\views/equipments/show.blade.php ENDPATH**/ ?>