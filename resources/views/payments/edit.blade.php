@extends('payments.layout')
@section('payments-content')
<div class="management-toolbar">
    <h2>Validar pago</h2>
    <a class="secondary" href="{{ route('payments.show', $payment) }}">← Cancelar</a>
</div>

{{-- Información del pago (solo lectura) --}}
<section aria-labelledby="payment-readonly-info" class="payment-info-card">
    <div class="payment-info-header">
        <h3 id="payment-readonly-info">Información del pago</h3>
        <span class="state {{ 
            $payment->estado === 'REALIZADO' ? 'active' : 
            ($payment->estado === 'VENCIDO' || $payment->estado === 'IMPAGO' ? 'inactive' : '') 
        }}" style="
            @if($payment->estado === 'VENCIDO') background: #fee2e2; color: #991b1b; @endif
            @if($payment->estado === 'IMPAGO') background: #fee2e2; color: #7f1d1d; @endif
            @if($payment->estado === 'PENDIENTE') background: #fef3c7; color: #92400e; @endif
            @if($payment->estado === 'REALIZADO') background: #d1fae5; color: #065f46; @endif
        ">{{ $payment->estado }}</span>
    </div>
    
    <dl class="payment-info-grid">
        <div class="info-item">
            <dt>Código de pago</dt>
            <dd class="highlight-code">{{ $payment->codigo }}</dd>
        </div>
        <div class="info-item">
            <dt>Monto</dt>
            <dd class="highlight-amount">S/ {{ number_format($payment->monto, 2) }}</dd>
        </div>
        <div class="info-item">
            <dt>Contrato</dt>
            <dd>{{ $payment->contract->codigo }}</dd>
        </div>
        <div class="info-item">
            <dt>Cliente</dt>
            <dd>{{ trim(($payment->contract->client->nombres_razon_social ?? '') . ' ' . ($payment->contract->client->apellidos ?? '')) }}</dd>
        </div>
        <div class="info-item">
            <dt>Período de servicio</dt>
            <dd>{{ \Carbon\Carbon::parse($payment->periodo_servicio)->isoFormat('MMMM YYYY') }}</dd>
        </div>
        <div class="info-item">
            <dt>Fecha de vencimiento</dt>
            <dd>{{ $payment->fecha_vencimiento->format('d/m/Y') }}</dd>
        </div>
    </dl>
</section>

<style>
.payment-info-card {
    background: linear-gradient(135deg, #f8fffe 0%, #f0f9f6 100%);
    border: 1px solid #d1fae5;
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 32px;
}

.payment-info-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #d1fae5;
}

.payment-info-header h3 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: #065f46;
}

