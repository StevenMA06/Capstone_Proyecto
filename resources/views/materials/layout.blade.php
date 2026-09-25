@extends('layouts.management')
@section('title', 'Gestión de materiales')
@push('styles')<link rel="stylesheet" href="{{ asset('css/toast.css') }}?v=1">@endpush
@section('workspace-content')
<section class="management record-tables clients-management">
    <nav class="workspace-breadcrumb" aria-label="Ruta de navegación"><a href="{{ route('dashboard') }}">Inicio</a><span aria-hidden="true">/</span><span>Gestión de materiales</span></nav>
    <header class="module-heading">
        <span class="eyebrow">INVENTARIO DE MATERIALES</span>
        <h1>Gestión de materiales</h1>
        <p>Administra el stock de materiales con control automático de ingresos, salidas y Kardex.</p>
    </header>
    @if(session('status'))
    <div class="client-toast" data-toast role="status" aria-live="polite" aria-atomic="true">
        <span>{{ session('status') }}</span><button type="button" data-toast-close aria-label="Cerrar notificación">&times;</button>
    </div>
    <script src="{{ asset('js/toast.js') }}" defer></script>
    @endif
    @if($errors->any())
        <div class="notice error-summary" role="alert"><strong>Revisa los datos indicados:</strong>
            <ul>@foreach($errors->messages() as $field => $messages)<li><a href="#{{ $field }}">{{ $messages[0] }}</a></li>@endforeach</ul>
        </div>
    @endif
    @yield('materials-content')
</section>
@endsection
