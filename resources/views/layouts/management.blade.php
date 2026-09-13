<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Gestión de clientes') · Lunnys Perú</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=4">
    <link rel="stylesheet" href="{{ asset('css/management.css') }}?v=1">
    @include('layouts.session-monitor')
</head>
<body class="workspace-shell">
<a class="skip-content" href="#module-content">Ir al contenido</a>
<header class="workspace-topbar">
    <a class="workspace-brand" href="{{ route('dashboard') }}"><span class="brand-mark" aria-hidden="true">L</span><span>Lunnys <strong>Perú</strong><small>PLATAFORMA DE GESTIÓN</small></span></a>
    <div class="workspace-account">
        <span class="user-avatar" aria-hidden="true">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
        <div class="user-label"><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->role->nombre }}</span></div>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="secondary" type="submit">Cerrar sesión</button></form>
    </div>
</header>
<div class="workspace-body">
    <aside class="workspace-sidebar" aria-label="Menú de módulos">
        <details class="module-menu" open>
            <summary>Módulos del sistema</summary>
            <nav aria-label="Navegación principal">
                <a href="{{ route('dashboard') }}">@include('layouts.module-icon', ['icon' => 'home'])<span>Dashboard</span></a>
                @can('manage-users')<a href="{{ route('users.index') }}">@include('layouts.module-icon', ['icon' => 'users'])<span>Usuarios</span></a>@endcan
                @can('register-clients')<a class="{{ request()->routeIs('clients.*') ? 'nav-active' : '' }}" @if(request()->routeIs('clients.*')) aria-current="page" @endif href="{{ route('clients.index') }}">@include('layouts.module-icon', ['icon' => 'users'])<span>Clientes</span></a>@endcan
                @can('view-access-history')<a class="{{ request()->routeIs('access.index') ? 'nav-active' : '' }}" @if(request()->routeIs('access.index')) aria-current="page" @endif href="{{ route('access.index') }}">@include('layouts.module-icon', ['icon' => 'file'])<span>Historial de accesos</span></a>@endcan
                <p class="nav-section">PRÓXIMOS MÓDULOS</p>
                @foreach(['Ventas' => 'cart', 'Contratos' => 'file', 'Pagos' => 'money', 'Facturación' => 'file', 'Equipos' => 'box', 'Materiales' => 'box', 'Zonas' => 'pin', 'Instalaciones' => 'tool', 'Reportes' => 'chart', 'BI' => 'chart', 'Chatbot' => 'chat'] as $module => $icon)
                    <span class="nav-pending" aria-disabled="true" title="{{ $module }}: próximamente">@include('layouts.module-icon', ['icon' => $icon])<span>{{ $module }}</span><small>Pronto</small></span>
                @endforeach
            </nav>
        </details>
        <div class="sidebar-note"><span class="online-dot" aria-hidden="true"></span> Lunnys Perú R.2.D.</div>
    </aside>
    <main class="workspace-main" id="module-content" tabindex="-1">@yield('workspace-content')</main>
</div>
</body>
</html>
