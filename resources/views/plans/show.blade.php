@extends('plans.layout')
@section('plans-content')
<div class="management-toolbar"><h2>Ficha del plan {{ $plan->numero_plan }}</h2>@can('manage-plans')<a class="action-link" href="{{ route('plans.edit', $plan) }}">Editar plan</a>@endcan</div>
<dl class="account-detail">
    <div><dt>Identificador</dt><dd>#{{ $plan->plan_id }}</dd></div><div><dt>Número de plan</dt><dd>{{ $plan->numero_plan }}</dd></div>
    <div><dt>Nombre</dt><dd>{{ $plan->nombre }}</dd></div><div><dt>Tipo de servicio</dt><dd>{{ $plan->tipo_servicio }}</dd></div>
    <div><dt>Velocidad</dt><dd>{{ $plan->velocidad_mbps ? $plan->velocidad_mbps.' Mbps' : 'No aplica' }}</dd></div><div><dt>Precio mensual</dt><dd>S/ {{ number_format($plan->precio_mensual, 2) }}</dd></div>
    <div><dt>Descripción</dt><dd style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $plan->descripcion ?? 'Sin descripción' }}</dd></div><div><dt>Condiciones</dt><dd style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $plan->condiciones ?? 'Sin condiciones adicionales' }}</dd></div>
    <div><dt>Estado</dt><dd>{{ $plan->estado === 'ACTIVO' ? 'Activo' : 'Inactivo' }}</dd></div><div><dt>Registrado por</dt><dd>{{ $plan->creator?->name ?? '—' }}</dd></div>
    <div><dt>Fecha de registro</dt><dd>{{ $plan->creado_en }}</dd></div><div><dt>Última actualización</dt><dd>{{ $plan->actualizado_en }}</dd></div>
</dl>
@can('manage-plans')
<div class="status-box"><div><h3>{{ $plan->estado === 'ACTIVO' ? 'Inhabilitar plan' : 'Activar plan' }}</h3><p>Los planes inactivos se conservan para consulta y no se pueden seleccionar en contratos nuevos. Los contratos existentes mantienen su precio contratado.</p></div>
<form method="POST" action="{{ route('plans.status', $plan) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $plan->estado === 'ACTIVO' ? '0' : '1' }}"><button type="submit" class="secondary">{{ $plan->estado === 'ACTIVO' ? 'Inhabilitar' : 'Activar' }}</button></form></div>
@endcan
<a href="{{ route('plans.index') }}">Volver al listado de planes</a>
@endsection
