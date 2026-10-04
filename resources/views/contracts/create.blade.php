@extends('layouts.management')
@section('title', 'Registrar contrato')
@section('workspace-content')
<section class="management">
<a href="{{ route('contracts.index') }}">Volver a contratos</a><h1>Registrar contrato</h1><p class="muted">Los campos marcados con * son obligatorios. El contrato se registra pendiente y conserva el precio vigente del plan al guardarlo.</p>
@if($errors->any())<div class="notice error-summary" role="alert"><strong>Revisa los campos indicados:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if($plans->isEmpty())<p class="notice">No hay planes activos. Solicita a Gerencia que registre o active un plan.</p>@endif
@if($clients->isEmpty() || $zones->isEmpty() || $employees->isEmpty())<p class="notice">Para registrar un contrato se necesita al menos un cliente, una zona y un empleado responsable activos.</p>@endif
@can('contracts.manage')<form method="POST" action="{{ route('contracts.store') }}" class="account-form">
@csrf
<div><label for="plan_id">Plan de servicio *</label><select id="plan_id" name="plan_id" required @error('plan_id') aria-invalid="true" aria-describedby="plan_id-error" @enderror><option value="">Selecciona un plan activo</option>@foreach($plans as $plan)<option value="{{ $plan->plan_id }}" @selected((string) old('plan_id') === (string) $plan->plan_id)>{{ $plan->numero_plan }} · {{ $plan->nombre }} · S/ {{ number_format($plan->precio_mensual, 2) }}/mes</option>@endforeach</select>@error('plan_id')<p class="field-error" id="plan_id-error">{{ $message }}</p>@enderror</div>
@foreach([
    ['cliente_id', 'Cliente', $clients->map(fn ($c) => ['id' => $c->cliente_id, 'label' => $c->numero_documento.' · '.trim($c->nombres_razon_social.' '.$c->apellidos)])],
    ['zona_id', 'Zona', $zones->map(fn ($z) => ['id' => $z->zona_id, 'label' => $z->nombre])],
    ['responsable_id', 'Empleado responsable', $employees->map(fn ($e) => ['id' => $e->empleado_id, 'label' => $e->nombres.' '.$e->apellidos])],
] as [$field, $label, $options])
<div><label for="{{ $field }}">{{ $label }} *</label><select id="{{ $field }}" name="{{ $field }}" required @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror><option value="">Selecciona una opción</option>@foreach($options as $option)<option value="{{ $option['id'] }}" @selected((string) old($field) === (string) $option['id'])>{{ $option['label'] }}</option>@endforeach</select>@error($field)<p class="field-error" id="{{ $field }}-error">{{ $message }}</p>@enderror</div>
@endforeach
@foreach([['codigo', 'Código del contrato', 'text', 40], ['fecha_inicio', 'Fecha de inicio', 'date', null], ['duracion_meses', 'Duración en meses', 'number', null], ['direccion_servicio', 'Dirección de servicio', 'text', 255]] as [$field, $label, $type, $length])
<div><label for="{{ $field }}">{{ $label }} *</label><input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field) }}" required @if($length) maxlength="{{ $length }}" @endif @if($field === 'duracion_meses') min="1" max="65535" step="1" @endif @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>@error($field)<p class="field-error" id="{{ $field }}-error">{{ $message }}</p>@enderror</div>
@endforeach
<div><label for="observaciones">Observaciones (opcional)</label><textarea id="observaciones" name="observaciones" rows="3" maxlength="10000" @error('observaciones') aria-invalid="true" aria-describedby="observaciones-error" @enderror>{{ old('observaciones') }}</textarea>@error('observaciones')<p class="field-error" id="observaciones-error">{{ $message }}</p>@enderror</div>
<div class="form-actions"><button type="submit" class="action-link" @disabled($plans->isEmpty() || $clients->isEmpty() || $zones->isEmpty() || $employees->isEmpty())>Registrar contrato</button><a class="secondary" href="{{ route('contracts.index') }}">Cancelar</a></div>
</form>@endcan
</section>
@endsection
