@extends('contracts.layout')
@section('contracts-title', 'Contrato '.$contract->codigo)
@section('contracts-description', 'Información y estado vigente del contrato.')
@section('contracts-crumb', $contract->codigo)
@section('contracts-content')
<div class="contracts-detail-actions"><a class="action-link" href="{{ route('contracts.edit', $contract->contrato_id) }}">Actualizar contrato</a>@if($contract->firma_revision !== 'VERIFICADA' || !$contract->pdf_contrato_ruta)<a class="secondary" href="{{ route('contracts.draft', $contract->contrato_id) }}">Descargar borrador PDF</a>@endif @if($contract->pdf_contrato_ruta)<a class="secondary" href="{{ route('contracts.pdf', $contract->contrato_id) }}">Descargar PDF adjunto</a>@endif<a class="secondary" href="{{ route('contracts.index') }}">Volver al listado</a></div>
<section class="contracts-panel"><h2>Datos del contrato</h2><dl class="contracts-detail">
    <div><dt>Código</dt><dd>{{ $contract->codigo }}</dd></div><div><dt>Cliente</dt><dd>{{ trim($contract->nombres_razon_social.' '.$contract->apellidos) }} · {{ $contract->tipo_documento }} {{ $contract->numero_documento }}</dd></div>
    <div><dt>Plan</dt><dd>{{ $contract->plan_nombre }} · {{ $contract->numero_plan }}</dd></div><div><dt>Precio mensual contratado</dt><dd>S/ {{ number_format($contract->precio_contratado, 2) }}</dd></div>
    <div><dt>Zona de cobertura</dt><dd>{{ $contract->zona_nombre }}</dd></div><div><dt>Dirección de servicio</dt><dd>{{ $contract->direccion_servicio }}</dd></div>
    <div><dt>Fecha de inicio</dt><dd>{{ \Illuminate\Support\Carbon::parse($contract->fecha_inicio)->format('d/m/Y') }}</dd></div><div><dt>Duración</dt><dd>{{ $contract->duracion_meses }} meses</dd></div><div><dt>Finalización</dt><dd>{{ \Illuminate\Support\Carbon::parse($contract->fecha_fin)->format('d/m/Y') }}</dd></div>
    <div><dt>Responsable</dt><dd>{{ $contract->responsable_nombres }} {{ $contract->responsable_apellidos }}</dd></div><div><dt>Observaciones</dt><dd>{{ $contract->observaciones ?: 'Sin observaciones' }}</dd></div><div><dt>Estado</dt><dd><span class="state {{ $contract->estado === 'ACTIVO' ? 'active' : 'inactive' }}">{{ ucfirst(mb_strtolower($contract->estado)) }}</span></dd></div><div><dt>Documento</dt><dd>{{ $contract->pdf_contrato_ruta ? 'PDF adjunto disponible' : 'Pendiente de adjuntar' }}</dd></div><div><dt>Firma del cliente</dt><dd>{{ $contract->firma_revision === 'VERIFICADA' ? 'Trazo detectado automáticamente' : ($contract->firma_revision === 'SIN_FIRMA' ? 'No detectada en el PDF adjunto' : 'Pendiente de verificación') }}</dd></div>
</dl>
@if($contract->estado === 'PENDIENTE')<p class="contract-status-help">Descarga el borrador y sube el PDF con la firma del cliente desde «Actualizar contrato». La activación se realiza automáticamente al verificarlo.</p>@endif
</section>
@endsection
