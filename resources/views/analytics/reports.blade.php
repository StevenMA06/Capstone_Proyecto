@extends('layouts.management')
@section('title', 'Reportes')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/analytics.css') }}?v=11">
@endpush
@section('workspace-content')
<section class="analytics">
<header class="an-heading"><div><span class="an-eyebrow">CENTRO DE INFORMACIÓN</span><h1>Reportes de gestión</h1><p>Consulta los registros de cada módulo para apoyar el seguimiento operativo.</p></div></header>
<nav class="an-tabs" aria-label="Información de gestión">
@foreach(['reports'=>'Reportes','indicators'=>'Indicadores','bi'=>'Panel BI'] as $module=>$label)
@can($module.'.view')<a href="{{ route($module.'.index') }}" @if($module==='reports') aria-current="page" @endif>{{ $label }}</a>@endcan
@endforeach
</nav>
<section class="an-catalog-panel"><h2>¿Qué módulo quieres consultar?</h2><p>Los reportes se organizan según las áreas del sistema.</p><div class="an-catalog">@foreach($modules as $key => [$label, $types])<a class="an-catalog-item" href="{{ route('reports.index', ['modulo' => $key, 'tipo' => $types[0], 'desde' => $from, 'hasta' => $to]) }}" @if($selectedModule === $key) aria-current="true" @endif>{{ $label }}</a>@endforeach</div></section>
<form class="an-filters" method="get" action="{{ route('reports.index') }}">
<input type="hidden" name="modulo" value="{{ $selectedModule }}">
<div class="an-type"><label for="tipo">Reporte</label><select id="tipo" name="tipo">@foreach($availableReports as $key => $definition)<option value="{{ $key }}" @selected($selectedType === $key)>{{ $definition[1] }}</option>@endforeach</select></div>
<div><label for="desde">Desde</label><input type="date" id="desde" name="desde" value="{{ $from }}" required></div><div><label for="hasta">Hasta</label><input type="date" id="hasta" name="hasta" value="{{ $to }}" required></div>
<button class="an-primary" type="submit">Generar reporte</button>
</form>
@if($errors->any())<p class="an-error" role="alert">{{ $errors->first() }}</p>@endif
<div class="an-context"><span>Período aplicado: {{ $from }} — {{ $to }}</span><span>Generado: {{ now('America/Lima')->format('d/m/Y H:i') }}</span></div>
<section class="an-panel"><header class="an-panel-header"><div><h2>{{ $report['title'] }}</h2><p>{{ number_format($report['total']) }} registros · {{ $report['dateField'] }}{{ $report['dateField'] === 'Corte actual' ? ' (el rango de fechas no se aplica)' : '' }}</p></div><div class="an-actions">@foreach(['pdf' => 'PDF', 'xlsx' => 'Excel'] as $format => $label)<form method="post" action="{{ route('reports.export') }}">@csrf<input type="hidden" name="type" value="{{ $selectedType }}"><input type="hidden" name="from" value="{{ $from }}"><input type="hidden" name="to" value="{{ $to }}"><input type="hidden" name="format" value="{{ $format }}"><button class="{{ $format === 'pdf' ? 'an-secondary' : 'an-primary' }}" type="submit">Descargar {{ $label }}</button></form>@endforeach</div></header>
@if($report['rows'])<div class="an-table-wrap" tabindex="0" role="region" aria-label="Resultados del reporte"><table class="an-table"><thead><tr>@foreach($report['columns'] as $column)<th scope="col">{{ $column }}</th>@endforeach</tr></thead><tbody>@foreach($report['rows'] as $row)<tr>@foreach($row as $value)<td>{{ $value ?? '—' }}</td>@endforeach</tr>@endforeach</tbody></table></div>@else<div class="an-empty"><h2>Sin registros para mostrar</h2><p>No hay datos que coincidan con el reporte y el período seleccionados.</p></div>@endif
@if($report['pagination']->hasPages())
<nav class="an-pagination" aria-label="Páginas del reporte"><span>Mostrando {{ $report['pagination']->firstItem() }}–{{ $report['pagination']->lastItem() }} de {{ $report['total'] }}</span><div>
@if($report['pagination']->previousPageUrl())<a class="an-secondary" href="{{ $report['pagination']->previousPageUrl() }}">Anterior</a>@endif
<span>Página {{ $report['pagination']->currentPage() }} de {{ $report['pagination']->lastPage() }}</span>
@if($report['pagination']->nextPageUrl())<a class="an-secondary" href="{{ $report['pagination']->nextPageUrl() }}">Siguiente</a>@endif
</div></nav>@endif
<p class="an-footer-note">Las descargas incluyen todos los resultados del reporte seleccionado.</p></section>
</section>
@endsection
