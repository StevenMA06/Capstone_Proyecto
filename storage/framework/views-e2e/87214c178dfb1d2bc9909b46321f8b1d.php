<?php $__env->startSection('title', 'Pedidos de instalación'); ?>
<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/installations.css')); ?>?v=29">
<script src="<?php echo e(asset('js/installations.js')); ?>?v=12" defer></script>
<script src="<?php echo e(asset('js/resource-rejection.js')); ?>?v=1" defer></script>
<?php $__env->stopPush(); ?>
<?php $__env->startSection('workspace-content'); ?>
<?php
    $listingRoute = request()->routeIs('orders.assigned') ? 'orders.assigned' : 'orders.index';
?>
<nav class="workspace-breadcrumb" aria-label="Ruta de navegación"><a href="<?php echo e(route('dashboard')); ?>">Inicio</a><span aria-hidden="true">›</span><span aria-current="page">Pedidos de instalación</span></nav>
<header class="installation-heading order-heading"><div><p class="eyebrow">OPERACIONES</p><h1>Pedidos de instalación</h1><p class="muted">Sigue cada pedido desde la fecha acordada hasta la ejecución técnica. Cada ficha indica el siguiente paso.</p></div></header>
<?php if(session('status')): ?><div class="notice success" role="status"><?php echo e(session('status')); ?></div><?php endif; ?>
<?php if($errors->any()): ?><div class="notice" role="alert"><strong>Revisa los campos indicados:</strong><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p><?php echo e($error); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><?php endif; ?>

<div class="order-overview" aria-label="Resumen de pedidos">
    <a href="<?php echo e(route($listingRoute, ['estado' => 'PENDIENTE'])); ?>" class="order-overview-item"><span>Pendientes de preparación</span><strong><?php echo e($counts->get('PENDIENTE', 0)); ?></strong></a>
    <a href="<?php echo e(route($listingRoute, ['estado' => 'PROGRAMADO'])); ?>" class="order-overview-item"><span>Programados</span><strong><?php echo e($counts->get('PROGRAMADO', 0)); ?></strong></a>
    <a href="<?php echo e(route($listingRoute, ['estado' => 'REPROGRAMADO'])); ?>" class="order-overview-item"><span>Reprogramados</span><strong><?php echo e($counts->get('REPROGRAMADO', 0)); ?></strong></a>
    <a href="<?php echo e(route($listingRoute, ['estado' => 'EN_PROCESO'])); ?>" class="order-overview-item"><span>En proceso</span><strong><?php echo e($counts->get('EN_PROCESO', 0)); ?></strong></a>
    <a href="<?php echo e(route($listingRoute, ['estado' => 'COMPLETADO'])); ?>" class="order-overview-item"><span>Completados</span><strong><?php echo e($counts->get('COMPLETADO', 0)); ?></strong></a>
    <?php if(auth()->user()->hasPermission('orders.approve')): ?><a href="<?php echo e(route('orders.index', ['reprogramacion' => 'pendiente'])); ?>" class="order-overview-item"><span>Por aprobar en Gerencia</span><strong><?php echo e($pendingReprogrammingCount); ?></strong></a><?php endif; ?>
    <?php if(auth()->user()->hasPermission('orders.resources.approve')): ?><a href="<?php echo e(route('orders.index', ['recursos' => 'pendiente'])); ?>" class="order-overview-item"><span>Recursos por autorizar</span><strong><?php echo e($pendingResourceApprovalCount); ?></strong></a><?php endif; ?>
</div>

<form method="GET" action="<?php echo e(route($listingRoute)); ?>" class="installation-panel order-filters" data-installation-form>
    <label for="order-search">Pedido, contrato o cliente<input id="order-search" type="search" name="search" maxlength="150" value="<?php echo e(request('search')); ?>" placeholder="Buscar por código o nombre"></label>
    <label for="order-state">Estado<select id="order-state" name="estado"><option value="">Todos los estados</option><?php $__currentLoopData = $states; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $state): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($state); ?>" <?php if(request('estado') === $state): echo 'selected'; endif; ?>><?php echo e(ucfirst(mb_strtolower(str_replace('_', ' ', $state)))); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
    <div class="order-filter-actions"><button type="submit" class="action-link">Buscar</button><a class="action-link secondary" href="<?php echo e(route($listingRoute)); ?>">Limpiar</a></div>
    <p class="installation-loading" role="status" hidden>Buscando pedidos…</p>
</form>

