<?php $__env->startSection('payments-content'); ?>


<div class="payment-status-stats">
    <div class="stats-header">
        <h2>Estadísticas de Pagos</h2>
        
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'payments.index')): ?><form method="GET" action="<?php echo e(route('payments.index')); ?>" class="month-filter-form">
            <label for="month-filter">Mes:</label>
            <select 
                id="month-filter" 
                name="month"
                onchange="this.form.submit()"
                style="min-width: 200px; padding: 6px 12px; border-radius: 4px; border: 1px solid #d1d5db;"
            >
                <option value="">Todos los meses</option>
                <?php
                    // Generar meses: año actual y siguiente
                    $currentYear = now()->year;
                    $months = [];
                    
                    for ($y = $currentYear; $y <= $currentYear + 1; $y++) {
                        for ($m = 1; $m <= 12; $m++) {
                            $months[] = sprintf('%d-%02d', $y, $m);
                        }
                    }
                ?>
                
                <?php $__currentLoopData = $months; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $monthValue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option 
                        value="<?php echo e($monthValue); ?>" 
                        <?php if($selectedMonth === $monthValue): echo 'selected'; endif; ?>
                    >
                        <?php echo e(\Carbon\Carbon::parse($monthValue . '-01')->locale('es')->isoFormat('MMMM [de] YYYY')); ?>

                    </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </form><?php endif; ?>

    </div>
    
    <div class="stats-grid">
        <?php
            $statusConfig = [
                'PENDIENTE' => [
                    'icon' => 'bi-clock-history',
                    'color' => '#f59e0b',
                    'label' => 'Pendientes',
                    'description' => 'Por cobrar'
                ],
                'VENCIDO' => [
                    'icon' => 'bi-exclamation-triangle-fill',
                    'color' => '#ef4444',
                    'label' => 'Vencidos',
                    'description' => 'Requieren atención'
                ],
                'ANULADO' => [
                    'icon' => 'bi-slash-circle',
                    'color' => '#6b7280',
                    'label' => 'Anulados',
                    'description' => 'Error administrativo'
                ],
                'REALIZADO' => [
                    'icon' => 'bi-check-circle-fill',
                    'color' => '#10b981',
                    'label' => 'Realizados',
                    'description' => 'Pagados'
                ]
            ];
            // Calcular total solo con los estados (excluir 'total' y 'month' del array $statistics)
            $totalPayments = ($statistics['PENDIENTE'] ?? 0) + 
                           ($statistics['VENCIDO'] ?? 0) + 
                           ($statistics['IMPAGO'] ?? 0) + 
                           ($statistics['ANULADO'] ?? 0) + 
                           ($statistics['REALIZADO'] ?? 0);
        ?>
        
        <?php $__currentLoopData = $statusConfig; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status => $config): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $count = $statistics[$status] ?? 0;
                $percentage = $totalPayments > 0 ? round(($count / $totalPayments) * 100) : 0;
                $isFiltered = request('status') === $status;
            ?>
            
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'payments.index')): ?><a 
                href="<?php echo e(route('payments.index', array_merge(request()->except('status', 'page'), ['status' => $status]))); ?>" 
                class="stat-card clickable <?php echo e($isFiltered ? 'filtered' : ''); ?> <?php echo e($status === 'PENDIENTE' || $status === 'VENCIDO' ? 'urgent' : ''); ?>"
                style="border-left-color: <?php echo e($config['color']); ?>;"
                title="Clic para filtrar por <?php echo e($config['label']); ?>"
            >
                <div class="stat-icon" style="background: <?php echo e($config['color']); ?>20;">
                    <i class="bi <?php echo e($config['icon']); ?>" style="font-size: 1.5rem; color: <?php echo e($config['color']); ?>;"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo e($config['label']); ?></h3>
                    <p class="stat-number"><?php echo e($count); ?></p>
                    <p class="stat-label"><?php echo e($config['description']); ?></p>
                    <?php if($totalPayments > 0): ?>
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
    
    <?php if($totalPayments === 0): ?>
        <p class="stats-empty">
            <?php if($selectedMonth): ?>
                No hay pagos en <?php echo e(\Carbon\Carbon::parse($selectedMonth)->locale('es')->isoFormat('MMMM [de] YYYY')); ?>.
            <?php else: ?>
                No hay pagos registrados en el sistema.
            <?php endif; ?>
        </p>
    <?php else: ?>
        <p class="stats-summary">
            Total de pagos 
            <?php if($selectedMonth): ?>
                en <?php echo e(\Carbon\Carbon::parse($selectedMonth)->locale('es')->isoFormat('MMMM [de] YYYY')); ?>

            <?php else: ?>
                en <strong>todos los meses</strong>
            <?php endif; ?>
            : 
            <strong><?php echo e($totalPayments); ?></strong>
        </p>
    <?php endif; ?>
