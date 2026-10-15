@extends('layouts.management')
@section('title', 'Pedidos de instalación')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/installations.css') }}?v=29">
<script src="{{ asset('js/installations.js') }}?v=12" defer></script>
<script src="{{ asset('js/resource-rejection.js') }}?v=1" defer></script>
@endpush
@section('workspace-content')
@php
    $listingRoute = request()->routeIs('orders.assigned') ? 'orders.assigned' : 'orders.index';
@endphp
<nav class="workspace-breadcrumb" aria-label="Ruta de navegación"><a href="{{ route('dashboard') }}">Inicio</a><span aria-hidden="true">›</span><span aria-current="page">Pedidos de instalación</span></nav>
<header class="installation-heading order-heading"><div><p class="eyebrow">OPERACIONES</p><h1>Pedidos de instalación</h1><p class="muted">Sigue cada pedido desde la fecha acordada hasta la ejecución técnica. Cada ficha indica el siguiente paso.</p></div></header>
@if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="notice" role="alert"><strong>Revisa los campos indicados:</strong>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

<div class="order-overview" aria-label="Resumen de pedidos">
    <a href="{{ route($listingRoute, ['estado' => 'PENDIENTE']) }}" class="order-overview-item"><span>Pendientes de preparación</span><strong>{{ $counts->get('PENDIENTE', 0) }}</strong></a>
    <a href="{{ route($listingRoute, ['estado' => 'PROGRAMADO']) }}" class="order-overview-item"><span>Programados</span><strong>{{ $counts->get('PROGRAMADO', 0) }}</strong></a>
    <a href="{{ route($listingRoute, ['estado' => 'REPROGRAMADO']) }}" class="order-overview-item"><span>Reprogramados</span><strong>{{ $counts->get('REPROGRAMADO', 0) }}</strong></a>
    <a href="{{ route($listingRoute, ['estado' => 'EN_PROCESO']) }}" class="order-overview-item"><span>En proceso</span><strong>{{ $counts->get('EN_PROCESO', 0) }}</strong></a>
    <a href="{{ route($listingRoute, ['estado' => 'COMPLETADO']) }}" class="order-overview-item"><span>Completados</span><strong>{{ $counts->get('COMPLETADO', 0) }}</strong></a>
    @if(auth()->user()->hasPermission('orders.approve'))<a href="{{ route('orders.index', ['reprogramacion' => 'pendiente']) }}" class="order-overview-item"><span>Por aprobar en Gerencia</span><strong>{{ $pendingReprogrammingCount }}</strong></a>@endif
    @if(auth()->user()->hasPermission('orders.resources.approve'))<a href="{{ route('orders.index', ['recursos' => 'pendiente']) }}" class="order-overview-item"><span>Recursos por autorizar</span><strong>{{ $pendingResourceApprovalCount }}</strong></a>@endif
</div>

<form method="GET" action="{{ route($listingRoute) }}" class="installation-panel order-filters" data-installation-form>
    <label for="order-search">Pedido, contrato o cliente<input id="order-search" type="search" name="search" maxlength="150" value="{{ request('search') }}" placeholder="Buscar por código o nombre"></label>
    <label for="order-state">Estado<select id="order-state" name="estado"><option value="">Todos los estados</option>@foreach($states as $state)<option value="{{ $state }}" @selected(request('estado') === $state)>{{ ucfirst(mb_strtolower(str_replace('_', ' ', $state))) }}</option>@endforeach</select></label>
    <div class="order-filter-actions"><button type="submit" class="action-link">Buscar</button><a class="action-link secondary" href="{{ route($listingRoute) }}">Limpiar</a></div>
    <p class="installation-loading" role="status" hidden>Buscando pedidos…</p>
</form>

