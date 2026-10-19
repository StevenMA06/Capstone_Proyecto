@extends('layouts.management')
@section('title', 'Instalación '.$installation->codigo)

@push('styles')<link rel="stylesheet" href="{{ asset('css/installations.css') }}?v=28"><script src="{{ asset('js/installations.js') }}?v=8" defer></script>@endpush
@section('workspace-content')
@php($running = $installation->estado === 'EN_PROCESO' && $execution?->inicio_en && $execution->estado === 'EN_PROCESO')
@php($ownWork = in_array((int) auth()->user()->empleado_id, [(int) $installation->tecnico_id, (int) $installation->tecnico_secundario_id], true))
@php($canWork = ! $installation->fase_flujo || $ownWork)
@php($photoReady = $driveStatus->get('FOTO', collect())->contains('estado', 'CONFIRMADO'))
@php($pendingEvidence = $driveStatus->flatten()->contains('estado', 'PENDIENTE'))
@php($visitDate = $installation->fecha_programada ? \Illuminate\Support\Carbon::parse($installation->fecha_programada) : null)
<a class="action-link secondary" href="{{ route(auth()->user()->hasPermission('technician.panel') ? 'technician.index' : 'executions.index') }}">← Volver al panel técnico</a>
<nav class="workspace-breadcrumb" aria-label="Ruta de navegación"><a href="{{ route('dashboard') }}">Inicio</a><span aria-hidden="true">›</span><a href="{{ route(auth()->user()->hasPermission('technician.panel') ? 'technician.index' : 'executions.index') }}">Instalaciones</a><span aria-hidden="true">›</span><span aria-current="page">Ficha #{{ $installation->pedido_id }}</span></nav>
<header class="installation-heading installation-detail-heading">
    <div><p class="eyebrow">EJECUCIÓN DE INSTALACIONES</p><h1>Ficha técnica #{{ $installation->pedido_id }}</h1><p class="installation-detail-code">{{ $installation->codigo }}</p></div>
    <div class="installation-detail-heading-actions"><span class="installation-state state-{{ strtolower($installation->estado) }}">{{ ucfirst(mb_strtolower(str_replace('_', ' ', $installation->estado))) }}</span>
    @if($installation->fase_flujo === 'ESPERANDO_CONFIRMACION_TECNICO' && $ownWork && !((int) $installation->tecnico_id === (int) auth()->user()->empleado_id ? $installation->confirmado_por_tecnico_en : $installation->confirmado_por_tecnico_secundario_en))
    @routecan('technician.confirm')<form method="POST" action="{{ route('technician.confirm', $installation->pedido_id) }}">@csrf<button class="action-link" type="submit">Confirmar asignación</button></form>@endroutecan
    @elseif(! $pendingReprogramming && $canWork && (!$installation->fase_flujo || ($installation->fase_flujo === 'CONFIRMADO' && $resourcesPrepared)) && (in_array($installation->estado, ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO']) || ($installation->estado === 'EN_PROCESO' && ! $execution?->inicio_en)))
    @can('technician.manage')
@routecan('technician.start')<form method="POST" action="{{ route('technician.start', $installation->pedido_id) }}" data-installation-form>@csrf<button class="action-link" type="submit">Iniciar instalación</button></form>@endroutecan
@endcan
    @endif
    @if(! $running && $installation->fase_flujo && $installation->fase_flujo === 'CONFIRMADO' && ! $resourcesPrepared)<p class="order-change-note" role="status">La asignación está confirmada. Espera a que Almacén marque como entregados todos los recursos solicitados; entonces se habilitará el inicio.</p>@endif
    </div>
</header>
<div class="installation-visit-overview" aria-label="Visita acordada">
    <div><small>Día de la visita</small><strong>{{ $visitDate ? ucfirst($visitDate->locale('es')->isoFormat('dddd')).' '.$visitDate->format('d/m/Y') : 'Por confirmar' }}</strong></div>
    <div><small>Hora de inicio</small><strong>{{ $visitDate ? $visitDate->format('H:i') : 'Por confirmar' }}</strong></div>
    <div><small>Franja horaria</small><strong>{{ $installation->franja_horaria ?: 'Por confirmar' }}</strong></div>
</div>
@if(session('status') && ! session('evidence_result'))<div class="success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="field-error" role="alert"><strong>No se pudo guardar:</strong>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@if($installation->estado === 'CANCELADO' && $cancellationEvent)<div class="field-error" role="note"><strong>Pedido cancelado</strong><p><strong>Motivo:</strong> {{ $cancellationEvent->motivo }}</p><small>{{ $cancellationEvent->nombre_usuario ?: 'Proceso automático' }} · {{ \Illuminate\Support\Carbon::parse($cancellationEvent->registrado_en)->format('d/m/Y · H:i') }}</small></div>@endif
@if($pendingReprogramming)<div class="order-change-note" role="status">Hay una solicitud de nueva fecha pendiente de Gerencia. La visita conserva la fecha actual y no se puede iniciar hasta que Gerencia decida.</div>@endif
@if($installation->estado === 'REPROGRAMADO' && $installation->fecha_original)<div class="order-change-note" role="status"><strong>Visita reprogramada.</strong> Fecha inicial: {{ \Illuminate\Support\Carbon::parse($installation->fecha_original)->format('d/m/Y · H:i') }}.@if($latestApprovedReprogramming?->motivo_detalle ?? $installation->motivo_reprogramacion) Motivo: {{ $latestApprovedReprogramming?->motivo_detalle ?? $installation->motivo_reprogramacion }}.@endif Revisa la nueva fecha antes de confirmar o iniciar.</div>@endif
@if($running)<div class="installation-next-step" role="status"><strong>Instalación en curso</strong><span>Registra los recursos utilizados y las fotos de campo. Entrega el contrato físico en la empresa; el personal de Contratos lo escaneará y verificará.</span></div>@endif
@if($running)<div class="installation-readiness" aria-label="Requisitos para finalizar"><span class="{{ $photoReady ? 'is-ready' : '' }}">{{ $photoReady ? '✓' : '○' }} Foto {{ $photoReady ? 'confirmada' : 'pendiente' }}</span><span class="{{ $contractReady ? 'is-ready' : '' }}">{{ $contractReady ? '✓' : '○' }} Contrato {{ $contractReady ? 'confirmado' : 'pendiente' }}</span></div>@endif
<nav class="installation-jump" aria-label="Secciones de la ficha" data-installation-tabs>
    <a href="#instalacion-datos" data-installation-tab="datos" aria-controls="instalacion-datos"><strong>Datos</strong><small>Cliente y visita</small></a>
    <a href="#instalacion-recursos" data-installation-tab="recursos" aria-controls="instalacion-recursos"><strong>Recursos</strong><small>Equipo y material</small></a>
    <a href="#instalacion-incidencias" data-installation-tab="incidencias" aria-controls="instalacion-incidencias"><strong>Incidencias</strong><small>Si ocurrió algo</small></a>
    <a href="#instalacion-evidencias" data-installation-tab="evidencias" aria-controls="instalacion-evidencias"><strong>Evidencias</strong><small>Fotos de campo y estado del contrato</small></a>
    @if($running)<a href="#instalacion-cierre" data-installation-tab="cierre" aria-controls="instalacion-cierre"><strong>Cierre</strong><small>{{ $photoReady && $contractReady ? 'Disponible' : 'Pendiente' }}</small></a>@endif
</nav>
<details class="installation-panel installation-collapsible" id="instalacion-datos" data-installation-section="datos" tabindex="-1">
    <summary>Información para el trabajo</summary>
    <div class="installation-detail-sections">
        <section class="installation-detail-block"><h3>Cliente y servicio</h3><dl class="installation-detail-grid">
            <div><dt>Cliente</dt><dd>{{ trim(($client->nombres_razon_social ?? '').' '.($client->apellidos ?? '')) ?: ($installation->solicitante_nombre ?: 'No registrado en Mikrowisp') }}</dd></div>
            <div><dt>Teléfonos de contacto</dt><dd>{{ $client->telefono ?? $installation->telefono_contacto ?? 'No registrado' }}@if($installation->telefono_alternativo) · {{ $installation->telefono_alternativo }}@endif</dd></div>
            <div class="installation-detail-wide"><dt>Dirección de instalación</dt><dd>{{ $contract->direccion_servicio ?? $installation->direccion_solicitada ?? 'No registrada' }}</dd><span>Referencia: {{ $client->referencia_direccion ?? $installation->referencia_direccion_solicitada ?? 'Sin referencia' }}</span>@if($installation->ubicacion_compartida)<a href="{{ $installation->ubicacion_compartida }}" target="_blank" rel="noopener">Abrir ubicación compartida por WhatsApp</a>@endif</div>
            <div><dt>Contrato</dt><dd>{{ $contract->codigo ?? 'No registrado' }}</dd></div>
            <div><dt>Plan solicitado</dt><dd>{{ $contract->plan_nombre ?? $installation->plan_solicitado ?? 'No registrado' }}</dd></div>
        </dl></section>
        <section class="installation-detail-block"><h3>Personas responsables</h3><dl class="installation-detail-grid installation-detail-people">
            <div><dt>Equipo técnico</dt><dd>{{ $technician ? trim($technician->nombres.' '.$technician->apellidos) : 'Sin asignar' }}</dd><span>Responsable principal</span></div>
            @if($installation->tecnico_secundario_id)<div><dt>Segundo técnico</dt><dd>{{ $secondTechnician ? trim($secondTechnician->nombres.' '.$secondTechnician->apellidos) : 'Asignado' }}</dd><span>Confirma desde su ficha</span></div>@endif
            <div><dt>Coordinación con el cliente</dt><dd>{{ $coordinator?->employee?->full_name ?? ($coordinator ? 'Sin empleado asociado' : 'Sin registrar') }}</dd><span>{{ $installation->canal_coordinacion ? ucfirst(mb_strtolower($installation->canal_coordinacion)).' · ' : '' }}{{ $coordinator ? 'Usuario: '.$coordinator->nombre_usuario : 'Aún no registrada' }}</span></div>
            <div><dt>Responsable de asignación</dt><dd>{{ $assigner?->employee?->full_name ?? ($assigner ? 'Sin empleado asociado' : 'Sin registrar') }}</dd><span>{{ $assigner ? 'Usuario: '.$assigner->nombre_usuario : 'Aún no registrado' }}</span></div>
        </dl></section>
        <section class="installation-detail-block"><h3>Seguimiento</h3><dl class="installation-detail-grid installation-detail-timeline">
            <div><dt>Inicio de la instalación</dt><dd>{{ $execution?->inicio_en ? \Illuminate\Support\Carbon::parse($execution->inicio_en)->format('d/m/Y · H:i') : 'Sin iniciar' }}</dd></div>
            <div><dt>Finalización</dt><dd>{{ $execution?->fin_en ? \Illuminate\Support\Carbon::parse($execution->fin_en)->format('d/m/Y · H:i') : 'Sin finalizar' }}</dd></div>
        </dl>@if($installation->observaciones)<p class="installation-detail-note"><strong>Observaciones del pedido</strong><span>{{ $installation->observaciones }}</span></p>@endif</section>
    </div>
</details>
<section class="installation-panel" id="instalacion-incidencias" data-installation-section="incidencias" tabindex="-1">
    <div class="installation-section-heading"><div><h2>Incidencias</h2><p class="muted">{{ $incidents->count() }} registradas</p></div></div>
    <p class="installation-section-help">Si se avería o extravía un equipo, o se desperdicia material durante la visita, registra qué ocurrió y adjunta una evidencia para que el jefe pueda revisarlo.</p>
    @if($running && $canWork)
    @routecan('technician.incident')<details class="installation-inline-action" @if($errors->any()) open @endif><summary>Registrar incidencia</summary><form method="POST" action="{{ route('technician.incident', $installation->pedido_id) }}" enctype="multipart/form-data" class="installation-form">@csrf
        <label>Tipo<select name="tipo" required><option value="">Selecciona</option><option value="AVERIA">Avería</option><option value="EXTRAVIO">Extravío</option><option value="DESPERDICIO">Desperdicio</option><option value="OTRO">Otro</option></select></label>
        <label>Recurso afectado<select name="recurso_tipo" data-incident-type required><option value="">Selecciona</option><option value="EQUIPO" @selected(old('recurso_tipo') === 'EQUIPO')>Equipo</option><option value="MATERIAL" @selected(old('recurso_tipo') === 'MATERIAL')>Material</option><option value="OTRO" @selected(old('recurso_tipo') === 'OTRO')>Otro</option></select></label>
        <label>Equipo o material afectado<select name="recurso_id" data-incident-resource><option value="">Selecciona un recurso utilizado</option>@foreach($usedEquipment as $item)<option value="{{ $item->equipo_id }}" data-resource-type="EQUIPO" @selected(old('recurso_tipo') === 'EQUIPO' && old('recurso_id') == $item->equipo_id)>{{ $item->codigo }} · {{ $item->nombre }}</option>@endforeach @foreach($usedMaterials as $item)<option value="{{ $item->material_id }}" data-resource-type="MATERIAL" @selected(old('recurso_tipo') === 'MATERIAL' && old('recurso_id') == $item->material_id)>{{ $item->codigo }} · {{ $item->nombre }}</option>@endforeach</select><small>Selecciona un recurso ya registrado; para «Otro» deja este campo vacío.</small></label>
        <label>Descripción del recurso<input name="recurso_descripcion" maxlength="200" required value="{{ old('recurso_descripcion') }}"></label>
        <label>Cantidad afectada<input name="cantidad" type="number" min="0.001" step="0.001" value="{{ old('cantidad') }}"></label>
        <label>Causa<input name="causa" maxlength="500" required value="{{ old('causa') }}"></label>
        <label>Observación<textarea name="observacion" maxlength="5000" required>{{ old('observacion') }}</textarea></label>
        <label>Foto o PDF de evidencia<input name="evidencia" type="file" accept="image/jpeg,image/png,image/webp,application/pdf"></label>
        <button type="submit" class="action-link">Registrar incidencia</button>
    </form></details>@endroutecan
    @endif
    <ul class="installation-files">@forelse($incidents as $incident)<li><strong>{{ $incident->tipo }} · {{ $incident->recurso_descripcion }}</strong><p>{{ $incident->causa }} — {{ $incident->observacion }}</p><small>{{ $incident->registrado_en }}</small>@if($incident->evidencia_ruta)<a class="action-link secondary" href="{{ route('technician.incident.download', [$installation->pedido_id, $incident->incidencia_id]) }}">Descargar evidencia</a>@endif</li>@empty<li>No hay incidencias registradas.</li>@endforelse</ul>
</section>
<div class="installation-grid" id="instalacion-recursos" data-installation-section="recursos" tabindex="-1">
<section class="installation-panel">
    <h2>Equipos utilizados</h2>
    <p class="installation-section-help">Consulta los equipos asignados a esta instalación y su estado actual.</p>
    <div class="table-scroll"><table class="users-table installation-table"><thead><tr><th>Equipo</th><th>Serie / marca / modelo</th><th>Estado</th></tr></thead><tbody>
    @forelse($usedEquipment as $item)<tr><td data-label="Equipo"><div>{{ $item->nombre }}<small>{{ $item->codigo }}</small></div></td><td data-label="Serie / marca / modelo"><div>{{ $item->numero_serie ?? 'Sin serie' }}<small>{{ $item->marca ?? 'Sin marca' }} · {{ $item->modelo ?? 'Sin modelo' }}</small></div></td><td data-label="Estado">{{ ucfirst(mb_strtolower($item->estado_asignacion)) }}</td></tr>
    @empty<tr><td colspan="3">Sin equipos registrados.</td></tr>@endforelse
    </tbody></table></div>
</section>
<section class="installation-panel">
    <h2>Materiales utilizados</h2>
    <p class="installation-section-help">Consulta los materiales asignados a esta instalación.</p>
    <div class="table-scroll"><table class="users-table installation-table"><thead><tr><th>Material / unidad</th><th>Asignada</th></tr></thead><tbody>
    @forelse($usedMaterials as $item)<tr><td data-label="Material / unidad"><div>{{ $item->nombre }}<small>{{ $item->codigo }} · {{ $item->unidad_medida }}</small></div></td><td data-label="Asignada">{{ \App\Support\InventoryQuantity::format($item->cantidad_asignada) }} {{ $item->unidad_medida }}</td></tr>
    @empty<tr><td colspan="2">Sin materiales registrados.</td></tr>@endforelse
    </tbody></table></div>
</section>
</div>
<section class="installation-panel" data-installation-section="incidencias">
    <h2>Observación técnica</h2>
    <p class="installation-section-help">Anota el trabajo realizado o un dato útil para futuras consultas. Las averías y pérdidas se registran en «Incidencias».</p>
    @if($running && $canWork)@can('technician.manage')
@routecan('technician.observation')<details class="installation-inline-action" @if($errors->has('observacion_tecnica')) open @endif><summary>{{ $execution?->observacion_tecnica ? 'Editar observación' : 'Agregar observación' }}</summary><form method="POST" action="{{ route('technician.observation', $installation->pedido_id) }}" class="installation-form">@csrf
    <label for="observacion_tecnica">Detalle del trabajo</label>
    <textarea id="observacion_tecnica" name="observacion_tecnica" rows="4" maxlength="5000">{{ old('observacion_tecnica', $execution?->observacion_tecnica) }}</textarea>
    <button type="submit" class="action-link">Guardar observación</button></form></details>@endroutecan
@endcan
    @else<p class="installation-notes">{{ $execution?->observacion_tecnica ?? 'Sin observaciones técnicas.' }}</p>@endif
    @if($running && $execution?->observacion_tecnica)<p class="installation-notes">{{ $execution->observacion_tecnica }}</p>@endif
</section>
<div class="installation-grid" id="instalacion-evidencias" data-installation-section="evidencias" tabindex="-1">
<section class="installation-panel" id="instalacion-fotos">
    <h2>Fotos de campo</h2>
    <p class="installation-section-help">Sube una fotografía clara de la instalación terminada. Debe confirmarse en Drive para poder cerrar el trabajo.</p>
    <p class="installation-evidence-status {{ $photoReady ? 'is-ready' : '' }}">{{ $photoReady ? '✓ Foto de instalación confirmada en Drive' : 'Falta confirmar una foto de la instalación en Drive' }}</p>
    @if(session('evidence_result') === 'FOTO')<p class="installation-inline-result" role="status">{{ session('status') }}</p>@endif
    @if($running && $canWork && auth()->user()->hasPermission('technician.evidence'))
    <details class="installation-inline-action" @if(session('evidence_result') === 'FOTO' || $errors->has('evidence')) open @endif><summary>Agregar foto de la instalación</summary><form method="POST" action="{{ route('technician.evidence.store', $installation->pedido_id) }}" enctype="multipart/form-data" class="installation-form" data-return-anchor="instalacion-fotos">
        @csrf
        <p class="installation-section-help">Esta evidencia quedará asociada al pedido y será necesaria para finalizarlo.</p>
        <label for="file-photo">Fotografía</label><div class="installation-file-choices" data-evidence-picker hidden><button class="action-link" type="button" data-open-camera aria-controls="file-photo">Abrir cámara</button><button class="action-link secondary" type="button" data-open-files aria-controls="file-photo">Elegir de la galería</button></div>
        <input id="file-photo" type="file" name="evidence" accept="image/jpeg,image/png,image/webp" required data-evidence-input><figure class="installation-file-preview" hidden><img alt="Vista previa del archivo seleccionado"><figcaption aria-live="polite"></figcaption></figure><small>JPG, PNG o WEBP · Máximo 5 MB</small>
        <label for="description-photo">Descripción (opcional)</label><textarea id="description-photo" name="descripcion" maxlength="500" rows="2">{{ old('descripcion') }}</textarea>
        <button type="submit" class="action-link">Guardar fotografía</button>
    </form></details>@endif
    <ul class="installation-files">@forelse($evidences as $file)<li><strong>{{ $file->tipo === 'COMPROBANTE_CLIENTE' ? 'Comprobante con el cliente' : 'Instalación terminada' }}</strong><a class="installation-photo-link" href="{{ route('technician.evidence.preview', [$installation->pedido_id, $file->evidencia_id]) }}" target="_blank" rel="noopener"><img class="installation-photo" loading="lazy" src="{{ route('technician.evidence.preview', [$installation->pedido_id, $file->evidencia_id]) }}" alt="{{ $file->tipo === 'COMPROBANTE_CLIENTE' ? 'Comprobante con el cliente' : 'Foto de instalación' }}"></a><a class="action-link secondary" href="{{ route('technician.evidence.download', [$installation->pedido_id, $file->evidencia_id]) }}">Descargar {{ $file->nombre_archivo ?? 'fotografía' }}</a><p>{{ $file->descripcion }}</p><small>{{ $file->subido_en }}</small></li>@empty<li>Aún no hay fotografías.</li>@endforelse</ul>
    @include('clients.pagination', ['records' => $evidences, 'label' => 'Paginación de fotografías'])
</section>
<section class="installation-panel" id="instalacion-contrato">
    <h2>Contrato físico</h2>
    <p class="installation-section-help">Al terminar la instalación, completa y firma el formato físico con el cliente. Al volver a la empresa, entrégalo a Call Center para que registre al cliente en Mikrowisp y cargue el escaneo una sola vez desde Contratos.</p>
    <p class="installation-evidence-status {{ $contractReady ? 'is-ready' : '' }}">{{ $contractReady ? '✓ Contrato firmado verificado en Contratos y confirmado en Drive' : ($contract?->firma_revision === 'VERIFICADA' ? 'Firma verificada; falta confirmar el PDF en Drive' : ($installation->estado === 'COMPLETADO' ? 'Trabajo de campo terminado; Call Center debe completar el registro del cliente y el contrato' : 'El contrato físico se completa y firma al terminar el trabajo')) }}</p>
    @if($contract)@routecan('contracts.show')<a class="action-link secondary" href="{{ route('contracts.show', $installation->contrato_id) }}">Ver contrato {{ $contract->codigo }}</a>@endroutecan @endif
</section>
</div>
@if($running && ($pendingEvidence || ($contract?->firma_revision === 'VERIFICADA' && ! $contractReady)))
<div class="installation-drive-help installation-panel" data-installation-section="evidencias">
    <strong>Hay archivos pendientes de confirmar en Google Drive</strong>
    <p>{{ $driveAuthorizationExpired ? 'La autorización de Google Drive expiró o fue revocada. El responsable del sistema debe renovar la conexión; después se podrá reintentar el envío de los archivos pendientes.' : 'El cierre seguirá bloqueado hasta que Drive confirme la foto de instalación y el PDF firmado que se subió en Contratos.' }}</p>
    @if($driveConfigured && $canWork)
        @routecan('technician.syncEvidence')<form method="POST" action="{{ route('technician.syncEvidence', $installation->pedido_id) }}" data-return-anchor="instalacion-evidencias">@csrf<button type="submit" class="action-link secondary">Reintentar envío a Drive</button></form>@endroutecan
    @elseif(! $driveConfigured)
        <p class="installation-drive-action">La carpeta de Drive aún no está vinculada. Informa al responsable del sistema; podrás reintentar el envío cuando termine esa configuración.</p>
    @endif
    @if(session('evidence_result') === 'DRIVE')<p class="installation-inline-result" role="status">{{ session('status') }}</p>@endif
</div>
@endif
@if($running)
<section class="installation-panel installation-finish" id="instalacion-cierre" data-installation-section="cierre" tabindex="-1">
    <h2>Cierre automático del trabajo de campo</h2>
    <p>El sistema marcará la instalación como realizada cuando confirme en Google Drive la foto final. Call Center completará después el registro de Mikrowisp y la carga del contrato firmado en Contratos.</p>
    <div class="installation-readiness" role="status">
        <p class="installation-evidence-status {{ $photoReady ? 'is-ready' : '' }}">{{ $photoReady ? '✓ Foto de la instalación confirmada en Drive' : 'Pendiente: subir la foto de la instalación y confirmar su envío a Drive' }}</p>
        <p class="installation-evidence-status {{ $contractReady ? 'is-ready' : '' }}">{{ $contractReady ? '✓ Contrato firmado verificado y confirmado en Drive' : 'Seguimiento administrativo: Call Center registrará al cliente y cargará el contrato firmado después de la visita' }}</p>
    </div>
    @if($photoReady && $contractReady)
        <p class="installation-inline-result" role="status">Las dos evidencias están confirmadas. El sistema está registrando el cierre del pedido.</p>
    @endif
</section>
@elseif($installation->estado !== 'COMPLETADO')
<p class="muted">Inicia la instalación para registrar el trabajo, el consumo y las evidencias.</p>
@endif
@endsection