</div>

<div class="clients-grid">
<aside class="client-filter-panel" aria-labelledby="filter-title">
    <h2 id="filter-title">Filtros y buscador</h2>
    <p class="muted">Encuentra los pagos que necesitas.</p>
    
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'payments.index')): ?><form class="filters client-filters" method="GET" action="<?php echo e(route('payments.index')); ?>">
        <div>
            <label for="search">Cliente o documento</label>
            <input id="search" name="search" value="<?php echo e(request('search')); ?>" placeholder="Buscar por documento…" maxlength="180">
            <small>Número de documento del cliente.</small>
        </div>
        <div>
            <label for="contract">Código de contrato</label>
            <input id="contract" name="contract" value="<?php echo e(request('contract')); ?>" placeholder="Ej: CON-2026-001" maxlength="40">
            <small>Buscar pagos de un contrato específico.</small>
        </div>
<!--         <fieldset>
            <legend>Estado del pago</legend>
            <?php $__currentLoopData = ['' => 'Todos los estados', 'PENDIENTE' => 'Pendiente', 'REALIZADO' => 'Realizado', 'VENCIDO' => 'Vencido', 'ANULADO' => 'Anulado']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <label class="state-option"><input type="radio" name="status" value="<?php echo e($value); ?>" <?php if((request('status') ?? '') === $value): echo 'checked'; endif; ?>><span><?php echo e($label); ?></span></label>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </fieldset> -->
        <div>
            <label for="from">Desde (vencimiento)</label>
            <input id="from" name="from" type="date" value="<?php echo e(request('from')); ?>">
        </div>
        <div>
            <label for="to">Hasta (vencimiento)</label>
            <input id="to" name="to" type="date" value="<?php echo e(request('to')); ?>">
        </div>
        <div class="filter-buttons">
            <button class="action-link" type="submit">Aplicar filtros</button>
            
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'payments.index')): ?><a class="clear-link" href="<?php echo e(route('payments.index')); ?>">Limpiar filtros</a><?php endif; ?>

        </div>
    </form><?php endif; ?>

