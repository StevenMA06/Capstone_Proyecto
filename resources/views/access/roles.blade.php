@extends('layouts.management')
@section('title', 'Roles y permisos')
@push('styles')<link rel="stylesheet" href="{{ asset('css/access-admin.css') }}?v=3"><script src="{{ asset('js/access-admin.js') }}?v=2" defer></script>@endpush
@section('workspace-content')
<header class="module-heading"><h1>Roles y permisos</h1><p>Configura las consultas y acciones autorizadas para cada rol.</p></header>
@if(session('status'))<p class="notice success" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="notice" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<div class="access-admin-grid">
<aside class="access-panel"><h2>Roles registrados</h2>@can('roles.manage')<a class="action-link" href="{{ route('roles.index') }}">Nuevo rol</a>@endcan
<ul class="access-role-list">@foreach($roles as $role)<li><a href="{{ route('roles.index', ['role' => $role->getKey()]) }}" @if($selected?->getKey() === $role->getKey()) aria-current="page" @endif>{{ $role->nombre }}</a><small>{{ $role->estado === 'ACTIVO' ? 'Activo' : 'Inactivo' }}</small></li>@endforeach</ul></aside>
<section class="access-panel">
@php($protected = $selected && $selected->nombre === \App\Models\Role::ADMIN)
<h2>{{ $selected ? 'Configurar '.$selected->nombre : 'Nuevo rol' }}</h2>
@if($protected)<p>Este rol está protegido y conserva acceso completo al sistema.</p>@endif
<form method="POST" data-role-permissions action="{{ $selected ? route('roles.update', $selected) : route('roles.store') }}">@csrf @if($selected) @method('PUT') @endif
<fieldset @disabled($protected || !auth()->user()->can('roles.manage'))>
<div class="access-fields"><label>Nombre del rol<input name="nombre" maxlength="50" required value="{{ old('nombre', $selected?->nombre) }}"></label>
<label>Descripción<textarea name="descripcion" required maxlength="255" rows="3">{{ old('descripcion', $selected?->descripcion) }}</textarea></label>
<label>Estado<select name="estado"><option value="ACTIVO" @selected(old('estado', $selected?->estado) !== 'INACTIVO')>Activo</option><option value="INACTIVO" @selected(old('estado', $selected?->estado) === 'INACTIVO')>Inactivo</option></select></label></div>
</fieldset>
@php($groups = collect($catalog)->groupBy('modulo', preserveKeys: true))
<section class="permission-editor" aria-labelledby="permission-title">
<div class="permission-heading"><div><h3 id="permission-title">Permisos por módulo</h3><p>Selecciona lo que este rol puede consultar y hacer en cada módulo.</p></div><span class="permission-total" data-permission-total role="status" aria-live="polite"></span></div>
<details class="permission-help"><summary>Cómo funcionan los permisos</summary><p>Al marcar una acción, se seleccionan también las consultas que necesita. Al retirar una consulta, se retiran las acciones que dependen de ella. Los filtros solo cambian lo que ves: conservan tu selección.</p><p>Los roles inactivos no conceden permisos. Empleados e historial de accesos son exclusivos del Administrador.</p><p>Para revisar recursos pendientes en Gerencia, habilita «Pedidos de instalación · Consultar» y «Autorizar recursos de instalación». El listado de Almacén requiere «Solicitudes a Almacén · Consultar solicitudes de Almacén».</p></details>
<div class="permission-toolbar" data-permission-toolbar hidden>
<label for="permission-search">Buscar permiso<input id="permission-search" type="search" placeholder="Ej. clientes, registrar, contrato firmado" autocomplete="off"></label>
<label for="permission-module">Módulo<select id="permission-module"><option value="">Todos los módulos</option>@foreach($groups as $module => $permissions)<option value="{{ $module }}">{{ $module }}</option>@endforeach</select></label>
<label for="permission-state">Mostrar<select id="permission-state"><option value="">Todos los permisos</option><option value="selected">Solo seleccionados</option><option value="unselected">Sin seleccionar</option></select></label>
<button class="secondary" type="button" data-clear-permission-filters>Limpiar filtros</button>
</div>
<p class="permission-results" data-permission-results role="status" aria-live="polite"></p>
<fieldset @disabled($protected || !auth()->user()->can('roles.manage'))>
<div class="access-selection"><button class="secondary" type="button" data-permission-selection="all" hidden>Seleccionar resultados</button><button class="secondary" type="button" data-permission-selection="none" hidden>Deseleccionar resultados</button></div>
<div class="permission-modules">
@foreach($groups as $module => $permissions)
<details class="permission-module" data-permission-module="{{ $module }}">
<summary><span>{{ $module }}</span><small data-module-count></small></summary>
<div class="permission-module-body">
<button class="secondary permission-module-select" type="button" data-module-selection hidden>Seleccionar resultados de este módulo</button>
<div class="access-checks">
@foreach($permissions as $code => $permission)
<label data-permission-option><input type="checkbox" name="permissions[]" value="{{ $code }}" data-dependencies="{{ implode(',', \App\Support\AccessCatalog::dependencies($code)) }}" @disabled(\App\Support\AccessCatalog::reserved($code)) @checked(in_array($code, old('permissions', $protected ? array_keys($catalog) : ($snapshot['permissions'] ?? []))))><span><strong>{{ $permission['accion'] }}</strong><small>{{ $permission['descripcion'] }}</small>@if(\App\Support\AccessCatalog::reserved($code))<em class="permission-reserved">Exclusivo del Administrador</em>@endif</span></label>
@endforeach
</div></div></details>
@endforeach
</div>
<p class="permission-empty" data-permission-empty hidden>No hay permisos que coincidan. Prueba otra búsqueda o limpia los filtros.</p>
<button class="action-link permission-save" type="submit">Guardar rol y permisos</button>
</fieldset></section></form></section></div>
@endsection
