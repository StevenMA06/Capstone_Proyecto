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
<form class="payment-validation-form" method="POST" action="{{ route('payments.update', $payment) }}" novalidate>
    @csrf
    @method('PUT')
    
    <input type="hidden" name="contrato_id" value="{{ $payment->contrato_id }}">
    <input type="hidden" name="monto" value="{{ $payment->monto }}">
    <input type="hidden" name="periodo_servicio" value="{{ $payment->periodo_servicio }}">
    <input type="hidden" name="fecha_vencimiento" value="{{ $payment->fecha_vencimiento->format('Y-m-d') }}">
    <input type="hidden" name="marcar_realizado" value="1">

    <section class="form-section">
        <h3><i class="bi bi-cash-stack"></i> Datos del pago realizado</h3>
        
        <div class="form-grid">
            <div class="form-group">
                <label for="fecha_pago">Fecha de pago <span class="required">*</span></label>
                <input id="fecha_pago" name="fecha_pago" type="date" 
                    value="{{ old('fecha_pago', $payment->fecha_pago?->format('Y-m-d') ?? date('Y-m-d')) }}"
                    @error('fecha_pago') aria-invalid="true" @enderror
                    required>
                @error('fecha_pago')<span class="error-msg">{{ $message }}</span>@enderror
            </div>

            <div class="form-group">
                <label for="metodo_pago">Método de pago <span class="required">*</span></label>
                <select id="metodo_pago" name="metodo_pago" 
                    @error('metodo_pago') aria-invalid="true" @enderror
                    required>
                    <option value="">-- Seleccionar método --</option>
                    @foreach(['EFECTIVO' => 'Efectivo', 'TRANSFERENCIA' => 'Transferencia', 'YAPE' => 'Yape', 'PLIN' => 'Plin', 'TARJETA' => 'Tarjeta', 'OTRO' => 'Otro'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('metodo_pago', $payment->metodo_pago) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('metodo_pago')<span class="error-msg">{{ $message }}</span>@enderror
            </div>

            <div class="form-group full-width">
                <label for="referencia_externa">Referencia externa (opcional)</label>
                <input id="referencia_externa" name="referencia_externa" type="text" maxlength="120"
                    value="{{ old('referencia_externa', $payment->referencia_externa) }}"
                    placeholder="Ej: Operación #123456, Voucher 789, etc."
                    @error('referencia_externa') aria-invalid="true" @enderror>
                @error('referencia_externa')<span class="error-msg">{{ $message }}</span>@enderror
            </div>

            <div class="form-group full-width">
                <label for="observaciones">Observaciones (opcional)</label>
                <textarea id="observaciones" name="observaciones" rows="3" maxlength="500"
                    placeholder="Información adicional sobre el pago..."
                    @error('observaciones') aria-invalid="true" @enderror>{{ old('observaciones', $payment->observaciones) }}</textarea>
                @error('observaciones')<span class="error-msg">{{ $message }}</span>@enderror
            </div>
        </div>

        @if($payment->estado !== 'REALIZADO')
        <div class="info-banner">
            <i class="bi bi-info-circle-fill"></i>
            <span>Al enviar este formulario, el pago se marcará como <strong>REALIZADO</strong> y se generará automáticamente el comprobante PDF.</span>
        </div>
        @endif
    </section>

    <div class="form-actions">
        <button class="btn-primary" type="submit">
            <i class="bi bi-check-circle"></i>
            Confirmar pago
        </button>
        <a class="btn-secondary" href="{{ route('payments.show', $payment) }}">Cancelar</a>
    </div>
</form>

<style>
.payment-validation-form {
    width: 100%;
}

.form-section {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 32px;
    margin-bottom: 24px;
}

.form-section h3 {
    margin: 0 0 24px 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: #111827;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-section h3 i {
    color: #087846;
    font-size: 1.25rem;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    font-weight: 500;
    color: #374151;
    font-size: 0.9375rem;
}

.required {
    color: #dc2626;
    margin-left: 2px;
}

.form-group input[type="date"],
.form-group input[type="text"],
.form-group select,
.form-group textarea {
    padding: 10px 14px;
    border: 1.5px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.9375rem;
    font-family: inherit;
    transition: all 0.2s ease;
    background: white;
}

.form-group input[type="date"]:focus,
.form-group input[type="text"]:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #087846;
    box-shadow: 0 0 0 3px rgba(8, 120, 70, 0.1);
}

.form-group textarea {
    resize: vertical;
    min-height: 80px;
}

.form-group select {
    cursor: pointer;
}

.error-msg {
    color: #dc2626;
    font-size: 0.875rem;
    margin-top: -4px;
}

.form-group input[aria-invalid="true"],
.form-group select[aria-invalid="true"],
.form-group textarea[aria-invalid="true"] {
    border-color: #dc2626;
}

.info-banner {
    margin-top: 24px;
    padding: 16px 20px;
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    border: 1px solid #10b981;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
    color: #065f46;
    font-size: 0.9375rem;
}

.info-banner i {
    font-size: 1.25rem;
    color: #10b981;
    flex-shrink: 0;
}

.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-start;
}

