@extends('layouts.management')
@section('title', 'Coordinación de visitas')
@push('styles')<link rel="stylesheet" href="{{ asset('css/installations.css') }}?v=31"><script src="{{ asset('js/coordination.js') }}?v=4" defer></script>@endpush
@section('workspace-content')
<nav class="workspace-breadcrumb" aria-label="Ruta de navegación"><a href="{{ route('dashboard') }}">Inicio</a><span aria-hidden="true">›</span><span aria-current="page">Coordinación de visitas</span></nav>
<header class="installation-heading"><div><p class="eyebrow">ASISTENTE DE VENTAS</p><h1>Solicitudes e instalaciones</h1><p class="muted">Coordina las visitas de los contratos registrados y da seguimiento a cada instalación.</p></div></header>
@if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="notice" role="alert"><strong>Revisa los campos indicados:</strong>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

<form method="GET" action="{{ route('office.coordination.index') }}" class="installation-panel order-filters">
    <label for="visit-search">Pedido, contrato o cliente<input id="visit-search" type="search" name="search" maxlength="150" value="{{ request('search') }}" placeholder="Buscar por código o nombre"></label>
    <label for="visit-state">Visitas<select id="visit-state" name="estado"><option value="">Todas</option><option value="por_confirmar" @selected(request('estado') === 'por_confirmar')>Por confirmar</option><option value="confirmadas" @selected(request('estado') === 'confirmadas')>Con fecha confirmada</option></select></label>
    <div class="order-filter-actions"><button type="submit" class="action-link">Buscar</button><a class="action-link secondary" href="{{ route('office.coordination.index') }}">Limpiar</a></div>
</form>

