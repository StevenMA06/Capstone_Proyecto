@extends('layouts.management')
@section('title', 'Coordinación de visitas')
@push('styles')<link rel="stylesheet" href="{{ asset('css/installations.css') }}?v=29"><script src="{{ asset('js/coordination.js') }}?v=4" defer></script>@endpush
@section('workspace-content')
<nav class="workspace-breadcrumb" aria-label="Ruta de navegación"><a href="{{ route('dashboard') }}">Inicio</a><span aria-hidden="true">›</span><span aria-current="page">Coordinación de visitas</span></nav>
<header class="installation-heading"><div><p class="eyebrow">CALL CENTER</p><h1>Coordinación de visitas</h1><p class="muted">Acuerda la fecha con el cliente y regístrala aquí. Instalaciones verá la visita confirmada en su pedido.</p></div></header>
@if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="notice" role="alert"><strong>Revisa los campos indicados:</strong>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

<form method="GET" action="{{ route('office.coordination.index') }}" class="installation-panel order-filters">
    <label for="visit-search">Pedido, contrato o cliente<input id="visit-search" type="search" name="search" maxlength="150" value="{{ request('search') }}" placeholder="Buscar por código o nombre"></label>
    <label for="visit-state">Visitas<select id="visit-state" name="estado"><option value="">Todas</option><option value="por_confirmar" @selected(request('estado') === 'por_confirmar')>Por confirmar</option><option value="confirmadas" @selected(request('estado') === 'confirmadas')>Con fecha confirmada</option></select></label>
    <div class="order-filter-actions"><button type="submit" class="action-link">Buscar</button><a class="action-link secondary" href="{{ route('office.coordination.index') }}">Limpiar</a></div>
</form>

