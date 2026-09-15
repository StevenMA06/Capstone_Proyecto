@extends('plans.layout')
@section('plans-content')
<div class="management-toolbar"><h2>Listado de planes <span class="count">{{ $records->total() }}</span></h2>@can('manage-plans')<a class="action-link" href="{{ route('plans.create') }}">+ Registrar plan</a>@endcan</div>
<form method="GET" action="{{ route('plans.index') }}" class="filters">
    <div><label for="search">Número de plan</label><input id="search" name="search" value="{{ request('search') }}" placeholder="Buscar por número de plan" maxlength="30"></div>
    <div><label for="estado">Estado</label><select id="estado" name="estado"><option value="">Todos los estados</option>@foreach(['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo'] as $value => $label)<option value="{{ $value }}" @selected(request('estado') === $value)>{{ $label }}</option>@endforeach</select></div>
    <button class="action-link" type="submit">Buscar</button><a class="clear-link" href="{{ route('plans.index') }}">Limpiar</a>
</form>
<div class="table-scroll"><table class="users-table"><caption class="sr-only">Planes registrados</caption><thead><tr><th>Número</th><th>Nombre</th><th>Servicio</th><th>Velocidad</th><th>Precio mensual</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($records as $plan)<tr>
    <td>{{ $plan->numero_plan }}</td><td>{{ $plan->nombre }}</td><td>{{ $plan->tipo_servicio }}</td><td>{{ $plan->velocidad_mbps ? $plan->velocidad_mbps.' Mbps' : 'No aplica' }}</td><td>S/ {{ number_format($plan->precio_mensual, 2) }}</td><td><span class="state {{ $plan->estado === 'ACTIVO' ? 'active' : 'inactive' }}">{{ $plan->estado === 'ACTIVO' ? 'Activo' : 'Inactivo' }}</span></td>
    <td><div class="row-actions"><a href="{{ route('plans.show', $plan) }}">Ver ficha</a>@can('manage-plans')<a href="{{ route('plans.edit', $plan) }}">Editar</a>@endcan</div></td>
</tr>@empty<tr><td colspan="7" class="empty-state">No se encontraron planes con esos criterios.</td></tr>@endforelse
</tbody></table></div>
@include('clients.pagination', ['label' => 'Paginación de planes'])
@endsection
