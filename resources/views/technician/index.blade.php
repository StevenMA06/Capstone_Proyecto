@extends('layouts.management')
@section('title', 'Ejecución de Instalaciones')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/installations.css') }}?v=2">
<script src="{{ asset('js/installations.js') }}?v=1" defer></script>
@endpush
@section('workspace-content')
<nav class="workspace-breadcrumb" aria-label="Ruta de navegación"><a href="{{ route('dashboard') }}">Inicio</a><span aria-hidden="true">›</span><span aria-current="page">Instalaciones</span></nav>
<header class="installation-heading"><div><h1>Ejecución de Instalaciones</h1>
<p class="muted">{{ auth()->user()->isManager() ? 'Supervisa las asignaciones y el trabajo del equipo técnico.' : 'Consulta tus asignaciones y registra el trabajo realizado.' }}</p></div></header>
@if($errors->any())<div class="field-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="GET" action="{{ route('technician.index') }}" class="installation-search installation-panel" data-installation-form>
<label class="installation-search-text" for="search">Código o cliente<input type="search" id="search" name="search" maxlength="150" value="{{ request('search') }}" placeholder="Buscar por código, nombre o apellidos"></label>
<label for="pedido_id">ID de ficha<input type="number" id="pedido_id" name="pedido_id" min="1" value="{{ request('pedido_id') }}" placeholder="Ej. 15"></label>
<label for="fecha">Fecha programada<input type="date" id="fecha" name="fecha" value="{{ request('fecha') }}"></label>
<label for="estado">Estado<select id="estado" name="estado"><option value="">Todos</option>@foreach($states as $state)<option value="{{ $state }}" @selected(request('estado') === $state)>{{ ucfirst(mb_strtolower(str_replace('_', ' ', $state))) }}</option>@endforeach</select></label>
<div class="installation-filter-actions"><button type="submit" class="action-link">Aplicar filtros</button><a class="action-link secondary" href="{{ route('technician.index') }}">Limpiar filtros</a></div>
<p class="installation-loading" role="status" hidden>Buscando instalaciones…</p>
</form>
<section class="installation-list" aria-labelledby="installation-list-title">
<header class="installation-list-heading"><div><h2 id="installation-list-title">Instalaciones asignadas</h2><p class="muted">{{ $records->total() }} {{ $records->total() === 1 ? 'instalación encontrada' : 'instalaciones encontradas' }}</p></div><small>Pendientes primero, luego en proceso y completadas</small></header>
@if($records->isEmpty())
<div class="installation-panel installation-empty"><h3>{{ request()->hasAny(['search', 'fecha', 'estado', 'pedido_id']) ? 'No hay instalaciones que coincidan con los filtros.' : 'No hay instalaciones asignadas.' }}</h3><p class="muted">Las asignaciones disponibles aparecerán aquí.</p><a class="action-link secondary" href="{{ route('technician.index') }}">Limpiar filtros</a></div>
@else
<div class="table-scroll"><table class="users-table installation-table"><thead><tr><th>Código</th><th>Cliente / dirección</th><th>Fecha programada</th><th>Técnico</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@foreach($records as $record)
<tr>
<td data-label="Código"><div><strong>{{ $record->codigo }}</strong><small>Ficha #{{ $record->pedido_id }}</small></div></td>
<td data-label="Cliente / dirección"><div>{{ trim($record->nombres_razon_social.' '.$record->apellidos) }}<small>{{ $record->direccion_servicio }}</small></div></td>
<td data-label="Fecha programada">{{ $record->fecha_programada ? \Illuminate\Support\Carbon::parse($record->fecha_programada)->format('d/m/Y H:i') : 'Sin programar' }}</td>
<td data-label="Técnico">{{ $record->tecnico_id ? trim($record->tecnico_nombres.' '.$record->tecnico_apellidos) : 'Sin asignar' }}</td>
<td data-label="Estado"><span class="installation-state state-{{ strtolower($record->estado) }}">{{ ucfirst(mb_strtolower(str_replace('_', ' ', $record->estado))) }}</span></td>
<td data-label="Acciones"><div class="installation-row-actions">
<a class="action-link secondary" href="{{ route('technician.show', $record->pedido_id) }}">Ver detalle</a>
@if($record->tecnico_id && in_array($record->estado, ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO']))
@can('technician.manage')<form method="POST" action="{{ route('technician.start', $record->pedido_id) }}" data-installation-form>@csrf<button type="submit" class="action-link">Iniciar instalación</button><span class="installation-loading" role="status" hidden>Registrando inicio…</span></form>@endcan
@endif
</div></td>
</tr>
@endforeach
</tbody></table></div>
@include('clients.pagination', ['label' => 'Paginación de instalaciones asignadas'])
@endif
</section>
@endsection
