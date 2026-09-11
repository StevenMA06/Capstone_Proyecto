@extends('users.layout')
@section('management-content')
<h2>{{ $account->exists ? 'Editar cuenta' : 'Registrar cuenta' }}</h2>
<p class="muted">Los campos marcados con * son obligatorios.</p>
<form class="account-form" method="POST" action="{{ $account->exists ? route('users.update', $account) : route('users.store') }}">
    @csrf
    @if($account->exists) @method('PUT') @endif
    <div><label for="nombre_usuario">Nombre de usuario *</label><input id="nombre_usuario" name="nombre_usuario" value="{{ old('nombre_usuario', $account->nombre_usuario) }}" required minlength="3" maxlength="60" pattern="[a-zA-Z0-9_.-]+" aria-describedby="username-help" @error('nombre_usuario') aria-invalid="true" @enderror><small id="username-help">Letras sin acentos, números, puntos, guiones o guiones bajos.</small></div>
    <div><label for="correo">Correo electrónico *</label><input id="correo" name="correo" type="email" value="{{ old('correo', $account->correo) }}" autocomplete="email" required maxlength="150" @error('correo') aria-invalid="true" @enderror></div>
    <div><label for="rol_id">Rol *</label><select id="rol_id" name="rol_id" required><option value="">Selecciona un rol</option>@foreach($roles as $role)<option value="{{ $role->rol_id }}" @selected((string) old('rol_id', $account->rol_id) === (string) $role->rol_id)>{{ $role->nombre }}</option>@endforeach</select></div>
    <div><label for="empleado_id">Empleado asociado (opcional)</label><select id="empleado_id" name="empleado_id"><option value="">Sin empleado asociado</option>@foreach($employees as $employee)<option value="{{ $employee->empleado_id }}" @selected((string) old('empleado_id', $account->empleado_id) === (string) $employee->empleado_id)>{{ $employee->full_name }} · {{ $employee->numero_documento }}</option>@endforeach</select></div>
    <div><label for="new-password">{{ $account->exists ? 'Nueva contraseña (opcional)' : 'Contraseña *' }}</label><input id="new-password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="128" @required(!$account->exists) aria-describedby="password-help"><small id="password-help">Mínimo 12 caracteres con letras y números. {{ $account->exists ? 'Déjala vacía para conservar la actual.' : '' }}</small></div>
    <div><label for="password-confirmation">Confirmar contraseña {{ $account->exists ? '' : '*' }}</label><input id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" maxlength="128" @required(!$account->exists)></div>
    <div class="form-actions"><button class="action-link" type="submit">{{ $account->exists ? 'Guardar cambios' : 'Registrar cuenta' }}</button><a class="secondary" href="{{ route('users.index') }}">Cancelar</a></div>
</form>
@endsection
