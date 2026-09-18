@extends('layouts.management')
@section('title', 'Contratos')
@push('styles')<link rel="stylesheet" href="{{ asset('css/contracts.css') }}?v=2">@endpush
@section('workspace-content')
<section class="management contracts-module">
    <header class="management-header"><div><span class="eyebrow">GESTIÓN COMERCIAL</span><h1>@yield('contracts-title', 'Gestión de Contratos')</h1><nav class="workspace-breadcrumb" aria-label="Ruta de navegación"><a href="{{ route('dashboard') }}">Inicio</a><span aria-hidden="true">/</span>@hasSection('contracts-crumb')<a href="{{ route('contracts.index') }}">Contratos</a><span aria-hidden="true">/</span><span aria-current="page">@yield('contracts-crumb')</span>@else<span aria-current="page">Contratos</span>@endif</nav><p>@yield('contracts-description', 'Consulta y administra los contratos de los clientes.')</p></div></header>
    @if(session('status'))
        @php($feedbackKind = in_array(session('status_kind'), ['activated', 'pending', 'cancelled', 'finalized'], true) ? session('status_kind') : 'success')
        <div class="contract-feedback contract-feedback--{{ $feedbackKind }}" role="status">
            <span class="contract-feedback-icon" aria-hidden="true">
                @if($feedbackKind === 'pending')!@else ✓ @endif
            </span>
            <div><strong>{{ match($feedbackKind) { 'activated' => 'Contrato activado', 'pending' => 'Contrato pendiente', 'cancelled' => 'Contrato cancelado', 'finalized' => 'Contrato finalizado', default => 'Acción completada' } }}</strong><p>{{ session('status') }}</p></div>
        </div>
    @endif
    @if($errors->any())<div class="notice error-summary" role="alert"><strong>Revisa los campos indicados:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('contracts-content')
</section>
@endsection
