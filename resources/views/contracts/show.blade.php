@extends('contracts.layout')
@section('contracts-title', 'Contrato '.$contract->codigo)
@section('contracts-description', 'Información y estado vigente del contrato.')
@section('contracts-crumb', $contract->codigo)
@section('contracts-content')
<div class="contracts-detail-actions">@if($contract->estado !== 'CANCELADO')<a class="action-link" href="{{ route('contracts.edit', $contract->contrato_id) }}">Actualizar contrato</a>@endif @if($contract->estado !== 'CANCELADO' && ($contract->firma_revision !== 'VERIFICADA' || !$contract->pdf_contrato_ruta))<a class="secondary" href="{{ route('contracts.draft', $contract->contrato_id) }}">Descargar contrato para firma</a>@endif @if($contract->pdf_contrato_ruta)<a class="secondary" href="{{ route('contracts.pdf', $contract->contrato_id) }}">Descargar PDF adjunto</a>@endif<a class="secondary" href="{{ route('contracts.index') }}">Volver al listado</a></div>
<section class="contracts-panel"><h2>Datos del contrato</h2><dl class="contracts-detail">
    <div><dt>Código</dt><dd>{{ $contract->codigo }}</dd></div><div><dt>Cliente</dt><dd>{{ trim($contract->nombres_razon_social.' '.$contract->apellidos) }} · {{ $contract->tipo_documento }} {{ $contract->numero_documento }}</dd></div>
    <div><dt>Plan</dt><dd>{{ $contract->plan_nombre }} · {{ $contract->numero_plan }}</dd></div><div><dt>Precio mensual contratado</dt><dd>S/ {{ number_format($contract->precio_contratado, 2) }}</dd></div>
    <div><dt>Zona de cobertura</dt><dd>{{ $contract->zona_nombre }}</dd></div><div><dt>Dirección de servicio</dt><dd>{{ $contract->direccion_servicio }}</dd></div>
    <div><dt>Fecha de inicio</dt><dd>{{ \Illuminate\Support\Carbon::parse($contract->fecha_inicio)->format('d/m/Y') }}</dd></div><div><dt>Duración</dt><dd>{{ $contract->duracion_meses }} meses</dd></div><div><dt>Finalización</dt><dd>{{ \Illuminate\Support\Carbon::parse($contract->fecha_fin)->format('d/m/Y') }}</dd></div>
    <div><dt>Responsable</dt><dd>{{ $contract->responsable_nombres }} {{ $contract->responsable_apellidos }}</dd></div><div><dt>Observaciones</dt><dd>{{ $contract->observaciones ?: 'Sin observaciones' }}</dd></div><div><dt>Estado</dt><dd><span class="state {{ $contract->estado === 'ACTIVO' ? 'active' : 'inactive' }}">{{ ucfirst(mb_strtolower($contract->estado)) }}</span></dd></div><div><dt>Documento</dt><dd>{{ $contract->pdf_contrato_ruta ? 'PDF adjunto disponible' : 'Pendiente de adjuntar' }}</dd></div><div><dt>Firma del cliente</dt><dd>{{ $contract->firma_revision === 'VERIFICADA' ? 'Trazo detectado automáticamente' : ($contract->firma_revision === 'SIN_FIRMA' ? 'No detectada en el PDF adjunto' : 'Pendiente de verificación') }}</dd></div>
    @if($contract->estado === 'CANCELADO')<div><dt>Motivo de cancelación</dt><dd>{{ $contract->motivo_cancelacion ?: 'No registrado' }}</dd></div>@endif
</dl>
@if($contract->estado === 'CANCELADO')<p class="contract-status-help">Este contrato está cancelado y se conserva únicamente para consulta. Si el cliente desea volver, utiliza el flujo de reactivación.</p>@endif
@if($contract->estado === 'PENDIENTE')<p class="contract-status-help">Descarga el contrato para firma y sube el PDF firmado desde «Actualizar contrato». La activación se realiza automáticamente al verificarlo.</p>@endif
</section>
<section class="contracts-panel">
<h2>Evidencias del contrato firmado</h2>
<p>Documentos adjuntados durante la instalación. Su registro no sustituye la verificación del PDF ni activa automáticamente el contrato.</p>
<div class="table-scroll"><table><thead><tr><th>Archivo</th><th>Observación</th><th>Fecha</th><th>Acción</th></tr></thead><tbody>
@forelse($fieldEvidences as $file)
<tr><td>{{ $file->nombre_archivo }}</td><td>{{ $file->observacion }}</td><td>{{ $file->subido_en }}</td><td><a class="action-link secondary" href="{{ route('contracts.evidence', [$contract->contrato_id, $file->evidencia_contrato_id]) }}">Descargar evidencia</a></td></tr>
@empty<tr><td colspan="4">Aún no hay evidencias adjuntadas desde instalaciones.</td></tr>@endforelse
</tbody></table></div>
@include('clients.pagination', ['records' => $fieldEvidences, 'label' => 'Paginación de evidencias del contrato'])
</section>
@endsection
