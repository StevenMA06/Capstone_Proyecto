@extends('materials.layout')
@section('materials-content')
<div class="form-layout">@can('materials.manage')<form class="standard-form" method="POST" action="{{route('materials.entry.store',$material)}}">@csrf<h2>Registrar ingreso de material</h2><div class="inventory-grid"><section class="inventory-card"><h3>Datos del movimiento</h3>
@if($material->inventario_en_empaque)
<div class="form-group">
<label for="cantidad">Cantidad a ingresar (en {{$material->unidad_empaque}}) *</label>
<input type="number" step="1" id="cantidad" name="cantidad" value="{{old('cantidad')}}" required min="1" max="999999999" autofocus>
<small>Ingresa la cantidad de <strong>{{$material->unidad_empaque}}</strong> completas (cada {{$material->unidad_empaque}} contiene {{$material->factor_conversion}} {{$material->unidad_base}})</small>
<small style="display:block;margin-top:0.5rem;color:#059669;">
💡 Ejemplos: 
@for($i = 1; $i <= 3; $i++)
<span style="display:inline-block;margin-right:1rem;">{{$i}} {{$material->unidad_empaque}} = {{$i * $material->factor_conversion}} {{$material->unidad_base}}</span>
@endfor
</small>
@error('cantidad')<span class="error-message">{{$message}}</span>@enderror
</div>
@else
<div class="form-group">
<label for="cantidad">Cantidad a ingresar (en {{$material->unidad_base}}) *</label>
<input type="number" step="0.001" id="cantidad" name="cantidad" value="{{old('cantidad')}}" required min="0.001" max="999999999.999" autofocus>
<small>Ingresa la cantidad positiva que deseas agregar al stock</small>
@error('cantidad')<span class="error-message">{{$message}}</span>@enderror
</div>
@endif
<div class="form-group"><label for="observacion">Observación</label><textarea id="observacion" name="observacion" maxlength="500" rows="3" placeholder="Opcional: ingreso de proveedor, compra, etc.">{{old('observacion')}}</textarea>@error('observacion')<span class="error-message">{{$message}}</span>@enderror</div>
</section><aside class="inventory-card"><h3>Material seleccionado</h3><p class="muted">Material: <strong>{{$material->nombre}}</strong> ({{$material->codigo}})</p>
@if($material->inventario_en_empaque)
<p class="muted">Stock actual: <strong>{{number_format($material->stock_actual, 0)}} {{$material->unidad_empaque}}</strong></p>
<p class="muted">Equivalente: {{number_format($material->getStockInBase(), 2)}} {{$material->unidad_base}}</p>
<p class="muted">Contenido por {{$material->unidad_empaque}}: <strong>{{$material->factor_conversion}} {{$material->unidad_base}}</strong></p>
@else
<p class="muted">Stock actual: <strong>{{\App\Support\InventoryQuantity::format($material->stock_actual)}} {{$material->unidad_base}}</strong></p>
@endif
<p class="inventory-note">El movimiento quedará registrado en el Kardex con su fecha y responsable.</p></aside></div><div class="form-actions"><button type="submit" class="action-link">Confirmar ingreso</button><a href="{{route('materials.show',$material)}}" class="clear-link">Cancelar</a></div></form>@endcan</div>
@endsection
