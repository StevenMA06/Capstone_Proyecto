@extends('layouts.management')
@section('title', ['reports'=>'Reportes','indicators'=>'Indicadores','bi'=>'Business Intelligence'][$screen])
@push('styles')
<link rel="stylesheet" href="{{ asset('css/analytics.css') }}?v=1">
<script src="{{ asset('js/analytics-demo.js') }}?v=1" defer></script>
@endpush
@section('workspace-content')
<section class="analytics" data-analytics="{{ $screen }}" data-office="0">
<header class="an-heading"><div><span class="an-eyebrow">CENTRO DE INFORMACIÓN</span><h1>{{ ['reports'=>'Reportes','indicators'=>'Indicadores de gestión','bi'=>'Business Intelligence'][$screen] }}</h1><p>{{ ['reports'=>'El detalle de tu operación, en un solo lugar.','indicators'=>'Conoce los tiempos, la calidad y la disponibilidad de tus procesos.','bi'=>'Una visión de tu operación para orientar las decisiones.'][$screen] }}</p></div><span class="an-version">Vista de presentación</span></header>
<nav class="an-tabs" aria-label="Información de gestión">
@foreach(['reports'=>'Reportes','indicators'=>'Indicadores','bi'=>'Panel BI'] as $module=>$label)
@can($module.'.view')<a href="{{ route($module.'.index') }}" @if($screen===$module) aria-current="page" @endif>{{ $label }}</a>@endcan
@endforeach</nav>
<p class="an-demo"><strong>Datos de demostración</strong> · Ejemplos ficticios de abril a septiembre de 2026. Los filtros cambian esta muestra; todavía no están conectados con los registros de la empresa.</p>
<form class="an-filters" id="an-filters">
@if($screen==='reports')<div class="an-type"><label for="an-type">Reporte</label><select id="an-type" name="tipo"></select></div>@else<div class="an-type"><label for="an-process">Proceso operativo</label><select id="an-process" name="proceso"><option value="all">Todos los procesos</option value="comercial">Comercial</option><option value="pagos">Pagos y cobranza</option><option value="instalaciones">Instalaciones</option><option value="inventario">Inventario</option><option value="reportes">Reportes</option></select></div>@endif
<div><label for="an-from">Desde</label><input type="date" id="an-from" name="desde" value="2026-04-01" required></div><div><label for="an-to">Hasta</label><input type="date" id="an-to" name="hasta" value="2026-09-30" required></div>
<button class="an-primary" type="submit">Aplicar filtros</button><button class="an-reset" type="button" id="an-reset">Restablecer</button>
</form><p class="an-error" id="an-error" role="alert" hidden></p>
<div class="an-context"><span id="an-period"></span><span id="an-updated"></span></div>
<div id="an-content" aria-live="polite"></div>
<noscript><p>Activa JavaScript para consultar esta demostración de las pantallas.</p></noscript>
<dialog class="an-dialog" id="an-dialog" aria-labelledby="an-dialog-title"><h2 id="an-dialog-title"></h2><p id="an-dialog-text"></p><form method="dialog"><button class="an-primary">Entendido</button></form></dialog>
</section>
@endsection
