@extends('contracts.layout')
@section('contracts-title', 'Contrato '.$contract->codigo)
@section('contracts-description', 'Información y estado vigente del contrato.')
@section('contracts-crumb', $contract->codigo)
@section('contracts-content')
@php($pendingCancellation = $cancellationHistory->firstWhere('estado', 'PENDIENTE'))
<div class="contracts-detail-actions">
    @if($contract->estado !== 'CANCELADO' && ! $pendingCancellation)
        @can('contracts.manage')
            @routecan('contracts.edit')<a class="action-link" href="{{ route('contracts.edit', $contract->contrato_id) }}">Actualizar contrato</a>@endroutecan
        @endcan
    @endif
    @if($contract->estado !== 'CANCELADO' && ($contract->firma_revision !== 'VERIFICADA' || !$contract->pdf_contrato_ruta))
        @routecan('contracts.draft')<a class="secondary" href="{{ route('contracts.draft', $contract->contrato_id) }}">Descargar contrato para firma</a>@endroutecan
    @endif
    @if($contract->pdf_contrato_ruta)
        @routecan('contracts.pdf')<a class="secondary" href="{{ route('contracts.pdf', $contract->contrato_id) }}">Descargar PDF adjunto</a>@endroutecan
    @endif
    @routecan('contracts.index')<a class="secondary" href="{{ route('contracts.index') }}">Volver al listado</a>@endroutecan
</div>
<section class="contracts-panel"><h2>Datos del contrato</h2><dl class="contracts-detail">
    <div><dt>Código</dt><dd>{{ $contract->codigo }}</dd></div><div><dt>Cliente</dt><dd>{{ trim($contract->nombres_razon_social.' '.$contract->apellidos) }} · {{ $contract->tipo_documento }} {{ $contract->numero_documento }}</dd></div>
    <div><dt>Plan</dt><dd>{{ $contract->plan_nombre }} · {{ $contract->numero_plan }}</dd></div><div><dt>Precio mensual contratado</dt><dd>S/ {{ number_format($contract->precio_contratado, 2) }}</dd></div>
    <div><dt>Zona de cobertura</dt><dd>{{ $contract->zona_nombre }}</dd></div><div><dt>Dirección de servicio</dt><dd>{{ $contract->direccion_servicio }}</dd></div>
    <div><dt>Fecha de inicio</dt><dd>{{ \Illuminate\Support\Carbon::parse($contract->fecha_inicio)->format('d/m/Y') }}</dd></div><div><dt>Duración</dt><dd>{{ $contract->duracion_meses }} meses</dd></div><div><dt>Finalización</dt><dd>{{ \Illuminate\Support\Carbon::parse($contract->fecha_fin)->format('d/m/Y') }}</dd></div>
    <div><dt>Responsable</dt><dd>{{ $contract->responsable_nombres }} {{ $contract->responsable_apellidos }}</dd></div><div><dt>Observaciones</dt><dd>{{ $contract->observaciones ?: 'Sin observaciones' }}</dd></div>
    <div><dt>Estado</dt><dd><span class="state {{ $contract->estado === 'ACTIVO' ? 'active' : 'inactive' }}">{{ ucfirst(mb_strtolower($contract->estado)) }}</span></dd></div>
    <div><dt>Documento</dt><dd>{{ $contract->pdf_contrato_ruta ? 'PDF adjunto disponible' : 'Pendiente de adjuntar' }}</dd></div><div><dt>Firma del cliente</dt><dd>{{ $contract->firma_revision === 'VERIFICADA' ? 'Trazo detectado automáticamente' : ($contract->firma_revision === 'SIN_FIRMA' ? 'No detectada en el PDF adjunto' : 'Pendiente de verificación') }}</dd></div>
    @if($contract->estado === 'CANCELADO')<div><dt>Motivo de cancelación</dt><dd>{{ $contract->motivo_cancelacion ?: 'No registrado' }}</dd></div>@endif
