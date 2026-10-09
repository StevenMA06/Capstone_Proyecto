@extends('users.layout')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/user-form.css') }}?v=1">
<script src="{{ asset('js/user-form.js') }}?v=1" defer></script>
@endpush
@section('management-content')
<h2>{{ $account->exists ? 'Editar cuenta' : 'Registrar cuenta' }}</h2>
@can('users.manage')
@routecan($account->exists ? 'users.update' : 'users.store')<form class="account-form" method="POST" action="{{ $account->exists ? route('users.update', $account) : route('users.store') }}">
    @csrf
    @if($account->exists) @method('PUT') @endif
    <div><label for="nombre_usuario">Nombre de usuario *</label><input id="nombre_usuario" name="nombre_usuario" value="{{ old('nombre_usuario', $account->nombre_usuario) }}" required minlength="3" maxlength="60" pattern="[a-zA-Z0-9_.-]+" aria-describedby="username-help @error('nombre_usuario') nombre_usuario-error @enderror" @error('nombre_usuario') aria-invalid="true" @enderror><small id="username-help">Letras sin acentos, números, puntos, guiones o guiones bajos.</small>@error('nombre_usuario')<p id="nombre_usuario-error" class="field-error" role="alert">{{ $message }}</p>@enderror</div>
    <div><label for="correo">Correo electrónico *</label><input id="correo" name="correo" type="email" value="{{ old('correo', $account->correo) }}" autocomplete="email" required maxlength="150" @error('correo') aria-invalid="true" aria-describedby="correo-error" @enderror>@error('correo')<p id="correo-error" class="field-error" role="alert">{{ $message }}</p>@enderror</div>
    @if($account->exists && !auth()->user()->hasPermission('user-roles.manage'))<input type="hidden" name="rol_id" value="{{ $account->rol_id }}">@endif
    <div><label for="rol_id">Rol principal *</label><select id="rol_id" name="rol_id" @disabled($account->exists && !auth()->user()->hasPermission('user-roles.manage')) required @error('rol_id') aria-invalid="true" aria-describedby="rol_id-error" @enderror><option value="">Selecciona un rol</option>@foreach($roles as $role)<option value="{{ $role->rol_id }}" @selected((string) old('rol_id', $account->rol_id) === (string) $role->rol_id)>{{ $role->nombre }}</option>@endforeach</select>@error('rol_id')<p id="rol_id-error" class="field-error" role="alert">{{ $message }}</p>@enderror</div>
    @if($account->employee && !$account->employee->isEligible())
    <div><label>Empleado asociado</label><p>{{ $account->employee->full_name }} · {{ $account->employee->numero_documento }}</p><small>El empleado no tiene un vínculo activo vigente. Se conserva la asociación histórica; actualiza su ficha y habilítalo antes de habilitar esta cuenta.</small><input type="hidden" name="empleado_id" value="{{ $account->empleado_id }}">@error('empleado_id')<p class="field-error">{{ $message }}</p>@enderror</div>
    @else
    <div><label for="empleado_id">Empleado asociado (opcional)</label><select id="empleado_id" name="empleado_id" @error('empleado_id') aria-invalid="true" aria-describedby="empleado_id-error" @enderror><option value="">Sin empleado asociado</option>@foreach($employees as $employee)<option value="{{ $employee->empleado_id }}" data-email="{{ $employee->correo }}" @selected((string) old('empleado_id', $account->empleado_id) === (string) $employee->empleado_id)>{{ $employee->full_name }} · {{ $employee->numero_documento }}</option>@endforeach</select>@error('empleado_id')<p id="empleado_id-error" class="field-error" role="alert">{{ $message }}</p>@enderror</div>
    @endif
    <div><label for="new-password">{{ $account->exists ? 'Nueva contraseña (opcional)' : 'Contraseña *' }}</label><div class="user-password-field"><input id="new-password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="128" @required(!$account->exists) aria-describedby="password-help @error('password') password-error @enderror" @error('password') aria-invalid="true" @enderror><button class="password-toggle" type="button" data-password-toggle aria-label="Mostrar contraseña" data-show-label="Mostrar contraseña" data-hide-label="Ocultar contraseña" aria-controls="new-password" aria-pressed="false" hidden><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m3 3 18 18"/></svg></button></div><small id="password-help">Mínimo 12 caracteres con letras y números. {{ $account->exists ? 'Déjala vacía para conservar la actual.' : '' }}</small>@error('password')<p id="password-error" class="field-error" role="alert">{{ $message }}</p>@enderror</div>
    <div><label for="password-confirmation">Confirmar contraseña {{ $account->exists ? '' : '*' }}</label><div class="user-password-field"><input id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" maxlength="128" @required(!$account->exists)><button class="password-toggle" type="button" data-password-toggle aria-label="Mostrar confirmación de contraseña" data-show-label="Mostrar confirmación de contraseña" data-hide-label="Ocultar confirmación de contraseña" aria-controls="password-confirmation" aria-pressed="false" hidden><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m3 3 18 18"/></svg></button></div></div>
    <div class="form-actions"><button class="action-link" type="submit">{{ $account->exists ? 'Guardar cambios' : 'Registrar cuenta' }}</button>
@routecan('users.index')<a class="secondary" href="{{ route('users.index') }}">Cancelar</a>@endroutecan
</div>
</form>@endroutecan
@endcan
@endsection


