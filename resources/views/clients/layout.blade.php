@extends('layouts.management')
@section('title', 'Gestión de clientes')
@section('workspace-content')
<section class="management clients-management">
    <nav class="workspace-breadcrumb" aria-label="Ruta de navegación"><a href="{{ route('dashboard') }}">Inicio</a><span aria-hidden="true">/</span><span>Gestión de clientes</span></nav>
    <header class="module-heading">
        <span class="eyebrow">RELACIÓN CON TUS CLIENTES</span>
        <h1>Gestión de clientes</h1>
        <p>Administra su información y consulta todo su historial en un solo lugar.</p>
    </header>
    @if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="notice error-summary" role="alert"><strong>Revisa los datos indicados:</strong>
            <ul>@foreach($errors->messages() as $field => $messages)<li><a href="#{{ $field }}">{{ $messages[0] }}</a></li>@endforeach</ul>
        </div>
    @endif
    @yield('clients-content')
</section>
@endsection
