@extends('layouts.management')
@section('title', 'Instalación '.$installation->codigo)
@section('workspace_class', 'record-tables')
@push('styles')<link rel="stylesheet" href="{{ asset('css/installations.css') }}">@endpush
@section('workspace-content')
@php($running = $installation->estado === 'EN_PROCESO' && $execution?->inicio_en && $execution->estado === 'EN_PROCESO')
<a class="action-link secondary" href="{{ route('technician.index') }}">← Volver al panel técnico</a>
<header class="installation-heading">
    <div><p class="eyebrow">EJECUCIÓN DE INSTALACIONES</p><h1>Ficha #{{ $installation->pedido_id }} · {{ $installation->codigo }}</h1>
    <p class="muted">{{ ucfirst(mb_strtolower(str_replace('_', ' ', $installation->estado))) }} · Programación: {{ $installation->fecha_programada ?? 'Sin programar' }}</p></div>
    @if(in_array($installation->estado, ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO']) || ($installation->estado === 'EN_PROCESO' && ! $execution?->inicio_en))
    <form method="POST" action="{{ route('technician.start', $installation->pedido_id) }}">@csrf<button class="action-link" type="submit">Iniciar instalación</button></form>
    @endif
</header>
@if(session('status'))<div class="success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="field-error" role="alert"><strong>No se pudo guardar:</strong>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="installation-panel">
    <h2>Información para el trabajo</h2>
    <dl class="installation-details">
        <div><dt>Cliente</dt><dd>{{ $client->nombres_razon_social ?? '' }} {{ $client->apellidos ?? '' }}</dd></div>
        <div><dt>Teléfono</dt><dd>{{ $client->telefono ?? 'No registrado' }}</dd></div>
        <div><dt>Dirección del servicio</dt><dd>{{ $contract->direccion_servicio ?? 'No registrada' }}</dd></div>
        <div><dt>Referencia</dt><dd>{{ $client->referencia_direccion ?? 'Sin referencia' }}</dd></div>
        <div><dt>Contrato / plan</dt><dd>{{ $contract->codigo ?? '—' }} · {{ $contract->plan_nombre ?? '—' }}</dd></div>
        <div><dt>Técnico asignado</dt><dd>{{ $technician ? $technician->nombres.' '.$technician->apellidos : 'Sin asignar' }}</dd></div>
        <div><dt>Inicio</dt><dd>{{ $execution?->inicio_en ?? 'Sin iniciar' }}</dd></div>
        <div><dt>Finalización</dt><dd>{{ $execution?->fin_en ?? 'Sin finalizar' }}</dd></div>
    </dl>
    @if($installation->observaciones)<p>{{ $installation->observaciones }}</p>@endif
</section>
<div class="installation-grid">
<section class="installation-panel">
    <h2>Equipos utilizados</h2>
    @if($running)
    <form method="POST" action="{{ route('technician.equipment', $installation->pedido_id) }}" class="installation-form">
        @csrf
        <label for="equipo_id">Equipo disponible o asignado a esta instalación</label>
        <select id="equipo_id" name="equipo_id" required><option value="">Selecciona un equipo</option>
            @foreach($equipment as $item)<option value="{{ $item->equipo_id }}" @selected(old('equipo_id') == $item->equipo_id)>{{ $item->codigo }} · {{ $item->nombre }}{{ $item->numero_serie ? ' · '.$item->numero_serie : '' }}</option>@endforeach
        </select>
        <button type="submit" class="action-link">Registrar equipo utilizado</button>
    </form>
    @endif
    <div class="table-scroll"><table class="users-table"><thead><tr><th>Equipo</th><th>Serie</th><th>Estado</th></tr></thead><tbody>
    @forelse($usedEquipment as $item)<tr><td>{{ $item->nombre }}<small>{{ $item->codigo }}</small></td><td>{{ $item->numero_serie ?? '—' }}</td><td>{{ ucfirst(mb_strtolower($item->estado_asignacion)) }}</td></tr>
    @empty<tr><td colspan="3">Sin equipos registrados.</td></tr>@endforelse
    </tbody></table></div>
</section>
<section class="installation-panel">
    <h2>Materiales utilizados</h2>
    @if($running)
    <form method="POST" action="{{ route('technician.material', $installation->pedido_id) }}" class="installation-form">
        @csrf
        <label for="material_id">Material</label><select id="material_id" name="material_id" required><option value="">Selecciona un material</option>
        @foreach($materials as $item)<option value="{{ $item->material_id }}" @selected(old('material_id') == $item->material_id)>{{ $item->nombre }} · Stock: {{ \App\Support\InventoryQuantity::format($item->stock_actual) }} {{ $item->unidad_medida }}</option>@endforeach</select>
        <label for="cantidad_utilizada">Total utilizado en esta instalación</label>
        <input id="cantidad_utilizada" name="cantidad_utilizada" type="number" min="0.001" max="999999999.999" step="0.001" value="{{ old('cantidad_utilizada') }}" required aria-describedby="consumption-help">
        <small id="consumption-help">Indica el total acumulado del material. Si ya registraste 5 y utilizaste 2 más, ingresa 7. Solo se descontará la diferencia.</small>
        <button type="submit" class="action-link">Guardar consumo</button>
    </form>
    @endif
    <div class="table-scroll"><table class="users-table"><thead><tr><th>Material</th><th>Total utilizado</th></tr></thead><tbody>
    @forelse($usedMaterials as $item)<tr><td>{{ $item->nombre }}</td><td>{{ \App\Support\InventoryQuantity::format($item->cantidad_utilizada) }} {{ $item->unidad_medida }}</td></tr>
    @empty<tr><td colspan="2">Sin materiales registrados.</td></tr>@endforelse
    </tbody></table></div>
</section>
</div>
<section class="installation-panel">
    <h2>Observación técnica</h2>
    @if($running)<form method="POST" action="{{ route('technician.observation', $installation->pedido_id) }}" class="installation-form">@csrf
    <label for="observacion_tecnica">Detalle del trabajo</label>
    <textarea id="observacion_tecnica" name="observacion_tecnica" rows="4" maxlength="5000">{{ old('observacion_tecnica', $execution?->observacion_tecnica) }}</textarea>
    <button type="submit" class="action-link">Guardar observación</button></form>
    @else<p class="installation-notes">{{ $execution?->observacion_tecnica ?? 'Sin observaciones técnicas.' }}</p>@endif
</section>
<div class="installation-grid">
@foreach([false, true] as $isContract)
<section class="installation-panel">
    <h2>{{ $isContract ? 'Evidencia del contrato firmado' : 'Evidencias fotográficas' }}</h2>
    @if($running)
    <form method="POST" action="{{ route($isContract ? 'technician.contractEvidence' : 'technician.evidence.store', $installation->pedido_id) }}" enctype="multipart/form-data" class="installation-form">
        @csrf
        <label for="file-{{ (int) $isContract }}">{{ $isContract ? 'Contrato firmado' : 'Fotografía' }}</label>
        <input id="file-{{ (int) $isContract }}" type="file" name="evidence" accept="{{ $isContract ? 'application/pdf,' : '' }}image/jpeg,image/png,image/webp" required>
        <small>{{ $isContract ? 'PDF, JPG, PNG o WEBP' : 'JPG, PNG o WEBP' }} · Máximo 5 MB</small>
        <label for="description-{{ (int) $isContract }}">Descripción</label>
        <textarea id="description-{{ (int) $isContract }}" name="descripcion" maxlength="500" rows="2">{{ old('descripcion') }}</textarea>
        @if($isContract)<small>Se vincula al contrato {{ $contract->codigo }}. La carga no verifica una firma digital ni activa el contrato automáticamente.</small>@endif
        <button type="submit" class="action-link">Guardar {{ $isContract ? 'contrato firmado' : 'fotografía' }}</button>
    </form>
    @endif
    @php($files = $isContract ? $contractEvidences : $evidences)
    <ul class="installation-files">
    @forelse($files as $file)
    <li><a class="action-link secondary" href="{{ route($isContract ? 'technician.contract.download' : 'technician.evidence.download', [$installation->pedido_id, $isContract ? $file->evidencia_contrato_id : $file->evidencia_id]) }}">Descargar {{ $file->nombre_archivo ?? 'evidencia' }}</a>
    <p>{{ $isContract ? $file->observacion : $file->descripcion }}</p><small>{{ $file->subido_en }}</small></li>
    @empty<li>Aún no hay evidencias.</li>@endforelse
    </ul>
    @include('clients.pagination', ['records' => $files, 'label' => $isContract ? 'Paginación de contratos firmados' : 'Paginación de fotografías'])
</section>
@endforeach
</div>
@if($running)
<section class="installation-panel installation-finish">
    <h2>Finalizar instalación</h2>
    <p>Comprueba los equipos, consumos y evidencias antes de finalizar. Al completar la instalación, esta ficha quedará disponible para consulta.</p>
    <form method="POST" action="{{ route('technician.finish', $installation->pedido_id) }}">@csrf<button type="submit" class="action-link">Finalizar instalación</button></form>
</section>
@elseif($installation->estado !== 'COMPLETADO')
<p class="muted">Inicia la instalación para registrar el trabajo, el consumo y las evidencias.</p>
@endif
@endsection
