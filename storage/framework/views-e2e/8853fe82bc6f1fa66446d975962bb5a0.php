<?php $__env->startSection('equipments-content'); ?>


<div class="payment-status-stats">
    <div class="stats-header">
        <h2>Estadísticas de Equipos</h2>
    </div>
    
    <div class="stats-grid">
        <?php
            $statusConfig = [
                'DISPONIBLE' => ['icon' => 'bi-check-circle-fill', 'color' => '#10b981', 'label' => 'Disponibles'],
                'ASIGNADO' => ['icon' => 'bi-box-arrow-right', 'color' => '#3b82f6', 'label' => 'Asignados'],
                'MANTENIMIENTO' => ['icon' => 'bi-tools', 'color' => '#f59e0b', 'label' => 'En mantenimiento'],
                'INACTIVO' => ['icon' => 'bi-slash-circle', 'color' => '#6b7280', 'label' => 'Inactivos'],
            ];
            $totalEquipments = array_sum($statistics);
        ?>
        
        <?php $__currentLoopData = $statusConfig; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status => $config): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $count = $statistics[$status] ?? 0;
                $percentage = $totalEquipments > 0 ? round(($count / $totalEquipments) * 100) : 0;
                $isFiltered = request('estado') === $status;
            ?>
            
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'equipments.index')): ?><a 
                href="<?php echo e(route('equipments.index', array_merge(request()->except('estado', 'page'), ['estado' => $status]))); ?>" 
                class="stat-card clickable <?php echo e($isFiltered ? 'filtered' : ''); ?>"
                style="border-left-color: <?php echo e($config['color']); ?>;"
                title="Clic para filtrar por <?php echo e($config['label']); ?>"
            >
                <div class="stat-icon" style="background: <?php echo e($config['color']); ?>20;">
                    <i class="bi <?php echo e($config['icon']); ?>" style="font-size: 1.5rem; color: <?php echo e($config['color']); ?>;"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo e($config['label']); ?></h3>
                    <p class="stat-number"><?php echo e($count); ?></p>
                    <?php if($totalEquipments > 0): ?>
                        <div class="stat-progress">
                            <div class="stat-progress-bar" style="width: <?php echo e($percentage); ?>%; background: <?php echo e($config['color']); ?>;"></div>
                        </div>
                        <p class="stat-percentage"><?php echo e($percentage); ?>% del total</p>
                    <?php endif; ?>
                </div>
                <?php if($isFiltered): ?>
                    <span class="badge-filtered">🔍 Filtrado</span>
                <?php endif; ?>
            </a><?php endif; ?>

        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
</div>

<div class="clients-grid">
<aside class="client-filter-panel" aria-labelledby="filter-title">
    <h2 id="filter-title">Filtros y buscador</h2>
    <p class="muted">Encuentra el equipo que necesitas.</p>
    
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'equipments.index')): ?><form class="filters client-filters" method="GET" action="<?php echo e(route('equipments.index')); ?>">
        <div><label for="search">Código, nombre o serie</label><input id="search" name="search" value="<?php echo e(request('search')); ?>" placeholder="Buscar equipo…" maxlength="180"><small>Código, nombre, tipo, marca o número de serie.</small></div>
        <fieldset><legend>Estado del equipo</legend>
            <?php $__currentLoopData = ['' => 'Todos los estados', 'DISPONIBLE' => 'Disponible', 'ASIGNADO' => 'Asignado', 'MANTENIMIENTO' => 'Mantenimiento', 'INACTIVO' => 'Inactivo']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <label class="state-option"><input type="radio" name="estado" value="<?php echo e($value); ?>" <?php if((request('estado') ?? '') === $value): echo 'checked'; endif; ?>><span><?php echo e($label); ?></span></label>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </fieldset>
        <fieldset><legend>Condición del equipo</legend>
            <?php $__currentLoopData = ['' => 'Todas las condiciones', 'NUEVO' => 'Nuevo', 'BUENO' => 'Bueno', 'REGULAR' => 'Regular', 'MALO' => 'Malo', 'MALOGRADO' => 'Malogrado']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <label class="state-option"><input type="radio" name="condicion" value="<?php echo e($value); ?>" <?php if((request('condicion') ?? '') === $value): echo 'checked'; endif; ?>><span><?php echo e($label); ?></span></label>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </fieldset>
        <div class="filter-buttons"><button class="action-link" type="submit">Aplicar filtros</button>
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'equipments.index')): ?><a class="clear-link" href="<?php echo e(route('equipments.index')); ?>">Limpiar filtros</a><?php endif; ?>
</div>
    </form><?php endif; ?>

