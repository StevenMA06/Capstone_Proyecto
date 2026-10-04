@extends('zones.layout')
@section('zones-content')
<section class="coverage-check" aria-labelledby="coverage-title">
    <div><span class="coverage-icon" aria-hidden="true"><i class="bi bi-geo-alt"></i></span><div><h2 id="coverage-title">Verificar cobertura</h2><p>Escribe un distrito, provincia, departamento o nombre de zona.</p></div></div>
    <form method="GET" action="{{ route('zones.index') }}">
        <label class="sr-only" for="coverage">Ubicación que deseas verificar</label>
        <input id="coverage" name="coverage" value="{{ $coverageQuery }}" maxlength="120" placeholder="Ej. Huanchaco o Trujillo">
        <button class="action-link" type="submit">Verificar</button>
    </form>
    @if($coverageQuery !== '')
        <div class="coverage-result {{ $coverageMatches->isEmpty() ? 'unavailable' : 'available' }}" role="status">
            @if($coverageMatches->isEmpty())
                <strong>Sin cobertura disponible</strong><span>No se encontraron zonas activas para “{{ $coverageQuery }}”.</span>
            @else
                <strong>Cobertura disponible</strong><span>{{ $coverageMatches->count() }} {{ $coverageMatches->count() === 1 ? 'zona activa encontrada' : 'zonas activas encontradas' }} para “{{ $coverageQuery }}”.</span>
                <ul>@foreach($coverageMatches as $match)<li><a href="{{ route('zones.show', $match) }}">{{ $match->nombre }}</a> · {{ $match->distrito }}@if($match->provincia), {{ $match->provincia }}@endif <span>{{ $match->plans->isEmpty() ? 'Sin planes activos asociados' : $match->plans->pluck('nombre')->join(', ') }}</span></li>@endforeach</ul>
            @endif
        </div>
    @endif
</section>

<div class="zones-workspace">
    <aside class="zones-filter-panel" aria-labelledby="zones-filter-title">
        <h2 id="zones-filter-title">Filtros y buscador</h2><p>Encuentra zonas por cualquier dato de ubicación.</p>
        <form method="GET" action="{{ route('zones.index') }}" class="zones-filters">
            <div><label for="search">Ubicación</label><input id="search" name="search" value="{{ request('search') }}" maxlength="120" placeholder="Código, zona, distrito..."></div>
            <div><label for="estado">Estado</label><select id="estado" name="estado"><option value="">Todos los estados</option><option value="ACTIVA" @selected(request('estado') === 'ACTIVA')>Activa</option><option value="INACTIVA" @selected(request('estado') === 'INACTIVA')>Inactiva</option></select></div>
            <button class="action-link" type="submit">Aplicar filtros</button><a class="clear-link" href="{{ route('zones.index') }}">Limpiar filtros</a>
        </form>
        <div class="zones-filter-note">Las zonas inactivas se conservan en el historial y no aparecen al registrar contratos.</div>
    </aside>
    <section class="zones-results" aria-labelledby="zones-list-title">
        <div class="management-toolbar"><div><h2 id="zones-list-title">Zonas registradas</h2><p class="zones-total">{{ $records->total() }} zonas encontradas</p></div>@can('manage-zones')@can('zones.manage')<a class="action-link" href="{{ route('zones.create') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Registrar zona</a>@endcan
@endcan</div>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Listado de zonas de cobertura">
            <table class="users-table zones-table"><caption class="sr-only">Zonas de cobertura registradas</caption><thead><tr><th>Zona</th><th>Ubicación</th><th>Descripción</th><th>Planes</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
            @forelse($records as $zone)<tr>
                <td><strong>{{ $zone->nombre }}</strong><span class="zone-code">{{ $zone->codigo }}</span></td>
                <td>{{ $zone->distrito }}<span class="zone-location">{{ collect([$zone->provincia, $zone->departamento])->filter()->join(', ') }}</span></td>
                <td>{{ $zone->descripcion ?: 'Sin descripción' }}</td>
                <td><strong>{{ $zone->active_plans_count }}</strong> disponibles</td>
                <td><span class="state {{ $zone->estado === 'ACTIVA' ? 'active' : 'inactive' }}">{{ $zone->estado === 'ACTIVA' ? 'Activa' : 'Inactiva' }}</span></td>
                <td><div class="row-actions"><a href="{{ route('zones.show', $zone) }}">Ver detalle</a>@can('manage-zones')@can('zones.manage')<a href="{{ route('zones.edit', $zone) }}">Editar</a>@endcan
@endcan</div></td>
            </tr>@empty<tr><td colspan="6" class="empty-state">No se encontraron zonas con esos criterios.</td></tr>@endforelse
            </tbody></table>
        </div>
        @include('clients.pagination', ['label' => 'Paginación de zonas'])
    </section>
</div>
@endsection