</aside>
<section class="client-list-panel" aria-labelledby="list-title">
    <div class="management-toolbar">
        <div>
            <h2 id="list-title">Listado de pagos</h2>
            <p class="muted"><?php echo e($payments->total()); ?> <?php echo e($payments->total() === 1 ? 'pago encontrado' : 'pagos encontrados'); ?></p>
        </div>
        
    </div>
    <div class="table-scroll">
        <table class="users-table client-table">
            <caption class="sr-only">Pagos registrados</caption>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Cliente</th>
                    <th>Contrato</th>
                    <th>Monto</th>
                    <th>F. Vencimiento</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
                <td>
                    <strong><?php echo e($payment->codigo); ?></strong>
                    <?php if($payment->periodo_servicio): ?>
                        <small class="table-subtitle"><?php echo e($payment->periodo_servicio); ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="client-name"><?php echo e(trim(($payment->contract->client->nombres_razon_social ?? '') . ' ' . ($payment->contract->client->apellidos ?? ''))); ?></span>
                    <small class="table-subtitle"><?php echo e($payment->contract->client->tipo_documento ?? ''); ?> <?php echo e($payment->contract->client->numero_documento ?? ''); ?></small>
                </td>
                <td>
                    <strong><?php echo e($payment->contract->codigo ?? ''); ?></strong>
                    <?php if($payment->contract->plan): ?>
                        <small class="table-subtitle"><?php echo e($payment->contract->plan->nombre); ?></small>
                    <?php endif; ?>
                </td>
                <td><strong>S/ <?php echo e(number_format($payment->monto, 2)); ?></strong></td>
                <td>
                    <?php echo e(\Carbon\Carbon::parse($payment->fecha_vencimiento)->format('d/m/Y')); ?>

                    <?php
                        $vencimiento = \Carbon\Carbon::parse($payment->fecha_vencimiento);
                        $hoy = \Carbon\Carbon::today();
                        $diasRestantes = $hoy->diffInDays($vencimiento, false);
                    ?>
                    <?php if($payment->estado === 'PENDIENTE' && $diasRestantes <= 7 && $diasRestantes >= 0): ?>
                        <small class="table-subtitle" style="color: #d97706;">⚠ <?php echo e($diasRestantes); ?> <?php echo e($diasRestantes === 1 ? 'día' : 'días'); ?></small>
                    <?php elseif($payment->estado === 'PENDIENTE' && $diasRestantes < 0): ?>
                        <small class="table-subtitle" style="color: #dc2626;">⚠ Vencido</small>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="state <?php echo e($payment->estado === 'REALIZADO' ? 'active' : 
                        ($payment->estado === 'VENCIDO' || $payment->estado === 'IMPAGO' ? 'inactive' : '')); ?>" style="
                        <?php if($payment->estado === 'VENCIDO'): ?> background: #fee2e2; color: #991b1b; <?php endif; ?>
                        <?php if($payment->estado === 'IMPAGO'): ?> background: #ede9fe; color: #5b21b6; <?php endif; ?>
                        <?php if($payment->estado === 'PENDIENTE'): ?> background: #fef3c7; color: #92400e; <?php endif; ?>
                        <?php if($payment->estado === 'ANULADO'): ?> background: #e5e7eb; color: #374151; <?php endif; ?>
                    ">
                        <?php echo e(ucfirst(strtolower($payment->estado))); ?>

                    </span>
                    <?php if($payment->fecha_pago): ?>
                        <small class="table-subtitle"><?php echo e(\Carbon\Carbon::parse($payment->fecha_pago)->format('d/m/Y')); ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="row-actions">
                        
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'payments.show')): ?><a href="<?php echo e(route('payments.show', $payment)); ?>" aria-label="Ver detalle del pago <?php echo e($payment->codigo); ?>">Ver detalle</a><?php endif; ?>

                        <?php if($payment->estado === 'PENDIENTE' || $payment->estado === 'VENCIDO'): ?>
                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('payments.manage')): ?>
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'payments.edit')): ?><a href="<?php echo e(route('payments.edit', $payment)); ?>" aria-label="Validar pago <?php echo e($payment->codigo); ?>">Validar pago</a><?php endif; ?>
<?php endif; ?>
                        <?php endif; ?>
                        <?php if($payment->constancia_ruta && $payment->estado === 'REALIZADO'): ?>
                            <?php
                                $extension = strtolower(pathinfo($payment->constancia_ruta, PATHINFO_EXTENSION));
                                $isImage = in_array($extension, ['jpg', 'jpeg', 'png']);
                            ?>
                            
<?php if (\Illuminate\Support\Facades\Blade::check('routecan', 'payments.receipt')): ?><a href="<?php echo e(route('payments.receipt', $payment)); ?>" target="_blank" aria-label="Ver comprobante del pago <?php echo e($payment->codigo); ?>" title="Ver comprobante">
                                <?php if($isImage): ?>
                                    <i class="bi bi-file-earmark-image-fill" style="color: #087846;"></i> Comprobante
                                <?php else: ?>
                                    <i class="bi bi-file-pdf-fill" style="color: #dc2626;"></i> Comprobante
                                <?php endif; ?>
                            </a><?php endif; ?>

                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="7" class="empty-state">No se encontraron pagos con esos criterios.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php echo $__env->make('payments.pagination', ['records' => $payments, 'label' => 'Paginación de pagos'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</section>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('payments.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\Capstone\Capstone_Proyecto\resources\views/payments/index.blade.php ENDPATH**/ ?>