@if($unplannedContracts->isNotEmpty())
<section class="order-list" aria-labelledby="contracts-awaiting-visit">
    <header class="installation-list-heading"><div><h2 id="contracts-awaiting-visit">Visitas por coordinar</h2><p class="muted">Cada contrato genera su pedido de instalación al registrarse. Confirma aquí la fecha y hora acordadas con el cliente; la firma del contrato se verifica desde Contratos.</p></div></header>
    @foreach($unplannedContracts as $candidate)
    <article class="installation-panel order-card coordination-candidate" id="contrato-{{ $candidate->contrato_id }}">
        <div class="order-card-header"><div><p class="order-card-kicker">CONTRATO {{ $candidate->codigo }}</p><h3>{{ trim($candidate->cliente_nombre.' '.$candidate->cliente_apellidos) }}</h3></div><span class="installation-state state-reprogramado">Visita por confirmar</span></div>
        <div class="order-card-primary coordination-candidate-meta"><div><small>Dirección del servicio</small><strong>{{ $candidate->direccion_servicio }}</strong></div><div class="order-card-contact"><small>Contacto del cliente</small>@if($candidate->cliente_telefono)<a href="tel:{{ preg_replace('/[^0-9+]/', '', (string) $candidate->cliente_telefono) }}">{{ $candidate->cliente_telefono }}</a>@else<strong>Teléfono sin registrar</strong>@endif @if($candidate->cliente_correo)<a href="mailto:{{ $candidate->cliente_correo }}">{{ $candidate->cliente_correo }}</a>@else<span>Correo sin registrar</span>@endif</div><div class="order-card-contract-start"><small>Inicio del contrato</small><strong>{{ $candidate->contrato_fecha_inicio ? \Illuminate\Support\Carbon::parse($candidate->contrato_fecha_inicio)->format('d/m/Y') : 'Sin registrar' }}</strong></div><div><small>Estado del contrato</small><strong>{{ $candidate->estado === 'ACTIVO' ? 'Firma verificada' : 'Firma pendiente' }}</strong></div></div>
        <details class="order-action coordination-action" @if(old('contract_context') == $candidate->contrato_id) open @endif><summary>Registrar visita pendiente</summary>
            <form method="POST" action="{{ route('office.coordination.contract', $candidate->contrato_id) }}" class="order-form coordination-form coordination-create-form" data-coordination-form>@csrf
                <input type="hidden" name="contract_context" value="{{ $candidate->contrato_id }}">
                <div class="coordination-create-intro"><strong>Datos de la visita</strong><span>Registra el horario acordado. Instalaciones recibirá el pedido al guardar.</span></div>
                <label>Día acordado<input type="date" name="fecha_visita" min="{{ now('America/Lima')->toDateString() }}" value="{{ old('contract_context') == $candidate->contrato_id ? old('fecha_visita') : '' }}" required data-visit-date><small>Selecciona el día aceptado por el cliente.</small></label>
                <label>Franja horaria<select name="franja_horaria" required data-visit-window><option value="">Selecciona mañana o tarde</option>@foreach($visitWindows as $window => $bounds)<option value="{{ $window }}" @selected(old('contract_context') == $candidate->contrato_id && old('franja_horaria') === $window)>{{ $window === '08:00 a 12:00' ? 'Mañana' : 'Tarde' }} · {{ $window }}</option>@endforeach</select><small>Solo se ofrecen las franjas disponibles.</small></label>
                <label>Hora de inicio<select name="hora_inicio" required data-visit-time><option value="">Primero selecciona la franja</option>@foreach($visitTimes as $window => $times)@foreach($times as $time)<option value="{{ $time }}" data-window="{{ $window }}" @selected(old('contract_context') == $candidate->contrato_id && old('hora_inicio') === $time)>{{ $time }}</option>@endforeach @endforeach</select><small>Debe estar dentro de la franja elegida.</small></label>
                <label>Medio de confirmación<select name="canal_coordinacion" required data-visit-channel><option value="">Selecciona cómo lo acordaron</option>@foreach(['LLAMADA'=>'Llamada','WHATSAPP'=>'WhatsApp','CORREO'=>'Correo','PRESENCIAL'=>'Presencial'] as $key=>$label)<option value="{{ $key }}" @selected(old('contract_context') == $candidate->contrato_id && old('canal_coordinacion') === $key)>{{ $label }}</option>@endforeach</select><small>Así queda registrado cómo se acordó la visita.</small></label>
                <label class="coordination-create-note">Detalle del acuerdo (opcional)<textarea name="detalle_coordinacion" maxlength="500" rows="2" placeholder="Por ejemplo, una referencia para llegar o una indicación del cliente">{{ old('contract_context') == $candidate->contrato_id ? old('detalle_coordinacion') : '' }}</textarea></label>
                <label class="coordination-consent"><input type="checkbox" name="aceptacion_cliente" value="1" required @checked(old('contract_context') == $candidate->contrato_id && old('aceptacion_cliente'))><span><strong>El cliente aceptó esta visita</strong><small>Confirmo el día, la hora y las condiciones del servicio acordadas con el cliente.</small></span></label>
                <section class="coordination-review" aria-label="Resumen de la visita" aria-live="polite" data-visit-review><div class="coordination-review-heading"><div><small>ANTES DE CONTINUAR</small><h4>Revisa la visita acordada</h4></div><span>Instalaciones verá estos datos al guardar</span></div><div class="coordination-review-grid"><div><small>Día</small><strong data-summary-date>Por seleccionar</strong></div><div><small>Franja</small><strong data-summary-window>Por seleccionar</strong></div><div><small>Hora de inicio</small><strong data-summary-time>Por seleccionar</strong></div><div><small>Confirmado por</small><strong data-summary-channel>Por seleccionar</strong></div></div></section>
                <div class="coordination-create-footer"><span>La firma del contrato se verifica después en Contratos.</span><button type="submit" class="action-link">Guardar visita y crear pedido</button></div>
            </form>
        </details>
    </article>
    @endforeach
</section>
@endif

