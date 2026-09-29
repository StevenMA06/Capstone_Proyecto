@extends('layouts.management')
@section('title', 'Dashboard')
@push('styles')<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v=6"><script src="{{ asset('js/dashboard.js') }}?v=1" defer></script>@endpush
@section('workspace-content')
<section class="overview">
    <header class="overview-heading">
        <div><span class="eyebrow">RESUMEN DE TU OPERACIÓN</span><h1>{{ auth()->user()->isAdministrator() ? 'Panel de Administración' : (auth()->user()->isManager() ? 'Panel principal de Gerencia' : 'Panel de oficina') }}</h1>
        <nav class="workspace-breadcrumb" aria-label="Ruta de navegación"><span>Inicio</span><span aria-hidden="true">/</span><span aria-current="page">Dashboard</span></nav></div>
        <p class="overview-greeting">Hola, <strong>{{ auth()->user()->name }}</strong><br><span>Bienvenido a Lunnys Perú</span></p>
    </header>
    <div class="overview-metrics">
        @if($clientCount !== null)
        <a class="metric-card summary-metric" href="{{ route('clients.index') }}"><h2>Clientes registrados</h2><p class="metric-value">{{ number_format($clientCount) }}</p>@include('layouts.module-icon', ['icon' => 'users'])</a>
        @endif
        @if($summary !== null)
        @foreach([
            ['Contratos activos', 'contracts', 'file'],
            ['Pagos pendientes', 'payments', 'money'],
            ['Instalaciones en proceso', 'installations', 'tool'],
        ] as [$label, $key, $icon])
        <a class="metric-card summary-metric" href="{{ route($key.'.index') }}"><h2>{{ $label }}</h2><p class="metric-value">{{ number_format($summary[$key]) }}</p>@include('layouts.module-icon', ['icon' => $icon])</a>
        @endforeach
        @foreach(['equipment' => 'Equipos', 'materials' => 'Materiales'] as $key => $label)
        <button type="button" class="metric-card inventory-trigger {{ $summary[$key.'Low'] ? 'metric-alert' : '' }}" data-inventory-open="{{ $key }}-dialog" aria-haspopup="dialog" aria-controls="{{ $key }}-dialog">
            <span class="inventory-title">{{ $label }}</span>
            @include('layouts.module-icon', ['icon' => $key === 'equipment' ? 'router' : 'box'])
            <span class="metric-note">Haz clic para ver stock</span>
            @if($summary[$key.'Low'])<span class="stock-warning">⚠ Stock mínimo</span>@endif
        </button>
        @endforeach
        @endif
    </div>
    @if($summary !== null)
    <section class="overview-chart" aria-labelledby="sales-heading">
        <header class="chart-heading"><div><h2 id="sales-heading">Resumen de ventas</h2><p>Últimos 6 meses · Ventas confirmadas · Mes actual en curso</p></div><div class="sales-total"><span>Total del período</span><strong>S/ {{ number_format($summary['salesTotal'], 2) }}</strong></div></header>
        @if($summary['salesTotal'] == 0)<p class="chart-empty">Aún no hay ventas confirmadas en este período.</p>@endif
        <ol class="sales-bars" aria-label="Ventas confirmadas por mes, en soles">
            @foreach($summary['months'] as $month)
            <li><span class="bar-amount">S/ {{ number_format($month['total'], 2) }}</span><div class="bar-track" aria-hidden="true"><div class="bar-fill" style="height: {{ round($month['total'] / $summary['salesMax'] * 100, 2) }}%"></div></div><span class="bar-month">{{ $month['label'] }}</span></li>
            @endforeach
        </ol>
        <p class="chart-note">Indicadores calculados con los registros existentes.</p>
    </section>
    @endif
    @if($summary !== null)
    <section class="overview-chart" aria-labelledby="top-plans-heading">
        <header class="chart-heading"><div><h2 id="top-plans-heading">Planes más vendidos</h2><p>Top 5 · Ventas confirmadas en los últimos 6 meses · Cantidad de ventas</p></div></header>
        @if($summary['topPlans']->isEmpty())
        <p class="chart-empty">Aún no hay ventas confirmadas de planes en este período.</p>
        @else
        <ol class="plan-ranking" aria-label="Planes ordenados por cantidad de ventas">
            @foreach($summary['topPlans'] as $plan)
            <li>
                <div class="plan-ranking-label"><strong>{{ $plan->nombre }}</strong><span>{{ $plan->numero_plan }}</span></div>
                <div class="plan-ranking-track" aria-hidden="true"><span style="width: {{ round($plan->total / $summary['topPlansMax'] * 100, 2) }}%"></span></div>
                <span class="plan-ranking-count">{{ number_format($plan->total) }} {{ $plan->total == 1 ? 'venta' : 'ventas' }}</span>
            </li>
            @endforeach
        </ol>
        @endif
    </section>
    @endif
    <section class="overview-shortcuts">
        @if($summary !== null)<div class="future-grid">
            <a class="future-shortcut" href="{{ route('bi.index') }}">@include('layouts.module-icon', ['icon' => 'chart'])<div><strong>Business Intelligence</strong><span>Explorar el panel de demostración</span></div><span class="coming-soon">Ver pantalla</span></a>
            <div class="future-shortcut" aria-disabled="true">@include('layouts.module-icon', ['icon' => 'chat'])<div><strong>Chatbot IA</strong><span>Asistencia para tu operación</span></div><span class="coming-soon">Próximamente</span></div>
        </div>@endif
    </section>
    @if($summary !== null)
        @foreach(['equipment' => 'Equipos', 'materials' => 'Materiales'] as $key => $label)
        <dialog class="inventory-dialog" id="{{ $key }}-dialog" aria-labelledby="{{ $key }}-title">
            <header class="inventory-heading"><h2 id="{{ $key }}-title">Inventario de {{ mb_strtolower($label) }}</h2><form method="dialog"><button type="submit" class="secondary" autofocus aria-label="Cerrar inventario de {{ mb_strtolower($label) }}">Cerrar ×</button></form></header>
            @if($key === 'equipment')<p class="muted">Unidades disponibles para asignación, agrupadas por nombre, tipo, marca y modelo. Mínimo: {{ config('inventory.equipment_minimum') }} por grupo.</p>@endif
            @if($summary[$key.'Low'])<p class="stock-warning" role="status">⚠ Stock igual o inferior al mínimo: {{ $summary[$key.'Low'] }} {{ $key === 'equipment' ? ($summary[$key.'Low'] === 1 ? 'grupo de equipos' : 'grupos de equipos') : ($summary[$key.'Low'] === 1 ? 'material' : 'materiales') }}.</p>@endif
            @include('operations.inventory-table', ['records' => $summary[$key], 'kind' => $key])
            <footer class="inventory-footer"><a class="action-link" href="{{ route($key.'.index') }}">Abrir {{ mb_strtolower($label) }} →</a></footer>
        </dialog>
        @endforeach
    @endif
</section>
@endsection
