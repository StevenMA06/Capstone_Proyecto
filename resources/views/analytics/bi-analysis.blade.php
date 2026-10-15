@if($sales!==null)
<h2 class="an-section-title">Ventas derivadas de contratos</h2><p class="an-section-note">Ventas confirmadas vinculadas a un contrato, por fecha de venta dentro del período. Las ventas anuladas se excluyen.</p>
<div class="an-chart-grid"><article class="an-card"><span class="an-label">Contratos con venta confirmada</span><div class="an-number">{{ number_format($sales['count']) }}</div></article><article class="an-card"><span class="an-label">Monto de ventas confirmadas</span><div class="an-number">S/ {{ number_format($sales['amount'],2) }}</div></article></div>
@endif
@if($staff!==null)
<section class="an-panel an-observation"><header class="an-panel-header"><div><h2>Participación del personal</h2><p>{{ $staff['scope'] }}</p></div></header>
<form method="get" action="{{ route('bi.index') }}" class="an-actions an-staff-filter">@if($preview)<input type="hidden" name="vista_previa" value="1">@endif<input type="hidden" name="desde" value="{{ $from }}"><input type="hidden" name="hasta" value="{{ $to }}"><input type="hidden" name="proceso" value="{{ $process }}"><label for="staff-top">Máximo de empleados</label><select id="staff-top" name="top">@foreach([5,10,20,50] as $limit)<option value="{{ $limit }}" @selected($staff['top']===$limit)>{{ $limit }}</option>@endforeach</select><button class="an-secondary">Actualizar lista</button></form>
@if($staff['rows']->isEmpty())<p class="an-empty">No hay participación registrada en el período seleccionado.</p>@else
<p class="an-description">Mostrando {{ $staff['rows']->count() }} de {{ $staff['total'] }} empleados con actividad. Orden: cantidad de contratos y ventas.</p>
<div class="an-table-wrap"><table class="an-table"><thead><tr><th>Empleado</th><th>Cargo</th><th>Contratos</th><th>Ventas confirmadas</th><th>Monto de ventas</th></tr></thead><tbody>@foreach($staff['rows'] as $person)<tr><td>{{ $person->nombres }} {{ $person->apellidos }}</td><td>{{ $person->cargo }}</td><td>{{ $person->contratos }}</td><td>{{ $person->ventas }}</td><td>S/ {{ number_format($person->monto,2) }}</td></tr>@endforeach</tbody></table></div>@endif
</section>@endif
<section class="an-panel an-observation" aria-labelledby="explorer-title"><h2 id="explorer-title">Explorar un indicador</h2><p class="an-description">Elige una representación y consulta sus valores exactos. Cantidades y montos se muestran por separado.</p>
<form class="an-filters" method="get" action="{{ route('bi.index') }}">
@if($preview)<input type="hidden" name="vista_previa" value="1">@endif<input type="hidden" name="desde" value="{{ $from }}"><input type="hidden" name="hasta" value="{{ $to }}"><input type="hidden" name="proceso" value="{{ $process }}">
<div><label for="bi-indicator">Indicador</label><select id="bi-indicator" name="indicador">@foreach($chartIndicators as $key=>$definition)<option value="{{ $key }}" @selected($explorer['indicator']===$key)>{{ $definition[0] }}</option>@endforeach</select></div>
<div><label for="bi-group">Agrupar por</label><select id="bi-group" name="agrupacion">@foreach(['dia'=>'Día','semana'=>'Semana (lunes)','mes'=>'Mes','estado'=>'Estado / movimiento','empleado'=>'Empleado responsable'] as $key=>$label)<option value="{{ $key }}" @selected($explorer['group']===$key)>{{ $label }}</option>@endforeach</select></div>
<div><label for="bi-chart">Gráfico</label><select id="bi-chart" name="tipo_grafico">@foreach(['bar'=>'Barras','line'=>'Líneas','area'=>'Área','pie'=>'Circular','doughnut'=>'Anillo'] as $key=>$label)<option value="{{ $key }}" @selected($explorer['type']===$key)>{{ $label }}</option>@endforeach</select></div>
<div><label for="bi-measure">Medida</label><select id="bi-measure" name="medida"><option value="cantidad" @selected($explorer['measure']==='cantidad')>Cantidad de registros</option><option value="monto" @selected($explorer['measure']==='monto')>Monto (ventas o pagos)</option></select></div><button class="an-primary">Actualizar gráfico</button>
</form>
<h3>{{ $explorer['title'] }} · {{ $explorer['unit'] }}</h3><p class="an-kpi-formula">{{ $explorer['scope'] }} Las semanas y meses extremos pueden ser parciales.</p>
@include('analytics.chart', ['chart'=>$explorer])
</section>
