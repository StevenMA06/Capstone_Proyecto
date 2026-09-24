<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Gestión de clientes') · Lunnys Perú</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=4">
    <link rel="stylesheet" href="{{ asset('css/management.css') }}?v=11">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @include('layouts.session-monitor')
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('css/record-tables.css') }}?v=1">
    <script src="{{ asset('js/validation-es.js') }}?v=1" defer></script>
</head>
<body class="workspace-shell">
<a class="skip-content" href="#module-content">Ir al contenido</a>
<header class="workspace-topbar">
    <a class="workspace-brand" href="{{ auth()->user()->isTechnician() ? route('technician.index') : route('dashboard') }}"><img class="brand-logo" src="{{ asset('images/lunnys-peru.png') }}" alt="Logo de Lunnys Perú"><span>Lunnys <strong>Perú</strong><small>PLATAFORMA DE GESTIÓN</small></span></a>
    <div class="workspace-account">
        <span class="user-avatar" aria-hidden="true">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
        <div class="user-label"><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->role->nombre }}</span></div>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="secondary" type="submit">Cerrar sesión</button></form>
    </div>
</header>
<div class="workspace-body">
    <aside class="workspace-sidebar" aria-label="Menú de módulos">
        <div class="module-menu">
            <nav aria-label="Navegación principal">
                @unless(auth()->user()->isTechnician())<a class="{{ request()->routeIs('dashboard') ? 'nav-active' : '' }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif href="{{ route('dashboard') }}">@include('layouts.module-icon', ['icon' => 'home'])<span>Dashboard</span></a>@endunless
                @canany(['register-clients', 'view-office'])<p class="nav-section">Comercial</p>@endcanany
                @can('register-clients')<a class="{{ request()->routeIs('clients.*') ? 'nav-active' : '' }}" @if(request()->routeIs('clients.*')) aria-current="page" @endif href="{{ route('clients.index') }}">@include('layouts.module-icon', ['icon' => 'users'])<span>Clientes</span></a>@endcan
                @can('view-office')
                @foreach(['plans' => ['Planes de servicio', 'file'], 'contracts' => ['Contratos', 'file'], 'payments' => ['Pagos', 'money']] as $key => [$module, $icon])
                    <a class="{{ request()->routeIs($key.'.*') ? 'nav-active' : '' }}" @if(request()->routeIs($key.'.*')) aria-current="page" @endif href="{{ route($key.'.index') }}">@include('layouts.module-icon', ['icon' => $icon])<span>{{ $module }}</span></a>
                @endforeach
                @can('view-zones')<a class="{{ request()->routeIs('zones.*') ? 'nav-active' : '' }}" @if(request()->routeIs('zones.*')) aria-current="page" @endif href="{{ route('zones.index') }}">@include('layouts.module-icon', ['icon' => 'pin'])<span>Zonas de cobertura</span></a>@endcan
                @endcan
                @canany(['view-technician', 'view-operations'])<p class="nav-section">Operaciones</p>@endcanany
                @can('view-technician')<a class="{{ request()->routeIs('technician.*') ? 'nav-active' : '' }}" @if(request()->routeIs('technician.*')) aria-current="page" @endif href="{{ route('technician.index') }}">@include('layouts.module-icon', ['icon' => 'tool'])<span>Panel técnico</span></a>@endcan
                @can('view-operations')
                @foreach(['installations' => ['Instalaciones', 'tool'], 'equipment' => ['Equipos', 'router'], 'materials' => ['Materiales', 'box']] as $key => [$module, $icon])
                    <a class="{{ request()->routeIs($key.'.*') ? 'nav-active' : '' }}" @if(request()->routeIs($key.'.*')) aria-current="page" @endif href="{{ route($key.'.index') }}">@include('layouts.module-icon', ['icon' => $icon])<span>{{ $module }}</span></a>
                @endforeach
                @endcan
                @canany(['manage-employees', 'manage-users', 'view-access-history'])<p class="nav-section">Administración</p>@endcanany
                @can('manage-employees')<a class="{{ request()->routeIs('employees.*') ? 'nav-active' : '' }}" @if(request()->routeIs('employees.*')) aria-current="page" @endif href="{{ route('employees.index') }}">@include('layouts.module-icon', ['icon' => 'users'])<span>Empleados</span></a>@endcan
                @can('manage-users')<a class="{{ request()->routeIs('users.*') ? 'nav-active' : '' }}" @if(request()->routeIs('users.*')) aria-current="page" @endif href="{{ route('users.index') }}">@include('layouts.module-icon', ['icon' => 'users'])<span>Usuarios</span></a>@endcan
                @can('view-access-history')<a class="{{ request()->routeIs('access.index') ? 'nav-active' : '' }}" @if(request()->routeIs('access.index')) aria-current="page" @endif href="{{ route('access.index') }}">@include('layouts.module-icon', ['icon' => 'file'])<span>Historial de accesos</span></a>@endcan
                @can('view-operations')
                <p class="nav-section nav-section-future">Próximamente</p>
                @foreach(['Ventas' => 'cart', 'Facturación' => 'file', 'Reportes' => 'chart', 'BI' => 'chart', 'Chatbot' => 'chat'] as $module => $icon)
                    <span class="nav-pending" aria-disabled="true" title="{{ $module }}: próximamente">@include('layouts.module-icon', ['icon' => $icon])<span>{{ $module }}</span></span>
                @endforeach
                @endcan
            </nav>
        </div>

    </aside>
    <main class="workspace-main" id="module-content" tabindex="-1">@yield('workspace-content')</main>
</div>
</body>
</html>
