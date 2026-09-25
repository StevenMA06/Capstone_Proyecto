@extends('materials.layout')
@section('materials-content')
<div class="form-layout"><form class="standard-form" method="POST" action="{{route('materials.entry.store',$material)}}">@csrf<h2>Registrar ingreso de material</h2><p class="muted">Material: <strong>{{$material->nombre}}</strong> ({{$material->codigo}})</p><p class="muted">Stock actual: <strong>{{number_format($material->stock_actual,3)}} {{$material->unidad_medida}}</strong></p>
<div class="form-group"><label for="cantidad">Cantidad a ingresar *</label><input type="number" step="0.001" id="cantidad" name="cantidad" value="{{old('cantidad')}}" required min="0.001" autofocus><small>Ingresa la cantidad positiva que deseas agregar al stock</small>@error('cantidad')<span class="error-message">{{$message}}</span>@enderror</div>
<div class="form-group"><label for="observacion">Observación</label><textarea id="observacion" name="observacion" rows="3" placeholder="Opcional: ingreso de proveedor, compra, etc.">{{old('observacion')}}</textarea>@error('observacion')<span class="error-message">{{$message}}</span>@enderror</div>
<div class="form-actions"><button type="submit" class="action-link">Confirmar ingreso</button><a href="{{route('materials.show',$material)}}" class="clear-link">Cancelar</a></div></form></div>
@endsection
