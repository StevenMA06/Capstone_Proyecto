@extends('layouts.management')
@section('title', 'Solicitudes a Almacén')
@push('styles')<link rel="stylesheet" href="{{ asset('css/installations.css') }}?v=29"><script src="{{ asset('js/resource-rejection.js') }}?v=1" defer></script>@endpush
@section('workspace-content')
<nav class="workspace-breadcrumb" aria-label="Ruta de navegación"><a href="{{ route('dashboard') }}">Inicio</a><span aria-hidden="true">›</span><span aria-current="page">Solicitudes a Almacén</span></nav>
<header class="installation-heading order-heading"><div><p class="eyebrow">INVENTARIO</p><h1>Solicitudes de instalación</h1><p class="muted">Revisa la disponibilidad y prepara los recursos para cada pedido. Si solicitaste el recurso desde esta misma cuenta, Gerencia debe autorizarlo primero.</p></div></header>
@if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="notice" role="alert"><strong>Revisa la solicitud:</strong>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="order-list" aria-label="Solicitudes de recursos">
    <header class="installation-list-heading warehouse-list-heading"><div><h2>{{ $view === 'pendientes' ? 'Solicitudes en trámite' : 'Historial de solicitudes' }}</h2><p class="muted">{{ $records->total() }} {{ $view === 'pendientes' ? 'por resolver' : 'resueltas' }}</p></div></header>
    <nav class="warehouse-view-switch" aria-label="Ver solicitudes"><a href="{{ route('warehouse.requests.index', array_filter(['pedido' => request('pedido')])) }}" @if($view === 'pendientes') aria-current="page" @endif>Pendientes <span class="warehouse-tab-count">{{ $pendingCount }}</span></a><a href="{{ route('warehouse.requests.index', array_filter(['vista' => 'historial', 'pedido' => request('pedido'), 'buscar' => $filters['buscar'] ?? null, 'resultado' => $filters['resultado'] ?? null])) }}" @if($view === 'historial') aria-current="page" @endif>Historial</a></nav>
    @if(request()->filled('pedido') && $records->count())<p class="order-resource-summary">Solicitud vinculada al pedido <strong>{{ $records->first()->pedido_codigo }}</strong>. <a href="{{ route('warehouse.requests.index') }}">Volver a todas las solicitudes</a></p>@endif
    @if($view === 'historial')
    <form method="GET" action="{{ route('warehouse.requests.index') }}" class="warehouse-history-filter" role="search">
        <input type="hidden" name="vista" value="historial">
        @if(request()->filled('pedido'))<input type="hidden" name="pedido" value="{{ request('pedido') }}">@endif
        <label class="warehouse-history-search"><span>Buscar en el historial</span><input type="search" name="buscar" value="{{ $filters['buscar'] ?? '' }}" placeholder="Pedido, cliente, recurso o usuario" maxlength="150"></label>
        <label class="warehouse-history-result"><span>Resultado</span><select name="resultado"><option value="">Todos los resultados</option><option value="ENTREGADA" @selected(($filters['resultado'] ?? '') === 'ENTREGADA')>Preparadas</option><option value="RECHAZADA" @selected(($filters['resultado'] ?? '') === 'RECHAZADA')>Rechazadas</option><option value="ANULADA" @selected(($filters['resultado'] ?? '') === 'ANULADA')>Anuladas</option></select></label>
        <div class="warehouse-history-actions"><button type="submit" class="action-link">Buscar</button><a class="action-link secondary" href="{{ route('warehouse.requests.index', array_filter(['vista' => 'historial', 'pedido' => request('pedido')])) }}">Limpiar</a></div>
    </form>
    @endif
    @forelse($records as $group)
    @php
        $items = $group->resources;
        $representative = $items->first();
        $activeOrder = in_array($group->pedido_estado, ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO'], true);
        $pendingStates = ['PENDIENTE_ALMACEN', 'PENDIENTE_GERENCIA', 'AUTORIZADA'];
        $pending = $items->contains(fn ($item) => in_array($item->estado, $pendingStates, true));
        $sameAccount = (int) $group->solicitado_por === (int) auth()->id();
        $allAuthorizedForWarehouse = $items->every(fn ($item) => in_array($item->estado, ['PENDIENTE_ALMACEN', 'AUTORIZADA'], true));
        $canDeliver = $allAuthorizedForWarehouse && $activeOrder
            && (!$sameAccount || ($items->every(fn ($item) => $item->estado === 'AUTORIZADA' && $item->resuelto_por && (int) $item->resuelto_por !== (int) auth()->id())));
        $canReview = $canDeliver && auth()->user()->hasPermission('warehouse.fulfill');
        $groupLabel = $items->contains('estado', 'PENDIENTE_GERENCIA') ? 'Pendiente de Gerencia'
            : ($items->contains('estado', 'AUTORIZADA') ? 'Autorizada por Gerencia'
                : ($items->contains('estado', 'PENDIENTE_ALMACEN') ? 'Pendiente de Almacén'
                    : ($items->contains('estado', 'RECHAZADA') ? 'Rechazada' : ($items->every(fn ($item) => $item->estado === 'ENTREGADA') ? 'Preparada' : 'Anulada'))));
    @endphp
    @include('warehouse.request-card')
    @empty<div class="installation-panel installation-empty"><h3>{{ $view === 'pendientes' ? 'No hay solicitudes pendientes.' : 'Todavía no hay solicitudes resueltas.' }}</h3><p class="muted">{{ $view === 'pendientes' ? 'Aparecerán aquí cuando Instalaciones solicite equipo o material.' : 'Aquí quedará registrado quién autorizó, preparó, rechazó o anuló cada recurso.' }}</p></div>@endforelse
    @include('clients.pagination', ['records' => $records, 'label' => 'Paginación de solicitudes'])
</section>
@endsection
