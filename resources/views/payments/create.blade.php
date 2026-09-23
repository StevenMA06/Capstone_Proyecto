@extends('payments.layout')
@section('payments-content')
<div class="management-toolbar">
    <h2>{{ $payment->exists ? 'Editar pago #'.$payment->codigo : 'Registrar pago' }}</h2>
    <a class="secondary" href="{{ route('payments.index') }}">Ver pagos</a>
</div>

@if($payment->exists && $payment->estado === 'REALIZADO')
    <div class="notice" role="alert">
        <strong>⚠️ Este pago ya está realizado y no puede ser modificado.</strong>
        <p>Los pagos realizados son definitivos para mantener la integridad del historial financiero.</p>
    </div>
    <a class="action-link" href="{{ route('payments.show', $payment) }}">Ver detalle del pago</a>
@elseif($payment->exists && in_array($payment->estado, ['IMPAGO', 'ANULADO']))
    <div class="notice error" role="alert">
        <strong>⚠️ Este pago está {{ $payment->estado === 'IMPAGO' ? 'marcado como IMPAGO' : 'anulado' }} y no puede ser modificado.</strong>
        <p>{{ $payment->estado === 'IMPAGO' ? 'El pago superó el período de tolerancia de 7 días.' : 'El pago fue anulado por error administrativo.' }}</p>
    </div>
    <a class="action-link" href="{{ route('payments.show', $payment) }}">Ver detalle del pago</a>
@else
<p class="muted">
    {{ $payment->exists ? 'Completa los datos del pago cuando el cliente realice el pago.' : 'Los campos marcados con * son obligatorios. El código se genera automáticamente.' }}