<section class="order-list" aria-labelledby="visit-list-title">
    <header class="installation-list-heading"><div><h2 id="visit-list-title">Visitas de clientes</h2><p class="muted">{{ $records->total() }} {{ $records->total() === 1 ? 'pedido encontrado' : 'pedidos encontrados' }}</p></div></header>
    @forelse($records as $record)
        @php
            $coordinated = (bool) $record->coordinado_en || (! $record->fase_flujo && (bool) $record->fecha_programada);
            $visitDate = $record->fecha_programada ? \Illuminate\Support\Carbon::parse($record->fecha_programada) : null;
            $change = $latestChanges->get($record->pedido_id);
            $pending = $change?->estado === 'SOLICITADA';
            $context = old('form_context') === 'visit-'.$record->pedido_id;
            $coordinationStepUrl = $pending
                ? (auth()->user()->hasPermission('orders.approve') ? route('orders.index', ['reprogramacion' => 'pendiente']).'#pedido-'.$record->pedido_id : null)
                : ((! $coordinated || $change?->estado === 'RECHAZADA') ? '#coordinar-visita-'.$record->pedido_id : null);
        @endphp
        <article class="installation-panel order-card" id="visita-{{ $record->pedido_id }}">
            <div class="order-card-header"><div><p class="order-card-kicker">Pedido {{ $record->codigo }} · Contrato {{ $record->contrato_codigo }}</p><h3>{{ trim($record->cliente_nombre.' '.$record->cliente_apellidos) }}</h3></div><span class="installation-state {{ $coordinated ? 'state-programado' : 'state-reprogramado' }}">{{ $coordinated ? 'Fecha confirmada' : 'Por confirmar' }}</span></div>
            <div class="coordination-info order-card-primary">
                <div class="order-card-visit"><small>Visita vigente</small><dl>
                    <div><dt>Día</dt><dd>{{ $visitDate ? ucfirst($visitDate->locale('es')->isoFormat('dddd')).' '.$visitDate->format('d/m/Y') : 'Por confirmar' }}</dd></div>
                    <div><dt>Hora de inicio</dt><dd>{{ $visitDate ? $visitDate->format('H:i') : 'Por confirmar' }}</dd></div>
                    <div><dt>Franja</dt><dd>{{ $record->franja_horaria ?: 'Por confirmar' }}</dd></div>
                </dl></div>
                <div class="order-card-address"><small>Dirección del servicio</small><strong>{{ $record->direccion_servicio }}</strong></div>
                <div class="order-card-contact"><small>Contacto del cliente</small>@if($record->cliente_telefono)<a href="tel:{{ preg_replace('/[^0-9+]/', '', (string) $record->cliente_telefono) }}">{{ $record->cliente_telefono }}</a>@else<strong>Teléfono sin registrar</strong>@endif @if($record->cliente_correo)<a href="mailto:{{ $record->cliente_correo }}">{{ $record->cliente_correo }}</a>@else<span>Correo sin registrar</span>@endif</div>
                <div class="order-card-contract-start"><small>Inicio del contrato</small><strong>{{ $record->contrato_fecha_inicio ? \Illuminate\Support\Carbon::parse($record->contrato_fecha_inicio)->format('d/m/Y') : 'Sin registrar' }}</strong></div>
            </div>
            @if($pending)
                <div class="order-next order-next-attention" role="{{ $coordinationStepUrl ? 'group' : 'status' }}"><strong>Cambio de fecha pendiente de Gerencia</strong><span>Se solicitó {{ \Illuminate\Support\Carbon::parse($change->fecha_nueva)->format('d/m/Y · H:i') }} · {{ $change->franja_nueva }}. La fecha vigente permanece hasta que Gerencia resuelva la solicitud.</span>@if($coordinationStepUrl)<a class="order-next-link" href="{{ $coordinationStepUrl }}">Revisar cambio en Pedidos de instalación <span aria-hidden="true">→</span></a>@endif</div>
            @elseif(! $coordinated)
                <div class="order-next order-next-attention" role="group"><strong>Falta confirmar la fecha con el cliente</strong><a class="order-next-link" href="{{ $coordinationStepUrl }}">Confirmar visita <span aria-hidden="true">→</span></a></div>
            @endif
            @if($change?->estado === 'RECHAZADA')<div class="order-next order-next-attention" role="group"><strong>Gerencia rechazó el último cambio</strong><span>{{ $change->decision_observacion }}. La visita vigente no cambió; puedes registrar un nuevo acuerdo.</span><a class="order-next-link" href="{{ $coordinationStepUrl }}">Registrar otro acuerdo <span aria-hidden="true">→</span></a></div>@endif
            @if(! $pending)
                <details class="order-action coordination-action" id="coordinar-visita-{{ $record->pedido_id }}" @if($context) open @endif><summary>{{ $coordinated ? 'Solicitar cambio de fecha' : 'Confirmar fecha de visita' }}</summary>
                    <form method="POST" action="{{ route('office.coordination.save', $record->pedido_id) }}" enctype="multipart/form-data" class="order-form coordination-form" data-coordination-form>@csrf @method('PATCH')
                        <input type="hidden" name="form_context" value="visit-{{ $record->pedido_id }}">
                        <p class="coordination-form-intro">{{ $coordinated ? 'Acuerda el cambio con el cliente. Gerencia revisará la evidencia antes de modificar la visita vigente.' : 'Asegúrate de que el cliente acepte este día y horario antes de guardar. Instalaciones verá la confirmación de inmediato.' }}</p>
                        <label>1. {{ $coordinated ? 'Nuevo día acordado' : 'Día acordado' }}<input type="date" name="fecha_visita" min="{{ now('America/Lima')->toDateString() }}" value="{{ $context ? old('fecha_visita') : '' }}" required data-visit-date><small>Selecciona el día confirmado con el cliente.</small></label>
                        <label>2. Franja horaria<select name="franja_horaria" required data-visit-window><option value="">Selecciona mañana o tarde</option>@foreach($visitWindows as $window => $bounds)<option value="{{ $window }}" @selected($context && old('franja_horaria') === $window)>{{ $window === '08:00 a 12:00' ? 'Mañana' : 'Tarde' }} · {{ $window }}</option>@endforeach</select><small>Solo se programan visitas dentro de estas franjas de prueba.</small></label>
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
