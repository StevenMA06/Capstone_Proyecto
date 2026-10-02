@extends('materials.layout')
@section('materials-content')
<div class="client-detail"><div class="detail-header"><div><h2>{{$material->nombre}}</h2><p class="muted">Código: {{$material->codigo}}</p></div><div class="detail-actions"><a class="clear-link" href="{{ route('materials.index') }}">Volver al listado</a><a href="{{route('materials.entry',$material)}}" class="action-link">Registrar ingreso</a><a href="{{route('materials.exit',$material)}}" class="action-link">Registrar salida</a><a href="{{route('materials.edit',$material)}}" class="action-link">Editar</a><a href="{{route('materials.kardex',$material)}}" class="action-link">Ver Kardex</a></div></div>
<div class="detail-grid"><div class="detail-card"><h3>Información del material</h3><dl><dt>Descripción</dt><dd>{{$material->descripcion??'—'}}</dd><dt>Unidad base</dt><dd>{{$material->unidad_base}}</dd>@if($material->usesPackaging())<dt>Unidad de empaque</dt><dd>{{$material->unidad_empaque}}</dd><dt>Contenido por empaque</dt><dd>{{number_format($material->factor_conversion, 2)}} {{$material->unidad_base}}</dd>@endif<dt>Stock actual</dt><dd><strong style="font-size:1.25rem;color:{{$material->isLowStock()?'#dc2626':'#059669'}};">
@if($material->inventario_en_empaque)
{{number_format($material->stock_actual, 0)}} {{$material->unidad_empaque}}
@else
{{\App\Support\InventoryQuantity::format($material->stock_actual)}} {{$material->unidad_base}}
@endif
</strong>@if($material->isLowStock())<span style="color:#dc2626;"> ⚠ Nivel bajo</span>@endif
@if($material->inventario_en_empaque)
<small style="display:block;color:#6b7280;margin-top:0.25rem;">({{number_format($material->getStockInBase(), 2)}} {{$material->unidad_base}})</small>
@endif
</dd><dt>Stock mínimo</dt><dd>
@if($material->inventario_en_empaque)
{{number_format($material->stock_minimo, 0)}} {{$material->unidad_empaque}}
<small style="display:block;color:#6b7280;margin-top:0.25rem;">({{number_format($material->stock_minimo * $material->factor_conversion, 2)}} {{$material->unidad_base}})</small>
@else
{{\App\Support\InventoryQuantity::format($material->stock_minimo)}} {{$material->unidad_base}}
@endif
</dd><dt>Estado</dt><dd><span class="state {{$material->estado==='ACTIVO'?'active':'inactive'}}">{{ucfirst(strtolower($material->estado))}}</span></dd></dl></div>
<div class="detail-card"><h3>Registro</h3><dl><dt>Registrado por</dt><dd>{{$material->creator->name??'Usuario eliminado'}}</dd><dt>Fecha de registro</dt><dd>{{$material->creado_en->format('d/m/Y H:i')}}</dd><dt>Última actualización</dt><dd>{{$material->actualizado_en->format('d/m/Y H:i')}}</dd></dl></div></div>
<div class="detail-card"><h3>Historial de movimientos Kardex ({{$movements->total()}})</h3>@if($movements->count()>0)<div class="table-scroll"><table class="users-table"><thead><tr><th>Fecha</th><th>Movimiento</th><th>Cantidad</th><th>Anterior</th><th>Posterior</th><th>Responsable</th></tr></thead><tbody>@foreach($movements as $m)<tr><td>{{$m->fecha_movimiento->format('d/m/Y H:i')}}</td><td><strong>{{$m->tipo_movimiento}}</strong></td><td>{{\App\Support\InventoryQuantity::format($m->cantidad)}}</td><td>{{$m->cantidad_anterior!==null?\App\Support\InventoryQuantity::format($m->cantidad_anterior):'—'}}</td><td>{{$m->cantidad_posterior!==null?\App\Support\InventoryQuantity::format($m->cantidad_posterior):'—'}}</td><td>{{$m->responsible->name??'—'}}</td></tr>@endforeach</tbody></table></div>@if($movements->hasPages())@include('clients.pagination',['records'=>$movements,'label'=>'Paginación'])@endif @else<p class="muted">No hay movimientos registrados.</p>@endif</div></div>
@endsection
