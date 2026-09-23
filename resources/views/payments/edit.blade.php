@extends('payments.layout')
@section('payments-content')
<div class="management-toolbar">
    <h2>Validar pago</h2>
    <a class="secondary" href="{{ route('payments.show', $payment) }}">← Cancelar</a>
</div>

<p class="muted">
    Registra los datos del pago realizado por el cliente. Solo puedes modificar los campos de validación.
</p>

{{-- Información del pago (solo lectura) --}}
<section aria-labelledby="payment-readonly-info" style="background: #f4f7f6; padding: 16px; border-radius: 4px; margin-bottom: 24px;">
    <h3 id="payment-readonly-info" style="margin-bottom: 12px; font-size: 1rem; color: #374151;">Información del pago</h3>
    <dl class="account-detail" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin: 0;">
        <div><dt>Código</dt><dd><strong>{{ $payment->codigo }}</strong></dd></div>
        <div><dt>Contrato</dt><dd>{{ $payment->contract->codigo }}</dd></div>
        <div><dt>Cliente</dt><dd>{{ trim(($payment->contract->client->nombres_razon_social ?? '') . ' ' . ($payment->contract->client->apellidos ?? '')) }}</dd></div>
        <div><dt>Monto</dt><dd><strong style="color: #087846;">S/ {{ number_format($payment->monto, 2) }}</strong></dd></div>
        <div><dt>Período</dt><dd>{{ $payment->periodo_servicio }}</dd></div>
        <div><dt>Vencimiento</dt><dd>{{ $payment->fecha_vencimiento->format('d/m/Y') }}</dd></div>
    </dl>
</section>

{{-- Formulario de validación --}}
<form class="account-form" method="POST" action="{{ route('payments.update', $payment) }}" novalidate>
    @csrf
    @method('PUT')
    
    <input type="hidden" name="contrato_id" value="{{ $payment->contrato_id }}">
    <input type="hidden" name="monto" value="{{ $payment->monto }}">
    <input type="hidden" name="periodo_servicio" value="{{ $payment->periodo_servicio }}">
    <input type="hidden" name="fecha_vencimiento" value="{{ $payment->fecha_vencimiento->format('Y-m-d') }}">

    <h3 style="margin-bottom: 16px; font-size: 1rem; color: #087846;">Datos del pago realizado</h3>

    <div>
        <label for="fecha_pago">Fecha de pago</label>
        <input id="fecha_pago" name="fecha_pago" type="date" 
            value="{{ old('fecha_pago', $payment->fecha_pago?->format('Y-m-d') ?? date('Y-m-d')) }}"
            @error('fecha_pago') aria-invalid="true" aria-describedby="fecha_pago-error" @enderror>
        <small>Fecha en que el cliente realizó el pago.</small>
        @error('fecha_pago')<p id="fecha_pago-error" class="error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="metodo_pago">Método de pago</label>
        <select id="metodo_pago" name="metodo_pago" @error('metodo_pago') aria-invalid="true" aria-describedby="metodo_pago-error" @enderror>
            <option value="">-- Seleccionar --</option>
            @foreach(['EFECTIVO', 'TRANSFERENCIA', 'YAPE', 'PLIN', 'TARJETA', 'OTRO'] as $metodo)
                <option value="{{ $metodo }}" @selected(old('metodo_pago', $payment->metodo_pago) === $metodo)>{{ $metodo }}</option>
            @endforeach
        </select>
        <small>Cómo realizó el pago el cliente.</small>
        @error('metodo_pago')<p id="metodo_pago-error" class="error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="referencia_externa">Referencia externa (opcional)</label>
        <input id="referencia_externa" name="referencia_externa" type="text" maxlength="120"
            value="{{ old('referencia_externa', $payment->referencia_externa) }}"
            placeholder="Número de operación, voucher, etc."
            @error('referencia_externa') aria-invalid="true" aria-describedby="referencia_externa-error" @enderror>
        <small>Número de operación, transacción o comprobante externo.</small>
        @error('referencia_externa')<p id="referencia_externa-error" class="error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label>
            <input type="checkbox" name="marcar_realizado" value="1" 
                @checked(old('marcar_realizado', $payment->estado === 'REALIZADO'))
                @if($payment->estado === 'REALIZADO') disabled @endif>
            <span>Marcar pago como realizado</span>
        </label>
        <small>
            @if($payment->estado === 'REALIZADO')
                Este pago ya está marcado como realizado.
            @else
                Al marcar esta opción, el estado cambiará a REALIZADO y no podrá revertirse.
            @endif
        </small>
    </div>

    <div>
        <label for="observaciones">Observaciones (opcional)</label>
        <textarea id="observaciones" name="observaciones" rows="3" maxlength="500"
            placeholder="Información adicional sobre el pago..."
            @error('observaciones') aria-invalid="true" aria-describedby="observaciones-error" @enderror>{{ old('observaciones', $payment->observaciones) }}</textarea>
        <small>Información adicional sobre el pago.</small>
        @error('observaciones')<p id="observaciones-error" class="error">{{ $message }}</p>@enderror
    </div>

    <div class="form-actions">
        <button class="action-link" type="submit">Registrar pago</button>
        <a class="secondary" href="{{ route('payments.show', $payment) }}">Cancelar</a>
    </div>
</form>

{{-- Sección de anular pago --}}
@if($payment->canBeCancelled())
<section aria-labelledby="cancel-section" style="margin-top: 48px; padding-top: 24px; border-top: 1px solid #e5e7eb;">
    <details class="client-deactivate">
        <summary style="color: #dc2626;">Anular pago</summary>
        <p class="muted">El pago se marcará como anulado por error administrativo. La información se conserva en el historial pero no se puede volver a activar.</p>
        <form method="POST" action="{{ route('payments.cancel', $payment) }}" style="max-width: 500px;">
            @csrf @method('PATCH')
            <div style="margin-bottom: 12px;">
                <label for="observaciones_anular">Motivo de anulación *</label>
                <textarea id="observaciones_anular" name="observaciones" rows="2" maxlength="500" required placeholder="Describe el motivo de la anulación..."></textarea>
                <small>Requerido. Explica por qué se anula este pago.</small>
            </div>
            <button class="secondary danger" type="submit">Confirmar anulación</button>
        </form>
    </details>
</section>
@endif

@endsection
