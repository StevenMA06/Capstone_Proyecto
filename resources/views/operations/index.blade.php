@extends('layouts.management')
@section('title', $title)
@push('styles')<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v=2">@endpush
@section('workspace-content')
<section class="overview">
    <header class="overview-heading"><div><h1>{{ $title }}</h1><nav class="workspace-breadcrumb" aria-label="Ruta de navegación"><a href="{{ route('dashboard') }}">Inicio</a><span aria-hidden="true">/</span><span aria-current="page">{{ $title }}</span></nav></div></header>
    <section class="overview-chart">
        <h2>Consulta de {{ mb_strtolower($title) }}</h2><p class="muted">{{ $records->total() }} registros · Vista de consulta</p>
        @if(in_array($kind, ['equipment', 'materials']))
            @if($kind === 'equipment')<p class="muted">Stock disponible para asignación por nombre, tipo, marca y modelo.</p>@endif
            @include('operations.inventory-table')
        @else
        <div class="table-scroll"><table class="users-table"><caption class="sr-only">Listado de {{ mb_strtolower($title) }}</caption>
            <thead><tr><th scope="col">Código</th><th scope="col">{{ $kind === 'payments' ? 'Contrato' : 'Cliente' }}</th><th scope="col">{{ $kind === 'contracts' ? 'Inicio' : ($kind === 'payments' ? 'Vencimiento' : 'Programación') }}</th>@if($kind !== 'installations')<th scope="col">Monto</th>@endif<th scope="col">Estado</th></tr></thead>
            <tbody>@forelse($records as $record)<tr>
                <td>{{ $record->codigo }}</td><td>{{ $kind === 'payments' ? $record->contrato_codigo : trim($record->nombres_razon_social.' '.$record->apellidos) }}</td>
                <td>{{ ($kind === 'contracts' ? $record->fecha_inicio : ($kind === 'payments' ? $record->fecha_vencimiento : $record->fecha_programada)) ?? 'Sin programar' }}</td>
                @if($kind !== 'installations')<td>S/ {{ number_format($kind === 'contracts' ? $record->precio_contratado : $record->monto, 2) }}</td>@endif
                <td>{{ ucfirst(mb_strtolower(str_replace('_', ' ', $record->estado))) }}</td>
            </tr>@empty<tr><td colspan="{{ $kind === 'installations' ? 4 : 5 }}" class="empty-state">No hay registros disponibles.</td></tr>@endforelse</tbody>
        </table></div>
        @endif
        @include('clients.pagination', ['label' => 'Paginación de '.mb_strtolower($title)])
    </section>
</section>
@endsection
