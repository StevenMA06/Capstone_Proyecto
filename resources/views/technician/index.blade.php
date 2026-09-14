@extends('layouts.management')
@section('title', 'Panel técnico')
@section('workspace-content')
<h1>Panel técnico</h1><p class="muted">{{ auth()->user()->isManager() ? 'Consulta de instalaciones y evidencias del equipo técnico.' : 'Consulta tus instalaciones asignadas y registra sus evidencias.' }}</p>
<div class="table-scroll"><table class="users-table"><thead><tr><th>Instalación</th><th>Programación</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($records as $record)<tr><td>{{ $record->codigo }}</td><td>{{ $record->fecha_programada ?? 'Sin programar' }}</td><td>{{ ucfirst(mb_strtolower(str_replace('_', ' ', $record->estado))) }}</td><td><a href="{{ route('technician.show', $record->pedido_id) }}">Ver instalación y evidencias</a></td></tr>
@empty<tr><td colspan="4">No hay instalaciones asignadas.</td></tr>@endforelse
</tbody></table></div>
@include('clients.pagination', ['label' => 'Paginación de instalaciones asignadas'])
@endsection
