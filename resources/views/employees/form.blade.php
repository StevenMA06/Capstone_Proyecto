@extends('employees.layout')
@section('employees-content')
<h2>{{ $employee->exists ? 'Editar empleado' : 'Registrar empleado' }}</h2>
<p>Los campos con * son obligatorios. La fecha de cese es el último día de trabajo (hora de Perú).</p>
<form class="account-form" method="POST" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}">
@csrf @if($employee->exists) @method('PUT') @endif
<div><label for="tipo_documento">Tipo de documento *</label><select id="tipo_documento" name="tipo_documento" required @error('tipo_documento') aria-invalid="true" aria-describedby="tipo_documento-error" @enderror>@foreach(['DNI','CE','OTRO'] as $type)<option value="{{ $type }}" @selected(old('tipo_documento', $employee->tipo_documento ?? 'DNI') === $type)>{{ $type }}</option>@endforeach</select>@error('tipo_documento')<p id="tipo_documento-error" class="field-error">{{ $message }}</p>@enderror</div>
@foreach([
    ['numero_documento','Número de documento','text',20,true], ['nombres','Nombres','text',100,true],
    ['apellidos','Apellidos','text',120,true], ['cargo','Cargo','text',80,true],
    ['correo','Correo electrónico','email',150,false], ['telefono','Teléfono','tel',25,false],
    ['fecha_ingreso','Fecha de inicio','date',null,true], ['fecha_cese','Fecha de cese','date',null,true]
] as [$field,$label,$type,$max,$required])
<div><label for="{{ $field }}">{{ $label }} {{ $required ? '*' : '(opcional)' }}</label><input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field, $employee->$field) }}" @required($required) @if($max) maxlength="{{ $max }}" @endif @if($type === 'date') min="1000-01-01" max="9999-12-31" @endif @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>@error($field)<p id="{{ $field }}-error" class="field-error" role="alert">{{ $message }}</p>@enderror</div>
@endforeach
<div class="form-actions"><button class="action-link" type="submit">{{ $employee->exists ? 'Guardar cambios' : 'Registrar empleado' }}</button><a class="secondary" href="{{ route('employees.index') }}">Cancelar</a></div>
</form>
<p class="muted">Si el vínculo ya terminó, el empleado y sus cuentas se inhabilitan al guardar. Modificar las fechas no reactiva cuentas automáticamente.</p>
@endsection
