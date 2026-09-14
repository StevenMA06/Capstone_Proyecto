@extends('layouts.management')
@section('title', 'Instalación '.$installation->codigo)
@section('workspace-content')
<a href="{{ route('technician.index') }}">Volver al panel técnico</a>
<h1>Instalación {{ $installation->codigo }}</h1><p>{{ ucfirst(mb_strtolower(str_replace('_', ' ', $installation->estado))) }} · {{ $installation->fecha_programada ?? 'Sin programar' }}</p>
<p>{{ $installation->observaciones }}</p>
@if(session('status'))<p class="success" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="field-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<h2>Registrar evidencia</h2>
<form method="POST" action="{{ route('technician.evidence.store', $installation->pedido_id) }}" enctype="multipart/form-data" class="account-form">
@csrf
<div><label for="evidence">Fotografía *</label><input id="evidence" type="file" name="evidence" accept="image/jpeg,image/png,image/webp" required><small>JPG, PNG o WEBP · Máximo 5 MB</small></div>
<div><label for="descripcion">Descripción</label><textarea id="descripcion" name="descripcion" maxlength="500">{{ old('descripcion') }}</textarea></div>
<div><button type="submit" class="action-link">Guardar evidencia</button></div>
</form>
<h2>Evidencias registradas</h2>
<div class="table-scroll"><table class="users-table"><thead><tr><th>Archivo</th><th>Descripción</th><th>Fecha</th><th>Acción</th></tr></thead><tbody>
@forelse($evidences as $evidence)<tr><td>{{ $evidence->nombre_archivo ?? 'Fotografía' }}</td><td>{{ $evidence->descripcion }}</td><td>{{ $evidence->subido_en }}</td><td><a href="{{ route('technician.evidence.download', [$installation->pedido_id, $evidence->evidencia_id]) }}">Descargar evidencia</a></td></tr>
@empty<tr><td colspan="4">Aún no hay evidencias.</td></tr>@endforelse
</tbody></table></div>
@include('clients.pagination', ['records' => $evidences, 'label' => 'Paginación de evidencias'])
@endsection
