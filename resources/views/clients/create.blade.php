@extends('clients.layout')
@section('clients-content')
<div class="management-toolbar">
    <h2>{{ $client->exists ? 'Editar cliente #'.$client->getKey() : 'Registrar cliente' }}</h2>
    <a class="secondary" href="{{ route('clients.index') }}">Ver clientes</a>
</div>
<p class="muted">Los campos marcados con * son obligatorios.</p>
<form class="account-form" method="POST" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}" novalidate>
    @csrf
    @if($client->exists) @method('PUT') @endif
    <div>
        <label for="tipo_documento">Tipo de documento *</label>
        <select id="tipo_documento" name="tipo_documento" required @error('tipo_documento') aria-invalid="true" aria-describedby="tipo_documento-error" @enderror>
            <option value="">Selecciona un tipo</option>
            @foreach(['DNI', 'CE', 'RUC', 'OTRO'] as $type)
                <option value="{{ $type }}" @selected(old('tipo_documento', $client->tipo_documento ?? 'DNI') === $type)>{{ $type }}</option>
            @endforeach
        </select>
        @error('tipo_documento')<p id="tipo_documento-error" class="error">{{ $message }}</p>@enderror
    </div>
    @php
        $fields = [
            ['numero_documento', 'Número de documento', 20, true, 'text'],
            ['nombres_razon_social', 'Nombres o razón social', 180, true, 'text'],
            ['apellidos', 'Apellidos', 120, true, 'text'],
            ['direccion', 'Dirección', 255, true, 'text'],
            ['referencia_direccion', 'Referencia de dirección', 255, false, 'text'],
            ['correo', 'Correo electrónico', 150, false, 'email'],
            ['telefono', 'Teléfono', 25, true, 'tel'],
            ['departamento', 'Departamento', 100, false, 'text'],
            ['provincia', 'Provincia', 100, false, 'text'],
            ['distrito', 'Distrito', 100, false, 'text'],
            ['latitud', 'Latitud', null, false, 'number'],
            ['longitud', 'Longitud', null, false, 'number'],
        ];
    @endphp
    @foreach($fields as [$name, $label, $length, $required, $type])
        <div>
            <label for="{{ $name }}">{{ $label }} {{ $required ? '*' : '(opcional)' }}</label>
            <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ is_scalar(old($name, $client->{$name})) ? old($name, $client->{$name}) : '' }}"
                @required($required) @if($length) maxlength="{{ $length }}" @endif
                @if($type === 'number') step="0.0000001" min="{{ $name === 'latitud' ? -90 : -180 }}" max="{{ $name === 'latitud' ? 90 : 180 }}" @endif
                @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
            <p id="{{ $name }}-error" class="error" @if(!$errors->has($name)) hidden @endif>{{ $errors->first($name) }}</p>
        </div>
    @endforeach
    <div class="form-actions">
        <button class="action-link" type="submit">{{ $client->exists ? 'Guardar cambios' : 'Registrar cliente' }}</button>
        <a class="secondary" href="{{ $client->exists ? route('clients.show', $client) : route('clients.index') }}">Cancelar</a>
    </div>
</form>
<script src="{{ asset('js/clients.js') }}" defer></script>
@endsection
