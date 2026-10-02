@extends('materials.layout')
@section('materials-content')
<div class="form-layout"><form class="standard-form" method="POST" action="{{route('materials.exit.store',$material)}}">@csrf<h2>Registrar salida de material</h2><div class="inventory-grid"><section class="inventory-card"><h3>Datos del movimiento</h3>
@if($material->inventario_en_empaque)
<div class="form-group">
<label for="cantidad">Cantidad a retirar (en {{$material->unidad_empaque}}) *</label>
<input type="number" step="1" id="cantidad" name="cantidad" value="{{old('cantidad')}}" required min="1" max="{{floor($material->stock_actual)}}" autofocus>
<small>Máximo disponible: <strong>{{number_format(floor($material->stock_actual), 0)}} {{$material->unidad_empaque}}</strong></small>
<small style="display:block;margin-top:0.5rem;">Cada {{$material->unidad_empaque}} contiene {{$material->factor_conversion}} {{$material->unidad_base}}</small>
@error('cantidad')<span class="error-message">{{$message}}</span>@enderror
</div>
@else
<div class="form-group">
<label for="cantidad">Cantidad a retirar (en {{$material->unidad_base}}) *</label>
<input type="number" step="0.001" id="cantidad" name="cantidad" value="{{old('cantidad')}}" required min="0.001" max="{{$material->stock_actual}}" autofocus>
<small>Máximo disponible: {{\App\Support\InventoryQuantity::format($material->stock_actual)}} {{$material->unidad_base}}</small>
@error('cantidad')<span class="error-message">{{$message}}</span>@enderror
</div>
@endif
<div class="form-group"><label for="observacion">Observación</label><textarea id="observacion" name="observacion" maxlength="500" rows="3" placeholder="Opcional: motivo de la salida">{{old('observacion')}}</textarea>@error('observacion')<span class="error-message">{{$message}}</span>@enderror</div>
</section><aside class="inventory-card"><h3>Material seleccionado</h3><p class="muted">Material: <strong>{{$material->nombre}}</strong> ({{$material->codigo}})</p>
@if($material->inventario_en_empaque)
<p class="muted">Stock actual: <strong style="color:{{$material->isLowStock()?'#dc2626':'#059669'}};">{{number_format($material->stock_actual, 0)}} {{$material->unidad_empaque}}</strong>@if($material->isLowStock())<span style="color:#dc2626;"> ⚠ Nivel bajo</span>@endif</p>
<p class="muted">Equivalente: {{number_format($material->getStockInBase(), 2)}} {{$material->unidad_base}}</p>
<p class="muted">Contenido por {{$material->unidad_empaque}}: <strong>{{$material->factor_conversion}} {{$material->unidad_base}}</strong></p>
@else
<p class="muted">Stock actual: <strong style="color:{{$material->isLowStock()?'#dc2626':'#059669'}};">{{\App\Support\InventoryQuantity::format($material->stock_actual)}} {{$material->unidad_base}}</strong>@if($material->isLowStock())<span style="color:#dc2626;"> ⚠ Nivel bajo</span>@endif</p>
@endif
<p class="inventory-note">El movimiento quedará registrado en el Kardex con su fecha y responsable.</p></aside></div><div class="form-actions"><button type="submit" class="action-link">Confirmar salida</button><a href="{{route('materials.show',$material)}}" class="clear-link">Cancelar</a></div></form></div>
@endsection