</dl>
@if($contract->estado === 'CANCELADO')<p class="contract-status-help">Este contrato está cancelado y se conserva únicamente para consulta. Si el cliente desea volver, utiliza el flujo de reactivación.</p>@endif
@if($contract->estado === 'PENDIENTE')<p class="contract-status-help">Cuando el técnico entregue el contrato físico firmado y la instalación esté terminada, sube el PDF desde «Actualizar contrato». El sistema validará la firma y cerrará el flujo cuando confirme también las evidencias en Drive.</p>@endif
</section>
@if($cancellationWorkflowReady && (auth()->user()->hasPermission('contracts.cancel.request') || auth()->user()->hasPermission('contracts.cancel.approve') || $cancellationHistory->isNotEmpty()))
<section class="contracts-panel cancellation-audit-panel" aria-labelledby="cancellation-audit-title">
    <div class="cancellation-audit-heading"><div><p class="contracts-eyebrow">CONTROL Y TRAZABILIDAD</p><h2 id="cancellation-audit-title">Solicitudes de cancelación</h2></div>@if($pendingCancellation)<span class="cancellation-state is-pending">Pendiente de revisión</span>@endif</div>
    <p class="contract-status-help">Revisa la evidencia antes de resolver una solicitud. El contrato sigue vigente hasta que otra cuenta autorizada apruebe la cancelación.</p>
    @if($cancellationHistory->isNotEmpty())
        <div class="cancellation-history">
        @foreach($cancellationHistory as $item)
            @php($stateLabel = ['PENDIENTE' => 'Pendiente', 'APROBADA' => 'Aprobada', 'RECHAZADA' => 'Rechazada', 'CERRADA' => 'Cerrada automáticamente'][$item->estado] ?? $item->estado)
            <article class="cancellation-history-item">
                <div class="cancellation-history-top"><strong>{{ $item->categoria }}</strong><span class="cancellation-state {{ $item->estado === 'APROBADA' ? 'is-approved' : ($item->estado === 'RECHAZADA' || $item->estado === 'CERRADA' ? 'is-rejected' : 'is-pending') }}">{{ $stateLabel }}</span></div>
                <p>{{ $item->explicacion }}</p>
                @php($requestedAt = \Illuminate\Support\Carbon::parse($item->solicitado_en))
                <dl><div><dt>Solicitó</dt><dd>{{ $item->solicitante_usuario }}</dd></div><div><dt>Fecha</dt><dd>{{ $requestedAt->format('d/m/Y') }}</dd></div><div><dt>Hora</dt><dd>{{ $requestedAt->format('H:i') }}</dd></div>
                    <div><dt>Resolvió</dt><dd>{{ $item->revisor_usuario ? $item->revisor_usuario.' · '.\Illuminate\Support\Carbon::parse($item->resuelto_en)->format('d/m/Y · H:i') : ($item->estado === 'CERRADA' ? 'Sistema · '.\Illuminate\Support\Carbon::parse($item->resuelto_en)->format('d/m/Y · H:i') : 'Pendiente') }}</dd></div></dl>
                <div class="cancellation-history-actions">@routecan('contracts.cancellation.evidence')<a class="secondary" href="{{ route('contracts.cancellation.evidence', [$contract->contrato_id, $item->solicitud_cancelacion_id]) }}">Ver evidencia · {{ $item->evidencia_nombre }}</a>@endroutecan</div>
                @if($item->decision_motivo)<p class="cancellation-decision-note"><strong>Decisión:</strong> {{ $item->decision_motivo }}</p>@endif
                @if($item->estado === 'PENDIENTE' && auth()->user()->hasPermission('contracts.cancel.approve'))
                    @if((int) $item->solicitado_por !== (int) auth()->id())
                <div class="cancellation-review-actions">
                        <form class="cancellation-approve-card" method="POST" action="{{ route('contracts.cancellation.approve', [$contract->contrato_id, $item->solicitud_cancelacion_id]) }}">@csrf<div><strong>Si apruebas</strong><p>El contrato y cualquier pedido de instalación pendiente asociado se cancelarán.</p></div><button class="action-link" type="submit">Aprobar y cancelar</button></form>
                        <form class="cancellation-reject-card" method="POST" action="{{ route('contracts.cancellation.reject', [$contract->contrato_id, $item->solicitud_cancelacion_id]) }}">@csrf<div><strong>Si rechazas</strong><p>El contrato seguirá vigente. Registra el motivo para conservar la decisión en el historial.</p></div><label for="decision_motivo_{{ $item->solicitud_cancelacion_id }}">Motivo del rechazo *</label><textarea id="decision_motivo_{{ $item->solicitud_cancelacion_id }}" name="decision_motivo" minlength="10" maxlength="500" required placeholder="Explica qué debe corregirse o por qué no procede"></textarea><button class="secondary" type="submit">Rechazar solicitud</button>@error('decision_motivo')<small class="field-error">{{ $message }}</small>@enderror</form>
                    </div>
                    @else<p class="cancellation-review-lock">Tu cuenta inició esta solicitud. Debe revisarla otra persona autorizada.</p>@endif
                @endif
            </article>
        @endforeach
        </div>
    @endif
    @if(auth()->user()->hasPermission('contracts.cancel.request') && in_array($contract->estado, ['PENDIENTE', 'ACTIVO'], true) && ! $pendingCancellation)
        @include('contracts._cancellation-request-form')
    @endif
</section>
@endif
@if($showFieldEvidenceHistory && $fieldEvidences?->total() > 0)
<section class="contracts-panel">
<h2>Archivos de campo anteriores</h2>
<p>Estos archivos se muestran después de completar la instalación y confirmar en Drive la firma del contrato y la fotografía de instalación.</p>
<div class="table-scroll"><table><thead><tr><th>Archivo</th><th>Observación</th><th>Fecha</th><th>Acción</th></tr></thead><tbody>
@forelse($fieldEvidences as $file)
<tr><td>{{ $file->nombre_archivo }}</td><td>{{ $file->observacion }}</td><td>{{ $file->subido_en }}</td><td>
@routecan('contracts.evidence')<a class="action-link secondary" href="{{ route('contracts.evidence', [$contract->contrato_id, $file->evidencia_contrato_id]) }}">Descargar evidencia</a>@endroutecan
</td></tr>
@empty<tr><td colspan="4">Aún no hay evidencias adjuntadas desde instalaciones.</td></tr>@endforelse
</tbody></table></div>
@include('clients.pagination', ['records' => $fieldEvidences, 'label' => 'Paginación de evidencias del contrato'])
</section>
@endif
@endsection
