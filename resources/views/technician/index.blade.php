@extends('layouts.management')
@section('title', 'Panel técnico')
@section('workspace_class', 'record-tables')
@push('styles')<link rel="stylesheet" href="{{ asset('css/installations.css') }}">@endpush
@section('workspace-content')
<h1>Panel técnico</h1><p class="muted">{{ auth()->user()->isManager() ? 'Consulta de instalaciones y ejecución del equipo técnico.' : 'Consulta tus asignaciones y registra el trabajo realizado.' }}</p>
<form method="GET" action="{{ route('technician.index') }}" class="installation-search">
<label for="pedido_id">ID de la ficha<input type="number" id="pedido_id" name="pedido_id" min="1" value="{{ request('pedido_id') }}" placeholder="Ejemplo: 15"></label>
<button type="submit" class="action-link">Buscar instalación</button><a class="action-link secondary" href="{{ route('technician.index') }}">Limpiar</a>
</form>
@error('pedido_id')<p class="field-error" role="alert">{{ $message }}</p>@enderror
<div class="table-scroll"><table class="users-table"><thead><tr><th>Instalación</th><th>Programación</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($records as $record)<tr><td><strong>#{{ $record->pedido_id }} · {{ $record->codigo }}</strong></td><td>{{ $record->fecha_programada ?? 'Sin programar' }}</td><td>{{ ucfirst(mb_strtolower(str_replace('_', ' ', $record->estado))) }}</td><td><a href="{{ route('technician.show', $record->pedido_id) }}">Ver ficha</a></td></tr>
@empty<tr><td colspan="4">No hay instalaciones asignadas.</td></tr>@endforelse
</tbody></table></div>
@include('clients.pagination', ['label' => 'Paginación de instalaciones asignadas'])
@endsection
