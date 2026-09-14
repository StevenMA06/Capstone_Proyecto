@extends('layouts.management')
@section('title', 'Dashboard')
@push('styles')<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v=1">@endpush
@section('workspace-content')
<section class="overview">
    <header class="overview-heading">
        <div><span class="eyebrow">RESUMEN DE TU OPERACIÓN</span><h1>{{ auth()->user()->isManager() ? 'Panel principal de Gerencia' : 'Panel principal' }}</h1>
        <nav class="workspace-breadcrumb" aria-label="Ruta de navegación"><span>Inicio</span><span aria-hidden="true">/</span><span aria-current="page">Dashboard</span></nav></div>
        <p class="overview-greeting">Hola, <strong>{{ auth()->user()->name }}</strong><br><span>Bienvenido a Lunnys Perú</span></p>
    </header>
    <div class="overview-metrics">
        @if($clientCount !== null)
        <article class="metric-card"><div class="metric-top"><h2>Clientes registrados</h2>@include('layouts.module-icon', ['icon' => 'users'])</div><p class="metric-value">{{ number_format($clientCount) }}</p><p class="metric-note">Activos e inactivos</p></article>
        @endif
        @if($summary !== null)
        @foreach([
            ['Contratos activos', 'contracts', 'file', 'Estado activo vigente'],
            ['Pagos pendientes', 'payments', 'money', 'Incluye pagos vencidos'],
            ['Instalaciones programadas', 'installations', 'tool', 'Incluye reprogramadas'],
            ['Equipos disponibles', 'equipment', 'box', 'Disponibles para asignación'],
            ['Materiales en mínimo', 'materials', 'box', 'Stock igual o inferior al mínimo'],
        ] as [$label, $key, $icon, $note])
        <article class="metric-card {{ $key === 'materials' && $summary[$key] > 0 ? 'metric-alert' : '' }}"><div class="metric-top"><h2>{{ $label }}</h2>@include('layouts.module-icon', ['icon' => $icon])</div><p class="metric-value">{{ number_format($summary[$key]) }}</p><p class="metric-note">{{ $note }}</p></article>
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
        <p class="chart-note">Indicadores calculados con los registros existentes. La gestión de ventas, inventario e instalaciones estará disponible en sus próximos módulos.</p>
    </section>
    @endif
    <section class="overview-shortcuts" aria-labelledby="shortcuts-heading"><h2 id="shortcuts-heading">Accesos rápidos</h2>
        <div class="shortcut-grid">
            @can('register-clients')<a class="shortcut" href="{{ route('clients.index') }}">@include('layouts.module-icon', ['icon' => 'users'])<div><strong>Gestionar clientes</strong><span>Consulta y administra su información</span></div><span aria-hidden="true">→</span></a>@endcan
            @can('manage-users')<a class="shortcut" href="{{ route('users.index') }}">@include('layouts.module-icon', ['icon' => 'users'])<div><strong>Gestionar usuarios</strong><span>Administra cuentas, roles y estados</span></div><span aria-hidden="true">→</span></a>@endcan
            @can('view-access-history')<a class="shortcut" href="{{ route('access.index') }}">@include('layouts.module-icon', ['icon' => 'file'])<div><strong>Historial de accesos</strong><span>Consulta los ingresos al sistema</span></div><span aria-hidden="true">→</span></a>@endcan
            @if($clientCount === null && $summary === null)<p>No tienes módulos habilitados por el momento. Consulta con Gerencia.</p>@endif
        </div>
        @if($summary !== null)<div class="future-grid">
            <div class="future-shortcut" aria-disabled="true">@include('layouts.module-icon', ['icon' => 'chart'])<div><strong>Business Intelligence</strong><span>Análisis e indicadores avanzados</span></div><span class="coming-soon">Próximamente</span></div>
            <div class="future-shortcut" aria-disabled="true">@include('layouts.module-icon', ['icon' => 'chat'])<div><strong>Chatbot IA</strong><span>Asistencia para tu operación</span></div><span class="coming-soon">Próximamente</span></div>
        </div>@endif
    </section>
</section>
@endsection
