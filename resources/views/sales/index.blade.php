@extends('layouts.management')
@section('title', 'Gestión de ventas')
@push('styles')<link rel="stylesheet" href="{{ asset('css/sales.css') }}?v=4">@endpush
@section('workspace-content')
<section class="management sales-module">
    <header class="management-header">
        <div><span class="eyebrow">GESTIÓN COMERCIAL</span><h1>Gestión de Ventas</h1>
            <nav class="workspace-breadcrumb" aria-label="Ruta de navegación"><a href="{{ route('dashboard') }}">Inicio</a><span aria-hidden="true">/</span><span aria-current="page">Ventas</span></nav>
            <p>Ventas generadas al confirmar contratos firmados.</p>
        </div>
    </header>

    <form id="sales-filters" class="sales-filters" method="GET" action="{{ route('sales.index') }}">
        <div><label for="cliente">Buscar cliente</label><input id="cliente" name="cliente" type="search" value="{{ $filters['cliente'] ?? '' }}" placeholder="Nombre o razón social" maxlength="180" data-sales-search></div>
        <div><label for="desde">Fecha inicio</label><input id="desde" name="desde" type="date" value="{{ $filters['desde'] ?? '' }}" data-sales-filter></div>
        <div><label for="hasta">Fecha fin</label><input id="hasta" name="hasta" type="date" value="{{ $filters['hasta'] ?? '' }}" data-sales-filter></div>
        <div><label for="empleado_id">Responsable</label><select id="empleado_id" name="empleado_id" data-sales-filter><option value="">Todos los empleados</option>@foreach($employees as $employee)<option value="{{ $employee->empleado_id }}" @selected((string) ($filters['empleado_id'] ?? '') === (string) $employee->empleado_id)>{{ $employee->nombres }} {{ $employee->apellidos }}</option>@endforeach</select></div>
        <div class="sales-filter-actions"><button class="action-link" type="submit">Filtrar</button><a class="secondary" href="{{ route('sales.index') }}">Restablecer</a></div>
    </form>

    <div class="sales-kpis" aria-label="Resumen de ventas filtradas">
        <div class="sales-kpi"><span>Ventas encontradas</span><strong>{{ number_format($summary->quantity) }}</strong><small>Según los filtros seleccionados</small></div>
        <div class="sales-kpi"><span>Monto acumulado</span><strong>S/ {{ number_format($summary->amount, 2) }}</strong><small>Suma de precios mensuales registrados</small></div>
    </div>

    <div class="sales-charts">
        <section class="sales-card" aria-labelledby="sales-employee-title">
            <div class="sales-card-heading"><h2 id="sales-employee-title">Conteo de ventas por empleado</h2><p>Responsable al confirmar el contrato</p></div>
            @if($byEmployee->isEmpty())<p class="sales-empty">No hay ventas para mostrar.</p>@else
                <div class="sales-bars-scroll"><div class="sales-bars" role="img" aria-label="{{ $byEmployee->map(fn($item) => $item->nombres.' '.$item->apellidos.': '.$item->quantity)->join('; ') }}">
                    @foreach($byEmployee as $item)<div class="sales-bar-item"><strong>{{ $item->quantity }}</strong><span class="sales-bar" style="--bar-height: {{ max(4, (int) round($item->quantity / $employeeMax * 100)) }}%"></span><span class="sales-bar-label" title="{{ $item->nombres }} {{ $item->apellidos }}">{{ $item->nombres }}<br>{{ $item->apellidos }}</span></div>@endforeach
                </div></div>
            @endif
        </section>

        <section class="sales-card" aria-labelledby="sales-period-title">
            <div class="sales-card-heading"><h2 id="sales-period-title">Ventas por intervalo de fechas</h2><p>{{ $daily ? 'Monto diario' : 'Monto mensual' }} · {{ $byPeriod->sum('quantity') }} ventas</p></div>
            @if($byPeriod->isEmpty())<p class="sales-empty">No hay ventas para mostrar.</p>@else
                @php
                    $pointCount = $byPeriod->count();
                    $maxAmount = max(1, (float) $byPeriod->max('amount'));
                    $points = $byPeriod->values()->map(fn ($item, $i) => [
                        'x' => 38 + ($pointCount === 1 ? 195 : $i * 390 / ($pointCount - 1)),
                        'y' => 180 - (float) $item->amount / $maxAmount * 135,
                        'label' => $daily ? \Carbon\CarbonImmutable::parse($item->period)->format('d/m') : \Carbon\CarbonImmutable::parse($item->period.'-01')->format('m/Y'),
                        'amount' => (float) $item->amount,
                    ]);
                @endphp
                <div class="sales-line-scroll"><div class="sales-line-chart">
                    <svg class="sales-line" viewBox="0 0 460 225" role="img" aria-label="Monto de ventas por {{ $daily ? 'día' : 'mes' }}: {{ $byPeriod->map(fn($item) => $item->period.' S/ '.number_format($item->amount, 2))->join('; ') }}">
                        <line x1="38" y1="45" x2="428" y2="45" class="sales-gridline"/><line x1="38" y1="112" x2="428" y2="112" class="sales-gridline"/><line x1="38" y1="180" x2="428" y2="180" class="sales-axis"/>
                        @if($pointCount > 1)<polyline points="{{ $points->map(fn($p) => round($p['x'], 1).','.round($p['y'], 1))->join(' ') }}" class="sales-line-path"/>@endif
                        @foreach($points as $point)<circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5" class="sales-line-point"><title>{{ $point['label'] }} · S/ {{ number_format($point['amount'], 2) }}</title></circle>@endforeach
                    </svg>
                    <div class="sales-line-labels" aria-hidden="true">
                        <span class="sales-axis-value sales-axis-value-max">{{ number_format($maxAmount, 0) }}</span><span class="sales-axis-value sales-axis-value-min">0</span>
                        @foreach($points as $i => $point)
                            @if($pointCount <= 7 || $i === 0 || $i === $pointCount - 1)<span class="sales-period-label" style="left: {{ round($point['x'] / 460 * 100, 2) }}%">{{ $point['label'] }}</span>@endif
                        @endforeach
                    </div>
                </div></div>
            @endif
        </section>

        <section class="sales-card" aria-labelledby="sales-state-title">
            <div class="sales-card-heading"><h2 id="sales-state-title">Distribución por estado</h2><p>Incluye cliente, fecha y responsable; selecciona estados para comparar</p></div>
            @php($stateTotal = array_sum($byState->all()))
            @php($selectedStates = $filters['estados'] ?? $states)
            <div class="sales-state-content">
                <div class="sales-donut-wrap">
                    <svg class="sales-donut" viewBox="0 0 120 120" role="group" aria-label="Distribución de ventas: {{ collect($states)->map(fn($state) => ucfirst(mb_strtolower($state)).' '.($byState[$state] ?? 0))->join('; ') }}">
                        <circle cx="60" cy="60" r="42" class="sales-donut-track"/>
                        @php($offset = 0)
                        @foreach($states as $state)
                            @php($length = $stateTotal ? (float) ($byState[$state] ?? 0) / $stateTotal * 263.89 : 0)
                            @if($length > 0)<circle cx="60" cy="60" r="42" class="sales-donut-slice sales-donut-{{ strtolower($state) }} {{ in_array($state, $selectedStates, true) ? '' : 'is-muted' }}" stroke-dasharray="{{ $length }} {{ 263.89 - $length }}" stroke-dashoffset="{{ -$offset }}" data-sales-state="{{ $state }}" tabindex="0" role="button" aria-pressed="{{ in_array($state, $selectedStates, true) ? 'true' : 'false' }}" aria-label="{{ ucfirst(mb_strtolower($state)) }}: {{ $byState[$state] }} ventas. Activar o desactivar filtro"><title>{{ ucfirst(mb_strtolower($state)) }}: {{ $byState[$state] }} ventas</title></circle>@endif
                            @php($offset += $length)
                        @endforeach
                    </svg>
                    <span class="sales-donut-center" aria-hidden="true"><span>{{ $stateTotal }}</span><small>ventas</small></span>
                </div>
                <div class="sales-state-legend" aria-label="Filtrar estados de venta">
                    <span class="sales-state-filter-title">Mostrar en resultados</span>
                    @foreach($states as $state)<label class="sales-state-option"><input type="checkbox" name="estados[]" value="{{ $state }}" form="sales-filters" @checked(in_array($state, $selectedStates, true)) data-sales-filter><span class="sales-state-swatch sales-state-{{ strtolower($state) }}"></span><span class="sales-state-name">{{ ucfirst(mb_strtolower($state)) }}</span><span class="sales-state-count">{{ $byState[$state] ?? 0 }}</span><span class="sales-state-check" aria-hidden="true">✓</span></label>@endforeach
                </div>
            </div>
        </section>
    </div>

    <section class="sales-card sales-results" aria-labelledby="sales-results-title">
        <div class="sales-card-heading"><h2 id="sales-results-title">Ventas registradas</h2><p>{{ $sales->total() }} resultados · Cada venta enlaza con su contrato</p></div>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Listado de ventas"><table class="users-table sales-table"><caption class="sr-only">Ventas según los filtros aplicados</caption><thead><tr><th scope="col">Cliente</th><th scope="col">Contrato</th><th scope="col">Responsable</th><th scope="col">Estado de venta</th><th scope="col">Monto mensual</th><th scope="col">Fecha de venta</th><th scope="col">Detalle</th></tr></thead><tbody>
            @forelse($sales as $sale)<tr><td><strong>{{ trim($sale->nombres_razon_social.' '.$sale->apellidos) }}</strong><small>{{ $sale->numero_documento }}</small></td><td><strong>{{ $sale->codigo }}</strong><small>Contrato {{ ucfirst(mb_strtolower($sale->contrato_estado)) }}</small></td><td>{{ $sale->empleado_nombres }} {{ $sale->empleado_apellidos }}</td><td><span class="sales-status sales-status-{{ strtolower($sale->venta_estado) }}">{{ ucfirst(mb_strtolower($sale->venta_estado)) }}</span></td><td>S/ {{ number_format($sale->monto, 2) }}</td><td>{{ \Carbon\CarbonImmutable::parse($sale->fecha_venta)->format('d/m/Y H:i') }}</td><td><div class="sales-row-actions"><a href="{{ route('contracts.show', $sale->contrato_id) }}">Ver contrato</a><a href="{{ route('contracts.pdf', $sale->contrato_id) }}">Descargar PDF</a></div></td></tr>
            @empty<tr><td colspan="7" class="empty-state">No se encontraron ventas con los filtros seleccionados.</td></tr>@endforelse
        </tbody></table></div>
        @include('clients.pagination', ['records' => $sales, 'label' => 'Paginación de ventas'])
    </section>
</section>
<script src="{{ asset('js/sales.js') }}?v=1" defer></script>
@endsection