.btn-primary,
.btn-secondary {
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9375rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    border: none;
}

.btn-primary {
    background: #087846;
    color: white;
}

.btn-primary:hover {
    background: #065f37;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(8, 120, 70, 0.2);
}

.btn-secondary {
    background: #f3f4f6;
    color: #374151;
}

.btn-secondary:hover {
    background: #e5e7eb;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-section {
        padding: 24px 20px;
    }
}
</style>

{{-- Sección de anular pago --}}
@if($payment->canBeCancelled())
<section aria-labelledby="cancel-section" style="margin-top: 48px; padding-top: 24px; border-top: 1px solid #e5e7eb;">
    <details class="client-deactivate">
        <summary style="color: #dc2626; font-weight: 600; font-size: 1.05rem; cursor: pointer;">
            <i class="bi bi-exclamation-triangle-fill" style="margin-right: 8px;"></i>
            Anular pago
        </summary>
        <div style="margin-top: 20px; padding: 20px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;">
            <div style="display: flex; gap: 12px; margin-bottom: 16px;">
                <div style="flex-shrink: 0;">
                    <i class="bi bi-info-circle-fill" style="color: #dc2626; font-size: 1.25rem;"></i>
                </div>
                <div>
                    <p style="margin: 0 0 8px 0; color: #991b1b; font-weight: 500;">
                        Esta acción es irreversible
                    </p>
                    <p class="muted" style="margin: 0; color: #7f1d1d; font-size: 0.9rem;">
                        El pago se marcará como anulado por error administrativo. La información se conserva en el historial pero no se puede volver a activar.
                    </p>
                </div>
            </div>
            
            <form method="POST" action="{{ route('payments.cancel', $payment) }}" style="max-width: 600px;">
                @csrf @method('PATCH')
                <div style="margin-bottom: 16px;">
                    <label for="observaciones_anular" style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151;">
                        Motivo de anulación <span style="color: #dc2626;">*</span>
                    </label>
                    <textarea 
                        id="observaciones_anular" 
                        name="observaciones" 
                        rows="3" 
                        maxlength="500" 
                        required 
                        placeholder="Describe el motivo de la anulación..."
                        style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.95rem; font-family: inherit; resize: vertical;"
                    ></textarea>
                    <small style="display: block; margin-top: 6px; color: #6b7280;">
                        <i class="bi bi-pencil" style="margin-right: 4px;"></i>
                        Requerido. Explica por qué se anula este pago (máx. 500 caracteres).
                    </small>
                </div>
                <button class="secondary danger" type="submit" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; font-weight: 600;">
                    <i class="bi bi-x-circle-fill"></i>
                    Confirmar anulación
                </button>
            </form>
        </div>
    </details>
</section>
@endif

@endsection
