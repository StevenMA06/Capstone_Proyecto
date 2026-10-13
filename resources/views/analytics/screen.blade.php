@extends('layouts.management')
@section('title', $screen === 'bi' ? 'Panel BI' : 'Indicadores')
@push('styles')<link rel="stylesheet" href="{{ asset('css/analytics.css') }}?v=12">@endpush
@section('workspace-content')
<section class="analytics">
<header class="an-heading"><div><span class="an-eyebrow">INFORMACIÓN PARA DECIDIR</span><h1>{{ $screen === 'bi' ? 'Panel de gestión' : 'Indicadores de gestión' }}</h1><p>{{ $screen === 'bi' ? 'Compara la actividad y prioriza las acciones de cada módulo.' : 'Revisa la actividad operativa y la medición de tiempos por proceso.' }}</p></div></header>
<nav class="an-tabs" aria-label="Información de gestión">
@foreach(['reports'=>'Reportes','indicators'=>'Indicadores','bi'=>'Panel BI'] as $module=>$label)
@can($module.'.view')<a href="{{ route($module.'.index') }}" @if($screen===$module) aria-current="page" @endif>{{ $label }}</a>@endcan
@endforeach
</nav>
<form class="an-filters" method="get">
<div><label for="proceso">Proceso</label><select id="proceso" name="proceso"><option value="all">Todos los procesos</option>@foreach($processes as $key=>$label)<option value="{{ $key }}" @selected($process===$key)>{{ $label }}</option>@endforeach</select></div>
<div><label for="desde">Desde</label><input id="desde" type="date" name="desde" value="{{ $from }}" required></div><div><label for="hasta">Hasta</label><input id="hasta" type="date" name="hasta" value="{{ $to }}" required></div><button class="an-primary">Aplicar filtros</button><a class="an-reset" href="{{ route($screen==='bi' ? 'bi.index' : 'indicators.index') }}">Restablecer</a>
</form>
@if($errors->any())<p class="an-error" role="alert">{{ $errors->first() }}</p>@endif
<div class="an-context"><span>Actividad del {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</span><span>Actualizado: {{ now('America/Lima')->format('d/m/Y H:i') }}</span></div>
@if($screen==='indicators')
<h2 class="an-section-title">Actividad y situación operativa</h2><p class="an-section-note">Los registros del período y el estado actual se presentan por separado.</p>
@else
<h2 class="an-section-title">Actividad frente al período anterior</h2><p class="an-section-note">Comparación con {{ $previousFrom }} — {{ $previousTo }} (misma cantidad de días). Un aumento indica mayor actividad, no necesariamente mejor desempeño.</p>
@endif
<div class="an-grid">
@foreach($items as $key=>$item)
<article class="an-card"><span class="an-label">{{ $item['label'] }}</span><h3>{{ ['clientes'=>'Clientes registrados','contratos'=>'Contratos registrados','pagos'=>'Pagos realizados','instalaciones'=>'Pedidos recibidos','inventario'=>'Movimientos de inventario'][$key] }}</h3><div class="an-number">{{ number_format($item['count']) }}</div>
<p class="an-kpi-formula">{{ ['clientes'=>'Fecha de registro del cliente','contratos'=>'Fecha de registro del contrato','pagos'=>'Fecha de pago · solo realizados','instalaciones'=>'Fecha de solicitud','inventario'=>'Fecha del movimiento'][$key] }}</p>
@if($screen==='bi')<p class="an-kpi-formula">Anterior: <strong>{{ number_format($item['before']) }}</strong> · Variación: <strong>{{ $item['before'] ? number_format(($item['count']-$item['before'])/$item['before']*100,1).' %' : 'Sin base comparable' }}</strong></p>@endif
<a class="an-detail-link" href="{{ route('reports.index',['modulo'=>$key,'tipo'=>$item['report'],'desde'=>$from,'hasta'=>$to]) }}">Consultar reporte →</a></article>
@endforeach
</div>
@if($screen==='bi')
<h2 class="an-section-title">Evolución y composición</h2><div class="an-chart-grid">
@foreach($items as $key=>$item)
@php $max=max(1,...array_values($item['trend'])); @endphp
<section class="an-panel"><h2>{{ $item['label'] }} · evolución mensual</h2><p class="an-description">{{ $key==='pagos' ? 'Pagos realizados' : 'Registros del período' }}. Los meses inicial y final pueden ser parciales.</p>
@if($item['count']===0)<div class="an-empty">Sin actividad en el período seleccionado.</div>@elseif($loop->index % 2 === 0)
@php $points=[]; @endphp
@foreach($item['trend'] as $month=>$value)@php $points[]=(20+$loop->index*560/max(1,count($item['trend'])-1)).','. (175-$value/$max*145); @endphp@endforeach
<div class="an-context"><span>Máximo mensual: {{ $max }}</span><span>{{ array_key_first($item['trend']) }} — {{ array_key_last($item['trend']) }}</span></div><svg viewBox="0 0 600 210" role="img" aria-label="Evolución mensual de {{ $item['label'] }}"><path d="M20 175 H580" stroke="#cdded4" fill="none"/><polyline points="{{ implode(' ',$points) }}" stroke="#087b59" stroke-width="3" fill="none"/>@foreach($item['trend'] as $month=>$value)<circle cx="{{ 20+$loop->index*560/max(1,count($item['trend'])-1) }}" cy="{{ 175-$value/$max*145 }}" r="4" fill="#087b59"><title>{{ $month }}: {{ $value }}</title></circle>@endforeach</svg>
@else<div class="an-trend">@foreach($item['trend'] as $month=>$value)<div class="an-trend-col"><strong>{{ $value }}</strong><div class="an-trend-bar" style="height:{{ $value/$max*150 }}px"></div><span>{{ $month }}</span></div>@endforeach</div>@endif
<details class="an-chart-details"><summary>Ver cantidades por mes</summary><table class="an-table"><thead><tr><th>Mes</th><th>Cantidad</th></tr></thead><tbody>@foreach($item['trend'] as $month=>$value)<tr><td>{{ $month }}</td><td>{{ $value }}</td></tr>@endforeach</tbody></table></details>
</section>
@endforeach</div>
@endif
<h2 class="an-section-title">Estado actual por módulo</h2><p class="an-section-note">Corte al {{ now('America/Lima')->format('d/m/Y H:i') }}. Incluye todos los registros; el rango de fechas no se aplica a esta sección.</p>
<div class="an-chart-grid">@foreach($items as $key=>$item)
@php $total=array_sum($item['states']); @endphp
<section class="an-panel"><h2>{{ $key==='inventario' ? 'Inventario · equipos' : $item['label'] }}</h2><p class="an-description">{{ number_format($total) }} registros en total</p>
@if(!$total)<p class="an-empty">Sin registros disponibles.</p>@else
@if($screen==='bi')
@php $colors=['#087b59','#3a86a8','#d49b32','#aa6064','#8b7caf','#668776']; $segments=[]; $offset=0; @endphp
@foreach($item['states'] as $status=>$count)@php $next=$offset+$count/$total*100; $segments[]=$colors[$loop->index%6].' '.$offset.'% '.$next.'%'; $offset=$next; @endphp@endforeach
<div class="an-circle-layout"><div class="an-donut" style="background:conic-gradient({{ implode(',',$segments) }})" role="img" aria-label="Distribución por estado de {{ $item['label'] }}"><div><strong>{{ $total }}</strong><span>Total</span></div></div><div class="an-legend">@foreach($item['states'] as $status=>$count)<span><i class="an-dot" style="display:inline-block;background:{{ $colors[$loop->index%6] }}"></i> {{ ucfirst(strtolower(str_replace('_',' ',$status))) }} · <strong>{{ $count }}</strong> ({{ number_format($count/$total*100,1) }} %)</span>@endforeach</div></div>
@else<div class="an-bars">@foreach($item['states'] as $status=>$count)<div class="an-bar-row"><span>{{ ucfirst(strtolower(str_replace('_',' ',$status))) }}</span><div class="an-track"><div class="an-fill" style="width:{{ $count/$total*100 }}%"></div></div><strong>{{ $count }}</strong></div>@endforeach</div>@endif
@endif</section>@endforeach</div>
@if($process==='all' || $process==='inventario')
<section class="an-panel" style="margin-top:24px"><header class="an-panel-header"><div><h2>Existencias de materiales</h2><p>Estado actual · hasta 8 materiales, priorizando los que necesitan reposición. Cada cantidad conserva su unidad.</p></div><a class="an-secondary" href="{{ route('reports.index',['modulo'=>'inventario','tipo'=>'materiales_existencias']) }}">Ver inventario completo</a></header>
@if($materials->isEmpty())<p class="an-empty">No hay materiales activos registrados.</p>@else<div class="an-table-wrap"><table class="an-table"><thead><tr><th>Material</th><th>Disponible</th><th>Mínimo</th><th>Unidad</th><th>Situación</th></tr></thead><tbody>@foreach($materials as $material)<tr><td>{{ $material->nombre }}</td><td>{{ $material->stock_actual }}</td><td>{{ $material->stock_minimo }}</td><td>{{ $material->unidad_medida }}</td><td><span class="an-pill {{ $material->stock_actual <= $material->stock_minimo ? 'warn' : '' }}">{{ $material->stock_actual <= $material->stock_minimo ? 'Reponer' : 'Disponible' }}</span></td></tr>@endforeach</tbody></table></div>@endif</section>
@endif
@include('analytics.pretest')
@if($alerts)<h2 class="an-section-title">Prioridades de seguimiento</h2><div class="an-grid">@foreach($alerts as $alert)<article class="an-card"><span class="an-label">Situación actual</span><h3>{{ $alert['label'] }}</h3><div class="an-number">{{ $alert['value'] }}</div><p class="an-kpi-formula">{{ $alert['detail'] }}</p><a class="an-detail-link" href="{{ route('reports.index',['modulo'=>$alert['module'],'tipo'=>$alert['report'],'desde'=>'2000-01-01','hasta'=>now()->toDateString()]) }}">Revisar registros →</a></article>@endforeach</div>@endif
</section>
@endsection
