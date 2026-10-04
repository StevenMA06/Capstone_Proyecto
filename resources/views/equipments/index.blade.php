@extends('equipments.layout')
@section('equipments-content')

{{-- Estadísticas por estado --}}
<div class="payment-status-stats">
    <div class="stats-header">
        <h2>Estadísticas de Equipos</h2>
    </div>
    
    <div class="stats-grid">
        @php
            $statusConfig = [
                'DISPONIBLE' => ['icon' => 'bi-check-circle-fill', 'color' => '#10b981', 'label' => 'Disponibles'],
                'ASIGNADO' => ['icon' => 'bi-box-arrow-right', 'color' => '#3b82f6', 'label' => 'Asignados'],
                'MANTENIMIENTO' => ['icon' => 'bi-tools', 'color' => '#f59e0b', 'label' => 'En mantenimiento'],
                'INACTIVO' => ['icon' => 'bi-slash-circle', 'color' => '#6b7280', 'label' => 'Inactivos'],
            ];
            $totalEquipments = array_sum($statistics);
        @endphp
        
        @foreach($statusConfig as $status => $config)
            @php
                $count = $statistics[$status] ?? 0;
                $percentage = $totalEquipments > 0 ? round(($count / $totalEquipments) * 100) : 0;
                $isFiltered = request('estado') === $status;
            @endphp
            <a 
                href="{{ route('equipments.index', array_merge(request()->except('estado', 'page'), ['estado' => $status])) }}" 
                class="stat-card clickable {{ $isFiltered ? 'filtered' : '' }}"
                style="border-left-color: {{ $config['color'] }};"
                title="Clic para filtrar por {{ $config['label'] }}"
            >
                <div class="stat-icon" style="background: {{ $config['color'] }}20;">
                    <i class="bi {{ $config['icon'] }}" style="font-size: 1.5rem; color: {{ $config['color'] }};"></i>
                </div>
                <div class="stat-content">
                    <h3>{{ $config['label'] }}</h3>
                    <p class="stat-number">{{ $count }}</p>
                    @if($totalEquipments > 0)
                        <div class="stat-progress">
                            <div class="stat-progress-bar" style="width: {{ $percentage }}%; background: {{ $config['color'] }};"></div>
                        </div>
                        <p class="stat-percentage">{{ $percentage }}% del total</p>
                    @endif
                </div>
                @if($isFiltered)
                    <span class="badge-filtered">🔍 Filtrado</span>
                @endif
            </a>
        @endforeach
    </div>
</div>

<div class="clients-grid">
<aside class="client-filter-panel" aria-labelledby="filter-title">
    <h2 id="filter-title">Filtros y buscador</h2>
    <p class="muted">Encuentra el equipo que necesitas.</p>
    <form class="filters client-filters" method="GET" action="{{ route('equipments.index') }}">
        <div><label for="search">Código, nombre o serie</label><input id="search" name="search" value="{{ request('search') }}" placeholder="Buscar equipo…" maxlength="180"><small>Código, nombre, tipo, marca o número de serie.</small></div>
        <fieldset><legend>Estado del equipo</legend>
            @foreach(['' => 'Todos los estados', 'DISPONIBLE' => 'Disponible', 'ASIGNADO' => 'Asignado', 'MANTENIMIENTO' => 'Mantenimiento', 'INACTIVO' => 'Inactivo'] as $value => $label)
                <label class="state-option"><input type="radio" name="estado" value="{{ $value }}" @checked((request('estado') ?? '') === $value)><span>{{ $label }}</span></label>
            @endforeach
        </fieldset>
        <fieldset><legend>Condición del equipo</legend>
            @foreach(['' => 'Todas las condiciones', 'NUEVO' => 'Nuevo', 'BUENO' => 'Bueno', 'REGULAR' => 'Regular', 'MALO' => 'Malo', 'MALOGRADO' => 'Malogrado'] as $value => $label)
                <label class="state-option"><input type="radio" name="condicion" value="{{ $value }}" @checked((request('condicion') ?? '') === $value)><span>{{ $label }}</span></label>
            @endforeach
        </fieldset>
        <div class="filter-buttons"><button class="action-link" type="submit">Aplicar filtros</button><a class="clear-link" href="{{ route('equipments.index') }}">Limpiar filtros</a></div>
    </form>
</aside>
<section class="client-list-panel" aria-labelledby="list-title">
    <div class="management-toolbar"><div><h2 id="list-title">Listado de equipos</h2><p class="muted">{{ $equipments->total() }} {{ $equipments->total() === 1 ? 'equipo encontrado' : 'equipos encontrados' }}</p></div>
    <div style="display: flex; gap: 0.5rem;">
        @can('equipments.manage')<a class="action-link" href="{{ route('equipments.import') }}"><span aria-hidden="true">📁</span> Importar Excel</a>@endcan
        @can('equipments.manage')<a class="action-link" href="{{ route('equipments.create') }}"><span aria-hidden="true">＋</span> Registrar equipo</a>@endcan
    </div>
    </div>
    <div class="table-scroll"><table class="users-table client-table"><caption class="sr-only">Equipos registrados</caption><thead><tr><th>Código</th><th>Nombre</th><th>Tipo</th><th>Número de serie</th><th>Condición</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
    @forelse($equipments as $equipment)
    <tr>
        <td><strong>{{ $equipment->codigo }}</strong></td>
        <td><a class="client-name" href="{{ route('equipments.show', $equipment) }}">{{ $equipment->nombre }}</a><small class="table-subtitle">{{ $equipment->marca }} {{ $equipment->modelo }}</small></td>
        <td>{{ $equipment->tipo }}</td>
        <td>{{ $equipment->numero_serie ?? '—' }}</td>
        <td><span class="state {{ in_array($equipment->condicion, ['NUEVO', 'BUENO']) ? 'active' : (in_array($equipment->condicion, ['MALO', 'MALOGRADO']) ? 'inactive' : '') }}">{{ ucfirst(strtolower($equipment->condicion)) }}</span></td>
        <td><span class="state {{ $equipment->estado === 'DISPONIBLE' ? 'active' : ($equipment->estado === 'INACTIVO' ? 'inactive' : '') }}" style="@if($equipment->estado === 'ASIGNADO') background: #dbeafe; color: #1e40af; @endif @if($equipment->estado === 'MANTENIMIENTO') background: #fef3c7; color: #92400e; @endif">{{ ucfirst(strtolower($equipment->estado)) }}</span></td>
        <td><div class="row-actions"><a href="{{ route('equipments.show', $equipment) }}">Ver detalle</a>@if($equipment->estado !== 'ASIGNADO')@can('equipments.manage')<a href="{{ route('equipments.edit', $equipment) }}">Editar</a>@endcan
@endif<a href="{{ route('equipments.kardex', $equipment) }}">Ver Kardex</a></div></td>
    </tr>
    @empty<tr><td colspan="7" class="empty-state">No se encontraron equipos con esos criterios.</td></tr>@endforelse
    </tbody></table></div>
    @include('clients.pagination', ['records' => $equipments, 'label' => 'Paginación de equipos'])
</section>
</div>
@endsection
