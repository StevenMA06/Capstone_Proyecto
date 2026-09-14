@extends('layouts.management')
@section('title', 'Planes de servicio')
@section('workspace-content')
<h1>Planes de servicio</h1><p class="muted">Consulta de planes disponibles para los contratos.</p>
<div class="table-scroll"><table class="users-table"><thead><tr><th>Plan</th><th>Servicio</th><th>Precio mensual</th><th>Estado</th></tr></thead><tbody>
@forelse($records as $plan)<tr><td>{{ $plan->nombre }}<small class="table-subtitle">{{ $plan->numero_plan }}</small></td><td>{{ $plan->tipo_servicio }}</td><td>S/ {{ number_format($plan->precio_mensual, 2) }}</td><td>{{ ucfirst(mb_strtolower($plan->estado)) }}</td></tr>
@empty<tr><td colspan="4">No hay planes registrados.</td></tr>@endforelse
</tbody></table></div>
@include('clients.pagination', ['label' => 'Paginación de planes'])
@endsection
