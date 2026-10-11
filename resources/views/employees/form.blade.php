@extends('employees.layout')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/dni-lookup.css') }}?v=1">
<script src="{{ asset('js/dni-lookup.js') }}?v=1" defer></script>
@endpush
@section('employees-content')
<h2>{{ $employee->exists ? 'Editar empleado' : 'Registrar empleado' }}</h2>
@can('employees.manage')
@routecan($employee->exists ? 'employees.update' : 'employees.store')<form class="account-form" method="POST" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}">
@csrf @if($employee->exists) @method('PUT') @endif
<div><label for="tipo_documento">Tipo de documento *</label><select id="tipo_documento" name="tipo_documento" required @error('tipo_documento') aria-invalid="true" aria-describedby="tipo_documento-error" @enderror>@foreach(['DNI','CE','OTRO'] as $type)<option value="{{ $type }}" @selected(old('tipo_documento', $employee->tipo_documento ?? 'DNI') === $type)>{{ $type }}</option>@endforeach</select>@error('tipo_documento')<p id="tipo_documento-error" class="field-error">{{ $message }}</p>@enderror</div>
@foreach([
    ['numero_documento','Número de documento','text',20,true], ['nombres','Nombres','text',100,true],
    ['apellidos','Apellidos','text',120,true],
    ['correo','Correo electrónico','email',150,true], ['telefono','Teléfono','tel',25,true],
    ['fecha_ingreso','Fecha de inicio','date',null,true], ['fecha_cese','Fecha de cese','date',null,true]
] as [$field,$label,$type,$max,$required])
<div><label for="{{ $field }}">{{ $label }} {{ $required ? '*' : '(opcional)' }}</label><input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field, $employee->$field) }}" @required($required) @if($max) maxlength="{{ $max }}" @endif @if($type === 'date') min="1000-01-01" max="9999-12-31" @endif @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>@if($field === 'numero_documento') @include('layouts.dni-lookup', ['lookupModule' => 'employees']) @endif @error($field)<p id="{{ $field }}-error" class="field-error" role="alert">{{ $message }}</p>@enderror</div>
@endforeach
<div>
    <label for="cargo">Cargo *</label>
    <select id="cargo" name="cargo" required @error('cargo') aria-invalid="true" aria-describedby="cargo-error" @enderror>
        <option value="">Selecciona un cargo</option>
        @foreach(\App\Models\Employee::POSITIONS as $position)
        <option value="{{ $position }}" @selected(old('cargo', $employee->cargo) === $position)>{{ $position }}</option>
        @endforeach
    </select>
    @if($employee->exists && !in_array($employee->cargo, \App\Models\Employee::POSITIONS))<small>Cargo anterior: {{ $employee->cargo }}. Selecciona un cargo para guardar.</small>@endif
    @error('cargo')<p id="cargo-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
<div class="form-actions"><button class="action-link" type="submit">{{ $employee->exists ? 'Guardar cambios' : 'Registrar empleado' }}</button>
@routecan('employees.index')<a class="secondary" href="{{ route('employees.index') }}">Cancelar</a>@endroutecan
</div>
</form>@endroutecan
@endcan
<p class="muted">Si el vínculo ya terminó, el empleado y sus cuentas se inhabilitan al guardar. Modificar las fechas no reactiva cuentas automáticamente.</p>
@endsection
