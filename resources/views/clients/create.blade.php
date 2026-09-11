@extends('layouts.app')
@section('title', 'Registrar cliente')
@section('content')
<section class="management">
    <header class="management-header">
        <div><span class="eyebrow">LUNNY’S PERU R.2.D.</span><h1>Registrar cliente</h1><p>Ingresa los datos del cliente para completar su registro.</p></div>
        <a class="secondary" href="{{ route('dashboard') }}">Volver al panel</a>
    </header>
    @if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="notice error-summary" role="alert"><strong>No se pudo guardar el registro.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <p class="muted">Los campos marcados con * son obligatorios.</p>
    <form class="account-form" method="POST" action="{{ route('clients.store') }}">
        @csrf
        <div>
            <label for="tipo_documento">Tipo de documento *</label>
            <select id="tipo_documento" name="tipo_documento" required>
                @foreach(['DNI', 'CE', 'RUC', 'OTRO'] as $type)
                    <option value="{{ $type }}" @selected(old('tipo_documento', 'DNI') === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="numero_documento">Número de documento *</label><input id="numero_documento" name="numero_documento" value="{{ old('numero_documento') }}" required maxlength="20"></div>
        <div><label for="nombres_razon_social">Nombres o razón social *</label><input id="nombres_razon_social" name="nombres_razon_social" value="{{ old('nombres_razon_social') }}" required maxlength="180"></div>
        <div><label for="apellidos">Apellidos (opcional)</label><input id="apellidos" name="apellidos" value="{{ old('apellidos') }}" maxlength="120" autocomplete="family-name"></div>
        <div><label for="direccion">Dirección *</label><input id="direccion" name="direccion" value="{{ old('direccion') }}" required maxlength="255" autocomplete="street-address"></div>
        <div><label for="referencia_direccion">Referencia de dirección (opcional)</label><input id="referencia_direccion" name="referencia_direccion" value="{{ old('referencia_direccion') }}" maxlength="255"></div>
        <div><label for="correo">Correo electrónico (opcional)</label><input id="correo" name="correo" type="email" value="{{ old('correo') }}" maxlength="150" autocomplete="email"></div>
        <div><label for="telefono">Teléfono (opcional)</label><input id="telefono" name="telefono" type="tel" value="{{ old('telefono') }}" maxlength="25" autocomplete="tel"></div>
        <div><label for="departamento">Departamento (opcional)</label><input id="departamento" name="departamento" value="{{ old('departamento') }}" maxlength="100"></div>
        <div><label for="provincia">Provincia (opcional)</label><input id="provincia" name="provincia" value="{{ old('provincia') }}" maxlength="100"></div>
        <div><label for="distrito">Distrito (opcional)</label><input id="distrito" name="distrito" value="{{ old('distrito') }}" maxlength="100"></div>
        <div><label for="latitud">Latitud (opcional)</label><input id="latitud" name="latitud" type="number" step="0.0000001" min="-90" max="90" value="{{ old('latitud') }}"></div>
        <div><label for="longitud">Longitud (opcional)</label><input id="longitud" name="longitud" type="number" step="0.0000001" min="-180" max="180" value="{{ old('longitud') }}"></div>
        <div class="form-actions"><button class="action-link" type="submit">Registrar cliente</button><a class="secondary" href="{{ route('dashboard') }}">Cancelar</a></div>
    </form>
</section>
@endsection
