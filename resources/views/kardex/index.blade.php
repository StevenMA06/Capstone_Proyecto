@extends('kardex.layout')

@section('kardex-content')
@if($errors->any())<div class="notice error-summary" role="alert"><strong>Revisa los filtros:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="clients-grid">
    <!-- Panel de Filtros -->
    <aside class="client-filter-panel">
        <h2>Filtros avanzados</h2>
        <form class="filters client-filters" method="GET" action="{{ route('kardex.index') }}">
            <div>
                <label for="search">Código o nombre</label>
                <input id="search" name="search" maxlength="180" value="{{ request('search') }}" placeholder="Buscar…">
            </div>

            <fieldset>
                <legend>Tipo de artículo</legend>
                @php
                    $tiposArticulo = ['' => 'Todos', 'equipment' => 'Equipos', 'material' => 'Materiales'];
                @endphp
                @foreach($tiposArticulo as $valor => $label)
                <label class="state-option">
                    <input type="radio" name="item_type" value="{{ $valor }}" @checked((request('item_type') ?? '') === $valor)>
                    <span>{{ $label }}</span>
                </label>
                @endforeach
            </fieldset>

            <fieldset>
                <legend>Tipo de movimiento</legend>
                @php
                    $tiposMovimiento = [
                        '' => 'Todos',
                        'ENTRADA' => 'Entrada',
                        'SALIDA' => 'Salida',
                        'ASIGNACION' => 'Asignación',
                        'CONSUMO' => 'Consumo',
                        'DEVOLUCION' => 'Devolución',
                        'AJUSTE' => 'Ajuste'
                    ];
                @endphp
                @foreach($tiposMovimiento as $valor => $label)
                <label class="state-option">
                    <input type="radio" name="tipo_movimiento" value="{{ $valor }}" @checked((request('tipo_movimiento') ?? '') === $valor)>
                    <span>{{ $label }}</span>
                </label>
                @endforeach
            </fieldset>

            <div>
                <label for="fecha_desde">Desde</label>
                <input type="date" id="fecha_desde" name="fecha_desde" value="{{ request('fecha_desde') }}">
            </div>

            <div>
                <label for="fecha_hasta">Hasta</label>
                <input type="date" id="fecha_hasta" name="fecha_hasta" value="{{ request('fecha_hasta') }}">
            </div>

            <div class="filter-buttons">
                <button class="action-link" type="submit">Aplicar</button>
                <a class="clear-link" href="{{ route('kardex.index') }}">Limpiar</a>
            </div>
        </form>
    </aside>

    <!-- Panel de Listado -->
    <section class="client-list-panel">
        <div class="management-toolbar">
            <div>
                <h2>Historial de movimientos</h2>
                <p class="muted">{{ $movements->total() }} {{ $movements->total() === 1 ? 'movimiento encontrado' : 'movimientos encontrados' }}</p>
            </div>
        </div>

        <div class="table-scroll">
            <table class="users-table client-table">
                <thead>
                    <tr>
                        <th>Fecha/Hora</th>
                        <th>Movimiento</th>
                        <th>Tipo</th>
                        <th>Artículo</th>
                        <th>Código</th>
                        <th>Cantidad</th>
                        <th>Anterior</th>
                        <th>Posterior</th>
                        <th>Responsable</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movements as $m)
                    <tr>
                        <td>{{ $m->fecha_movimiento->format('d/m/Y H:i') }}</td>
                        <td><span class="movement-badge movement-{{ $m->tipo_movimiento }}">{{ $m->tipo_movimiento }}</span></td>
                        <td>{{ $m->item_type }}</td>
                        <td>{{ $m->item_name ?? '—' }}</td>
                        <td>{{ $m->item_code ?? '—' }}</td>
                        <td>{{ \App\Support\InventoryQuantity::format($m->cantidad) }}</td>
                        <td>{{ $m->cantidad_anterior !== null ? \App\Support\InventoryQuantity::format($m->cantidad_anterior) : '—' }}</td>
                        <td>{{ $m->cantidad_posterior !== null ? \App\Support\InventoryQuantity::format($m->cantidad_posterior) : '—' }}</td>
                        <td>{{ $m->responsible->nombre_completo ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="empty-state">No se encontraron movimientos con esos criterios.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('clients.pagination', ['records' => $movements, 'label' => 'Paginación de movimientos'])
    </section>
</div>
@endsection