<section class="order-list" aria-labelledby="order-list-title">
    <header class="installation-list-heading"><div><h2 id="order-list-title">Pedidos registrados</h2><p class="muted"><?php echo e($records->total()); ?> <?php echo e($records->total() === 1 ? 'pedido encontrado' : 'pedidos encontrados'); ?></p></div><small>Primero los que requieren atención</small></header>
    <?php $__empty_1 = true; $__currentLoopData = $records; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $record): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <?php
        $history = $reprogramming->get($record->pedido_id, collect());
        $events = $lifecycleEvents->get($record->pedido_id, collect());
        $resources = $resourceRequests->get($record->pedido_id, collect());
        $resourceRequestGroups = $resources->groupBy(fn ($item) => $item->solicitud_grupo_id ?: 'legacy-'.$item->solicitud_id);
        $latestResourceGroup = $resourceRequestGroups->first() ?? collect();
        $latestResourceState = $latestResourceGroup->first()->estado ?? null;
        $resourcesForManager = $resources->where('estado', 'PENDIENTE_GERENCIA');
        $resourcesForManagerGroups = $resourcesForManager->groupBy('solicitud_grupo_id');
        $showManagerResourceReview = $resourcesForManager->isNotEmpty() && auth()->user()->hasPermission('orders.resources.approve');
        $pending = $history->firstWhere('estado', 'SOLICITADA');
        $latestApproved = $history->firstWhere('estado', 'APROBADA');
        $editable = in_array($record->estado, ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO'], true);
        $canOperate = auth()->user()->hasPermission('orders.schedule');
        $coordinated = (bool) $record->fecha_programada;
        $assigned = (bool) $record->tecnico_id;
        $assignmentStockBlocked = ! $assigned && $equipmentOptions->isEmpty()
            && $assignedEquipment->get($record->pedido_id, collect())->isEmpty();
        $openResourceRequests = $resources->whereIn('estado', ['PENDIENTE_ALMACEN', 'PENDIENTE_GERENCIA', 'AUTORIZADA']);
        $equipmentReady = $assignedEquipment->get($record->pedido_id, collect())->isNotEmpty();
        $resourcesPrepared = $equipmentReady && $openResourceRequests->isEmpty()
            && ($latestResourceState === 'ENTREGADA' || ! $latestResourceState);
        $canStartResourceRequest = $openResourceRequests->isEmpty()
            && (in_array($latestResourceState, [null, 'RECHAZADA', 'ANULADA'], true) || ! $equipmentReady);
        $needsEquipmentRequest = $canStartResourceRequest;
        $needsMaterialRequest = $canStartResourceRequest;
        $equipmentStockBlocked = $needsEquipmentRequest && $equipmentOptions->isEmpty()
            && $assignedEquipment->get($record->pedido_id, collect())->isEmpty()
            && $openResourceRequests->where('tipo', 'EQUIPO')->isEmpty();
        $reservedMaterialIds = $assignedMaterials->get($record->pedido_id, collect())->pluck('material_id')
            ->merge($openResourceRequests->where('tipo', 'MATERIAL')->pluck('material_id'))->filter()->unique();
        $availableMaterialsForOrder = $materials->reject(fn ($material) => $reservedMaterialIds->contains((int) $material->material_id));
        $needsResourceRequest = $needsEquipmentRequest || $needsMaterialRequest;
        $canSchedule = $editable && ($coordinated || ! $record->fase_flujo);
        $canRequestResources = $editable && $coordinated && ! $pending && $resourceRequestableOrderIds->contains($record->pedido_id);
        $canViewTechnicalSheet = $record->tecnico_id && auth()->user()->hasPermission('technician.view');
        $visitDate = $record->fecha_programada ? \Illuminate\Support\Carbon::parse($record->fecha_programada) : null;
        $scheduleContext = old('form_context') === 'schedule-'.$record->pedido_id;
        $resourceContext = old('form_context') === 'resources-'.$record->pedido_id || (int) request('solicitar') === (int) $record->pedido_id;
        $nextStep = match (true) {
            $record->estado === 'CANCELADO' => 'Pedido cancelado',
            $record->estado === 'COMPLETADO' && $record->estado_documentacion === 'COMPLETA' => 'Instalación y documentación completas',
            $record->estado === 'COMPLETADO' && $record->estado_documentacion === 'PENDIENTE_CLIENTE_CONTRATO' => 'Instalación cerrada · falta formalizar cliente y contrato',
            $record->estado === 'COMPLETADO' => 'Instalación lista · completar revisión del contrato',
            $record->estado === 'EN_PROCESO' => 'Instalación en ejecución',
            (bool) $pending => 'Solicitud de cambio pendiente de aprobación de Gerencia',
            $resourcesForManager->isNotEmpty() => 'Gerencia debe autorizar los recursos solicitados',
            ! $coordinated => '1 · Asistente de Ventas debe confirmar la fecha con el cliente',
            $needsEquipmentRequest && $needsMaterialRequest => '2 · Solicita equipo y material a Almacén',
            $needsEquipmentRequest => '2 · Solicita el equipo a Almacén',
            $needsMaterialRequest => '2 · Solicita el material a Almacén',
            ! $resourcesPrepared => '2 · Almacén debe preparar los recursos solicitados',
            ! $assigned => '3 · Asigna al técnico instalador',
            $record->fase_flujo === 'ESPERANDO_CONFIRMACION_TECNICO' => '4 · El técnico debe confirmar la asignación',
            default => 'Pedido listo para la ejecución',
        };
    ?>
    <article class="installation-panel order-card" id="pedido-<?php echo e($record->pedido_id); ?>">
        <div class="order-card-header"><div><p class="order-card-kicker">PEDIDO #<?php echo e($record->pedido_id); ?> · <?php echo e($record->contrato_codigo ? 'CONTRATO '.$record->contrato_codigo : 'SOLICITUD DE WHATSAPP'); ?></p><h3><?php echo e($record->codigo); ?></h3><p class="order-card-client"><small>Cliente</small><strong><?php echo e($record->nombre_solicitud ?: 'Cliente sin identificar'); ?></strong></p></div><span class="installation-state state-<?php echo e(strtolower($record->estado)); ?>"><?php echo e(ucfirst(mb_strtolower(str_replace('_', ' ', $record->estado)))); ?></span></div>
        <div class="order-card-primary">
            <div class="order-card-visit"><small><?php echo e($record->estado === 'COMPLETADO' ? 'Visita realizada' : 'Visita vigente'); ?></small><dl>
                <div><dt>Día</dt><dd><?php echo e($visitDate ? ucfirst($visitDate->locale('es')->isoFormat('dddd')).' '.$visitDate->format('d/m/Y') : 'Por confirmar'); ?></dd></div>
                <div><dt>Hora de inicio</dt><dd><?php echo e($visitDate ? $visitDate->format('H:i') : 'Por confirmar'); ?></dd></div>
                <div><dt>Franja</dt><dd><?php echo e($record->franja_horaria ?: 'Por confirmar'); ?></dd></div>
            </dl></div>
            <div class="order-card-address"><small>Dirección del servicio</small><strong><?php echo e($record->direccion_servicio); ?></strong><?php if($record->ubicacion_compartida): ?><a href="<?php echo e($record->ubicacion_compartida); ?>" target="_blank" rel="noopener">Abrir ubicación compartida</a><?php endif; ?></div>
            <div class="order-card-contact"><small>Contacto del cliente</small><?php if($record->telefono_solicitud): ?><a href="tel:<?php echo e(preg_replace('/[^0-9+]/', '', (string) $record->telefono_solicitud)); ?>"><?php echo e($record->telefono_solicitud); ?></a><?php else: ?><strong>Teléfono sin registrar</strong><?php endif; ?> <?php if($record->correo_solicitud): ?><a href="mailto:<?php echo e($record->correo_solicitud); ?>"><?php echo e($record->correo_solicitud); ?></a><?php endif; ?></div>
            <div class="order-card-contract-start"><small>Inicio del contrato</small><strong><?php echo e($record->contrato_fecha_inicio ? \Illuminate\Support\Carbon::parse($record->contrato_fecha_inicio)->format('d/m/Y') : 'Sin registrar'); ?></strong></div>
            <div class="order-card-technician"><small>Equipo técnico</small><strong><?php echo e($record->tecnico_nombre ?: 'Sin asignar'); ?></strong><?php if($record->tecnico_secundario_nombre): ?><span>+ <?php echo e($record->tecnico_secundario_nombre); ?></span><?php endif; ?></div>
        </div>
        <?php if($pending): ?><div class="order-next order-next-attention" role="status"><small>CAMBIO DE FECHA PENDIENTE</small><strong>Gerencia debe revisar un cambio de fecha</strong><span>Se solicita <?php echo e(\Illuminate\Support\Carbon::parse($pending->fecha_nueva)->format('d/m/Y · H:i')); ?> · <?php echo e($pending->franja_nueva); ?>. La visita vigente se mantiene hasta que Gerencia decida.</span></div><?php endif; ?>
        <?php if($openResourceRequests->isNotEmpty() && !$showManagerResourceReview): ?><p class="order-resource-summary"><?php echo e($openResourceRequests->count()); ?> <?php echo e($openResourceRequests->count() === 1 ? 'recurso solicitado' : 'recursos solicitados'); ?> en trámite. <?php if(auth()->user()->hasPermission('warehouse.view')): ?><a href="<?php echo e(route('warehouse.requests.index')); ?>">Abrir solicitudes de Almacén</a><?php endif; ?></p><?php endif; ?>
        <?php if($showManagerResourceReview): ?><?php echo $__env->make('orders.resource-approval', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php endif; ?>
        <div class="order-card-toolbar" id="acciones-<?php echo e($record->pedido_id); ?>">
            <?php if($canRequestResources || $canViewTechnicalSheet || $pending): ?>
            <div class="order-toolbar-main" aria-label="Acciones principales del pedido">
            <?php if($pending && auth()->user()->hasPermission('orders.approve')): ?>
                <details class="order-action order-approval-toggle" data-order-panel <?php if($errors->has('decision_observacion') || $errors->has('evidencia_revisada')): ?> open <?php endif; ?>><summary>Revisar cambio de fecha</summary>
                    <div class="order-approval">
                        <header><p class="order-step-label">SOLICITUD DE REPROGRAMACIÓN</p><h4>Compara la propuesta con la visita vigente</h4><p>Registró <?php echo e($pending->solicitante_nombre); ?> el <?php echo e(\Illuminate\Support\Carbon::parse($pending->solicitado_en)->format('d/m/Y H:i')); ?>. Motivo: <?php echo e($reasonOptions[$pending->motivo_codigo] ?? $pending->motivo_codigo); ?><?php if($pending->motivo_detalle): ?> · <?php echo e($pending->motivo_detalle); ?><?php endif; ?></p></header>
                        <div class="order-date-comparison" aria-label="Comparación de fechas"><div><small>Visita vigente</small><strong><?php echo e(\Illuminate\Support\Carbon::parse($pending->fecha_anterior)->format('d/m/Y · H:i')); ?></strong><span><?php echo e($pending->franja_anterior ?: 'Sin franja registrada'); ?></span></div><div><small>Fecha solicitada</small><strong><?php echo e(\Illuminate\Support\Carbon::parse($pending->fecha_nueva)->format('d/m/Y · H:i')); ?></strong><span><?php echo e($pending->franja_nueva); ?></span></div></div>
                        <section class="order-review-evidence" aria-labelledby="evidencia-<?php echo e($pending->reprogramacion_id); ?>"><h5 id="evidencia-<?php echo e($pending->reprogramacion_id); ?>">Archivo para sustentar el acuerdo</h5><p>Quien solicitó el cambio adjuntó este archivo como respaldo de la coordinación con el cliente. Comprueba que corresponda a este pedido y muestre la fecha y franja aceptadas.</p>
                            <?php if($pending->evidencia_ruta): ?>
                                <div class="order-evidence-file"><span><?php echo e($pending->evidencia_nombre ?: 'Archivo adjunto'); ?></span><a class="action-link secondary" href="<?php echo e(route('orders.reprogram.evidence', [$record->pedido_id, $pending->reprogramacion_id])); ?>">Descargar archivo para revisar</a></div>
                            <?php else: ?>
                                <p class="order-review-warning">Esta solicitud anterior no tiene archivo de respaldo. Recházala para que registren una nueva con evidencia.</p>
                            <?php endif; ?>
                            <small>El sistema conserva el archivo sin alteraciones; la revisión del acuerdo con el cliente corresponde a Gerencia.</small>
                        </section>
                        <?php if($pending->detalle_coordinacion): ?><p class="order-coordination-detail"><strong>Detalle registrado de la coordinación:</strong> <?php echo e($pending->detalle_coordinacion); ?></p><?php endif; ?>
                        <?php if((int) $pending->solicitado_por === (int) auth()->id()): ?>
                            <p class="order-review-warning">Registraste esta solicitud con tu cuenta. Para mantener la separación entre solicitud y decisión, debe resolverla otra cuenta de Gerencia.</p>
                        <?php else: ?>
                            <div class="order-decision"><h5>Decisión</h5><p>Si apruebas, se guardará la nueva fecha, el pedido pasará a «Reprogramado» y se procesarán los avisos al técnico asignado y al cliente si tiene correo. El técnico tendrá que confirmar nuevamente su asignación.</p>
                            <?php if($pending->evidencia_ruta): ?><form method="POST" action="<?php echo e(route('orders.reprogram.approve', [$record->pedido_id, $pending->reprogramacion_id])); ?>" data-installation-form><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?><label class="order-review-check"><input type="checkbox" name="evidencia_revisada" value="1" required> Revisé el archivo y comprobé que respalda la fecha y franja propuestas para este pedido.</label><button class="action-link" type="submit">Aprobar cambio de fecha</button></form><?php endif; ?>
                            <details class="order-reject" <?php if($errors->has('decision_observacion')): ?> open <?php endif; ?>><summary>Rechazar cambio</summary><p>La fecha vigente seguirá igual. Explica por qué no se aprueba para que quede en el historial.</p><form method="POST" action="<?php echo e(route('orders.reprogram.reject', [$record->pedido_id, $pending->reprogramacion_id])); ?>" class="order-form" data-installation-form><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?><label>Motivo del rechazo<textarea name="decision_observacion" maxlength="500" rows="2" required><?php echo e(old('decision_observacion')); ?></textarea></label><button class="action-link secondary" type="submit">Confirmar rechazo</button></form></details>
                            </div>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endif; ?>
            <?php if($showManagerResourceReview && ($canRequestResources || $canViewTechnicalSheet)): ?><details class="order-manager-other-actions" data-order-panel <?php if($resourceContext): ?> open <?php endif; ?>><summary>Continuar gestión del pedido</summary><div class="order-manager-other-body"><?php endif; ?>
            <?php if($canRequestResources): ?>
            <details class="order-action order-resource-request <?php echo e($needsResourceRequest ? 'is-priority' : 'is-secondary'); ?>" id="solicitar-recursos-<?php echo e($record->pedido_id); ?>" data-order-panel <?php if((int) session('resource_request_order') === (int) $record->pedido_id || $resourceContext): ?> open <?php endif; ?>><summary><?php echo e($needsResourceRequest ? 'Solicitar recursos a Almacén' : ($resourcesPrepared ? 'Equipo y material preparados' : 'Ver estado de los recursos solicitados')); ?></summary>
                <div class="order-resource-request-body"><p><?php echo e($needsResourceRequest ? 'Registra el equipo y los materiales necesarios para esta visita. Almacén comprobará y preparará la solicitud conjunta antes de la salida.' : ($resourcesPrepared ? 'Almacén preparó el equipo y los materiales. La visita está lista para ejecutarse.' : 'La solicitud ya fue enviada. Consulta aquí el estado de cada recurso; el formulario volverá a aparecer solo si falta solicitar alguno.')); ?></p>
                    <?php if($assignedEquipment->get($record->pedido_id, collect())->isNotEmpty() || $assignedMaterials->get($record->pedido_id, collect())->isNotEmpty() || $openResourceRequests->isNotEmpty()): ?>
                    <div class="order-resource-status-grid" aria-label="Estado de los recursos del pedido">
                        <?php if($assignedEquipment->get($record->pedido_id, collect())->isNotEmpty() || $openResourceRequests->where('tipo', 'EQUIPO')->isNotEmpty()): ?>
                        <div class="order-resource-status-card"><h4>Equipo</h4>
                            <?php $__empty_2 = true; $__currentLoopData = $assignedEquipment->get($record->pedido_id, collect()); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?><p class="order-resource-state is-ready"><strong>Preparado</strong><span><?php echo e($item->codigo); ?> · <?php echo e($item->nombre); ?> · <?php echo e($item->tipo); ?><?php if($item->modelo): ?> · Modelo <?php echo e($item->modelo); ?><?php endif; ?> <?php if($item->numero_serie): ?> · Serie <?php echo e($item->numero_serie); ?><?php endif; ?></span></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
                                <?php $__currentLoopData = $openResourceRequests->where('tipo', 'EQUIPO'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p class="order-resource-state is-pending"><strong><?php echo e(match($item->estado) {'PENDIENTE_GERENCIA' => 'Pendiente de Gerencia', 'AUTORIZADA' => 'Autorizado; espera Almacén', default => 'Pendiente de Almacén'}); ?></strong><span><?php echo e($item->equipo_codigo ?: $item->equipo_tipo_solicitado); ?> · <?php echo e($item->equipo_nombre); ?><?php if($item->equipo_modelo): ?> · Modelo <?php echo e($item->equipo_modelo); ?><?php endif; ?> <?php if($item->equipo_serie): ?> · Serie <?php echo e($item->equipo_serie); ?><?php endif; ?></span></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if($assignedMaterials->get($record->pedido_id, collect())->isNotEmpty() || $openResourceRequests->where('tipo', 'MATERIAL')->isNotEmpty()): ?>
                        <div class="order-resource-status-card"><h4>Material</h4>
                            <?php $__empty_2 = true; $__currentLoopData = $assignedMaterials->get($record->pedido_id, collect()); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?><p class="order-resource-state is-ready"><strong>Preparado</strong><span><?php echo e($item->codigo); ?> · <?php echo e($item->nombre); ?> · <?php echo e(\App\Support\InventoryQuantity::format($item->cantidad_asignada)); ?> <?php echo e($item->inventario_en_empaque ? $item->unidad_empaque : $item->unidad_base); ?><?php if($item->inventario_en_empaque && $item->factor_conversion): ?> · <?php echo e(\App\Support\InventoryQuantity::format($item->cantidad_asignada * $item->factor_conversion)); ?> <?php echo e($item->unidad_base); ?> en total <?php endif; ?></span></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
                                <?php $__currentLoopData = $openResourceRequests->where('tipo', 'MATERIAL'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p class="order-resource-state is-pending"><strong><?php echo e(match($item->estado) {'PENDIENTE_GERENCIA' => 'Pendiente de Gerencia', 'AUTORIZADA' => 'Autorizado; espera Almacén', default => 'Pendiente de Almacén'}); ?></strong><span><?php echo e($item->material_codigo); ?> · <?php echo e($item->material_nombre); ?> · <?php echo e(\App\Support\InventoryQuantity::format($item->cantidad)); ?> <?php echo e($item->material_inventario_en_empaque ? $item->material_unidad_empaque : $item->material_unidad_base); ?><?php if($item->material_inventario_en_empaque && $item->material_factor_conversion): ?> · <?php echo e(\App\Support\InventoryQuantity::format($item->cantidad * $item->material_factor_conversion)); ?> <?php echo e($item->material_unidad_base); ?> en total <?php endif; ?></span></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if($equipmentStockBlocked): ?><div class="order-next order-next-attention" role="status"><strong>No hay equipos disponibles</strong><span>La solicitud a Almacén estará habilitada cuando se reabastezca el stock de equipos.</span></div><?php endif; ?>
                    <?php if($needsResourceRequest && ! $equipmentStockBlocked): ?>
                    <form method="POST" action="<?php echo e(route('orders.resources.request', $record->pedido_id)); ?>" class="order-resource-request-form" data-installation-form data-return-anchor="solicitar-recursos-<?php echo e($record->pedido_id); ?>"><?php echo csrf_field(); ?>
                        <input type="hidden" name="form_context" value="resources-<?php echo e($record->pedido_id); ?>">
                        <p class="order-resource-form-intro"><?php echo e($needsEquipmentRequest && $needsMaterialRequest ? 'Selecciona el equipo y agrega todos los materiales que necesita esta visita. Almacén revisará la solicitud completa y registrará la entrega antes de iniciar.' : 'Completa los recursos que faltan. Puedes agregar varios materiales a una sola solicitud.'); ?></p>
                        <div class="order-resource-request-forms <?php echo e($needsEquipmentRequest && $needsMaterialRequest ? '' : 'is-single'); ?>">
                            <?php if($needsEquipmentRequest): ?>
                            <section class="order-resource-choice" data-equipment-availability-form><h4>Equipo</h4><label>Unidad de equipo necesaria<select name="equipo_id"><option value="">No solicitar equipo ahora</option><?php $__currentLoopData = $equipmentOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($item->equipo_id); ?>" data-available="1" <?php if($resourceContext && old('equipo_id') == $item->equipo_id): echo 'selected'; endif; ?>><?php echo e($item->tipo); ?> · <?php echo e($item->codigo); ?> · <?php echo e($item->nombre); ?> · Modelo <?php echo e($item->modelo ?: 'Sin modelo registrado'); ?> · Serie <?php echo e($item->numero_serie ?: 'Sin serie'); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label><div class="resource-availability" data-resource-availability role="status">Selecciona una unidad para revisar su disponibilidad.</div><?php if($equipmentOptions->isEmpty()): ?><p class="order-next order-next-attention">No hay unidades disponibles para solicitar en este momento.</p><?php endif; ?><p class="muted">Almacén recibirá el código, modelo y número de serie de la unidad elegida.</p></section>
                            <?php endif; ?>
                            <?php if($needsMaterialRequest): ?>
                            <section class="order-resource-choice order-material-choice"><h4>Materiales necesarios</h4><p class="muted">Cada renglón usa la unidad de inventario configurada para ese material.</p>
                                <?php ($previousMaterialRows = old('materials')); ?>
                                <?php ($materialRequestRows = $resourceContext && is_array($previousMaterialRows) && $previousMaterialRows !== [] ? $previousMaterialRows : [['material_id' => '', 'cantidad_asignada' => '']]); ?>
                                <div class="order-material-lines" data-material-lines>
                                    <?php $__currentLoopData = $materialRequestRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $materialIndex => $materialRow): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <div class="order-material-line" data-material-line>
                                        <label>Material<select name="materials[<?php echo e($materialIndex); ?>][material_id]"><option value="">Selecciona un material</option><?php $__currentLoopData = $availableMaterialsForOrder; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($item->material_id); ?>" data-available="<?php echo e($item->stock_libre); ?>" data-unit="<?php echo e($item->inventario_en_empaque ? $item->unidad_empaque : $item->unidad_base); ?>" data-packaging="<?php echo e($item->inventario_en_empaque ? '1' : '0'); ?>" data-factor="<?php echo e($item->factor_conversion); ?>" data-base-unit="<?php echo e($item->unidad_base); ?>" <?php if(($materialRow['material_id'] ?? '') == $item->material_id): echo 'selected'; endif; ?>><?php echo e($item->codigo); ?> · <?php echo e($item->nombre); ?> · <?php echo e($item->inventario_en_empaque ? $item->unidad_empaque : $item->unidad_base); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
                                        <label>Cantidad en la unidad indicada<input name="materials[<?php echo e($materialIndex); ?>][cantidad_asignada]" type="number" min="0.001" step="0.001" value="<?php echo e($materialRow['cantidad_asignada'] ?? ''); ?>"></label>
                                        <div class="resource-availability" data-resource-availability role="status">Selecciona un material para ver unidad y stock libre.</div>
                                        <button type="button" class="action-link secondary order-material-remove" data-remove-material>Quitar material</button>
                                    </div>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </div>
                                <template data-material-template><div class="order-material-line" data-material-line>
                                    <label>Material<select name="materials[__INDEX__][material_id]"><option value="">Selecciona un material</option><?php $__currentLoopData = $availableMaterialsForOrder; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($item->material_id); ?>" data-available="<?php echo e($item->stock_libre); ?>" data-unit="<?php echo e($item->inventario_en_empaque ? $item->unidad_empaque : $item->unidad_base); ?>" data-packaging="<?php echo e($item->inventario_en_empaque ? '1' : '0'); ?>" data-factor="<?php echo e($item->factor_conversion); ?>" data-base-unit="<?php echo e($item->unidad_base); ?>"><?php echo e($item->codigo); ?> · <?php echo e($item->nombre); ?> · <?php echo e($item->inventario_en_empaque ? $item->unidad_empaque : $item->unidad_base); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
                                    <label>Cantidad en la unidad indicada<input name="materials[__INDEX__][cantidad_asignada]" type="number" min="0.001" step="0.001"></label>
                                    <div class="resource-availability" data-resource-availability role="status">Selecciona un material para ver unidad y stock libre.</div>
                                    <button type="button" class="action-link secondary order-material-remove" data-remove-material>Quitar material</button>
                                </div></template>
                                <button type="button" class="action-link secondary order-material-add" data-add-material>＋ Agregar otro material</button>
                            </section>
                            <?php endif; ?>
                        </div>
                        <div class="order-resource-submit"><p>Almacén reservará todo lo aprobado. El inventario físico disminuirá conforme se registre el consumo de cada material.</p><p class="field-error" data-resource-form-error role="alert" hidden>Selecciona al menos un material o un equipo antes de enviar.</p><button type="submit" class="action-link">Enviar solicitud completa a Almacén</button></div>
                    </form>
                    <?php endif; ?>
                </div>
            </details>
            <?php endif; ?>
            <?php if($canViewTechnicalSheet): ?><a class="action-link <?php echo e($canRequestResources && ! $resourcesPrepared ? 'secondary' : ''); ?>" href="<?php echo e(route('technician.show', $record->pedido_id)); ?>">Ver ficha técnica</a><?php endif; ?>
            <?php if($showManagerResourceReview && ($canRequestResources || $canViewTechnicalSheet)): ?></div></details><?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="order-toolbar-utility" aria-label="Consultas y otras acciones del pedido">
            <?php if($resources->isNotEmpty()): ?>
            <details class="order-action order-history order-resource-history" id="historial-recursos-<?php echo e($record->pedido_id); ?>" data-order-panel><summary>Solicitudes completas del pedido (<?php echo e($resourceRequestGroups->count()); ?>)</summary><ol class="order-history-list"><?php $__currentLoopData = $resourceRequestGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php ($request = $group->first()); ?>
                <li><div class="order-history-head"><strong>Solicitud conjunta · <?php echo e($group->count()); ?> <?php echo e($group->count() === 1 ? 'recurso' : 'recursos'); ?></strong><small><?php echo e(match($request->estado) {'PENDIENTE_ALMACEN' => 'Pendiente de Almacén', 'PENDIENTE_GERENCIA' => 'Pendiente de Gerencia', 'AUTORIZADA' => 'Autorizada por Gerencia', 'ENTREGADA' => 'Preparada completa', 'RECHAZADA' => 'Rechazada completa', default => 'Anulada'}); ?></small></div>
                <ul class="order-history-resources"><?php $__currentLoopData = $group; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($item->tipo === 'EQUIPO' ? ($item->equipo_tipo_solicitado ? 'Equipo tipo '.$item->equipo_tipo_solicitado : $item->equipo_codigo.' · '.$item->equipo_nombre) : $item->material_codigo.' · '.$item->material_nombre); ?> · <?php echo e(\App\Support\InventoryQuantity::format($item->cantidad)); ?><?php echo e($item->tipo === 'MATERIAL' ? ' '.($item->material_inventario_en_empaque ? $item->material_unidad_empaque : $item->material_unidad_base) : ' unidad'); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul>
                <p>Solicitó <?php echo e($request->solicitante); ?> el <?php echo e(\Illuminate\Support\Carbon::parse($request->solicitado_en)->format('d/m/Y H:i')); ?>.<?php if($request->resuelto_por): ?> Resolvió <?php echo e($request->aprobador); ?> el <?php echo e(\Illuminate\Support\Carbon::parse($request->resuelto_en)->format('d/m/Y H:i')); ?>.<?php endif; ?> <?php if($request->atendido_por): ?> Preparó <?php echo e($request->almacenista); ?> el <?php echo e(\Illuminate\Support\Carbon::parse($request->atendido_en)->format('d/m/Y H:i')); ?>.<?php endif; ?> <?php if($request->cerrado_por): ?> Cerró <?php echo e($request->cerrador); ?> el <?php echo e(\Illuminate\Support\Carbon::parse($request->cerrado_en)->format('d/m/Y H:i')); ?>.<?php endif; ?></p>
                <?php if($request->decision_motivo_codigo || $request->decision_observacion): ?><p>Motivo: <?php echo e(\App\Support\ResourceRejectionReasons::label($request->decision_motivo_codigo) ?: $request->decision_observacion); ?><?php if($request->decision_motivo_codigo && $request->decision_observacion): ?> · <?php echo e($request->decision_observacion); ?><?php endif; ?></p><?php endif; ?>
                </li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ol></details>
            <?php endif; ?>
            <details class="order-card-more" data-order-panel <?php if($scheduleContext): ?> open <?php endif; ?>><summary><?php echo e($showManagerResourceReview ? 'Seguimiento del pedido' : ($editable && $canOperate ? 'Detalles y acciones' : 'Detalles del pedido')); ?></summary>
            <div class="order-card-more-body">
            <div class="order-flow-overview"><div><p class="order-step-label">AVANCE DE LA INSTALACIÓN</p><h4>Estado actual</h4></div><p><?php echo e($nextStep); ?></p></div>
            <ol class="order-flow-stages" aria-label="Avance del pedido">
                <li class="<?php echo e($coordinated ? 'is-done' : 'is-current'); ?>"><span class="order-flow-number">1</span><div><strong>Visita confirmada</strong><small><?php echo e($coordinated ? 'Asistente de ventas registró la fecha' : 'Pendiente de coordinar'); ?></small></div></li>
                <li class="<?php echo e($resourcesPrepared ? 'is-done' : ($coordinated ? 'is-current' : '')); ?>"><span class="order-flow-number">2</span><div><strong>Recursos preparados</strong><small><?php echo e($resourcesPrepared ? 'Equipo y material listos' : ($openResourceRequests->isNotEmpty() ? 'En revisión por Gerencia o Almacén' : 'Pendiente de registrar los recursos necesarios')); ?></small></div></li>
                <li class="<?php echo e($assigned ? 'is-done' : ($resourcesPrepared ? 'is-current' : '')); ?>"><span class="order-flow-number">3</span><div><strong>Técnico responsable</strong><small><?php echo e($assigned ? 'Equipo de trabajo definido' : 'Se asigna después de preparar los recursos'); ?></small></div></li>
            </ol>
            <div class="order-flow-facts">
                <div><small>Contrato</small><strong><?php echo e($record->contrato_codigo ?: 'Se registrará después de instalar'); ?></strong></div>
                <div><small>Coordinación con el cliente</small><strong><?php echo e($record->coordinador_nombre ?: ($record->coordinador_usuario ? 'Cuenta '.$record->coordinador_usuario : 'Pendiente')); ?></strong><span><?php echo e($record->canal_coordinacion ? ucfirst(mb_strtolower($record->canal_coordinacion)) : 'Sin canal registrado'); ?></span></div>
                <div><small>Responsable de asignación</small><strong><?php echo e($record->asignador_nombre ?: ($record->asignador_usuario ? 'Cuenta '.$record->asignador_usuario : 'Pendiente de asignación')); ?></strong></div>
                <?php if($record->asignado_en): ?><div><small>Aviso al técnico</small><strong><?php echo e($record->notificado_en ? 'Procesado por correo' : 'Pendiente de envío'); ?></strong><span><?php echo e($record->confirmado_por_tecnico_en ? 'Confirmado por el técnico' : 'Espera confirmación del técnico'); ?></span></div><?php endif; ?>
            </div>
            <?php if($record->estado === 'REPROGRAMADO' && $record->fecha_original): ?>
            <p class="order-change-note"><strong>Fecha inicial:</strong> <?php echo e(\Illuminate\Support\Carbon::parse($record->fecha_original)->format('d/m/Y · H:i')); ?> <?php if($latestApproved?->motivo_detalle ?? $record->motivo_reprogramacion): ?> · <strong>Motivo:</strong> <?php echo e($latestApproved?->motivo_detalle ?? $record->motivo_reprogramacion); ?> <?php endif; ?></p>
            <?php endif; ?>
            <?php if($editable && $canOperate && $canSchedule && !$pending): ?>
            <div class="order-actions">
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('orders.schedule')): ?>
                <?php if(! $record->tecnico_id && ! $resourcesPrepared): ?><div class="order-next order-next-attention" role="status"><strong>Asignación de técnico en espera</strong><span><?php echo e($assignmentStockBlocked ? 'No hay equipos disponibles. Podrás asignar al técnico cuando se reabastezca el stock y Almacén prepare los recursos.' : 'Solicita los recursos y espera a que Almacén los prepare antes de asignar un técnico.'); ?></span></div><?php else: ?>
                <details class="order-action order-assignment-action" id="asignar-tecnico-<?php echo e($record->pedido_id); ?>" <?php if($scheduleContext): ?> open <?php endif; ?>><summary><?php echo e($record->tecnico_id ? 'Cambiar técnico asignado' : 'Asignar técnico · Paso 3'); ?></summary>
                    <div class="order-assignment-body">
                    <div class="order-assignment-context"><div><small>Visita confirmada</small><strong><?php echo e($visitDate ? ucfirst($visitDate->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')) : 'Fecha pendiente de registro'); ?></strong><span>Inicio <?php echo e($visitDate?->format('H:i') ?: 'por definir'); ?> · Franja <?php echo e($record->franja_horaria ?: 'por confirmar'); ?></span></div><div><small>Después de asignar</small><span>El técnico recibirá un correo para confirmar la visita. La asignación quedará pendiente hasta que responda.</span></div></div>
                    <?php ($visitTechnicians = $availableTechniciansByOrder->get($record->pedido_id, collect())); ?>
                    <form method="POST" action="<?php echo e(route('orders.schedule', $record->pedido_id)); ?>" class="order-form order-assignment-form" data-installation-form data-availability-url="<?php echo e(route('orders.available-technicians', $record->pedido_id)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                        <input type="hidden" name="form_context" value="schedule-<?php echo e($record->pedido_id); ?>">
                        <?php if(! $record->fase_flujo): ?><label class="order-assignment-date">Fecha y hora de visita<input type="datetime-local" name="fecha_programada" value="<?php echo e($scheduleContext ? old('fecha_programada') : ''); ?>" required><small>Este pedido todavía no tiene una visita confirmada.</small></label><?php endif; ?>
                        <label class="order-assignment-technician">Técnico responsable<select name="tecnico_id" required <?php if($visitTechnicians->isEmpty()): echo 'disabled'; endif; ?>><option value=""><?php echo e($visitTechnicians->isEmpty() ? ($visitDate ? 'Sin técnicos libres en este horario' : 'Selecciona primero la fecha y hora') : 'Selecciona un técnico'); ?></option><?php $__currentLoopData = $visitTechnicians; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $technician): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($technician->getKey()); ?>" <?php if(($scheduleContext ? old('tecnico_id') : $record->tecnico_id) == $technician->getKey()): echo 'selected'; endif; ?>><?php echo e($technician->full_name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select><small>Solo aparecen técnicos libres el día, hora y franja de esta visita.</small></label>
                        <label class="order-assignment-technician">Segundo técnico (opcional)<select name="tecnico_secundario_id" <?php if($visitTechnicians->isEmpty()): echo 'disabled'; endif; ?>><option value="">Técnico de apoyo</option><?php $__currentLoopData = $visitTechnicians; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $technician): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($technician->getKey()); ?>" <?php if(($scheduleContext ? old('tecnico_secundario_id') : $record->tecnico_secundario_id) == $technician->getKey()): echo 'selected'; endif; ?>><?php echo e($technician->full_name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select><small>También debe estar libre en el horario de la visita.</small></label>
                        <p class="order-assignment-responsible">La asignación quedará registrada a nombre de <strong><?php echo e(auth()->user()->employee?->full_name ?? 'Cuenta sin empleado asociado'); ?></strong>.</p>
                        <?php if($record->tecnico_id): ?><label>Motivo del cambio<textarea name="motivo_reprogramacion" maxlength="500" rows="2" required><?php echo e($scheduleContext ? old('motivo_reprogramacion') : ''); ?></textarea></label><?php endif; ?>
                        <div class="order-assignment-submit"><button type="submit" class="action-link"><?php echo e($record->tecnico_id ? 'Guardar cambio y avisar' : 'Confirmar asignación y avisar'); ?></button></div>
                    </form>
                    </div>
                </details>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if(($editable && auth()->user()->hasPermission('orders.cancel')) || ($record->estado === 'CANCELADO' && auth()->user()->hasPermission('orders.schedule') && in_array($record->contrato_estado, ['PENDIENTE', 'ACTIVO'], true)) || $history->isNotEmpty() || $resources->isNotEmpty() || $events->isNotEmpty()): ?><div class="order-card-utilities" aria-label="Opciones adicionales del pedido">
            <?php if($editable): ?><?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('orders.cancel')): ?><details class="order-action order-danger"><summary>Cancelar este pedido</summary><div class="order-cancel-body"><p>Selecciona el motivo que corresponde. La cancelación quedará registrada con tu usuario y fecha; se liberarán los recursos y podrás retomar el pedido si el contrato sigue vigente.</p><form method="POST" action="<?php echo e(route('orders.cancel', $record->pedido_id)); ?>" class="order-form order-cancel-form" data-confirm-cancel data-installation-form data-order-cancel-form><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?><label for="motivo-codigo-<?php echo e($record->pedido_id); ?>">Motivo de cancelación *</label><select id="motivo-codigo-<?php echo e($record->pedido_id); ?>" name="motivo_codigo" required data-order-cancel-reason><option value="">Selecciona el motivo</option><?php $__currentLoopData = $cancellationReasons; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $code => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($code); ?>"><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select><small class="order-cancel-help">La categoría y, si corresponde, el detalle se conservarán en el historial del pedido.</small><label class="order-cancel-detail" data-cancel-detail-wrap for="motivo-detalle-<?php echo e($record->pedido_id); ?>" hidden>Explica el motivo *<textarea id="motivo-detalle-<?php echo e($record->pedido_id); ?>" name="motivo_detalle" minlength="10" maxlength="500" rows="3" data-cancel-detail placeholder="Describe brevemente qué ocurrió y cuándo"></textarea><small>Mínimo 10 caracteres.</small></label><div class="order-cancel-submit"><button type="submit" class="action-link order-cancel-submit-button">Revisar cancelación</button></div></form></div></details><?php endif; ?> <?php endif; ?>
            <?php if($record->estado === 'CANCELADO' && auth()->user()->hasPermission('orders.schedule') && in_array($record->contrato_estado, ['PENDIENTE', 'ACTIVO'], true)): ?><details class="order-action"><summary>Retomar instalación</summary><p>El pedido volverá al inicio para confirmar la visita y solicitar nuevamente los recursos. El historial de la cancelación se conservará.</p><form method="POST" action="<?php echo e(route('orders.reopen', $record->pedido_id)); ?>" data-installation-form><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?><label>¿Por qué se retoma este pedido?<textarea name="motivo_reapertura" required minlength="5" maxlength="500" rows="2" placeholder="Por ejemplo: cancelación accidental; el cliente mantiene el servicio"></textarea></label><button type="submit" class="action-link">Reabrir y volver a coordinación</button></form></details><?php elseif($record->estado === 'CANCELADO' && auth()->user()->hasPermission('orders.schedule')): ?><p class="order-next order-next-muted"><?php echo e($record->contrato_estado === 'CANCELADO' ? 'El contrato se canceló. Reactívalo desde Contratos para que el pedido vuelva automáticamente a Coordinación.' : 'El contrato finalizó. Si el cliente solicita una nueva instalación, registra un contrato nuevo.'); ?></p><?php endif; ?>
            <?php if($events->isNotEmpty()): ?><details class="order-action order-history"><summary>Historial del pedido (<?php echo e($events->count()); ?>)</summary><ol class="order-history-list"><?php $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><div class="order-history-head"><strong><?php echo e(match($event->tipo) {'REAPERTURA' => 'Pedido reabierto', 'CIERRE_AUTOMATICO' => 'Instalación cerrada automáticamente', default => 'Pedido cancelado'}); ?></strong><small><?php echo e($event->usuario_nombre ?: 'Sistema'); ?> · <?php echo e(\Illuminate\Support\Carbon::parse($event->registrado_en)->format('d/m/Y · H:i')); ?></small></div><p><?php echo e($event->motivo); ?></p></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ol></details><?php endif; ?>
            <?php if($history->isNotEmpty()): ?>
            <details class="order-action order-history"><summary>Historial de reprogramaciones (<?php echo e($history->count()); ?>)</summary>
                <ol class="order-history-list"><?php $__currentLoopData = $history; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $change): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li><div class="order-history-head"><strong><?php echo e(match($change->estado) {'SOLICITADA' => 'Pendiente de Gerencia', 'APROBADA' => 'Aprobada', 'RECHAZADA' => 'Rechazada', default => 'Anulada'}); ?></strong><small>Solicitó <?php echo e($change->solicitante_nombre); ?> el <?php echo e(\Illuminate\Support\Carbon::parse($change->solicitado_en)->format('d/m/Y H:i')); ?></small></div>
                        <p><strong>Fecha anterior:</strong> <?php echo e(\Illuminate\Support\Carbon::parse($change->fecha_anterior)->format('d/m/Y H:i')); ?> · <?php echo e($change->franja_anterior ?: 'Sin franja'); ?><br><strong>Fecha solicitada:</strong> <?php echo e(\Illuminate\Support\Carbon::parse($change->fecha_nueva)->format('d/m/Y H:i')); ?> · <?php echo e($change->franja_nueva); ?></p>
                        <p><strong>Motivo:</strong> <?php echo e($reasonOptions[$change->motivo_codigo] ?? $change->motivo_codigo); ?><?php if($change->motivo_detalle): ?> · <?php echo e($change->motivo_detalle); ?><?php endif; ?></p>
                        <?php if($change->resuelto_por): ?><p><strong>Decisión:</strong> <?php echo e($change->aprobador_nombre); ?> el <?php echo e(\Illuminate\Support\Carbon::parse($change->resuelto_en)->format('d/m/Y H:i')); ?><?php if($change->decision_observacion): ?> · <?php echo e($change->decision_observacion); ?><?php endif; ?></p><?php endif; ?>
                        <?php if($change->evidencia_ruta && auth()->user()->hasPermission('orders.view')): ?><a href="<?php echo e(route('orders.reprogram.evidence', [$record->pedido_id, $change->reprogramacion_id])); ?>">Descargar archivo de respaldo: <?php echo e($change->evidencia_nombre ?: 'evidencia'); ?></a><?php endif; ?>
                    </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ol>
            </details>
            <?php endif; ?>
            </div><?php endif; ?>
            </div>
            </details>
            </div>
        </div>
    </article>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <div class="installation-panel installation-empty"><h3>No hay pedidos que coincidan con la búsqueda.</h3><p class="muted">Prueba con otro código, cliente o estado.</p><a class="action-link secondary" href="<?php echo e(route($listingRoute)); ?>">Ver todos los pedidos</a></div>
    <?php endif; ?>
    <?php echo $__env->make('clients.pagination', ['records' => $records, 'label' => 'Paginación de pedidos'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</section>
<dialog id="order-cancel-confirm" class="installation-confirm order-cancel-confirm" aria-labelledby="order-cancel-title"><h2 id="order-cancel-title">¿Confirmas la cancelación?</h2><p>Se guardará el motivo en el historial y se liberarán los recursos reservados. Si el contrato sigue vigente, podrás reabrir el pedido después.</p><div class="installation-filter-actions"><button type="button" class="action-link secondary" data-cancel-order>Volver a revisar</button><button type="button" class="action-link order-cancel-submit-button" data-accept-order>Confirmar cancelación</button></div></dialog>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.management', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\Capstone\Capstone_Proyecto\resources\views/orders/index.blade.php ENDPATH**/ ?>