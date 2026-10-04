@extends('plans.layout')
@section('plans-content')
<h2>{{ $plan->exists ? 'Editar plan' : 'Registrar plan' }}</h2><p class="muted">Los campos marcados con * son obligatorios.</p>
@can('plans.manage')<form method="POST" action="{{ $plan->exists ? route('plans.update', $plan) : route('plans.store') }}" class="account-form">
    @csrf @if($plan->exists) @method('PUT') @endif
    @if($plan->exists)
    <div><label for="plan-number">Número de plan</label><input id="plan-number" value="{{ $plan->numero_plan }}" readonly aria-describedby="plan-number-help"><small id="plan-number-help">Asignado automáticamente por el sistema. No se puede modificar.</small></div>
    @endif
    @foreach([
        ['nombre', 'Nombre del plan', true, 120], ['tipo_servicio', 'Tipo de servicio', true, 80],
        ['velocidad_mbps', 'Velocidad (Mbps)', false, null], ['precio_mensual', 'Precio mensual (S/)', true, null],
        ['descripcion', 'Descripción', false, 500], ['condiciones', 'Condiciones', false, 10000],
    ] as [$field, $label, $required, $length])
    <div><label for="{{ $field }}">{{ $label }} {{ $required ? '*' : '(opcional)' }}</label>
        @if(in_array($field, ['descripcion', 'condiciones']))
        <textarea id="{{ $field }}" name="{{ $field }}" rows="4" maxlength="{{ $length }}" @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>{{ old($field, $plan->$field) }}</textarea>
        @else
        <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $plan->$field) }}" type="{{ in_array($field, ['velocidad_mbps', 'precio_mensual']) ? 'number' : 'text' }}" @required($required) @if($length) maxlength="{{ $length }}" @endif @if($field === 'precio_mensual') min="0.01" max="9999999999.99" step="0.01" @elseif($field === 'velocidad_mbps') min="1" max="4294967295" step="1" @endif @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>
        @endif
        @error($field)<p id="{{ $field }}-error" class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>
    @endforeach
    <div class="form-actions"><button type="submit" class="action-link">{{ $plan->exists ? 'Guardar cambios' : 'Registrar plan' }}</button><a class="secondary" href="{{ $plan->exists ? route('plans.show', $plan) : route('plans.index') }}">Cancelar</a></div>
</form>@endcan
@endsection
