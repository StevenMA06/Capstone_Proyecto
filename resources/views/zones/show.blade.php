@extends('zones.layout')
@section('zones-content')
<div class="management-toolbar zone-detail-toolbar"><div><h2>{{ $zone->nombre }}</h2><p>{{ $zone->codigo }}</p></div>@can('manage-zones')@can('zones.manage')
@routecan('zones.edit')<a class="action-link" href="{{ route('zones.edit', $zone) }}"><i aria-hidden="true"></i> Editar zona</a>@endroutecan
@endcan
@endcan</div>
<div class="zone-detail-grid">
    <section class="zone-detail-card" aria-labelledby="zone-information"><h3 id="zone-information">Información de cobertura</h3><dl class="account-detail">
        <div><dt>Código</dt><dd>{{ $zone->codigo }}</dd></div><div><dt>Nombre</dt><dd>{{ $zone->nombre }}</dd></div>
        <div><dt>Distrito</dt><dd>{{ $zone->distrito }}</dd></div><div><dt>Provincia</dt><dd>{{ $zone->provincia ?: 'No registrada' }}</dd></div>
        <div><dt>Departamento</dt><dd>{{ $zone->departamento }}</dd></div><div><dt>Estado</dt><dd><span class="state {{ $zone->estado === 'ACTIVA' ? 'active' : 'inactive' }}">{{ $zone->estado === 'ACTIVA' ? 'Activa' : 'Inactiva' }}</span></dd></div>
        <div class="detail-wide"><dt>Descripción</dt><dd>{{ $zone->descripcion ?: 'Sin descripción adicional' }}</dd></div>
        <div><dt>Registrada por</dt><dd>{{ $zone->creator?->name ?? '—' }}</dd></div><div><dt>Última actualización</dt><dd>{{ $zone->actualizado_en }}</dd></div>
    </dl></section>
    <section class="zone-plans-card" aria-labelledby="zone-plans-title"><h3 id="zone-plans-title">Planes asociados</h3><p>Solo los planes activos y con asociación activa se ofrecen en contratos nuevos.</p>
        <ul>@forelse($zone->plans as $plan)<li><div><strong>{{ $plan->nombre }}</strong><span>{{ $plan->numero_plan }} · S/ {{ number_format($plan->precio_mensual, 2) }}</span></div><span class="state {{ $plan->estado === 'ACTIVO' && $plan->pivot->estado === 'ACTIVO' ? 'active' : 'inactive' }}">{{ $plan->estado === 'ACTIVO' && $plan->pivot->estado === 'ACTIVO' ? 'Disponible' : 'No disponible' }}</span></li>@empty<li class="empty-selection">No hay planes asociados.</li>@endforelse</ul>
    </section>
</div>
@can('manage-zones')<div class="status-box"><div><h3>{{ $zone->estado === 'ACTIVA' ? 'Inhabilitar zona' : 'Activar zona' }}</h3><p>La información y los contratos existentes se conservarán. Una zona inactiva deja de aparecer en el registro de contratos.</p></div>@can('zones.manage')
@routecan('zones.status')<form method="POST" action="{{ route('zones.status', $zone) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $zone->estado === 'ACTIVA' ? '0' : '1' }}"><button class="secondary" type="submit">{{ $zone->estado === 'ACTIVA' ? 'Inhabilitar' : 'Activar' }}</button></form>@endroutecan
@endcan</div>@endcan

@routecan('zones.index')<a class="secondary" href="{{ route('zones.index') }}">Volver al listado de zonas</a>@endroutecan

@endsection
