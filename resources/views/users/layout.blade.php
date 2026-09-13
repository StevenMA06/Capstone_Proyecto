@extends('layouts.management')
@section('title', 'Gestión de usuarios')
@section('workspace-content')
<section class="management">
    <nav class="workspace-breadcrumb" aria-label="Ruta de navegación"><a href="{{ route('dashboard') }}">Inicio</a><span aria-hidden="true">/</span><a href="{{ route('users.index') }}">Gestión de usuarios</a></nav>
    <header class="management-header">
        <div><span class="eyebrow">LUNNY’S PERU R.2.D. · GERENCIA</span><h1>Gestión de usuarios</h1><p>Administra las cuentas y los accesos de tu equipo.</p></div>
        <a class="secondary" href="{{ route('dashboard') }}">Volver al panel</a>
    </header>
    @if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="notice error-summary" role="alert"><strong>Revisa los siguientes datos:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('management-content')
</section>
@endsection
