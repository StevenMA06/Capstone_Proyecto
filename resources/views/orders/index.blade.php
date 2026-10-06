@extends('layouts.management')
@section('title','Pedidos de instalación')
@section('workspace-content')
<header class="module-heading"><h1>Pedidos de instalación</h1><p>Consulta la programación y las asignaciones autorizadas para tu cuenta.</p></header>
@if(session('status'))<p class="notice success" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="notice" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@can('orders.create')
<details class="client-list-panel"><summary>Registrar pedido</summary><form class="account-form" method="POST" action="{{ route('orders.store') }}">@csrf
<label>Contrato activo<select name="contrato_id" required><option value="">Selecciona un contrato</option>@foreach($contracts as $contract)<option value="{{ $contract->contrato_id }}">{{ $contract->codigo }}</option>@endforeach</select></label>
<label>Observaciones<textarea name="observaciones" maxlength="500">{{ old('observaciones') }}</textarea></label><button class="action-link">Registrar pedido</button></form></details>
@endcan
<div class="client-list-panel"><div class="table-scroll"><table class="users-table"><thead><tr><th>Pedido / contrato</th><th>Dirección</th><th>Técnico</th><th>Programación</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($records as $record)<tr><td>{{ $record->codigo }}<br>{{ $record->contrato_codigo }}</td><td>{{ $record->direccion_servicio }}</td><td>{{ $record->tecnico_nombre ?: 'Sin asignar' }}</td><td>{{ $record->fecha_programada ?: 'Sin programar' }}</td><td>{{ str_replace('_',' ',$record->estado) }}</td><td>
@if(in_array($record->estado,['PENDIENTE','PROGRAMADO','REPROGRAMADO']))
@can('orders.schedule')<details><summary>Programar / asignar</summary><form class="account-form" method="POST" action="{{ route('orders.schedule',$record->pedido_id) }}">@csrf @method('PATCH')
<label>Técnico<select name="tecnico_id" required><option value="">Selecciona</option>@foreach($technicians as $technician)<option value="{{ $technician->getKey() }}" @selected($record->tecnico_id==$technician->getKey())>{{ $technician->full_name }}</option>@endforeach</select></label>
<label>Fecha y hora (Perú)<input type="datetime-local" name="fecha_programada" required></label><label>Motivo de reprogramación<textarea name="motivo_reprogramacion" maxlength="500" @required($record->fecha_programada)></textarea></label><button class="action-link">Guardar programación</button></form></details>@endcan
@can('orders.cancel')<form method="POST" action="{{ route('orders.cancel',$record->pedido_id) }}">@csrf @method('PATCH')<button class="secondary">Cancelar pedido</button></form>@endcan
@endif
</td></tr>@empty<tr><td colspan="6">No hay pedidos disponibles.</td></tr>@endforelse
</tbody></table></div>@include('clients.pagination',['records'=>$records,'label'=>'Paginación de pedidos'])</div>
@endsection
