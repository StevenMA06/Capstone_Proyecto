@extends('users.layout')
@section('management-content')
<div class="management-toolbar"><h2>Detalle de la cuenta</h2>@can('edit-user', $account)<a class="action-link" href="{{ route('users.edit', $account) }}">Editar cuenta</a>@endcan</div>
<dl class="account-detail">
    <div><dt>Identificador</dt><dd>#{{ $account->usuario_id }}</dd></div>
    <div><dt>Nombre de usuario</dt><dd>{{ $account->nombre_usuario }}</dd></div>
    <div><dt>Empleado / nombre visible</dt><dd>{{ $account->name }}</dd></div>
    <div><dt>Correo electrónico</dt><dd>{{ $account->correo }}</dd></div>
    <div><dt>Rol</dt><dd>{{ $account->role->nombre }}</dd></div>
    <div><dt>Estado</dt><dd><span class="state {{ ($account->estado === 'ACTIVO') ? 'active' : 'inactive' }}">{{ ($account->estado === 'ACTIVO') ? 'Activo' : ucfirst(strtolower($account->estado)) }}</span></dd></div>
</dl>
@if($account->role->nombre === \App\Models\Role::ADMIN)
<p class="status-box">Cuenta owner protegida: no se puede eliminar, inhabilitar ni cambiar su rol.</p>
@else
<div class="status-box"><div><h3>{{ ($account->estado === 'ACTIVO') ? 'Inhabilitar cuenta' : 'Habilitar cuenta' }}</h3><p>{{ ($account->estado === 'ACTIVO') ? 'La cuenta no podrá acceder al sistema y sus sesiones activas se cerrarán.' : 'La cuenta podrá volver a iniciar sesión con sus credenciales.' }}</p></div>
<form method="POST" action="{{ route('users.status', $account) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ ($account->estado === 'ACTIVO') ? '0' : '1' }}"><button class="secondary {{ ($account->estado === 'ACTIVO') ? 'danger' : '' }}" type="submit">{{ ($account->estado === 'ACTIVO') ? 'Inhabilitar' : 'Habilitar' }}</button></form></div>
@endif
<a class="clear-link" href="{{ route('users.index') }}">Volver a las cuentas</a>
@endsection
