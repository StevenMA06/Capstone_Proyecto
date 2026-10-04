@extends('users.layout')
@section('management-content')
<div class="management-toolbar"><h2>Cuentas registradas <span class="count">{{ $users->total() }}</span></h2>@can('users.manage')<a class="action-link" href="{{ route('users.create') }}">+ Registrar cuenta</a>@endcan</div>
<form class="filters" method="GET" action="{{ route('users.index') }}">
    <div><label for="search">Nombre de usuario</label><input id="search" name="search" value="{{ request('search') }}" placeholder="Buscar por nombre de usuario" maxlength="60"></div>
    <div><label for="role">Rol</label><select name="role" id="role"><option value="">Todos los roles</option>@foreach($roles as $role)<option value="{{ $role->rol_id }}" @selected((string) request('role') === (string) $role->rol_id)>{{ $role->nombre }}</option>@endforeach</select></div>
    <button class="action-link" type="submit">Buscar</button><a class="clear-link" href="{{ route('users.index') }}">Limpiar</a>
</form>
<div class="table-scroll"><table class="users-table"><caption class="sr-only">Cuentas de usuario</caption><thead><tr><th>ID</th><th>Usuario</th><th>Nombre completo</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($users as $account)<tr>
    <td>#{{ $account->usuario_id }}</td><td><a href="{{ route('users.show', $account) }}">{{ $account->nombre_usuario }}</a></td><td>{{ $account->name }}</td><td>{{ $account->role_names }}</td><td><span class="state {{ ($account->estado === 'ACTIVO') ? 'active' : 'inactive' }}">{{ ($account->estado === 'ACTIVO') ? 'Activo' : ucfirst(strtolower($account->estado)) }}</span></td><td><div class="row-actions"><a href="{{ route('users.show', $account) }}" aria-label="Ver ficha de {{ $account->nombre_usuario }}">Ver ficha</a>@can('edit-user', $account)@can('users.manage')<a href="{{ route('users.edit', $account) }}" aria-label="Editar {{ $account->nombre_usuario }}">Editar</a>@endcan
@endcan</div></td>
</tr>@empty<tr><td colspan="6" class="empty-state">No se encontraron cuentas con esos criterios.</td></tr>@endforelse
</tbody></table></div>
<nav class="pagination" aria-label="Paginación de usuarios"><span>{{ $users->total() }} cuentas · Página {{ $users->currentPage() }} de {{ $users->lastPage() }}</span><div>@if($users->previousPageUrl())<a class="secondary" href="{{ $users->previousPageUrl() }}">Anterior</a>@endif @if($users->nextPageUrl())<a class="secondary" href="{{ $users->nextPageUrl() }}">Siguiente</a>@endif</div></nav>
@endsection
