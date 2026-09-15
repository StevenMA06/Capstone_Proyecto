@extends('plans.layout')
@section('plans-content')
<div class="plans-workspace">
<aside class="plans-filter-panel" aria-labelledby="plans-filter-title">
<h2 id="plans-filter-title">Filtros y buscador</h2>
<p>Encuentra un plan por su número y estado.</p>
<form method="GET" action="{{ route('plans.index') }}" class="plans-filters">
    <div><label for="search">Número de plan</label><input id="search" name="search" value="{{ request('search') }}" placeholder="Buscar por número de plan" maxlength="30"></div>
    <div><label for="estado">Estado</label><select id="estado" name="estado"><option value="">Todos los estados</option>@foreach(['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo'] as $value => $label)<option value="{{ $value }}" @selected(request('estado') === $value)>{{ $label }}</option>@endforeach</select></div>
    <button class="action-link" type="submit">Aplicar filtros</button><a class="clear-link" href="{{ route('plans.index') }}">Limpiar filtros</a>
</form>
<div class="plans-filter-note">Los planes inactivos conservan su información y no están disponibles para nuevos contratos.</div>
</aside>
<section class="plans-results" aria-labelledby="plans-list-title">
<div class="management-toolbar"><div><h2 id="plans-list-title">Listado de planes de servicio</h2><p class="plans-total">{{ $records->total() }} planes encontrados</p></div>@can('manage-plans')<a class="action-link" href="{{ route('plans.create') }}">+ Registrar nuevo plan</a>@endcan</div>
<div class="table-scroll" tabindex="0" role="region" aria-label="Listado de planes de servicio"><table class="users-table plans-table"><caption class="sr-only">Planes registrados</caption><thead><tr><th scope="col">Nombre del plan</th><th scope="col">Velocidad</th><th scope="col">Precio mensual</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead><tbody>
@forelse($records as $plan)<tr>
    <td><strong>{{ $plan->nombre }}</strong><span class="plan-number">{{ $plan->numero_plan }}</span><span class="plan-service">{{ $plan->tipo_servicio }}</span></td><td>{{ $plan->velocidad_mbps ? $plan->velocidad_mbps.' Mbps' : 'No aplica' }}</td><td class="plan-price">S/ {{ number_format($plan->precio_mensual, 2) }}</td><td><span class="state {{ $plan->estado === 'ACTIVO' ? 'active' : 'inactive' }}">{{ $plan->estado === 'ACTIVO' ? 'Activo' : 'Inactivo' }}</span></td>
    <td><div class="row-actions"><a href="{{ route('plans.show', $plan) }}">Ver ficha</a>@can('manage-plans')<a href="{{ route('plans.edit', $plan) }}">Editar</a>@endcan</div></td>
</tr>@empty<tr><td colspan="5" class="empty-state">No se encontraron planes con esos criterios.</td></tr>@endforelse
</tbody></table></div>
@include('clients.pagination', ['label' => 'Paginación de planes'])
</section>
</div>
@endsection
