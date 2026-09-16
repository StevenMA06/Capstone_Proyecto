@extends('layouts.management')
@section('title', 'Gestión de empleados')
@section('workspace-content')
<section class="management">
    <nav class="workspace-breadcrumb" aria-label="Ruta de navegación"><a href="{{ route('dashboard') }}">Inicio</a><span aria-hidden="true">/</span><a href="{{ route('employees.index') }}">Empleados</a></nav>
    <header class="management-header"><div><span class="eyebrow">PERSONAL · LUNNYS PERÚ</span><h1>Gestión de empleados</h1><p>Administra los datos del personal y la vigencia de su vínculo laboral.</p></div></header>
    @if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="notice error-summary" role="alert"><strong>Revisa los campos indicados:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('employees-content')
</section>
@endsection
