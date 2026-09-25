@extends('layouts.management')
@section('title','Kardex de Inventario')
@push('styles')<link rel="stylesheet" href="{{asset('css/toast.css')}}?v=1">@endpush
@push('styles')<link rel="stylesheet" href="{{ asset('css/inventory.css') }}?v=2"><script src="{{ asset('js/inventory.js') }}?v=1" defer></script>@endpush
@section('workspace-content')
<section class="management record-tables clients-management inventory-management"><nav class="workspace-breadcrumb"><a href="{{route('dashboard')}}">Inicio</a><span>/</span><span>Kardex de Inventario</span></nav><header class="module-heading"><span class="eyebrow">HISTORIAL INMUTABLE</span><h1>Kardex de Inventario</h1><p>Consulta todos los movimientos de equipos y materiales. El Kardex es de solo lectura y se genera automáticamente.</p></header>@yield('kardex-content')</section>
@endsection
