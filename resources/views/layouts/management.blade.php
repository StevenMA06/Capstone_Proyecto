<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Gestión de clientes') · Lunnys Perú</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=4">
    <link rel="stylesheet" href="{{ asset('css/management.css') }}?v=15">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @include('layouts.session-monitor')
    <script src="{{ asset('js/automatic-indicators.js') }}?v=4" defer></script>
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('css/record-tables.css') }}?v=1">
    <script src="{{ asset('js/validation-es.js') }}?v=1" defer></script>
    <script src="{{ asset('js/sidebar.js') }}?v=1" defer></script>
</head>
<body class="workspace-shell">
<a class="skip-content" href="#module-content">Ir al contenido</a>
<header class="workspace-topbar">
    <a class="workspace-brand" href="{{ route('dashboard') }}"><img class="brand-logo" src="{{ asset('images/lunnys-peru.png') }}" alt="Logo de Lunnys Perú"><span>Lunnys <strong>Perú</strong><small>PLATAFORMA DE GESTIÓN</small></span></a>
    <div class="workspace-account">
        @auth
            @php($account = auth()->user())
            <div class="workspace-account-summary" aria-label="Cuenta {{ $account->nombre_usuario }} · {{ $account->role_names ?: 'Sin rol asignado' }}">
                <span class="workspace-account-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($account->nombre_usuario ?: '?', 0, 1)) }}</span>
                <span class="workspace-account-identity"><strong>{{ $account->nombre_usuario }}</strong><small>{{ $account->role_names ?: 'Sin rol asignado' }}</small></span>
            </div>
        @endauth
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="secondary" type="submit">Cerrar sesión</button></form>
    </div>
</header>
<div class="workspace-body">
    <aside class="workspace-sidebar" aria-label="Menú de módulos">
        <details class="sidebar-disclosure" open>
            <summary class="sidebar-disclosure-toggle"><span class="sidebar-disclosure-label"><i class="bi bi-list" aria-hidden="true"></i> Menú de módulos</span><span class="nav-chevron" aria-hidden="true"></span></summary>
            <div class="module-menu">
                <nav aria-label="Navegación principal">
                    @include('access.navigation')
                </nav>
            </div>
        </details>

    </aside>
    <main class="workspace-main" id="module-content" tabindex="-1">@yield('workspace-content')</main>
</div>
</body>
</html>
