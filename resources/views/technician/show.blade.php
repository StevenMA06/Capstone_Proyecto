@extends('layouts.management')
@section('title', 'Instalación '.$installation->codigo)

@push('styles')<link rel="stylesheet" href="{{ asset('css/installations.css') }}?v=2"><script src="{{ asset('js/installations.js') }}?v=1" defer></script>@endpush
@section('workspace-content')
@php($running = $installation->estado === 'EN_PROCESO' && $execution?->inicio_en && $execution->estado === 'EN_PROCESO')
<a class="action-link secondary" href="{{ route(auth()->user()->hasPermission('technician.panel') ? 'technician.index' : 'executions.index') }}">← Volver al panel técnico</a>
<nav class="workspace-breadcrumb" aria-label="Ruta de navegación"><a href="{{ route('dashboard') }}">Inicio</a><span aria-hidden="true">›</span><a href="{{ route(auth()->user()->hasPermission('technician.panel') ? 'technician.index' : 'executions.index') }}">Instalaciones</a><span aria-hidden="true">›</span><span aria-current="page">Ficha #{{ $installation->pedido_id }}</span></nav>
<header class="installation-heading">
    <div><p class="eyebrow">EJECUCIÓN DE INSTALACIONES</p><h1>Ficha #{{ $installation->pedido_id }} · {{ $installation->codigo }}</h1>
    <p class="muted">{{ ucfirst(mb_strtolower(str_replace('_', ' ', $installation->estado))) }} · Programación: {{ $installation->fecha_programada ?? 'Sin programar' }}</p></div>
    @if(in_array($installation->estado, ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO']) || ($installation->estado === 'EN_PROCESO' && ! $execution?->inicio_en))
    @can('technician.manage')
@routecan('technician.start')<form method="POST" action="{{ route('technician.start', $installation->pedido_id) }}" data-installation-form>@csrf<button class="action-link" type="submit">Iniciar instalación</button></form>@endroutecan
@endcan
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
    @can('technician.manage')
@routecan('technician.equipment')<form method="POST" action="{{ route('technician.equipment', $installation->pedido_id) }}" class="installation-form">
        @csrf
        <label for="equipo_id">Equipo disponible o asignado a esta instalación</label>
        <select id="equipo_id" name="equipo_id" required><option value="">Selecciona un equipo</option>
            @foreach($equipment as $item)<option value="{{ $item->equipo_id }}" @selected(old('equipo_id') == $item->equipo_id)>{{ $item->codigo }} · {{ $item->nombre }}{{ $item->numero_serie ? ' · '.$item->numero_serie : '' }}</option>@endforeach
        </select>
        <button type="submit" class="action-link">Registrar equipo utilizado</button>
    </form>@endroutecan
@endcan
    @endif
    <div class="table-scroll"><table class="users-table installation-table"><thead><tr><th>Equipo</th><th>Serie / marca / modelo</th><th>Estado</th></tr></thead><tbody>
    @forelse($usedEquipment as $item)<tr><td data-label="Equipo"><div>{{ $item->nombre }}<small>{{ $item->codigo }}</small></div></td><td data-label="Serie / marca / modelo"><div>{{ $item->numero_serie ?? 'Sin serie' }}<small>{{ $item->marca ?? 'Sin marca' }} · {{ $item->modelo ?? 'Sin modelo' }}</small></div></td><td data-label="Estado">{{ ucfirst(mb_strtolower($item->estado_asignacion)) }}</td></tr>
    @empty<tr><td colspan="3">Sin equipos registrados.</td></tr>@endforelse
    </tbody></table></div>
</section>
<section class="installation-panel">
    <h2>Materiales utilizados</h2>
    @if($running)
    @can('technician.manage')
@routecan('technician.material')<form method="POST" action="{{ route('technician.material', $installation->pedido_id) }}" class="installation-form">
        @csrf
        <label for="material_id">Material</label><select id="material_id" name="material_id" required><option value="">Selecciona un material</option>
        @foreach($materials as $item)<option value="{{ $item->material_id }}" @selected(old('material_id') == $item->material_id)>{{ $item->nombre }} · Stock: {{ \App\Support\InventoryQuantity::format($item->stock_actual) }} {{ $item->unidad_medida }}</option>@endforeach</select>
        <label for="cantidad_utilizada">Total utilizado en esta instalación</label>
        <input id="cantidad_utilizada" name="cantidad_utilizada" type="number" min="0.001" max="999999999.999" step="0.001" value="{{ old('cantidad_utilizada') }}" required aria-describedby="consumption-help">
        <small id="consumption-help">Indica el total acumulado del material. Si ya registraste 5 y utilizaste 2 más, ingresa 7. Solo se descontará la diferencia.</small>
        <button type="submit" class="action-link">Guardar consumo</button>
    </form>@endroutecan
@endcan
    @endif
    <div class="table-scroll"><table class="users-table installation-table"><thead><tr><th>Material / unidad</th><th>Asignada</th><th>Utilizada</th><th>Devuelta</th></tr></thead><tbody>
    @forelse($usedMaterials as $item)<tr><td data-label="Material / unidad"><div>{{ $item->nombre }}<small>{{ $item->codigo }} · {{ $item->unidad_medida }}</small></div></td><td data-label="Asignada">{{ \App\Support\InventoryQuantity::format($item->cantidad_asignada) }}</td><td data-label="Utilizada">{{ \App\Support\InventoryQuantity::format($item->cantidad_utilizada) }} {{ $item->unidad_medida }}</td><td data-label="Devuelta">{{ \App\Support\InventoryQuantity::format($item->cantidad_devuelta) }}</td></tr>
    @empty<tr><td colspan="4">Sin materiales registrados.</td></tr>@endforelse
    </tbody></table></div>
</section>
</div>
<section class="installation-panel">
    <h2>Observación técnica</h2>
    @if($running)@can('technician.manage')
@routecan('technician.observation')<form method="POST" action="{{ route('technician.observation', $installation->pedido_id) }}" class="installation-form">@csrf
    <label for="observacion_tecnica">Detalle del trabajo</label>
    <textarea id="observacion_tecnica" name="observacion_tecnica" rows="4" maxlength="5000">{{ old('observacion_tecnica', $execution?->observacion_tecnica) }}</textarea>
    <button type="submit" class="action-link">Guardar observación</button></form>@endroutecan
@endcan
    @else<p class="installation-notes">{{ $execution?->observacion_tecnica ?? 'Sin observaciones técnicas.' }}</p>@endif
</section>
<div class="installation-grid">
@foreach([false, true] as $isContract)
<section class="installation-panel">
    <h2>{{ $isContract ? 'Evidencia del contrato firmado' : 'Evidencias fotográficas' }}</h2>
    @if($running && auth()->user()->hasPermission($isContract ? 'technician.contractEvidence' : 'technician.evidence'))
    <form method="POST" action="{{ route($isContract ? 'technician.contractEvidence' : 'technician.evidence.store', $installation->pedido_id) }}" enctype="multipart/form-data" class="installation-form">
        @csrf
        <label for="file-{{ (int) $isContract }}">{{ $isContract ? 'Contrato firmado' : 'Fotografía' }}</label>
        <input id="file-{{ (int) $isContract }}" type="file" name="evidence" accept="{{ $isContract ? 'application/pdf,' : '' }}image/jpeg,image/png,image/webp" required data-evidence-input>
        <figure class="installation-file-preview" hidden><img alt="Vista previa del archivo seleccionado"><figcaption aria-live="polite"></figcaption></figure>
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
    <li>
    @if(in_array($file->mime_type, ['image/jpeg', 'image/png', 'image/webp']))
    <a class="installation-photo-link" href="{{ route($isContract ? 'technician.contract.preview' : 'technician.evidence.preview', [$installation->pedido_id, $isContract ? $file->evidencia_contrato_id : $file->evidencia_id]) }}" target="_blank" rel="noopener"><img class="installation-photo" loading="lazy" src="{{ route($isContract ? 'technician.contract.preview' : 'technician.evidence.preview', [$installation->pedido_id, $isContract ? $file->evidencia_contrato_id : $file->evidencia_id]) }}" alt="Evidencia: {{ $file->nombre_archivo ?? 'Fotografía de instalación' }}"></a>
    @endif
    <a class="action-link secondary" href="{{ route($isContract ? 'technician.contract.download' : 'technician.evidence.download', [$installation->pedido_id, $isContract ? $file->evidencia_contrato_id : $file->evidencia_id]) }}">Descargar {{ $file->nombre_archivo ?? 'evidencia' }}</a>
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
    @can('technician.manage')
@routecan('technician.finish')<form method="POST" action="{{ route('technician.finish', $installation->pedido_id) }}" data-installation-form data-confirm-finish>@csrf<button type="submit" class="action-link">Finalizar instalación</button></form>@endroutecan
@endcan
    <dialog id="installation-finish-confirm" class="installation-confirm" aria-labelledby="finish-confirm-title" aria-describedby="finish-confirm-description">
        <h2 id="finish-confirm-title">¿Finalizar esta instalación?</h2>
        <p id="finish-confirm-description">Se registrará la hora actual y la ficha quedará disponible únicamente para consulta. Revisa los consumos y las evidencias antes de continuar.</p>
        <div class="installation-filter-actions"><button type="button" class="action-link secondary" data-cancel-finish autofocus>Seguir revisando</button><button type="button" class="action-link" data-accept-finish>Confirmar finalización</button></div>
    </dialog>
</section>
@elseif($installation->estado !== 'COMPLETADO')
<p class="muted">Inicia la instalación para registrar el trabajo, el consumo y las evidencias.</p>
@endif
@endsection
