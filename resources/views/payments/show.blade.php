@extends('payments.layout')
@section('payments-content')
<div class="management-toolbar">
    <div>
        <h2>Información del Pago</h2>
        <p class="muted">Código: {{ $payment->codigo }}</p>
    </div>
    <div class="row-actions">
        <a class="secondary" href="{{ route('payments.index') }}">← Volver a pagos</a>
        @if($payment->canBeEdited())
            @can('payments.manage')<a class="action-link" href="{{ route('payments.edit', $payment) }}">Validar pago</a>@endcan
        @endif
        @if($payment->constancia_ruta && $payment->isCompleted())
            <a class="action-link" href="{{ route('payments.receipt', $payment) }}" target="_blank" title="Ver comprobante de pago">
                <i class="bi bi-file-pdf"></i> Ver comprobante
            </a>
        @endif
    </div>
</div>

<section aria-labelledby="payment-info-title">
    <h3 id="payment-info-title" style="margin-bottom: 16px; font-size: 1.1rem; color: #087846;">Datos del Pago</h3>
    <dl class="account-detail">
        <div><dt>Código</dt><dd><strong>{{ $payment->codigo }}</strong></dd></div>
        <div><dt>Estado</dt><dd>
            <span class="state {{ 
                $payment->estado === 'REALIZADO' ? 'active' : 
                ($payment->estado === 'VENCIDO' || $payment->estado === 'IMPAGO' ? 'inactive' : '') 
            }}" style="
                @if($payment->estado === 'VENCIDO') background: #fee2e2; color: #991b1b; @endif
                @if($payment->estado === 'IMPAGO') background: #ede9fe; color: #5b21b6; @endif
                @if($payment->estado === 'PENDIENTE') background: #fef3c7; color: #92400e; @endif
                @if($payment->estado === 'ANULADO') background: #e5e7eb; color: #374151; @endif
            ">
                {{ ucfirst(strtolower($payment->estado)) }}
            </span>
        </dd></div>
        <div><dt>Período de servicio</dt><dd>{{ $payment->periodo_servicio ?? '—' }}</dd></div>
        <div><dt>Monto</dt><dd><strong style="color: #087846;">S/ {{ number_format($payment->monto, 2) }}</strong></dd></div>
        <div><dt>Fecha de vencimiento</dt><dd>{{ $payment->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</dd></div>
        <div><dt>Fecha de pago</dt><dd>{{ $payment->fecha_pago?->format('d/m/Y') ?? 'Aún no realizado' }}</dd></div>
        <div><dt>Método de pago</dt><dd>{{ $payment->metodo_pago ?? '—' }}</dd></div>
        <div><dt>Referencia externa</dt><dd>{{ $payment->referencia_externa ?? '—' }}</dd></div>
        <div><dt>Observaciones</dt><dd style="white-space: pre-line;">{{ $payment->observaciones ?? '—' }}</dd></div>
        @if($payment->constancia_ruta && $payment->isCompleted())
            <div>
                <dt>Comprobante de pago</dt>
                <dd>
                    <a href="{{ route('payments.receipt', $payment) }}" target="_blank" class="action-link" style="display: inline-flex; align-items: center; gap: 6px;">
                        <i class="bi bi-file-pdf-fill" style="color: #dc2626;"></i>
                        Descargar comprobante PDF
                    </a>
                </dd>
            </div>
        @endif
    </dl>
</section>

<section aria-labelledby="contract-info-title" style="margin-top: 32px;">
    <h3 id="contract-info-title" style="margin-bottom: 16px; font-size: 1.1rem; color: #087846;">Información del Contrato y Cliente</h3>
    <dl class="account-detail">
        <div><dt>Contrato</dt><dd><strong>{{ $payment->contract?->codigo ?? '—' }}</strong></dd></div>
        <div><dt>Cliente</dt><dd>{{ trim(($payment->contract?->client?->nombres_razon_social ?? '') . ' ' . ($payment->contract?->client?->apellidos ?? '')) }}</dd></div>
        <div><dt>Documento</dt><dd>{{ $payment->contract?->client?->tipo_documento }} {{ $payment->contract?->client?->numero_documento }}</dd></div>
        <div><dt>Correo electrónico</dt><dd>
            @if($payment->contract?->client?->correo)
                <a href="mailto:{{ $payment->contract->client->correo }}" style="color: #087846; text-decoration: none;">
                    <i class="bi bi-envelope"></i> {{ $payment->contract->client->correo }}
                </a>
            @else
                —
            @endif
        </dd></div>
        <div><dt>Teléfono</dt><dd>
            @if($payment->contract?->client?->telefono)
                <a href="tel:{{ $payment->contract->client->telefono }}" style="color: #087846; text-decoration: none;">
                    <i class="bi bi-telephone"></i> {{ $payment->contract->client->telefono }}
                </a>
            @else
                —
            @endif
        </dd></div>
        <div><dt>Plan contratado</dt><dd>{{ $payment->contract?->plan?->nombre ?? 'Sin plan' }}</dd></div>
    </dl>
</section>

@endsection
