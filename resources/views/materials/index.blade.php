@extends('materials.layout')

@section('materials-content')
<!-- Estadísticas de Stock -->
<div class="payment-status-stats">
    <div class="stats-header">
        <h2>Estadísticas de Stock</h2>
    </div>
    <div class="stats-grid">
        @php
            $stats = [
                ['label' => 'Total activos', 'count' => $statistics['total'], 'color' => '#3b82f6'],
                ['label' => 'Stock bajo', 'count' => $statistics['low_stock'], 'color' => '#f59e0b'],
                ['label' => 'Sin stock', 'count' => $statistics['out_of_stock'], 'color' => '#dc2626']
            ];
        @endphp
        @foreach($stats as $stat)
        <div class="stat-card" style="border-left-color: {{ $stat['color'] }};">
            <div class="stat-icon" style="background: {{ $stat['color'] }}20;">
                <i class="bi bi-box" style="font-size: 1.5rem; color: {{ $stat['color'] }};"></i>
            </div>
            <div class="stat-content">
                <h3>{{ $stat['label'] }}</h3>
                <p class="stat-number">{{ $stat['count'] }}</p>
            </div>
        </div>
        @endforeach
    </div>
</div>

<!-- Filtros y Listado -->
<div class="clients-grid">
    <!-- Panel de Filtros -->
    <aside class="client-filter-panel">
        <h2>Filtros y buscador</h2>
        
@routecan('materials.index')<form class="filters client-filters" method="GET" action="{{ route('materials.index') }}">
            <div>
                <label for="search">Código o nombre</label>
                <input id="search" name="search" value="{{ request('search') }}" placeholder="Buscar material…">
            </div>

            <fieldset>
                <legend>Estado</legend>
                @php
                    $estados = ['' => 'Todos', 'ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo'];
                @endphp
                @foreach($estados as $valor => $label)
                <label class="state-option">
                    <input type="radio" name="estado" value="{{ $valor }}" @checked((request('estado') ?? '') === $valor)>
                    <span>{{ $label }}</span>
                </label>
                @endforeach
            </fieldset>

            <fieldset>
                <legend>Stock</legend>
                <label class="state-option">
                    <input type="checkbox" name="low_stock" value="1" @checked(request('low_stock'))>
                    <span>Solo stock bajo</span>
                </label>
            </fieldset>

            <div class="filter-buttons">
                <button class="action-link" type="submit">Aplicar</button>
                
@routecan('materials.index')<a class="clear-link" href="{{ route('materials.index') }}">Limpiar</a>@endroutecan

            </div>
        </form>@endroutecan

    </aside>

    <!-- Panel de Listado -->
    <section class="client-list-panel">
        <div class="management-toolbar">
            <div>
                <h2>Listado de materiales</h2>
                <p class="muted">{{ $materials->total() }} {{ $materials->total() === 1 ? 'material encontrado' : 'materiales encontrados' }}</p>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <a class="action-link" href="{{ route('materials.import') }}"><span aria-hidden="true">📁</span> Importar Excel</a>
                <a class="action-link" href="{{ route('materials.create') }}"><span aria-hidden="true">＋</span> Registrar material</a>
            </div>
        </div>

        <div class="table-scroll">
            <table class="users-table client-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Unidad</th>
                        <th>Stock actual</th>
                        <th>Stock mínimo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($materials as $m)
                    <tr>
                        <td><strong>{{ $m->codigo }}</strong></td>
                        <td>
@routecan('materials.show')<a class="client-name" href="{{ route('materials.show', $m) }}">{{ $m->nombre }}</a>@endroutecan
</td>
                        <td>{{ $m->unidad_medida }}</td>
                        <td>
                            <strong style="color: {{ $m->isLowStock() ? '#dc2626' : '#059669' }};">
                                {{ \App\Support\InventoryQuantity::format($m->stock_actual) }}
                            </strong>
                            @if($m->isLowStock())
                            <small class="table-subtitle" style="color: #dc2626;">⚠ Bajo</small>
                            @endif
                        </td>
                        <td>{{ \App\Support\InventoryQuantity::format($m->stock_minimo) }}</td>
                        <td>
                            <span class="state {{ $m->estado === 'ACTIVO' ? 'active' : 'inactive' }}">
                                {{ ucfirst(strtolower($m->estado)) }}
                            </span>
                        </td>
                        <td>
                            <div class="row-actions">
                                
@routecan('materials.show')<a href="{{ route('materials.show', $m) }}">Ver</a>@endroutecan

                                @can('materials.manage')
@routecan('materials.entry')<a href="{{ route('materials.entry', $m) }}">Ingreso</a>@endroutecan
@endcan
                                @can('materials.manage')
@routecan('materials.exit')<a href="{{ route('materials.exit', $m) }}">Salida</a>@endroutecan
@endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="empty-state">No se encontraron materiales.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('clients.pagination', ['records' => $materials, 'label' => 'Paginación de materiales'])
    </section>
</div>
@endsection