</p>
<form class="account-form" method="POST" action="{{ $payment->exists ? route('payments.update', $payment) : route('payments.store') }}" novalidate>
    @csrf
    @if($payment->exists) @method('PUT') @endif
    
    <div>
        <label for="contrato_id">Contrato *</label>
        <select id="contrato_id" name="contrato_id" required 
            @if($payment->exists) disabled @endif
            @error('contrato_id') aria-invalid="true" aria-describedby="contrato_id-error" @enderror>
            <option value="">Selecciona un contrato activo</option>
            @foreach($contracts as $contract)
                <option value="{{ $contract->contrato_id }}" 
                    data-client="{{ trim($contract->client->nombres_razon_social . ' ' . $contract->client->apellidos) }}"
                    data-plan="{{ $contract->plan?->nombre ?? 'Sin plan' }}"
                    data-monthly="{{ $contract->plan?->precio_mensual ?? 0 }}"
                    @selected(old('contrato_id', $payment->contrato_id) == $contract->contrato_id)>
                    {{ $contract->codigo }} - {{ trim($contract->client->nombres_razon_social . ' ' . $contract->client->apellidos) }}
                </option>
            @endforeach
        </select>
        @if($payment->exists)
            <input type="hidden" name="contrato_id" value="{{ $payment->contrato_id }}">
            <small>El contrato no puede modificarse en un pago existente.</small>
        @else
            <small>Solo se muestran contratos con estado ACTIVO.</small>
        @endif
        @error('contrato_id')<p id="contrato_id-error" class="error">{{ $message }}</p>@enderror
    </div>

    <div id="contract-info" class="notice" style="display: none; background: #f4f7f6; padding: 12px; border-left: 3px solid #087846; margin-bottom: 16px;">
        <strong>Información del contrato seleccionado:</strong>
        <p style="margin: 4px 0 0 0; font-size: 0.9rem;">
            <span id="info-client"></span><br>
            <span id="info-plan"></span><br>
            <span id="info-monthly"></span>
        </p>
    </div>

    <div>
        <label for="monto">Monto a pagar (S/) *</label>
        <input id="monto" name="monto" type="number" step="0.01" min="0.01" max="999999.99" 
            value="{{ old('monto', $payment->monto) }}" required
            @if($payment->exists) readonly @endif
            @error('monto') aria-invalid="true" aria-describedby="monto-error" @enderror>
        <small>{{ $payment->exists ? 'El monto no puede modificarse en un pago existente.' : 'Ingresa el monto en soles.' }}</small>
        @error('monto')<p id="monto-error" class="error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="periodo_servicio">Período de servicio *</label>
        <input id="periodo_servicio" name="periodo_servicio" type="text" placeholder="YYYY-MM (Ej: 2026-09)" 
            pattern="\d{4}-(0[1-9]|1[0-2])" maxlength="7"
            value="{{ old('periodo_servicio', $payment->periodo_servicio) }}" required
            @if($payment->exists) readonly @endif
            @error('periodo_servicio') aria-invalid="true" aria-describedby="periodo_servicio-error" @enderror>
        <small>{{ $payment->exists ? 'El período de servicio no puede modificarse.' : 'Formato: YYYY-MM (Ej: 2026-09 para septiembre 2026).' }}</small>
        @error('periodo_servicio')<p id="periodo_servicio-error" class="error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="fecha_vencimiento">Fecha de vencimiento *</label>
        <input id="fecha_vencimiento" name="fecha_vencimiento" type="date" 
            value="{{ old('fecha_vencimiento', $payment->fecha_vencimiento?->format('Y-m-d')) }}" required
            @if($payment->exists) readonly @endif
            @if(!$payment->exists) min="{{ date('Y-m-d') }}" @endif
            @error('fecha_vencimiento') aria-invalid="true" aria-describedby="fecha_vencimiento-error" @enderror>
        <small>{{ $payment->exists ? 'La fecha de vencimiento no puede modificarse.' : 'Debe ser igual o posterior a hoy.' }}</small>
        @error('fecha_vencimiento')<p id="fecha_vencimiento-error" class="error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="fecha_pago">Fecha de pago (opcional)</label>
        <input id="fecha_pago" name="fecha_pago" type="date" 
            value="{{ old('fecha_pago', $payment->fecha_pago?->format('Y-m-d')) }}" 
            max="{{ date('Y-m-d') }}"
            @error('fecha_pago') aria-invalid="true" aria-describedby="fecha_pago-error" @enderror>
        <small>Déjalo vacío si aún no se realizó el pago. No puede ser posterior a hoy.</small>
        @error('fecha_pago')<p id="fecha_pago-error" class="error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="metodo_pago">Método de pago (opcional)</label>
        <select id="metodo_pago" name="metodo_pago" @error('metodo_pago') aria-invalid="true" aria-describedby="metodo_pago-error" @enderror>
            <option value="">-- Seleccionar --</option>
            @foreach(['EFECTIVO', 'TRANSFERENCIA', 'YAPE', 'PLIN', 'TARJETA', 'OTRO'] as $metodo)
                <option value="{{ $metodo }}" @selected(old('metodo_pago', $payment->metodo_pago) === $metodo)>{{ $metodo }}</option>
            @endforeach
        </select>
        <small>Requerido si indicaste fecha de pago.</small>
        @error('metodo_pago')<p id="metodo_pago-error" class="error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="referencia_externa">Referencia externa (opcional)</label>
        <input id="referencia_externa" name="referencia_externa" type="text" maxlength="120"
            value="{{ old('referencia_externa', $payment->referencia_externa) }}"
            @error('referencia_externa') aria-invalid="true" aria-describedby="referencia_externa-error" @enderror>
        <small>Número de operación, transacción o comprobante externo.</small>
        @error('referencia_externa')<p id="referencia_externa-error" class="error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="observaciones">Observaciones (opcional)</label>
        <textarea id="observaciones" name="observaciones" rows="5" maxlength="500"
            @error('observaciones') aria-invalid="true" aria-describedby="observaciones-error" @enderror>{{ old('observaciones', $payment->observaciones) }}</textarea>
        <small>Información adicional sobre el pago.</small>
        @error('observaciones')<p id="observaciones-error" class="error">{{ $message }}</p>@enderror
    </div>

    <div class="form-actions">
        <button class="action-link" type="submit">{{ $payment->exists ? 'Guardar cambios' : 'Registrar pago' }}</button>
        <a class="secondary" href="{{ $payment->exists ? route('payments.show', $payment) : route('payments.index') }}">Cancelar</a>
    </div>
</form>
@endif
<script src="{{ asset('js/payments.js') }}" defer></script>
@endsection
