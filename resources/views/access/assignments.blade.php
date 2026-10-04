@extends('layouts.management')
@section('title', 'Usuarios y roles')
@push('styles')<link rel="stylesheet" href="{{ asset('css/access-admin.css') }}">@endpush
@section('workspace-content')
<header class="module-heading"><h1>Usuarios y roles</h1><p>Asigna uno o varios roles a cada cuenta. Los permisos se combinan sin duplicar opciones.</p></header>
@if(session('status'))<p class="notice success" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="notice" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<div class="access-admin-grid"><section class="access-panel"><h2>Buscar cuenta</h2><form method="GET"><label>Nombre de usuario<input name="search" maxlength="60" value="{{ request('search') }}"></label><button class="action-link" type="submit">Buscar</button></form>
<ul class="access-role-list">@forelse($accounts as $account)<li><a href="{{ route('user-roles.index', ['user' => $account->getKey(), 'search' => request('search')]) }}">{{ $account->nombre_usuario }}</a><small>{{ $account->name }}</small></li>@empty<li>No se encontraron cuentas.</li>@endforelse</ul>
@include('clients.pagination', ['records' => $accounts, 'label' => 'Paginación de cuentas'])</section>
<section class="access-panel">@if($selected)
<h2>{{ $selected->nombre_usuario }}</h2><p>{{ $selected->name }} · {{ $selected->employee?->cargo ?? 'Sin empleado asociado' }} · {{ $selected->estado === 'ACTIVO' ? 'Activo' : 'No disponible' }}</p>
<h3>Roles asignados</h3><ul>@foreach($selected->roles as $role)<li>{{ $role->nombre }}{{ $role->estado !== 'ACTIVO' ? ' (inactivo, sin permisos)' : '' }}</li>@endforeach</ul>
<form method="POST" action="{{ route('user-roles.update', $selected) }}">@csrf @method('PUT')
<fieldset @disabled(!auth()->user()->can('user-roles.manage') || ($selected->hasRole(\App\Models\Role::ADMIN, false) && !auth()->user()->isAdministrator()))><legend>Selecciona roles activos</legend>
<div class="access-checks">@foreach($roles as $role)<label><input type="checkbox" name="roles[]" value="{{ $role->getKey() }}" @checked(in_array($role->getKey(), old('roles', $selected->roles->pluck('rol_id')->all())))><span>{{ $role->nombre }}<small>{{ $role->descripcion }}</small></span></label>@endforeach</div>
<button class="action-link" type="submit">Guardar asignación</button></fieldset></form>
@else<h2>Selecciona una cuenta</h2><p>Busca un usuario para consultar y actualizar sus roles.</p>@endif</section></div>
@endsection
