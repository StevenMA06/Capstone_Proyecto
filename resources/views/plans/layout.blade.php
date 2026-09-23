@extends('layouts.management')
@section('title', 'Planes de servicio')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/plans.css') }}?v=3">
@endpush
@section('workspace-content')
<section class="management record-tables plans-module">
    <nav class="workspace-breadcrumb" aria-label="Ruta de navegación"><a href="{{ route('dashboard') }}">Inicio</a><span aria-hidden="true">/</span><a href="{{ route('plans.index') }}">Planes de servicio</a></nav>
    <header class="management-header"><div><span class="eyebrow">CATÁLOGO DE SERVICIOS</span><h1>Gestión de Planes de Servicio</h1><p>Consulta los planes ofrecidos por Lunnys Perú.</p></div></header>
    @if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="notice error-summary" role="alert"><strong>Revisa los campos indicados:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('plans-content')
</section>
@endsection
