@extends('layouts.management')
@section('title', 'Reportes')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/analytics.css') }}?v=2">
@endpush
@section('workspace-content')
<section class="analytics">
<header class="an-heading"><div><span class="an-eyebrow">CENTRO DE INFORMACIÓN</span><h1>Reportes de gestión</h1><p>Consulta los registros de cada módulo para apoyar el seguimiento operativo.</p></div><span class="an-version">Datos de MySQL</span></header>
<nav class="an-tabs" aria-label="Información de gestión"><a href="{{ route('reports.index') }}" aria-current="page">Reportes <span>7.14</span></a>@can('indicators.view')<a href="{{ route('indicators.index') }}">Indicadores <span>7.15</span></a><a href="{{ route('bi.index') }}">Panel BI <span>7.16</span></a>@endcan</nav>
<section class="an-catalog-panel"><h2>1. Selecciona un módulo</h2><p>Los reportes se organizan según las áreas del sistema.</p><div class="an-catalog">@foreach($modules as $key => [$label, $types])<a class="an-catalog-item" href="{{ route('reports.index', ['modulo' => $key, 'tipo' => $types[0], 'desde' => $from, 'hasta' => $to]) }}" @if($selectedModule === $key) aria-current="true" @endif><span>MÓDULO</span>{{ $label }}<small>{{ implode(' · ', array_map(fn ($type) => \App\Services\ReportExportService::DEFINITIONS[$type][1], $types)) }}</small></a>@endforeach</div></section>
<form class="an-filters" method="get" action="{{ route('reports.index') }}">
<input type="hidden" name="modulo" value="{{ $selectedModule }}">
<div class="an-type"><label for="tipo">Reporte</label><select id="tipo" name="tipo">@foreach($availableReports as $key => $definition)<option value="{{ $key }}" @selected($selectedType === $key)>{{ $definition[1] }}</option>@endforeach</select></div>
<div><label for="desde">Desde</label><input type="date" id="desde" name="desde" value="{{ $from }}" required></div><div><label for="hasta">Hasta</label><input type="date" id="hasta" name="hasta" value="{{ $to }}" required></div>
<button class="an-primary" type="submit">Generar reporte</button>
</form>
<div class="an-context"><span>Período aplicado: {{ $from }} — {{ $to }}</span><span>Generado: {{ now('America/Lima')->format('d/m/Y H:i') }}</span></div>
<div class="an-stats"><article class="an-card"><span class="an-label">Módulo</span><div class="an-number">{{ $report['module'] }}</div></article><article class="an-card"><span class="an-label">Registros encontrados</span><div class="an-number">{{ count($report['rows']) }}</div></article><article class="an-card"><span class="an-label">Campo de fecha o corte</span><div class="an-number an-report-date">{{ $report['dateField'] }}</div></article><article class="an-card"><span class="an-label">Exportación</span><div class="an-number an-report-date">PDF · Excel</div></article></div>
<section class="an-panel"><header class="an-panel-header"><div><h2>{{ $report['title'] }}</h2><p>La información corresponde a {{ $report['dateField'] }}.</p></div><div class="an-actions">@foreach(['pdf' => 'PDF', 'xlsx' => 'Excel'] as $format => $label)<form method="post" action="{{ route('reports.export') }}">@csrf<input type="hidden" name="type" value="{{ $selectedType }}"><input type="hidden" name="from" value="{{ $from }}"><input type="hidden" name="to" value="{{ $to }}"><input type="hidden" name="format" value="{{ $format }}"><button class="{{ $format === 'pdf' ? 'an-secondary' : 'an-primary' }}" type="submit">Descargar {{ $label }}</button></form>@endforeach</div></header>
@if($report['rows'])<div class="an-table-wrap" tabindex="0" role="region" aria-label="Resultados del reporte"><table class="an-table"><thead><tr>@foreach($report['columns'] as $column)<th scope="col">{{ $column }}</th>@endforeach</tr></thead><tbody>@foreach($report['rows'] as $row)<tr>@foreach($row as $value)<td>{{ $value ?? '—' }}</td>@endforeach</tr>@endforeach</tbody></table></div>@else<div class="an-empty"><h2>Sin registros para mostrar</h2><p>No hay datos que coincidan con el reporte y el período seleccionados.</p></div>@endif
<p class="an-footer-note">Los reportes de corte actual muestran el estado al momento de la consulta y no dependen del rango de fechas. La información se consulta desde el servidor; no se aceptan filas suministradas por el navegador.</p></section>
</section>
@endsection

