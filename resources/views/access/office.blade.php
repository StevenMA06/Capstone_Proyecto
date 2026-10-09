@extends('layouts.management')
@section('title', 'Panel de Call Center')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/call-center.css') }}?v=1">
@endpush
@section('workspace-content')
@php
    $commercial = [
        ['office.coordination.index', 'Coordinar visitas', 'tool', 'Registra la fecha acordada con el cliente y da inicio al pedido de instalación.'],
        ['clients.index', 'Clientes', 'users', 'Consulta las fichas de clientes y sus datos de contacto.'],
        ['contracts.index', 'Contratos', 'file', 'Revisa los servicios contratados y la información de cada contrato.'],
        ['sales.index', 'Ventas', 'cart', 'Consulta las ventas y da seguimiento a la atención comercial.'],
        ['orders.index', 'Pedidos', 'tool', 'Revisa los pedidos y el avance de su atención.'],
        ['plans.index', 'Planes de servicio', 'file', 'Consulta las velocidades, tarifas y condiciones de los planes.'],
        ['payments.index', 'Pagos', 'money', 'Consulta los pagos y el estado de las obligaciones del cliente.'],
    ];
    $support = [
        ['zones.index', 'Zonas de cobertura', 'pin', 'Consulta las zonas donde se ofrece el servicio.'],
        ['equipments.availability', 'Disponibilidad de equipos', 'router', 'Consulta los equipos disponibles para la atención.'],
        ['materials.availability', 'Disponibilidad de materiales', 'box', 'Consulta las existencias de materiales.'],
        ['executions.index', 'Instalaciones', 'tool', 'Consulta el avance de las instalaciones.'],
    ];
    $allowed = fn ($item) => auth()->user()->hasPermission(\App\Support\AccessCatalog::forRoute($item[0]) ?? '');
    $commercial = array_values(array_filter($commercial, $allowed));
    $support = array_values(array_filter($support, $allowed));
@endphp
<div class="call-center-home">
    <nav class="cc-breadcrumb" aria-label="Ruta de navegación"><span>Inicio</span><span aria-hidden="true">/</span><span aria-current="page">Call Center</span></nav>
    <header class="cc-heading">
        <p class="cc-eyebrow">Atención comercial</p>
        <h1>Panel de Call Center</h1>
        <p>Hola, {{ auth()->user()->name }}. ¿Qué necesitas gestionar hoy?</p>
    </header>
    @if(count($commercial))
    <section class="cc-section" aria-labelledby="cc-commercial-title">
        <div class="cc-section-heading"><h2 id="cc-commercial-title">Gestión comercial</h2><p>Accede directamente a tus herramientas de trabajo.</p></div>
        <div class="cc-card-grid">
            @foreach($commercial as [$destination, $label, $icon, $description])
            <a class="cc-module-card" href="{{ route($destination) }}">
                <span class="cc-card-icon">@include('layouts.module-icon', ['icon' => $icon])</span>
                <span class="cc-card-copy"><strong>{{ $label }}</strong><span>{{ $description }}</span></span>
                <span class="cc-card-link">Abrir {{ mb_strtolower($label) }} <span aria-hidden="true">→</span></span>
            </a>
            @endforeach
        </div>
    </section>
    @endif
    @if(count($support))
    <section class="cc-section cc-support" aria-labelledby="cc-support-title">
        <div class="cc-section-heading"><h2 id="cc-support-title">Consultas de apoyo</h2><p>Verifica la cobertura y los recursos para atender al cliente.</p></div>
        <div class="cc-support-grid">
            @foreach($support as [$destination, $label, $icon, $description])
            <a class="cc-support-link" href="{{ route($destination) }}">
                <span class="cc-support-icon">@include('layouts.module-icon', ['icon' => $icon])</span>
                <span><strong>{{ $label }}</strong><small>{{ $description }}</small></span>
                <span class="cc-arrow" aria-hidden="true">→</span>
            </a>
            @endforeach
        </div>
    </section>
    @endif
    @if(!count($commercial) && !count($support))
    <div class="cc-empty"><h2>Aún no tienes herramientas asignadas</h2><p>Solicita al administrador los accesos necesarios para tu trabajo.</p></div>
    @endif
</div>
@endsection
