@extends('layouts.management')
@section('title', 'Roles y permisos')
@push('styles')<link rel="stylesheet" href="{{ asset('css/access-admin.css') }}"><script src="{{ asset('js/access-admin.js') }}" defer></script>@endpush
@section('workspace-content')
<header class="module-heading"><h1>Roles y permisos</h1><p>Configura las consultas y acciones autorizadas para cada rol.</p></header>
@if(session('status'))<p class="notice success" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="notice" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<div class="access-admin-grid">
<aside class="access-panel"><h2>Roles registrados</h2>@can('roles.manage')<a class="action-link" href="{{ route('roles.index') }}">Nuevo rol</a>@endcan
<ul class="access-role-list">@foreach($roles as $role)<li><a href="{{ route('roles.index', ['role' => $role->getKey()]) }}" @if($selected?->getKey() === $role->getKey()) aria-current="page" @endif>{{ $role->nombre }}</a><small>{{ $role->estado === 'ACTIVO' ? 'Activo' : 'Inactivo' }}</small></li>@endforeach</ul></aside>
<section class="access-panel">
@php($protected = $selected && in_array($selected->nombre, [\App\Models\Role::ADMIN, \App\Models\Role::MANAGER], true))
<h2>{{ $selected ? 'Configurar '.$selected->nombre : 'Nuevo rol' }}</h2>
@if($protected)<p>Este rol está protegido y conserva acceso completo al sistema.</p>@endif
<form method="POST" data-role-permissions action="{{ $selected ? route('roles.update', $selected) : route('roles.store') }}">@csrf @if($selected) @method('PUT') @endif
<fieldset @disabled($protected || !auth()->user()->can('roles.manage'))>
<div class="access-fields"><label>Nombre del rol<input name="nombre" maxlength="50" required value="{{ old('nombre', $selected?->nombre) }}"></label>
<label>Descripción<textarea name="descripcion" required maxlength="255" rows="3">{{ old('descripcion', $selected?->descripcion) }}</textarea></label>
<label>Estado<select name="estado"><option value="ACTIVO" @selected(old('estado', $selected?->estado) !== 'INACTIVO')>Activo</option><option value="INACTIVO" @selected(old('estado', $selected?->estado) === 'INACTIVO')>Inactivo</option></select></label></div>
<h3>Permisos</h3><p>Administrar requiere habilitar también la consulta. Los roles inactivos no conceden permisos.</p>
<div class="access-selection"><button class="secondary" type="button" data-permission-selection="all" hidden>Seleccionar todo</button><button class="secondary" type="button" data-permission-selection="none" hidden>Deseleccionar todo</button></div>
<div class="access-checks">@foreach($catalog as $code => $permission)<label><input type="checkbox" name="permissions[]" value="{{ $code }}" @checked(in_array($code, old('permissions', $protected ? array_keys($catalog) : ($snapshot['permissions'] ?? []))))><span><strong>{{ $permission['modulo'] }} · {{ $permission['accion'] }}</strong><small>{{ $permission['descripcion'] }}</small></span></label>@endforeach</div>
<button class="action-link" type="submit">Guardar rol y permisos</button>
</fieldset></form></section></div>
@endsection