<section class="order-list" aria-labelledby="visit-list-title">
    <header class="installation-list-heading"><div><h2 id="visit-list-title">Visitas de instalación</h2><p class="muted">{{ $records->total() }} {{ $records->total() === 1 ? 'pedido encontrado' : 'pedidos encontrados' }}</p></div></header>
    @forelse($records as $record)
        @php
            $coordinated = (bool) $record->coordinado_en || (! $record->fase_flujo && (bool) $record->fecha_programada);
            $visitDate = $record->fecha_programada ? \Illuminate\Support\Carbon::parse($record->fecha_programada) : null;
            $change = $latestChanges->get($record->pedido_id);
            $pending = $change?->estado === 'SOLICITADA';
            $context = old('form_context') === 'visit-'.$record->pedido_id;
            $contractVisitDate = ! $coordinated && $record->contrato_fecha_inicio
                ? \Illuminate\Support\Carbon::parse($record->contrato_fecha_inicio)->format('Y-m-d') : null;
            $coordinationStepUrl = $pending
                ? (auth()->user()->hasPermission('orders.approve') ? route('orders.index', ['reprogramacion' => 'pendiente']).'#pedido-'.$record->pedido_id : null)
                : ((! $coordinated || $change?->estado === 'RECHAZADA') ? '#coordinar-visita-'.$record->pedido_id : null);
        @endphp
        <article class="installation-panel order-card" id="visita-{{ $record->pedido_id }}">
            <div class="order-card-header"><div><p class="order-card-kicker">Pedido {{ $record->codigo }} · Contrato {{ $record->contrato_codigo ?? 'sin código' }}</p><h3>{{ $record->nombre_solicitud ?: 'Cliente' }}</h3><small>Instalación asociada al contrato</small></div><span class="installation-state {{ $coordinated ? 'state-programado' : 'state-reprogramado' }}">{{ $coordinated ? 'Fecha confirmada' : 'Por coordinar' }}</span></div>
            <div class="coordination-info order-card-primary">
                <div class="order-card-visit"><small>Visita vigente</small><dl>
                    <div><dt>Día</dt><dd>{{ $visitDate ? ucfirst($visitDate->locale('es')->isoFormat('dddd')).' '.$visitDate->format('d/m/Y') : 'Por confirmar' }}</dd></div>
                    <div><dt>Hora de inicio</dt><dd>{{ $visitDate ? $visitDate->format('H:i') : 'Por confirmar' }}</dd></div>
                    <div><dt>Franja</dt><dd>{{ $record->franja_horaria ?: 'Por confirmar' }}</dd></div>
                </dl></div>
                <div class="order-card-address"><small>Dirección del servicio</small><strong>{{ $record->direccion_servicio }}</strong>@if($record->ubicacion_compartida)<a href="{{ $record->ubicacion_compartida }}" target="_blank" rel="noopener">Abrir ubicación compartida</a>@endif</div>
                <div class="order-card-contact"><small>Contacto del cliente</small>@if($record->telefono_solicitud)<a href="tel:{{ preg_replace('/[^0-9+]/', '', (string) $record->telefono_solicitud) }}">{{ $record->telefono_solicitud }}</a>@else<strong>Teléfono sin registrar</strong>@endif @if($record->correo_solicitud)<a href="mailto:{{ $record->correo_solicitud }}">{{ $record->correo_solicitud }}</a>@endif</div>
                <div class="order-card-contract-start"><small>Disponibilidad indicada</small><strong>{{ $record->disponibilidad_fecha ? \Illuminate\Support\Carbon::parse($record->disponibilidad_fecha)->format('d/m/Y') : 'Por consultar' }}</strong><span>{{ $record->disponibilidad_desde && $record->disponibilidad_hasta ? substr($record->disponibilidad_desde, 0, 5).' a '.substr($record->disponibilidad_hasta, 0, 5) : 'Horario por consultar' }}</span></div>
            </div>
            @if(!$record->contrato_codigo && $record->estado_cobertura !== 'VALIDADA')
                <div class="order-next order-next-attention"><strong>{{ $record->estado_cobertura === 'FUERA_COBERTURA' ? 'Solicitud fuera de cobertura' : 'Falta validar cobertura' }}</strong><span>{{ $record->estado_cobertura === 'FUERA_COBERTURA' ? ($record->detalle_cobertura ?: 'No se confirmó cobertura para esta dirección.') : 'Confirma que la dirección esté dentro de una zona atendida antes de acordar la visita.' }}</span></div>
                @can('office.coordination')
                <details class="order-action coordination-action"><summary>{{ $record->estado_cobertura === 'FUERA_COBERTURA' ? 'Actualizar validación de cobertura' : 'Registrar resultado de cobertura' }}</summary>
                    <form method="POST" action="{{ route('office.coordination.coverage', $record->pedido_id) }}" class="order-form coordination-form">@csrf @method('PATCH')
                        <label>Resultado<select name="estado_cobertura" required><option value="">Selecciona</option><option value="VALIDADA">Cobertura disponible</option><option value="FUERA_COBERTURA">Fuera de cobertura</option></select></label>
                        <label>Detalle del resultado<textarea name="detalle_cobertura" maxlength="500" rows="2" placeholder="Obligatorio si está fuera de cobertura"></textarea></label>
                        <button type="submit" class="action-link">Guardar validación</button>
                    </form>
                </details>
                @endcan
            @endif
            @if($pending)
                <div class="order-next order-next-attention" role="{{ $coordinationStepUrl ? 'group' : 'status' }}"><strong>Cambio de fecha pendiente de Gerencia</strong><span>Se solicitó {{ \Illuminate\Support\Carbon::parse($change->fecha_nueva)->format('d/m/Y · H:i') }} · {{ $change->franja_nueva }}. La fecha vigente permanece hasta que Gerencia resuelva la solicitud.</span>@if($coordinationStepUrl)<a class="order-next-link" href="{{ $coordinationStepUrl }}">Revisar cambio en Pedidos de instalación <span aria-hidden="true">→</span></a>@endif</div>
            @elseif(! $coordinated)
                <div class="order-next order-next-attention" role="group"><strong>Falta confirmar la fecha con el cliente</strong><a class="order-next-link" href="{{ $coordinationStepUrl }}">Confirmar visita <span aria-hidden="true">→</span></a></div>
            @endif
            @if($change?->estado === 'RECHAZADA')<div class="order-next order-next-attention" role="group"><strong>Gerencia rechazó el último cambio</strong><span>{{ $change->decision_observacion }}. La visita vigente no cambió; puedes registrar un nuevo acuerdo.</span><a class="order-next-link" href="{{ $coordinationStepUrl }}">Registrar otro acuerdo <span aria-hidden="true">→</span></a></div>@endif
            @if(! $pending && ($record->contrato_codigo || $record->estado_cobertura === 'VALIDADA'))
                <details class="order-action coordination-action" id="coordinar-visita-{{ $record->pedido_id }}" @if($context) open @endif><summary>{{ $coordinated ? 'Solicitar cambio de fecha' : 'Confirmar fecha de visita' }}</summary>
                    <form method="POST" action="{{ route('office.coordination.save', $record->pedido_id) }}" enctype="multipart/form-data" class="order-form coordination-form" data-coordination-form>@csrf @method('PATCH')
                        <input type="hidden" name="form_context" value="visit-{{ $record->pedido_id }}">
                        <p class="coordination-form-intro">{{ $coordinated ? 'Acuerda el cambio con el cliente. Gerencia revisará la evidencia antes de modificar la visita vigente.' : 'Confirma cobertura y disponibilidad del equipo. Registra aquí el día y la hora exactos que ya acordaste; esta acción confirma la visita.' }}</p>
                        <label>1. {{ $coordinated ? 'Nuevo día acordado' : 'Día acordado' }}<input type="date" name="fecha_visita" min="{{ now('America/Lima')->toDateString() }}" value="{{ $context ? old('fecha_visita', $contractVisitDate) : $contractVisitDate }}" required data-visit-date><small>{{ $contractVisitDate ? 'Fecha tomada del inicio del contrato. Confírmala con el cliente y ajústala si corresponde.' : 'Selecciona el día confirmado con el cliente.' }}</small></label>
                        <label>2. Franja horaria<select name="franja_horaria" required data-visit-window><option value="">Selecciona mañana o tarde</option>@foreach($visitWindows as $window => $bounds)<option value="{{ $window }}" @selected($context && old('franja_horaria') === $window)>{{ $window === '08:00 a 12:00' ? 'Mañana' : 'Tarde' }} · {{ $window }}</option>@endforeach</select><small>Selecciona la franja acordada con el cliente.</small></label>
                        <label>3. Hora de inicio<select name="hora_inicio" required data-visit-time><option value="">Primero selecciona la franja</option>@foreach($visitTimes as $window => $times)@foreach($times as $time)<option value="{{ $time }}" data-window="{{ $window }}" @selected($context && old('hora_inicio') === $time)>{{ $time }}</option>@endforeach @endforeach</select><small>La hora debe estar dentro de la franja elegida.</small></label>
                        <label>4. Medio de confirmación<select name="canal_coordinacion" required data-visit-channel><option value="">Selecciona cómo lo acordaron</option>@foreach(['LLAMADA'=>'Llamada','WHATSAPP'=>'WhatsApp','CORREO'=>'Correo','PRESENCIAL'=>'Presencial'] as $key=>$label)<option value="{{ $key }}" @selected($context && old('canal_coordinacion') === $key)>{{ $label }}</option>@endforeach</select><small>Deja registrado el canal usado con el cliente.</small></label>
                        @if(! $coordinated)<label>Detalle del acuerdo (opcional)<textarea name="detalle_coordinacion" maxlength="500" rows="2">{{ $context ? old('detalle_coordinacion') : '' }}</textarea></label>@endif
                        @if($coordinated)
                            <label>Motivo del cambio<select name="motivo_codigo" required><option value="">Selecciona</option>@foreach($reasonOptions as $code=>$label)<option value="{{ $code }}" @selected($context && old('motivo_codigo') === $code)>{{ $label }}</option>@endforeach</select></label>
                            <label>Detalle adicional (obligatorio si eliges «Otro»)<textarea name="motivo_reprogramacion" maxlength="500" rows="2">{{ $context ? old('motivo_reprogramacion') : '' }}</textarea></label>
                            <label class="order-evidence-input">Archivo que respalda el acuerdo con el cliente *<input type="file" name="evidencia_acuerdo" accept="application/pdf,image/jpeg,image/png,image/webp" required><small>Correo o captura que muestre la fecha y franja aceptadas. PDF o imagen, máximo 5 MB.</small></label>
                        @endif
                        <section class="coordination-review" aria-label="Resumen para revisar antes de guardar" aria-live="polite" data-visit-review>
                            <div class="coordination-review-heading"><div><small>ANTES DE CONTINUAR</small><h4>Revisa la visita acordada</h4></div><span>{{ $coordinated ? 'Gerencia revisará este cambio antes de aplicarlo' : 'Instalaciones verá estos datos al guardar' }}</span></div>
                            <div class="coordination-review-grid">
                                <div><small>Día</small><strong data-summary-date>Por seleccionar</strong></div>
                                <div><small>Franja</small><strong data-summary-window>Por seleccionar</strong></div>
                                <div><small>Hora de inicio</small><strong data-summary-time>Por seleccionar</strong></div>
                                <div><small>Confirmado por</small><strong data-summary-channel>Por seleccionar</strong></div>
                            </div>
                        </section>
                        <button type="submit" class="action-link">{{ $coordinated ? 'Enviar cambio a Gerencia' : 'Guardar fecha confirmada' }}</button>
                    </form>
                </details>
            @endif
        </article>
    @empty
        <div class="installation-panel installation-empty"><h3>No hay visitas que coincidan.</h3><p class="muted">Prueba otra búsqueda o muestra todas las visitas.</p></div>
    @endforelse
    @include('clients.pagination', ['records' => $records, 'label' => 'Paginación de visitas'])
</section>
@endsection
