@extends('contracts.layout')
@section('contracts-title', 'Solicitudes de cancelación')
@section('contracts-description', 'Revisa las solicitudes recibidas y su historial de decisiones.')
@section('contracts-crumb', 'Solicitudes de cancelación')
@section('contracts-content')
@include('contracts._section-nav', ['active' => 'cancellations'])
<section class="contracts-panel cancellation-queue-panel" aria-labelledby="cancellation-queue-title">
    <header class="cancellation-queue-heading">
        <div><p class="contracts-eyebrow">REVISIÓN Y TRAZABILIDAD</p><h2 id="cancellation-queue-title">Bandeja de solicitudes</h2><p class="muted">Revisa el motivo y respaldo antes de aprobar o rechazar. Las decisiones quedan registradas en el contrato.</p></div>
        <span class="cancellation-state is-pending">{{ $pendingCancellationCount }} pendientes</span>
    </header>
    <form method="GET" action="{{ route('contracts.cancellations') }}" class="cancellation-queue-filters">
        <div><label for="cancellation-search">Buscar solicitud</label><input id="cancellation-search" name="q" value="{{ request('q') }}" maxlength="180" placeholder="Código, cliente, usuario o motivo"></div>
        <div><label for="cancellation-state">Estado</label><select id="cancellation-state" name="estado"><option value="">Todos los estados</option>@foreach($states as $code => $label)<option value="{{ $code }}" @selected(request('estado') === $code)>{{ $label }}</option>@endforeach</select></div>
        <div class="cancellation-queue-filter-actions"><button class="action-link" type="submit">Buscar</button><a class="secondary" href="{{ route('contracts.cancellations') }}">Limpiar</a></div>
    </form>
    <div class="cancellation-queue-list">
        @forelse($requests as $item)
            @php($stateLabel = $states[$item->estado] ?? $item->estado)
            <article class="cancellation-queue-card">
                <div class="cancellation-queue-card-header"><div><small>CONTRATO {{ $item->codigo }}</small><h3>{{ trim($item->nombres_razon_social.' '.$item->apellidos) }}</h3></div><span class="cancellation-state {{ $item->estado === 'APROBADA' ? 'is-approved' : (in_array($item->estado, ['RECHAZADA', 'CERRADA'], true) ? 'is-rejected' : 'is-pending') }}">{{ $stateLabel }}</span></div>
                @php($requestedAt = \Illuminate\Support\Carbon::parse($item->solicitado_en))
                <dl class="cancellation-queue-facts">
                    <div><dt>Motivo de cancelación</dt><dd>{{ $item->categoria }}</dd></div>
                    <div><dt>Solicitó</dt><dd>{{ $item->solicitante_usuario }}</dd></div>
                    <div><dt>Fecha</dt><dd>{{ $requestedAt->format('d/m/Y') }}</dd></div>
                    <div><dt>Hora</dt><dd>{{ $requestedAt->format('H:i') }}</dd></div>
                    <div><dt>Revisó</dt><dd>{{ $item->revisor_usuario ?: ($item->estado === 'CERRADA' ? 'Sistema' : 'Pendiente') }}</dd></div>
                    @if($item->revisor_usuario)
                        <div><dt>Rol al decidir</dt><dd>{{ $item->resuelto_roles ?: 'Sin registro histórico' }}</dd></div>
                        @if(! $item->resuelto_roles && $item->revisor_roles_actuales)
                            <div><dt>Roles actuales</dt><dd>{{ $item->revisor_roles_actuales }}</dd></div>
                        @endif
                    @endif
                </dl>
                <div class="cancellation-reason-detail"><span>Detalle del motivo</span><p class="cancellation-queue-explanation">{{ $item->explicacion }}</p></div>
                @if($item->estado === 'APROBADA')<p class="cancellation-decision-note"><strong>Decisión:</strong> Aprobada por {{ $item->revisor_usuario }}.</p>@elseif($item->decision_motivo)<p class="cancellation-decision-note"><strong>Decisión:</strong> {{ $item->decision_motivo }}</p>@endif
                <div class="cancellation-queue-actions">
                    @routecan('contracts.cancellation.evidence')<a class="secondary" href="{{ route('contracts.cancellation.evidence', [$item->contrato_id, $item->solicitud_cancelacion_id]) }}">Ver evidencia · {{ $item->evidencia_nombre }}</a>@endroutecan
                    <a class="action-link secondary" href="{{ route('contracts.show', $item->contrato_id) }}#cancellation-audit-title">{{ $item->estado === 'PENDIENTE' ? 'Revisar y decidir' : 'Ver contrato e historial' }} <span aria-hidden="true">→</span></a>
                </div>
            </article>
        @empty
            <div class="cancellation-queue-empty"><span aria-hidden="true">✓</span><strong>No hay solicitudes con estos filtros</strong><p>Prueba con otro estado o término de búsqueda.</p></div>
        @endforelse
    </div>
    @if($requests instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
        @include('clients.pagination', ['records' => $requests, 'label' => 'Paginación de solicitudes de cancelación'])
    @endif
</section>
@endsection
