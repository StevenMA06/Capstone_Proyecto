@extends('layouts.management')
@section('title', 'Ejecución de Instalaciones')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/installations.css') }}?v=28">
<script src="{{ asset('js/installations.js') }}?v=8" defer></script>
@endpush
@section('workspace-content')
<nav class="workspace-breadcrumb" aria-label="Ruta de navegación"><a href="{{ route('dashboard') }}">Inicio</a><span aria-hidden="true">›</span><span aria-current="page">Instalaciones</span></nav>
<header class="installation-heading"><div><p class="eyebrow">TRABAJO EN CAMPO</p><h1>{{ auth()->user()->hasPermission('technician.supervise') ? 'Supervisión de instalaciones' : 'Mis instalaciones' }}</h1>
<p class="muted">{{ auth()->user()->hasPermission('technician.supervise') ? 'Revisa quién está asignado, la visita acordada y el avance de cada instalación.' : 'Confirma la asignación, inicia la visita y registra el trabajo y sus evidencias desde tu celular.' }}</p></div></header>
@if($errors->any())<div class="field-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="GET" action="{{ route(auth()->user()->hasPermission('technician.panel') ? 'technician.index' : 'executions.index') }}" class="installation-search installation-panel" data-installation-form>
<label class="installation-search-text" for="search">Código o cliente<input type="search" id="search" name="search" maxlength="150" value="{{ request('search') }}" placeholder="Buscar por código, nombre o apellidos"></label>
<details class="installation-extra-filters" @if(request()->filled('pedido_id') || request()->filled('fecha') || request()->filled('estado')) open @endif><summary>Más filtros</summary><div>
<label for="pedido_id">ID de ficha<input type="number" id="pedido_id" name="pedido_id" min="1" value="{{ request('pedido_id') }}" placeholder="Ej. 15"></label>
<label for="fecha">Fecha programada<input type="date" id="fecha" name="fecha" value="{{ request('fecha') }}"></label>
<label for="estado">Estado<select id="estado" name="estado"><option value="">Todos</option>@foreach($states as $state)<option value="{{ $state }}" @selected(request('estado') === $state)>{{ ucfirst(mb_strtolower(str_replace('_', ' ', $state))) }}</option>@endforeach</select></label>
</div></details>
<div class="installation-filter-actions"><button type="submit" class="action-link">Aplicar filtros</button><a class="action-link secondary" href="{{ route(auth()->user()->hasPermission('technician.panel') ? 'technician.index' : 'executions.index') }}">Limpiar filtros</a></div>
<p class="installation-loading" role="status" hidden>Buscando instalaciones…</p>
</form>
<section class="installation-list" aria-labelledby="installation-list-title">
<header class="installation-list-heading"><div><h2 id="installation-list-title">Instalaciones {{ auth()->user()->hasPermission('technician.supervise') ? 'del equipo' : 'asignadas' }}</h2><p class="muted">{{ $records->total() }} {{ $records->total() === 1 ? 'instalación encontrada' : 'instalaciones encontradas' }}</p></div></header>
@if($records->isEmpty())
<div class="installation-panel installation-empty"><h3>{{ request()->hasAny(['search', 'fecha', 'estado', 'pedido_id']) ? 'No hay instalaciones que coincidan con los filtros.' : 'No hay instalaciones asignadas.' }}</h3><p class="muted">Las asignaciones disponibles aparecerán aquí.</p><a class="action-link secondary" href="{{ route(auth()->user()->hasPermission('technician.panel') ? 'technician.index' : 'executions.index') }}">Limpiar filtros</a></div>
@else
<div class="installation-cards">
@foreach($records as $record)
@php
    $mine = $record->tecnico_id && (int) $record->tecnico_id === (int) auth()->user()->empleado_id;
    $changePending = $pendingReprogramming->contains($record->pedido_id);
    $canBegin = ! $changePending && $mine && in_array($record->estado, ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO'], true) && (! $record->fase_flujo || $record->fase_flujo === 'CONFIRMADO');
    $canConfirm = $mine && $record->fase_flujo === 'ESPERANDO_CONFIRMACION_TECNICO';
    $supervising = auth()->user()->hasPermission('technician.supervise');
    $visitDate = $record->fecha_programada ? \Illuminate\Support\Carbon::parse($record->fecha_programada) : null;
    $nextAction = match (true) {
        $record->estado === 'CANCELADO' => 'Pedido cancelado',
        $record->estado === 'COMPLETADO' => 'Instalación finalizada',
        $record->estado === 'EN_PROCESO' => $supervising ? 'El técnico ya inició; consulta su avance en la ficha' : 'Continúa registrando recursos, incidencias y evidencias',
        $changePending => 'Espera la decisión de Gerencia; la visita conserva la fecha vigente',
        ! $record->fecha_programada => 'Falta acordar la fecha de visita con el cliente',
        ! $record->tecnico_id => 'Falta asignar al técnico instalador',
        $record->fase_flujo === 'ESPERANDO_CONFIRMACION_TECNICO' => $supervising ? 'El técnico aún debe confirmar que recibió la asignación' : 'Confirma que recibiste la asignación para poder iniciar',
        $canBegin => 'Ya puedes iniciar la instalación desde esta ficha',
        $supervising => 'Visita programada; el técnico podrá iniciarla',
        default => 'Visita programada',
    };
@endphp
<article class="installation-panel technician-card">
    <div class="technician-card-top"><div><p class="order-card-kicker">FICHA #{{ $record->pedido_id }}</p><h3>{{ $record->codigo }}</h3><p class="technician-card-client"><small>Cliente</small><strong>{{ trim($record->nombres_razon_social.' '.$record->apellidos) }}</strong></p></div><span class="installation-state state-{{ strtolower($record->estado) }}">{{ ucfirst(mb_strtolower(str_replace('_', ' ', $record->estado))) }}</span></div>
    <div class="technician-card-meta">
        <div class="technician-card-visit"><small>Visita acordada</small><dl>
            <div><dt>Día</dt><dd>{{ $visitDate ? ucfirst($visitDate->locale('es')->isoFormat('dddd')).' '.$visitDate->format('d/m/Y') : 'Por confirmar' }}</dd></div>
            <div><dt>Hora de inicio</dt><dd>{{ $visitDate ? $visitDate->format('H:i') : 'Por confirmar' }}</dd></div>
            <div><dt>Franja</dt><dd>{{ $record->franja_horaria ?: 'Por confirmar' }}</dd></div>
        </dl></div>
        <div><small>Dirección del servicio</small><strong>{{ $record->direccion_servicio }}</strong></div>
        <div><small>Técnico instalador</small><strong>{{ $record->tecnico_id ? trim($record->tecnico_nombres.' '.$record->tecnico_apellidos) : 'Sin asignar' }}</strong></div>
    </div>
    @if($record->estado === 'REPROGRAMADO' && $record->fecha_original)<p class="technician-card-note">Esta visita fue reprogramada · Fecha inicial: {{ \Illuminate\Support\Carbon::parse($record->fecha_original)->format('d/m/Y · H:i') }}</p>@endif
    <div class="technician-card-footer"><div class="technician-card-next {{ $record->estado === 'COMPLETADO' ? 'is-complete' : ($record->estado === 'CANCELADO' ? 'is-muted' : 'is-pending') }}"><small>{{ in_array($record->estado, ['COMPLETADO', 'CANCELADO']) ? 'SEGUIMIENTO' : 'SIGUIENTE PASO' }}</small><strong>{{ $nextAction }}</strong></div><div class="installation-row-actions">
        @if($supervising && in_array($record->estado, ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO'], true) && auth()->user()->hasPermission('office.coordination'))
            <a class="action-link" href="{{ route('office.coordination.index', ['search' => $record->codigo]) }}#visita-{{ $record->pedido_id }}">{{ $changePending ? 'Ver coordinación' : ($record->fecha_programada ? 'Solicitar cambio de fecha' : 'Coordinar fecha') }}</a>
        @endif
        @routecan('technician.show')<a class="action-link secondary" href="{{ route('technician.show', $record->pedido_id) }}">{{ $record->estado === 'EN_PROCESO' ? 'Continuar trabajo' : 'Abrir ficha' }}</a>@endroutecan
        @if($canConfirm)
            @routecan('technician.confirm')
            <form method="POST" action="{{ route('technician.confirm', $record->pedido_id) }}" data-installation-form>@csrf<button type="submit" class="action-link">Confirmar que recibí la asignación</button></form>
            @endroutecan
        @endif
        @if($canBegin)
            @can('technician.manage')
                @routecan('technician.start')
                <form method="POST" action="{{ route('technician.start', $record->pedido_id) }}" data-installation-form>@csrf<button type="submit" class="action-link">Iniciar instalación</button></form>
                @endroutecan
            @endcan
        @endif
    </div></div>
</article>
@endforeach
</div>
@include('clients.pagination', ['label' => 'Paginación de instalaciones asignadas'])
@endif
</section>
@endsection