</aside>
<section class="client-list-panel" aria-labelledby="list-title">
    <div class="management-toolbar"><div><h2 id="list-title">Listado de equipos</h2><p class="muted"><?php echo e($equipments->total()); ?> <?php echo e($equipments->total() === 1 ? 'equipo encontrado' : 'equipos encontrados'); ?></p></div>
    <div style="display: flex; gap: 0.5rem;">
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('equipments.manage')): ?>
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'equipments.import')): ?><a class="action-link" href="<?php echo e(route('equipments.import')); ?>"><span aria-hidden="true">📁</span> Importar Excel</a><?php endif; ?>
<?php endif; ?>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('equipments.manage')): ?>
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'equipments.create')): ?><a class="action-link" href="<?php echo e(route('equipments.create')); ?>"><span aria-hidden="true">＋</span> Registrar equipo</a><?php endif; ?>
<?php endif; ?>
    </div>
    </div>
    <div class="table-scroll"><table class="users-table client-table"><caption class="sr-only">Equipos registrados</caption><thead><tr><th>Código</th><th>Nombre</th><th>Tipo</th><th>Número de serie</th><th>Condición</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
    <?php $__empty_1 = true; $__currentLoopData = $equipments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $equipment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <tr>
        <td><strong><?php echo e($equipment->codigo); ?></strong></td>
        <td>
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'equipments.show')): ?><a class="client-name" href="<?php echo e(route('equipments.show', $equipment)); ?>"><?php echo e($equipment->nombre); ?></a><?php endif; ?>
<small class="table-subtitle"><?php echo e($equipment->marca); ?> <?php echo e($equipment->modelo); ?></small></td>
        <td><?php echo e($equipment->tipo); ?></td>
        <td><?php echo e($equipment->numero_serie ?? '—'); ?></td>
        <td><span class="state <?php echo e(in_array($equipment->condicion, ['NUEVO', 'BUENO']) ? 'active' : (in_array($equipment->condicion, ['MALO', 'MALOGRADO']) ? 'inactive' : '')); ?>"><?php echo e(ucfirst(strtolower($equipment->condicion))); ?></span></td>
        <td><span class="state <?php echo e($equipment->estado === 'DISPONIBLE' ? 'active' : ($equipment->estado === 'INACTIVO' ? 'inactive' : '')); ?>" style="<?php if($equipment->estado === 'ASIGNADO'): ?> background: #dbeafe; color: #1e40af; <?php endif; ?> <?php if($equipment->estado === 'MANTENIMIENTO'): ?> background: #fef3c7; color: #92400e; <?php endif; ?>"><?php echo e(ucfirst(strtolower($equipment->estado))); ?></span></td>
        <td><div class="row-actions">
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'equipments.show')): ?><a href="<?php echo e(route('equipments.show', $equipment)); ?>">Ver detalle</a><?php endif; ?>
<?php if($equipment->estado !== 'ASIGNADO'): ?><?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('equipments.manage')): ?>
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'equipments.edit')): ?><a href="<?php echo e(route('equipments.edit', $equipment)); ?>">Editar</a><?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'equipments.kardex')): ?><a href="<?php echo e(route('equipments.kardex', $equipment)); ?>">Ver Kardex</a><?php endif; ?>
</div></td>
    </tr>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="7" class="empty-state">No se encontraron equipos con esos criterios.</td></tr><?php endif; ?>
    </tbody></table></div>
    <?php echo $__env->make('clients.pagination', ['records' => $equipments, 'label' => 'Paginación de equipos'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</section>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('equipments.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\Capstone\Capstone_Proyecto\resources\views/equipments/index.blade.php ENDPATH**/ ?>