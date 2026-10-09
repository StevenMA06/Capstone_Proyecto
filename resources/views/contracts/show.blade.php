@extends('contracts.layout')
@section('contracts-title', 'Contrato '.$contract->codigo)
@section('contracts-description', 'Información y estado vigente del contrato.')
@section('contracts-crumb', $contract->codigo)
@section('contracts-content')
@can('sales.create')
@if($contract->estado === 'PENDIENTE')
<section class="contracts-panel"><h2>Registrar venta mediante contrato firmado</h2><p>Adjunta el PDF firmado para verificarlo y activar el contrato. No se modifican sus condiciones.</p>
<form method="POST" enctype="multipart/form-data" action="{{ route('sales.confirm',$contract->contrato_id) }}">@csrf
<label>Contrato firmado (PDF, máximo 10 MB)<input type="file" name="documento_pdf" accept="application/pdf" required></label><button class="action-link">Verificar firma y registrar venta</button></form></section>
@endif
@endcan
<div class="contracts-detail-actions">@if($contract->estado !== 'CANCELADO')@can('contracts.manage')
@routecan('contracts.edit')<a class="action-link" href="{{ route('contracts.edit', $contract->contrato_id) }}">Actualizar contrato</a>@endroutecan
@endcan
@endif @if($contract->estado !== 'CANCELADO' && ($contract->firma_revision !== 'VERIFICADA' || !$contract->pdf_contrato_ruta))
@routecan('contracts.draft')<a class="secondary" href="{{ route('contracts.draft', $contract->contrato_id) }}">Descargar contrato para firma</a>@endroutecan
@endif @if($contract->pdf_contrato_ruta)
@routecan('contracts.pdf')<a class="secondary" href="{{ route('contracts.pdf', $contract->contrato_id) }}">Descargar PDF adjunto</a>@endroutecan
@endif
@routecan('contracts.index')<a class="secondary" href="{{ route('contracts.index') }}">Volver al listado</a>@endroutecan
</div>
<section class="contracts-panel"><h2>Datos del contrato</h2><dl class="contracts-detail">
    <div><dt>Código</dt><dd>{{ $contract->codigo }}</dd></div><div><dt>Cliente</dt><dd>{{ trim($contract->nombres_razon_social.' '.$contract->apellidos) }} · {{ $contract->tipo_documento }} {{ $contract->numero_documento }}</dd></div>
    <div><dt>Plan</dt><dd>{{ $contract->plan_nombre }} · {{ $contract->numero_plan }}</dd></div><div><dt>Precio mensual contratado</dt><dd>S/ {{ number_format($contract->precio_contratado, 2) }}</dd></div>
    <div><dt>Zona de cobertura</dt><dd>{{ $contract->zona_nombre }}</dd></div><div><dt>Dirección de servicio</dt><dd>{{ $contract->direccion_servicio }}</dd></div>
    <div><dt>Fecha de inicio</dt><dd>{{ \Illuminate\Support\Carbon::parse($contract->fecha_inicio)->format('d/m/Y') }}</dd></div><div><dt>Duración</dt><dd>{{ $contract->duracion_meses }} meses</dd></div><div><dt>Finalización</dt><dd>{{ \Illuminate\Support\Carbon::parse($contract->fecha_fin)->format('d/m/Y') }}</dd></div>
    <div><dt>Responsable</dt><dd>{{ $contract->responsable_nombres }} {{ $contract->responsable_apellidos }}</dd></div><div><dt>Observaciones</dt><dd>{{ $contract->observaciones ?: 'Sin observaciones' }}</dd></div><div><dt>Estado</dt><dd><span class="state {{ $contract->estado === 'ACTIVO' ? 'active' : 'inactive' }}">{{ ucfirst(mb_strtolower($contract->estado)) }}</span></dd></div><div><dt>Documento</dt><dd>{{ $contract->pdf_contrato_ruta ? 'PDF adjunto disponible' : 'Pendiente de adjuntar' }}</dd></div><div><dt>Firma del cliente</dt><dd>{{ $contract->firma_revision === 'VERIFICADA' ? 'Trazo detectado automáticamente' : ($contract->firma_revision === 'SIN_FIRMA' ? 'No detectada en el PDF adjunto' : 'Pendiente de verificación') }}</dd></div>
    @if($contract->estado === 'CANCELADO')<div><dt>Motivo de cancelación</dt><dd>{{ $contract->motivo_cancelacion ?: 'No registrado' }}</dd></div>@endif
</dl>
@if($contract->estado === 'CANCELADO')<p class="contract-status-help">Este contrato está cancelado y se conserva únicamente para consulta. Si el cliente desea volver, utiliza el flujo de reactivación.</p>@endif
@if($contract->estado === 'PENDIENTE')<p class="contract-status-help">Cuando el técnico entregue el contrato físico firmado en la empresa, escanéalo y sube el PDF desde «Actualizar contrato» o «Verificar firma y registrar venta». La firma se comprueba aquí y el contrato se activa automáticamente al validarla.</p>@endif
</section>
<section class="contracts-panel">
<h2>Archivos de campo anteriores</h2>
<p>Consulta las imágenes del contrato que se hayan cargado antes de este cambio de flujo. El documento válido se escanea y verifica en Contratos.</p>
<div class="table-scroll"><table><thead><tr><th>Archivo</th><th>Observación</th><th>Fecha</th><th>Acción</th></tr></thead><tbody>
@forelse($fieldEvidences as $file)
<tr><td>{{ $file->nombre_archivo }}</td><td>{{ $file->observacion }}</td><td>{{ $file->subido_en }}</td><td>
@routecan('contracts.evidence')<a class="action-link secondary" href="{{ route('contracts.evidence', [$contract->contrato_id, $file->evidencia_contrato_id]) }}">Descargar evidencia</a>@endroutecan
</td></tr>
@empty<tr><td colspan="4">Aún no hay evidencias adjuntadas desde instalaciones.</td></tr>@endforelse
</tbody></table></div>
@include('clients.pagination', ['records' => $fieldEvidences, 'label' => 'Paginación de evidencias del contrato'])
</section>
@endsection