<section class="order-list" aria-labelledby="order-list-title">
    <header class="installation-list-heading"><div><h2 id="order-list-title">Pedidos registrados</h2><p class="muted">{{ $records->total() }} {{ $records->total() === 1 ? 'pedido encontrado' : 'pedidos encontrados' }}</p></div><small>Primero los que requieren atención</small></header>
    @forelse($records as $record)
    @php
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
    @endphp
    <article class="installation-panel order-card" id="pedido-{{ $record->pedido_id }}">
        <div class="order-card-header"><div><p class="order-card-kicker">PEDIDO #{{ $record->pedido_id }} · {{ $record->contrato_codigo ? 'CONTRATO '.$record->contrato_codigo : 'SOLICITUD DE WHATSAPP' }}</p><h3>{{ $record->codigo }}</h3><p class="order-card-client"><small>Cliente</small><strong>{{ $record->nombre_solicitud ?: 'Cliente sin identificar' }}</strong></p></div><span class="installation-state state-{{ strtolower($record->estado) }}">{{ ucfirst(mb_strtolower(str_replace('_', ' ', $record->estado))) }}</span></div>
        <div class="order-card-primary">
            <div class="order-card-visit"><small>{{ $record->estado === 'COMPLETADO' ? 'Visita realizada' : 'Visita vigente' }}</small><dl>
                <div><dt>Día</dt><dd>{{ $visitDate ? ucfirst($visitDate->locale('es')->isoFormat('dddd')).' '.$visitDate->format('d/m/Y') : 'Por confirmar' }}</dd></div>
                <div><dt>Hora de inicio</dt><dd>{{ $visitDate ? $visitDate->format('H:i') : 'Por confirmar' }}</dd></div>
                <div><dt>Franja</dt><dd>{{ $record->franja_horaria ?: 'Por confirmar' }}</dd></div>
            </dl></div>
            <div class="order-card-address"><small>Dirección del servicio</small><strong>{{ $record->direccion_servicio }}</strong>@if($record->ubicacion_compartida)<a href="{{ $record->ubicacion_compartida }}" target="_blank" rel="noopener">Abrir ubicación compartida</a>@endif</div>
            <div class="order-card-contact"><small>Contacto del cliente</small>@if($record->telefono_solicitud)<a href="tel:{{ preg_replace('/[^0-9+]/', '', (string) $record->telefono_solicitud) }}">{{ $record->telefono_solicitud }}</a>@else<strong>Teléfono sin registrar</strong>@endif @if($record->correo_solicitud)<a href="mailto:{{ $record->correo_solicitud }}">{{ $record->correo_solicitud }}</a>@endif</div>
            <div class="order-card-contract-start"><small>Inicio del contrato</small><strong>{{ $record->contrato_fecha_inicio ? \Illuminate\Support\Carbon::parse($record->contrato_fecha_inicio)->format('d/m/Y') : 'Sin registrar' }}</strong></div>
            <div class="order-card-technician"><small>Equipo técnico</small><strong>{{ $record->tecnico_nombre ?: 'Sin asignar' }}</strong>@if($record->tecnico_secundario_nombre)<span>+ {{ $record->tecnico_secundario_nombre }}</span>@endif</div>
        </div>
        @if($pending)<div class="order-next order-next-attention" role="status"><small>CAMBIO DE FECHA PENDIENTE</small><strong>Gerencia debe revisar un cambio de fecha</strong><span>Se solicita {{ \Illuminate\Support\Carbon::parse($pending->fecha_nueva)->format('d/m/Y · H:i') }} · {{ $pending->franja_nueva }}. La visita vigente se mantiene hasta que Gerencia decida.</span></div>@endif
        @if($openResourceRequests->isNotEmpty() && !$showManagerResourceReview)<p class="order-resource-summary">{{ $openResourceRequests->count() }} {{ $openResourceRequests->count() === 1 ? 'recurso solicitado' : 'recursos solicitados' }} en trámite. @if(auth()->user()->hasPermission('warehouse.view'))<a href="{{ route('warehouse.requests.index') }}">Abrir solicitudes de Almacén</a>@endif</p>@endif
        @if($showManagerResourceReview)@include('orders.resource-approval')@endif
        <div class="order-card-toolbar" id="acciones-{{ $record->pedido_id }}">
            @if($canRequestResources || $canViewTechnicalSheet || $pending)
            <div class="order-toolbar-main" aria-label="Acciones principales del pedido">
            @if($pending && auth()->user()->hasPermission('orders.approve'))
                <details class="order-action order-approval-toggle" data-order-panel @if($errors->has('decision_observacion') || $errors->has('evidencia_revisada')) open @endif><summary>Revisar cambio de fecha</summary>
                    <div class="order-approval">
                        <header><p class="order-step-label">SOLICITUD DE REPROGRAMACIÓN</p><h4>Compara la propuesta con la visita vigente</h4><p>Registró {{ $pending->solicitante_nombre }} el {{ \Illuminate\Support\Carbon::parse($pending->solicitado_en)->format('d/m/Y H:i') }}. Motivo: {{ $reasonOptions[$pending->motivo_codigo] ?? $pending->motivo_codigo }}@if($pending->motivo_detalle) · {{ $pending->motivo_detalle }}@endif</p></header>
                        <div class="order-date-comparison" aria-label="Comparación de fechas"><div><small>Visita vigente</small><strong>{{ \Illuminate\Support\Carbon::parse($pending->fecha_anterior)->format('d/m/Y · H:i') }}</strong><span>{{ $pending->franja_anterior ?: 'Sin franja registrada' }}</span></div><div><small>Fecha solicitada</small><strong>{{ \Illuminate\Support\Carbon::parse($pending->fecha_nueva)->format('d/m/Y · H:i') }}</strong><span>{{ $pending->franja_nueva }}</span></div></div>
                        <section class="order-review-evidence" aria-labelledby="evidencia-{{ $pending->reprogramacion_id }}"><h5 id="evidencia-{{ $pending->reprogramacion_id }}">Archivo para sustentar el acuerdo</h5><p>Quien solicitó el cambio adjuntó este archivo como respaldo de la coordinación con el cliente. Comprueba que corresponda a este pedido y muestre la fecha y franja aceptadas.</p>
                            @if($pending->evidencia_ruta)
                                <div class="order-evidence-file"><span>{{ $pending->evidencia_nombre ?: 'Archivo adjunto' }}</span><a class="action-link secondary" href="{{ route('orders.reprogram.evidence', [$record->pedido_id, $pending->reprogramacion_id]) }}">Descargar archivo para revisar</a></div>
                            @else
                                <p class="order-review-warning">Esta solicitud anterior no tiene archivo de respaldo. Recházala para que registren una nueva con evidencia.</p>
                            @endif
                            <small>El sistema conserva el archivo sin alteraciones; la revisión del acuerdo con el cliente corresponde a Gerencia.</small>
                        </section>
                        @if($pending->detalle_coordinacion)<p class="order-coordination-detail"><strong>Detalle registrado de la coordinación:</strong> {{ $pending->detalle_coordinacion }}</p>@endif
                        @if((int) $pending->solicitado_por === (int) auth()->id())
                            <p class="order-review-warning">Registraste esta solicitud con tu cuenta. Para mantener la separación entre solicitud y decisión, debe resolverla otra cuenta de Gerencia.</p>
                        @else
                            <div class="order-decision"><h5>Decisión</h5><p>Si apruebas, se guardará la nueva fecha, el pedido pasará a «Reprogramado» y se procesarán los avisos al técnico asignado y al cliente si tiene correo. El técnico tendrá que confirmar nuevamente su asignación.</p>
                            @if($pending->evidencia_ruta)<form method="POST" action="{{ route('orders.reprogram.approve', [$record->pedido_id, $pending->reprogramacion_id]) }}" data-installation-form>@csrf @method('PATCH')<label class="order-review-check"><input type="checkbox" name="evidencia_revisada" value="1" required> Revisé el archivo y comprobé que respalda la fecha y franja propuestas para este pedido.</label><button class="action-link" type="submit">Aprobar cambio de fecha</button></form>@endif
                            <details class="order-reject" @if($errors->has('decision_observacion')) open @endif><summary>Rechazar cambio</summary><p>La fecha vigente seguirá igual. Explica por qué no se aprueba para que quede en el historial.</p><form method="POST" action="{{ route('orders.reprogram.reject', [$record->pedido_id, $pending->reprogramacion_id]) }}" class="order-form" data-installation-form>@csrf @method('PATCH')<label>Motivo del rechazo<textarea name="decision_observacion" maxlength="500" rows="2" required>{{ old('decision_observacion') }}</textarea></label><button class="action-link secondary" type="submit">Confirmar rechazo</button></form></details>
                            </div>
                        @endif
                    </div>
                </details>
            @endif
            @if($showManagerResourceReview && ($canRequestResources || $canViewTechnicalSheet))<details class="order-manager-other-actions" data-order-panel @if($resourceContext) open @endif><summary>Continuar gestión del pedido</summary><div class="order-manager-other-body">@endif
            @if($canRequestResources)
            <details class="order-action order-resource-request {{ $needsResourceRequest ? 'is-priority' : 'is-secondary' }}" id="solicitar-recursos-{{ $record->pedido_id }}" data-order-panel @if((int) session('resource_request_order') === (int) $record->pedido_id || $resourceContext) open @endif><summary>{{ $needsResourceRequest ? 'Solicitar recursos a Almacén' : ($resourcesPrepared ? 'Equipo y material preparados' : 'Ver estado de los recursos solicitados') }}</summary>
                <div class="order-resource-request-body"><p>{{ $needsResourceRequest ? 'Registra el equipo y los materiales necesarios para esta visita. Almacén comprobará y preparará la solicitud conjunta antes de la salida.' : ($resourcesPrepared ? 'Almacén preparó el equipo y los materiales. La visita está lista para ejecutarse.' : 'La solicitud ya fue enviada. Consulta aquí el estado de cada recurso; el formulario volverá a aparecer solo si falta solicitar alguno.') }}</p>
                    @if($assignedEquipment->get($record->pedido_id, collect())->isNotEmpty() || $assignedMaterials->get($record->pedido_id, collect())->isNotEmpty() || $openResourceRequests->isNotEmpty())
                    <div class="order-resource-status-grid" aria-label="Estado de los recursos del pedido">
                        @if($assignedEquipment->get($record->pedido_id, collect())->isNotEmpty() || $openResourceRequests->where('tipo', 'EQUIPO')->isNotEmpty())
                        <div class="order-resource-status-card"><h4>Equipo</h4>
                            @forelse($assignedEquipment->get($record->pedido_id, collect()) as $item)<p class="order-resource-state is-ready"><strong>Preparado</strong><span>{{ $item->codigo }} · {{ $item->nombre }} · {{ $item->tipo }}@if($item->modelo) · Modelo {{ $item->modelo }}@endif @if($item->numero_serie) · Serie {{ $item->numero_serie }}@endif</span></p>@empty
                                @foreach($openResourceRequests->where('tipo', 'EQUIPO') as $item)<p class="order-resource-state is-pending"><strong>{{ match($item->estado) {'PENDIENTE_GERENCIA' => 'Pendiente de Gerencia', 'AUTORIZADA' => 'Autorizado; espera Almacén', default => 'Pendiente de Almacén'} }}</strong><span>{{ $item->equipo_codigo ?: $item->equipo_tipo_solicitado }} · {{ $item->equipo_nombre }}@if($item->equipo_modelo) · Modelo {{ $item->equipo_modelo }}@endif @if($item->equipo_serie) · Serie {{ $item->equipo_serie }}@endif</span></p>@endforeach
                            @endforelse
                        </div>
                        @endif
                        @if($assignedMaterials->get($record->pedido_id, collect())->isNotEmpty() || $openResourceRequests->where('tipo', 'MATERIAL')->isNotEmpty())
                        <div class="order-resource-status-card"><h4>Material</h4>
                            @forelse($assignedMaterials->get($record->pedido_id, collect()) as $item)<p class="order-resource-state is-ready"><strong>Preparado</strong><span>{{ $item->codigo }} · {{ $item->nombre }} · {{ \App\Support\InventoryQuantity::format($item->cantidad_asignada) }} {{ $item->inventario_en_empaque ? $item->unidad_empaque : $item->unidad_base }}@if($item->inventario_en_empaque && $item->factor_conversion) · {{ \App\Support\InventoryQuantity::format($item->cantidad_asignada * $item->factor_conversion) }} {{ $item->unidad_base }} en total @endif</span></p>@empty
                                @foreach($openResourceRequests->where('tipo', 'MATERIAL') as $item)<p class="order-resource-state is-pending"><strong>{{ match($item->estado) {'PENDIENTE_GERENCIA' => 'Pendiente de Gerencia', 'AUTORIZADA' => 'Autorizado; espera Almacén', default => 'Pendiente de Almacén'} }}</strong><span>{{ $item->material_codigo }} · {{ $item->material_nombre }} · {{ \App\Support\InventoryQuantity::format($item->cantidad) }} {{ $item->material_inventario_en_empaque ? $item->material_unidad_empaque : $item->material_unidad_base }}@if($item->material_inventario_en_empaque && $item->material_factor_conversion) · {{ \App\Support\InventoryQuantity::format($item->cantidad * $item->material_factor_conversion) }} {{ $item->material_unidad_base }} en total @endif</span></p>@endforeach
                            @endforelse
                        </div>
                        @endif
                    </div>
                    @endif
                    @if($equipmentStockBlocked)<div class="order-next order-next-attention" role="status"><strong>No hay equipos disponibles</strong><span>La solicitud a Almacén estará habilitada cuando se reabastezca el stock de equipos.</span></div>@endif
                    @if($needsResourceRequest && ! $equipmentStockBlocked)
                    <form method="POST" action="{{ route('orders.resources.request', $record->pedido_id) }}" class="order-resource-request-form" data-installation-form data-return-anchor="solicitar-recursos-{{ $record->pedido_id }}">@csrf
                        <input type="hidden" name="form_context" value="resources-{{ $record->pedido_id }}">
                        <p class="order-resource-form-intro">{{ $needsEquipmentRequest && $needsMaterialRequest ? 'Selecciona el equipo y agrega todos los materiales que necesita esta visita. Almacén revisará la solicitud completa y registrará la entrega antes de iniciar.' : 'Completa los recursos que faltan. Puedes agregar varios materiales a una sola solicitud.' }}</p>
                        <div class="order-resource-request-forms {{ $needsEquipmentRequest && $needsMaterialRequest ? '' : 'is-single' }}">
                            @if($needsEquipmentRequest)
                            <section class="order-resource-choice" data-equipment-availability-form><h4>Equipo</h4><label>Unidad de equipo necesaria<select name="equipo_id"><option value="">No solicitar equipo ahora</option>@foreach($equipmentOptions as $item)<option value="{{ $item->equipo_id }}" data-available="1" @selected($resourceContext && old('equipo_id') == $item->equipo_id)>{{ $item->tipo }} · {{ $item->codigo }} · {{ $item->nombre }} · Modelo {{ $item->modelo ?: 'Sin modelo registrado' }} · Serie {{ $item->numero_serie ?: 'Sin serie' }}</option>@endforeach</select></label><div class="resource-availability" data-resource-availability role="status">Selecciona una unidad para revisar su disponibilidad.</div>@if($equipmentOptions->isEmpty())<p class="order-next order-next-attention">No hay unidades disponibles para solicitar en este momento.</p>@endif<p class="muted">Almacén recibirá el código, modelo y número de serie de la unidad elegida.</p></section>
                            @endif
                            @if($needsMaterialRequest)
                            <section class="order-resource-choice order-material-choice"><h4>Materiales necesarios</h4><p class="muted">Cada renglón usa la unidad de inventario configurada para ese material.</p>
                                @php($previousMaterialRows = old('materials'))
                                @php($materialRequestRows = $resourceContext && is_array($previousMaterialRows) && $previousMaterialRows !== [] ? $previousMaterialRows : [['material_id' => '', 'cantidad_asignada' => '']])
                                <div class="order-material-lines" data-material-lines>
                                    @foreach($materialRequestRows as $materialIndex => $materialRow)
                                    <div class="order-material-line" data-material-line>
                                        <label>Material<select name="materials[{{ $materialIndex }}][material_id]"><option value="">Selecciona un material</option>@foreach($availableMaterialsForOrder as $item)<option value="{{ $item->material_id }}" data-available="{{ $item->stock_libre }}" data-unit="{{ $item->inventario_en_empaque ? $item->unidad_empaque : $item->unidad_base }}" data-packaging="{{ $item->inventario_en_empaque ? '1' : '0' }}" data-factor="{{ $item->factor_conversion }}" data-base-unit="{{ $item->unidad_base }}" @selected(($materialRow['material_id'] ?? '') == $item->material_id)>{{ $item->codigo }} · {{ $item->nombre }} · {{ $item->inventario_en_empaque ? $item->unidad_empaque : $item->unidad_base }}</option>@endforeach</select></label>
                                        <label>Cantidad en la unidad indicada<input name="materials[{{ $materialIndex }}][cantidad_asignada]" type="number" min="0.001" step="0.001" value="{{ $materialRow['cantidad_asignada'] ?? '' }}"></label>
                                        <div class="resource-availability" data-resource-availability role="status">Selecciona un material para ver unidad y stock libre.</div>
                                        <button type="button" class="action-link secondary order-material-remove" data-remove-material>Quitar material</button>
                                    </div>
                                    @endforeach
                                </div>
                                <template data-material-template><div class="order-material-line" data-material-line>
                                    <label>Material<select name="materials[__INDEX__][material_id]"><option value="">Selecciona un material</option>@foreach($availableMaterialsForOrder as $item)<option value="{{ $item->material_id }}" data-available="{{ $item->stock_libre }}" data-unit="{{ $item->inventario_en_empaque ? $item->unidad_empaque : $item->unidad_base }}" data-packaging="{{ $item->inventario_en_empaque ? '1' : '0' }}" data-factor="{{ $item->factor_conversion }}" data-base-unit="{{ $item->unidad_base }}">{{ $item->codigo }} · {{ $item->nombre }} · {{ $item->inventario_en_empaque ? $item->unidad_empaque : $item->unidad_base }}</option>@endforeach</select></label>
                                    <label>Cantidad en la unidad indicada<input name="materials[__INDEX__][cantidad_asignada]" type="number" min="0.001" step="0.001"></label>
                                    <div class="resource-availability" data-resource-availability role="status">Selecciona un material para ver unidad y stock libre.</div>
                                    <button type="button" class="action-link secondary order-material-remove" data-remove-material>Quitar material</button>
                                </div></template>
                                <button type="button" class="action-link secondary order-material-add" data-add-material>＋ Agregar otro material</button>
                            </section>
                            @endif
                        </div>
                        <div class="order-resource-submit"><p>Almacén reservará todo lo aprobado. El inventario físico disminuirá conforme se registre el consumo de cada material.</p><p class="field-error" data-resource-form-error role="alert" hidden>Selecciona al menos un material o un equipo antes de enviar.</p><button type="submit" class="action-link">Enviar solicitud completa a Almacén</button></div>
                    </form>
                    @endif
                </div>
            </details>
            @endif
            @if($canViewTechnicalSheet)<a class="action-link {{ $canRequestResources && ! $resourcesPrepared ? 'secondary' : '' }}" href="{{ route('technician.show', $record->pedido_id) }}">Ver ficha técnica</a>@endif
            @if($showManagerResourceReview && ($canRequestResources || $canViewTechnicalSheet))</div></details>@endif
            </div>
            @endif
            <div class="order-toolbar-utility" aria-label="Consultas y otras acciones del pedido">
            @if($resources->isNotEmpty())
            <details class="order-action order-history order-resource-history" id="historial-recursos-{{ $record->pedido_id }}" data-order-panel><summary>Solicitudes completas del pedido ({{ $resourceRequestGroups->count() }})</summary><ol class="order-history-list">@foreach($resourceRequestGroups as $group)
                @php($request = $group->first())
                <li><div class="order-history-head"><strong>Solicitud conjunta · {{ $group->count() }} {{ $group->count() === 1 ? 'recurso' : 'recursos' }}</strong><small>{{ match($request->estado) {'PENDIENTE_ALMACEN' => 'Pendiente de Almacén', 'PENDIENTE_GERENCIA' => 'Pendiente de Gerencia', 'AUTORIZADA' => 'Autorizada por Gerencia', 'ENTREGADA' => 'Preparada completa', 'RECHAZADA' => 'Rechazada completa', default => 'Anulada'} }}</small></div>
                <ul class="order-history-resources">@foreach($group as $item)<li>{{ $item->tipo === 'EQUIPO' ? ($item->equipo_tipo_solicitado ? 'Equipo tipo '.$item->equipo_tipo_solicitado : $item->equipo_codigo.' · '.$item->equipo_nombre) : $item->material_codigo.' · '.$item->material_nombre }} · {{ \App\Support\InventoryQuantity::format($item->cantidad) }}{{ $item->tipo === 'MATERIAL' ? ' '.($item->material_inventario_en_empaque ? $item->material_unidad_empaque : $item->material_unidad_base) : ' unidad' }}</li>@endforeach</ul>
                <p>Solicitó {{ $request->solicitante }} el {{ \Illuminate\Support\Carbon::parse($request->solicitado_en)->format('d/m/Y H:i') }}.@if($request->resuelto_por) Resolvió {{ $request->aprobador }} el {{ \Illuminate\Support\Carbon::parse($request->resuelto_en)->format('d/m/Y H:i') }}.@endif @if($request->atendido_por) Preparó {{ $request->almacenista }} el {{ \Illuminate\Support\Carbon::parse($request->atendido_en)->format('d/m/Y H:i') }}.@endif @if($request->cerrado_por) Cerró {{ $request->cerrador }} el {{ \Illuminate\Support\Carbon::parse($request->cerrado_en)->format('d/m/Y H:i') }}.@endif</p>
                @if($request->decision_motivo_codigo || $request->decision_observacion)<p>Motivo: {{ \App\Support\ResourceRejectionReasons::label($request->decision_motivo_codigo) ?: $request->decision_observacion }}@if($request->decision_motivo_codigo && $request->decision_observacion) · {{ $request->decision_observacion }}@endif</p>@endif
                </li>@endforeach</ol></details>
            @endif
            <details class="order-card-more" data-order-panel @if($scheduleContext) open @endif><summary>{{ $showManagerResourceReview ? 'Seguimiento del pedido' : ($editable && $canOperate ? 'Detalles y acciones' : 'Detalles del pedido') }}</summary>
            <div class="order-card-more-body">
            <div class="order-flow-overview"><div><p class="order-step-label">AVANCE DE LA INSTALACIÓN</p><h4>Estado actual</h4></div><p>{{ $nextStep }}</p></div>
            <ol class="order-flow-stages" aria-label="Avance del pedido">
                <li class="{{ $coordinated ? 'is-done' : 'is-current' }}"><span class="order-flow-number">1</span><div><strong>Visita confirmada</strong><small>{{ $coordinated ? 'Asistente de ventas registró la fecha' : 'Pendiente de coordinar' }}</small></div></li>
                <li class="{{ $resourcesPrepared ? 'is-done' : ($coordinated ? 'is-current' : '') }}"><span class="order-flow-number">2</span><div><strong>Recursos preparados</strong><small>{{ $resourcesPrepared ? 'Equipo y material listos' : ($openResourceRequests->isNotEmpty() ? 'En revisión por Gerencia o Almacén' : 'Pendiente de registrar los recursos necesarios') }}</small></div></li>
                <li class="{{ $assigned ? 'is-done' : ($resourcesPrepared ? 'is-current' : '') }}"><span class="order-flow-number">3</span><div><strong>Técnico responsable</strong><small>{{ $assigned ? 'Equipo de trabajo definido' : 'Se asigna después de preparar los recursos' }}</small></div></li>
            </ol>
            <div class="order-flow-facts">
                <div><small>Contrato</small><strong>{{ $record->contrato_codigo ?: 'Se registrará después de instalar' }}</strong></div>
                <div><small>Coordinación con el cliente</small><strong>{{ $record->coordinador_nombre ?: ($record->coordinador_usuario ? 'Cuenta '.$record->coordinador_usuario : 'Pendiente') }}</strong><span>{{ $record->canal_coordinacion ? ucfirst(mb_strtolower($record->canal_coordinacion)) : 'Sin canal registrado' }}</span></div>
                <div><small>Responsable de asignación</small><strong>{{ $record->asignador_nombre ?: ($record->asignador_usuario ? 'Cuenta '.$record->asignador_usuario : 'Pendiente de asignación') }}</strong></div>
                @if($record->asignado_en)<div><small>Aviso al técnico</small><strong>{{ $record->notificado_en ? 'Procesado por correo' : 'Pendiente de envío' }}</strong><span>{{ $record->confirmado_por_tecnico_en ? 'Confirmado por el técnico' : 'Espera confirmación del técnico' }}</span></div>@endif
            </div>
            @if($record->estado === 'REPROGRAMADO' && $record->fecha_original)
            <p class="order-change-note"><strong>Fecha inicial:</strong> {{ \Illuminate\Support\Carbon::parse($record->fecha_original)->format('d/m/Y · H:i') }} @if($latestApproved?->motivo_detalle ?? $record->motivo_reprogramacion) · <strong>Motivo:</strong> {{ $latestApproved?->motivo_detalle ?? $record->motivo_reprogramacion }} @endif</p>
            @endif
            @if($editable && $canOperate && $canSchedule && !$pending)
            <div class="order-actions">
                @can('orders.schedule')
                @if(! $record->tecnico_id && ! $resourcesPrepared)<div class="order-next order-next-attention" role="status"><strong>Asignación de técnico en espera</strong><span>{{ $assignmentStockBlocked ? 'No hay equipos disponibles. Podrás asignar al técnico cuando se reabastezca el stock y Almacén prepare los recursos.' : 'Solicita los recursos y espera a que Almacén los prepare antes de asignar un técnico.' }}</span></div>@else
                <details class="order-action order-assignment-action" id="asignar-tecnico-{{ $record->pedido_id }}" @if($scheduleContext) open @endif><summary>{{ $record->tecnico_id ? 'Cambiar técnico asignado' : 'Asignar técnico · Paso 3' }}</summary>
                    <div class="order-assignment-body">
                    <div class="order-assignment-context"><div><small>Visita confirmada</small><strong>{{ $visitDate ? ucfirst($visitDate->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')) : 'Fecha pendiente de registro' }}</strong><span>Inicio {{ $visitDate?->format('H:i') ?: 'por definir' }} · Franja {{ $record->franja_horaria ?: 'por confirmar' }}</span></div><div><small>Después de asignar</small><span>El técnico recibirá un correo para confirmar la visita. La asignación quedará pendiente hasta que responda.</span></div></div>
                    @php($visitTechnicians = $availableTechniciansByOrder->get($record->pedido_id, collect()))
                    <form method="POST" action="{{ route('orders.schedule', $record->pedido_id) }}" class="order-form order-assignment-form" data-installation-form data-availability-url="{{ route('orders.available-technicians', $record->pedido_id) }}">@csrf @method('PATCH')
                        <input type="hidden" name="form_context" value="schedule-{{ $record->pedido_id }}">
                        @if(! $record->fase_flujo)<label class="order-assignment-date">Fecha y hora de visita<input type="datetime-local" name="fecha_programada" value="{{ $scheduleContext ? old('fecha_programada') : '' }}" required><small>Este pedido todavía no tiene una visita confirmada.</small></label>@endif
                        <label class="order-assignment-technician">Técnico responsable<select name="tecnico_id" required @disabled($visitTechnicians->isEmpty())><option value="">{{ $visitTechnicians->isEmpty() ? ($visitDate ? 'Sin técnicos libres en este horario' : 'Selecciona primero la fecha y hora') : 'Selecciona un técnico' }}</option>@foreach($visitTechnicians as $technician)<option value="{{ $technician->getKey() }}" @selected(($scheduleContext ? old('tecnico_id') : $record->tecnico_id) == $technician->getKey())>{{ $technician->full_name }}</option>@endforeach</select><small>Solo aparecen técnicos libres el día, hora y franja de esta visita.</small></label>
                        <label class="order-assignment-technician">Segundo técnico (opcional)<select name="tecnico_secundario_id" @disabled($visitTechnicians->isEmpty())><option value="">Técnico de apoyo</option>@foreach($visitTechnicians as $technician)<option value="{{ $technician->getKey() }}" @selected(($scheduleContext ? old('tecnico_secundario_id') : $record->tecnico_secundario_id) == $technician->getKey())>{{ $technician->full_name }}</option>@endforeach</select><small>También debe estar libre en el horario de la visita.</small></label>
                        <p class="order-assignment-responsible">La asignación quedará registrada a nombre de <strong>{{ auth()->user()->employee?->full_name ?? 'Cuenta sin empleado asociado' }}</strong>.</p>
                        @if($record->tecnico_id)<label>Motivo del cambio<textarea name="motivo_reprogramacion" maxlength="500" rows="2" required>{{ $scheduleContext ? old('motivo_reprogramacion') : '' }}</textarea></label>@endif
                        <div class="order-assignment-submit"><button type="submit" class="action-link">{{ $record->tecnico_id ? 'Guardar cambio y avisar' : 'Confirmar asignación y avisar' }}</button></div>
                    </form>
                    </div>
                </details>
                @endif
                @endcan
            </div>
            @endif
            @if(($editable && auth()->user()->hasPermission('orders.cancel')) || ($record->estado === 'CANCELADO' && auth()->user()->hasPermission('orders.schedule') && in_array($record->contrato_estado, ['PENDIENTE', 'ACTIVO'], true)) || $history->isNotEmpty() || $resources->isNotEmpty() || $events->isNotEmpty())<div class="order-card-utilities" aria-label="Opciones adicionales del pedido">
            @if($editable)@can('orders.cancel')<details class="order-action order-danger"><summary>Cancelar este pedido</summary><div class="order-cancel-body"><p>Selecciona el motivo que corresponde. La cancelación quedará registrada con tu usuario y fecha; se liberarán los recursos y podrás retomar el pedido si el contrato sigue vigente.</p><form method="POST" action="{{ route('orders.cancel', $record->pedido_id) }}" class="order-form order-cancel-form" data-confirm-cancel data-installation-form data-order-cancel-form>@csrf @method('PATCH')<label for="motivo-codigo-{{ $record->pedido_id }}">Motivo de cancelación *</label><select id="motivo-codigo-{{ $record->pedido_id }}" name="motivo_codigo" required data-order-cancel-reason><option value="">Selecciona el motivo</option>@foreach($cancellationReasons as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select><small class="order-cancel-help">La categoría y, si corresponde, el detalle se conservarán en el historial del pedido.</small><label class="order-cancel-detail" data-cancel-detail-wrap for="motivo-detalle-{{ $record->pedido_id }}" hidden>Explica el motivo *<textarea id="motivo-detalle-{{ $record->pedido_id }}" name="motivo_detalle" minlength="10" maxlength="500" rows="3" data-cancel-detail placeholder="Describe brevemente qué ocurrió y cuándo"></textarea><small>Mínimo 10 caracteres.</small></label><div class="order-cancel-submit"><button type="submit" class="action-link order-cancel-submit-button">Revisar cancelación</button></div></form></div></details>@endcan @endif
            @if($record->estado === 'CANCELADO' && auth()->user()->hasPermission('orders.schedule') && in_array($record->contrato_estado, ['PENDIENTE', 'ACTIVO'], true))<details class="order-action"><summary>Retomar instalación</summary><p>El pedido volverá al inicio para confirmar la visita y solicitar nuevamente los recursos. El historial de la cancelación se conservará.</p><form method="POST" action="{{ route('orders.reopen', $record->pedido_id) }}" data-installation-form>@csrf @method('PATCH')<label>¿Por qué se retoma este pedido?<textarea name="motivo_reapertura" required minlength="5" maxlength="500" rows="2" placeholder="Por ejemplo: cancelación accidental; el cliente mantiene el servicio"></textarea></label><button type="submit" class="action-link">Reabrir y volver a coordinación</button></form></details>@elseif($record->estado === 'CANCELADO' && auth()->user()->hasPermission('orders.schedule'))<p class="order-next order-next-muted">{{ $record->contrato_estado === 'CANCELADO' ? 'El contrato se canceló. Reactívalo desde Contratos para que el pedido vuelva automáticamente a Coordinación.' : 'El contrato finalizó. Si el cliente solicita una nueva instalación, registra un contrato nuevo.' }}</p>@endif
            @if($events->isNotEmpty())<details class="order-action order-history"><summary>Historial del pedido ({{ $events->count() }})</summary><ol class="order-history-list">@foreach($events as $event)<li><div class="order-history-head"><strong>{{ match($event->tipo) {'REAPERTURA' => 'Pedido reabierto', 'CIERRE_AUTOMATICO' => 'Instalación cerrada automáticamente', default => 'Pedido cancelado'} }}</strong><small>{{ $event->usuario_nombre ?: 'Sistema' }} · {{ \Illuminate\Support\Carbon::parse($event->registrado_en)->format('d/m/Y · H:i') }}</small></div><p>{{ $event->motivo }}</p></li>@endforeach</ol></details>@endif
            @if($history->isNotEmpty())
            <details class="order-action order-history"><summary>Historial de reprogramaciones ({{ $history->count() }})</summary>
                <ol class="order-history-list">@foreach($history as $change)
                    <li><div class="order-history-head"><strong>{{ match($change->estado) {'SOLICITADA' => 'Pendiente de Gerencia', 'APROBADA' => 'Aprobada', 'RECHAZADA' => 'Rechazada', default => 'Anulada'} }}</strong><small>Solicitó {{ $change->solicitante_nombre }} el {{ \Illuminate\Support\Carbon::parse($change->solicitado_en)->format('d/m/Y H:i') }}</small></div>
                        <p><strong>Fecha anterior:</strong> {{ \Illuminate\Support\Carbon::parse($change->fecha_anterior)->format('d/m/Y H:i') }} · {{ $change->franja_anterior ?: 'Sin franja' }}<br><strong>Fecha solicitada:</strong> {{ \Illuminate\Support\Carbon::parse($change->fecha_nueva)->format('d/m/Y H:i') }} · {{ $change->franja_nueva }}</p>
                        <p><strong>Motivo:</strong> {{ $reasonOptions[$change->motivo_codigo] ?? $change->motivo_codigo }}@if($change->motivo_detalle) · {{ $change->motivo_detalle }}@endif</p>
                        @if($change->resuelto_por)<p><strong>Decisión:</strong> {{ $change->aprobador_nombre }} el {{ \Illuminate\Support\Carbon::parse($change->resuelto_en)->format('d/m/Y H:i') }}@if($change->decision_observacion) · {{ $change->decision_observacion }}@endif</p>@endif
                        @if($change->evidencia_ruta && auth()->user()->hasPermission('orders.view'))<a href="{{ route('orders.reprogram.evidence', [$record->pedido_id, $change->reprogramacion_id]) }}">Descargar archivo de respaldo: {{ $change->evidencia_nombre ?: 'evidencia' }}</a>@endif
                    </li>
                @endforeach</ol>
            </details>
            @endif
            </div>@endif
            </div>
            </details>
            </div>
        </div>
    </article>
    @empty
    <div class="installation-panel installation-empty"><h3>No hay pedidos que coincidan con la búsqueda.</h3><p class="muted">Prueba con otro código, cliente o estado.</p><a class="action-link secondary" href="{{ route($listingRoute) }}">Ver todos los pedidos</a></div>
    @endforelse
    @include('clients.pagination', ['records' => $records, 'label' => 'Paginación de pedidos'])
</section>
<dialog id="order-cancel-confirm" class="installation-confirm order-cancel-confirm" aria-labelledby="order-cancel-title"><h2 id="order-cancel-title">¿Confirmas la cancelación?</h2><p>Se guardará el motivo en el historial y se liberarán los recursos reservados. Si el contrato sigue vigente, podrás reabrir el pedido después.</p><div class="installation-filter-actions"><button type="button" class="action-link secondary" data-cancel-order>Volver a revisar</button><button type="button" class="action-link order-cancel-submit-button" data-accept-order>Confirmar cancelación</button></div></dialog>
@endsection
