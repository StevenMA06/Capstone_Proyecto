@extends('layouts.management')
@section('title', 'Zonas de cobertura')
@push('styles')<link rel="stylesheet" href="{{ asset('css/zones.css') }}?v=1">@endpush
@section('workspace-content')
<section class="management zones-module">
    <nav class="workspace-breadcrumb" aria-label="Ruta de navegación"><a href="{{ route('dashboard') }}">Inicio</a><span aria-hidden="true">/</span><a href="{{ route('zones.index') }}">Zonas de cobertura</a></nav>
    <header class="module-heading">
        <span class="eyebrow">COBERTURA COMERCIAL</span>
        <h1>Gestión de Zonas de Cobertura</h1>
        <p>Administra las ubicaciones donde Lunnys Perú ofrece sus planes de servicio.</p>
    </header>
    @if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="notice error-summary" role="alert"><strong>Revisa los campos indicados:</strong><ul>@foreach($errors->messages() as $field => $messages)<li><a href="#{{ str_replace('.', '-', $field) }}">{{ $messages[0] }}</a></li>@endforeach</ul></div>
    @endif
    @yield('zones-content')
</section>
@endsection