.payment-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 0;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.info-item dt {
    font-size: 0.75rem;
    font-weight: 500;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-item dd {
    margin: 0;
    font-size: 0.95rem;
    color: #1f2937;
}

.highlight-code {
    font-family: 'Courier New', monospace;
    font-weight: 600;
    color: #065f46 !important;
    font-size: 1rem !important;
}

.highlight-amount {
    font-size: 1.5rem !important;
    font-weight: 700;
    color: #087846 !important;
}

.validation-form-section {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 24px;
}

.validation-form-section h3 {
    margin: 0 0 20px 0;
    font-size: 1rem;
    font-weight: 600;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 8px;
}

.validation-form-section h3::before {
    content: "✓";
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    background: #087846;
    color: white;
    border-radius: 50%;
    font-size: 0.875rem;
    font-weight: bold;
}
</style>

{{-- Formulario de validación --}}
<section class="validation-form-section">
    <h3>Datos del pago realizado</h3>
    
    <form class="account-form" method="POST" action="{{ route('payments.update', $payment) }}" novalidate>
        @csrf
        @method('PUT')
        
        <input type="hidden" name="contrato_id" value="{{ $payment->contrato_id }}">
        <input type="hidden" name="monto" value="{{ $payment->monto }}">
        <input type="hidden" name="periodo_servicio" value="{{ $payment->periodo_servicio }}">
        <input type="hidden" name="fecha_vencimiento" value="{{ $payment->fecha_vencimiento->format('Y-m-d') }}">

        <div class="form-row">
            <div class="form-field">
                <label for="fecha_pago">Fecha de pago <span class="required">*</span></label>
                <input id="fecha_pago" name="fecha_pago" type="date" 
                    value="{{ old('fecha_pago', $payment->fecha_pago?->format('Y-m-d') ?? date('Y-m-d')) }}"
                    @error('fecha_pago') aria-invalid="true" aria-describedby="fecha_pago-error" @enderror
                    required>
                <small class="field-help">Fecha en que el cliente realizó el pago</small>
                @error('fecha_pago')<p id="fecha_pago-error" class="error">{{ $message }}</p>@enderror
            </div>

            <div class="form-field">
                <label for="metodo_pago">Método de pago <span class="required">*</span></label>
                <select id="metodo_pago" name="metodo_pago" 
                    @error('metodo_pago') aria-invalid="true" aria-describedby="metodo_pago-error" @enderror
                    required>
                    <option value="">-- Seleccionar --</option>
                    @foreach(['EFECTIVO' => 'Efectivo', 'TRANSFERENCIA' => 'Transferencia bancaria', 'YAPE' => 'Yape', 'PLIN' => 'Plin', 'TARJETA' => 'Tarjeta', 'OTRO' => 'Otro'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('metodo_pago', $payment->metodo_pago) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <small class="field-help">Cómo realizó el pago el cliente</small>
                @error('metodo_pago')<p id="metodo_pago-error" class="error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="form-field">
            <label for="referencia_externa">Referencia externa</label>
            <input id="referencia_externa" name="referencia_externa" type="text" maxlength="120"
                value="{{ old('referencia_externa', $payment->referencia_externa) }}"
                placeholder="Ej: Operación #123456, Voucher 789, etc."
                @error('referencia_externa') aria-invalid="true" aria-describedby="referencia_externa-error" @enderror>
            <small class="field-help">Número de operación, transacción o comprobante externo</small>
            @error('referencia_externa')<p id="referencia_externa-error" class="error">{{ $message }}</p>@enderror
        </div>

        <div class="form-field checkbox-field">
            <label class="checkbox-label">
                <input type="checkbox" name="marcar_realizado" value="1" 
                    @checked(old('marcar_realizado', $payment->estado === 'REALIZADO'))
                    @if($payment->estado === 'REALIZADO') disabled @endif>
                <span class="checkbox-text">
                    <strong>Marcar pago como realizado</strong>
                    <small>
                        @if($payment->estado === 'REALIZADO')
                            Este pago ya está marcado como realizado
                        @else
                            El estado cambiará a REALIZADO y se generará el comprobante automáticamente
                        @endif
                    </small>
                </span>
            </label>
        </div>

        <div class="form-field">
            <label for="observaciones">Observaciones</label>
            <textarea id="observaciones" name="observaciones" rows="3" maxlength="500"
                placeholder="Información adicional sobre el pago (opcional)..."
                @error('observaciones') aria-invalid="true" aria-describedby="observaciones-error" @enderror>{{ old('observaciones', $payment->observaciones) }}</textarea>
            <small class="field-help">Información adicional sobre el pago</small>
            @error('observaciones')<p id="observaciones-error" class="error">{{ $message }}</p>@enderror
        </div>

        <div class="form-actions" style="margin-top: 24px;">
            <button class="action-link" type="submit">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" style="margin-right: 6px;">
                    <path d="M13.78 4.22a.75.75 0 010 1.06l-7.25 7.25a.75.75 0 01-1.06 0L2.22 9.28a.75.75 0 011.06-1.06L6 10.94l6.72-6.72a.75.75 0 011.06 0z"/>
                </svg>
                Registrar pago
            </button>
            <a class="secondary" href="{{ route('payments.show', $payment) }}">Cancelar</a>
        </div>
    </form>
</section>

<style>
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.form-field {
    margin-bottom: 20px;
}

.form-field label {
    display: block;
    font-weight: 500;
    margin-bottom: 6px;
    color: #374151;
}

.required {
    color: #dc2626;
}

.field-help {
    display: block;
    margin-top: 4px;
    color: #6b7280;
    font-size: 0.875rem;
}

.checkbox-field {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 16px;
}

.checkbox-label {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    cursor: pointer;
    margin: 0;
}

.checkbox-label input[type="checkbox"] {
    margin-top: 3px;
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.checkbox-text {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.checkbox-text strong {
    color: #1f2937;
    font-weight: 600;
}

.checkbox-text small {
    color: #6b7280;
    font-weight: normal;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

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
