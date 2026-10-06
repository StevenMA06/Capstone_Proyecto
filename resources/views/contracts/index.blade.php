@extends('contracts.layout')
@section('contracts-content')
<div class="contracts-panel">

@routecan('contracts.index')<form method="GET" action="{{ route('contracts.index') }}" class="contracts-filters">
    <div><label for="search">Código o documento del cliente</label><input id="search" name="search" value="{{ request('search') }}" placeholder="Buscar contrato o documento" maxlength="180"></div>
    <div><label for="desde">Fecha de inicio desde</label><input id="desde" type="date" name="desde" value="{{ request('desde') }}"></div>
    <div><label for="hasta">Fecha de inicio hasta</label><input id="hasta" type="date" name="hasta" value="{{ request('hasta') }}"></div>
    <div><label for="plan_id">Plan</label><select id="plan_id" name="plan_id"><option value="">Todos los planes</option>@foreach($plans as $plan)<option value="{{ $plan->plan_id }}" @selected((string) request('plan_id') === (string) $plan->plan_id)>{{ $plan->nombre }}</option>@endforeach</select></div>
    <div><label for="estado">Estado</label><select id="estado" name="estado"><option value="">Todos los estados</option>@foreach($states as $state)<option value="{{ $state }}" @selected(request('estado') === $state)>{{ ucfirst(mb_strtolower($state)) }}</option>@endforeach</select></div>
    <div><label for="orden">Ordenar por</label><select id="orden" name="orden"><option value="recientes">Más recientes</option><option value="plan_desc" @selected(request('orden') === 'plan_desc')>Plan descendente</option><option value="estado_desc" @selected(request('orden') === 'estado_desc')>Estado descendente</option></select></div>
    <div class="contracts-filter-actions"><button class="action-link" type="submit">Filtrar</button>
@routecan('contracts.index')<a class="clear-link" href="{{ route('contracts.index') }}">Limpiar</a>@endroutecan
</div>
</form>@endroutecan

</div>
<section class="contracts-panel" aria-labelledby="contracts-list-title">
    <div class="management-toolbar contracts-toolbar"><div><h2 id="contracts-list-title">Contratos registrados</h2><p class="muted">{{ $records->total() }} contratos encontrados</p></div>@can('contracts.manage')
@routecan('contracts.create')<a class="action-link" href="{{ route('contracts.create') }}">+ Registrar contrato</a>@endroutecan
@endcan</div>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Listado de contratos"><table class="users-table contracts-table"><caption class="sr-only">Contratos registrados</caption><thead><tr><th scope="col">Cliente</th><th scope="col">Contrato</th><th scope="col">Plan</th><th scope="col">Monto mensual</th><th scope="col">Estado</th><th scope="col">Inicio</th><th scope="col">Finalización</th><th scope="col">Acciones</th></tr></thead><tbody>
    @forelse($records as $record)<tr><td><strong>{{ trim($record->nombres_razon_social.' '.$record->apellidos) }}</strong><small>{{ $record->tipo_documento }} {{ $record->numero_documento }}</small></td><td>{{ $record->codigo }}</td><td>{{ $record->plan_nombre }}</td><td>S/ {{ number_format($record->precio_contratado, 2) }}</td><td><span class="state {{ $record->estado === 'ACTIVO' ? 'active' : 'inactive' }}">{{ ucfirst(mb_strtolower($record->estado)) }}</span></td><td>{{ \Illuminate\Support\Carbon::parse($record->fecha_inicio)->format('d/m/Y') }}</td><td>{{ \Illuminate\Support\Carbon::parse($record->fecha_fin)->format('d/m/Y') }}</td><td><div class="row-actions">
@routecan('contracts.show')<a href="{{ route('contracts.show', $record->contrato_id) }}">Detalle</a>@endroutecan
@if($record->estado !== 'CANCELADO')@can('contracts.manage')
@routecan('contracts.edit')<a href="{{ route('contracts.edit', $record->contrato_id) }}">Actualizar</a>@endroutecan
@endcan
@endif
@routecan('contracts.pdf')<a href="{{ route('contracts.pdf', $record->contrato_id) }}">PDF</a>@endroutecan
</div></td></tr>
    @empty<tr><td colspan="8" class="empty-state">No se encontraron contratos con esos criterios.</td></tr>@endforelse
    </tbody></table></div>
    @include('clients.pagination', ['label' => 'Paginación de contratos'])
</section>
@endsection
