@extends('payments.layout')
@section('payments-content')
<div class="management-toolbar">
    <div>
        <h2>Pago #{{ $payment->codigo }}</h2>
        <p>Estado: <span class="state {{ 
            $payment->estado === 'REALIZADO' ? 'active' : 
            ($payment->estado === 'VENCIDO' ? 'inactive' : 
            ($payment->estado === 'ANULADO' ? 'inactive' : '')) 
        }}" style="
            @if($payment->estado === 'VENCIDO') background: #fee2e2; color: #991b1b; @endif
            @if($payment->estado === 'PENDIENTE') background: #fef3c7; color: #92400e; @endif
            @if($payment->estado === 'ANULADO') background: #e5e7eb; color: #374151; @endif
        ">{{ ucfirst(strtolower($payment->estado)) }}</span></p>
    </div>
    <div class="row-actions">
        <a class="secondary" href="{{ route('payments.index') }}">Ver pagos</a>
        @if($payment->canBeEdited())
            <a class="action-link" href="{{ route('payments.edit', $payment) }}">Editar pago</a>
        @endif
    </div>
</div>

<section aria-labelledby="payment-info-title">
    <h3 id="payment-info-title" style="margin-bottom: 16px; font-size: 1.1rem; color: #087846;">Información del Pago</h3>
    <dl class="account-detail">
        <div><dt>Código</dt><dd><strong>{{ $payment->codigo }}</strong></dd></div>
        <div><dt>Período de servicio</dt><dd>{{ $payment->periodo_servicio ?? '—' }}</dd></div>
        <div><dt>Monto</dt><dd><strong style="color: #087846;">S/ {{ number_format($payment->monto, 2) }}</strong></dd></div>
        <div><dt>Fecha de vencimiento</dt><dd>{{ $payment->fecha_vencimiento?->format('d/m/Y') ?? '—' }}
            @if($payment->isPending())
                @php
                    $vencimiento = $payment->fecha_vencimiento;
                    $hoy = \Carbon\Carbon::today();
                    $diasRestantes = $hoy->diffInDays($vencimiento, false);
                @endphp
                @if($diasRestantes <= 7 && $diasRestantes >= 0)
                    <small style="color: #d97706;">⚠ Vence en {{ $diasRestantes }} {{ $diasRestantes === 1 ? 'día' : 'días' }}</small>
                @elseif($diasRestantes < 0)
                    <small style="color: #dc2626;">⚠ Vencido hace {{ abs($diasRestantes) }} {{ abs($diasRestantes) === 1 ? 'día' : 'días' }}</small>
                @endif
            @endif
        </dd></div>
        <div><dt>Fecha de pago</dt><dd>{{ $payment->fecha_pago?->format('d/m/Y H:i') ?? 'Aún no realizado' }}</dd></div>
        <div><dt>Método de pago</dt><dd>{{ $payment->metodo_pago ?? '—' }}</dd></div>
        <div><dt>Referencia externa</dt><dd>{{ $payment->referencia_externa ?? '—' }}</dd></div>
        <div><dt>Observaciones</dt><dd>{{ $payment->observaciones ?? '—' }}</dd></div>
        <div><dt>Registrado por</dt><dd>{{ $payment->registeredBy?->nombre ?? 'Sistema' }} el {{ $payment->creado_en?->format('d/m/Y H:i') }}</dd></div>
        <div><dt>Última actualización</dt><dd>{{ $payment->actualizado_en?->format('d/m/Y H:i') }}</dd></div>
    </dl>
</section>

<section aria-labelledby="contract-info-title" style="margin-top: 32px;">
    <h3 id="contract-info-title" style="margin-bottom: 16px; font-size: 1.1rem; color: #087846;">Información del Contrato y Cliente</h3>
    <dl class="account-detail">
        <div><dt>Contrato</dt><dd><strong>{{ $payment->contract?->codigo ?? '—' }}</strong></dd></div>
        <div><dt>Cliente</dt><dd>{{ trim(($payment->contract?->client?->nombres_razon_social ?? '') . ' ' . ($payment->contract?->client?->apellidos ?? '')) }}</dd></div>
        <div><dt>Documento</dt><dd>{{ $payment->contract?->client?->tipo_documento }} {{ $payment->contract?->client?->numero_documento }}</dd></div>
        <div><dt>Plan contratado</dt><dd>{{ $payment->contract?->plan?->nombre ?? 'Sin plan' }}</dd></div>
        <div><dt>Precio mensual del plan</dt><dd>S/ {{ number_format($payment->contract?->plan?->precio_mensual ?? 0, 2) }}</dd></div>
    </dl>
</section>

<section aria-labelledby="actions-title" style="margin-top: 32px;">
    <h3 id="actions-title" style="margin-bottom: 16px; font-size: 1.1rem; color: #087846;">Acciones disponibles</h3>
    
    @if($payment->isPending() || $payment->isOverdue())
        <details class="client-deactivate" style="margin-bottom: 16px;">
            <summary>Marcar como realizado</summary>
            <p class="muted">Indica que este pago fue completado. Se requiere la fecha, método de pago y opcionalmente una referencia externa.</p>
            <form method="POST" action="{{ route('payments.complete', $payment) }}" style="max-width: 500px;">
                @csrf @method('PATCH')
                <div style="margin-bottom: 12px;">
                    <label for="fecha_pago">Fecha de pago *</label>
                    <input id="fecha_pago" name="fecha_pago" type="date" required max="{{ date('Y-m-d') }}" value="{{ date('Y-m-d') }}">
                </div>
                <div style="margin-bottom: 12px;">
                    <label for="metodo_pago">Método de pago *</label>
                    <select id="metodo_pago" name="metodo_pago" required>
                        <option value="">-- Seleccionar --</option>
                        @foreach(['EFECTIVO', 'TRANSFERENCIA', 'YAPE', 'PLIN', 'TARJETA', 'OTRO'] as $metodo)
                            <option value="{{ $metodo }}">{{ $metodo }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="margin-bottom: 12px;">
                    <label for="referencia_externa">Referencia externa</label>
                    <input id="referencia_externa" name="referencia_externa" type="text" maxlength="120">
                </div>
                <div style="margin-bottom: 12px;">
                    <label for="observaciones_completar">Observaciones</label>
                    <textarea id="observaciones_completar" name="observaciones" rows="2" maxlength="500"></textarea>
                </div>
                <button class="action-link" type="submit">Confirmar pago realizado</button>
            </form>
        </details>
    @endif

    @if($payment->isCompleted())
        <div style="margin-bottom: 16px;">
            @if($payment->constancia_ruta)
                {{-- RF-PAG-14: Ver comprobante generado --}}
                <a class="action-link" href="{{ asset($payment->constancia_ruta) }}" target="_blank" style="display: inline-block; margin-right: 12px;">
                    📄 Ver comprobante de pago
                </a>
                <p class="muted" style="margin-top: 8px;">Comprobante generado automáticamente al registrar el pago. Puede visualizar, imprimir o descargar.</p>
            @else
                <a class="action-link" href="{{ route('payments.receipt', $payment) }}" target="_blank" style="display: inline-block;">
                    📄 Generar comprobante de pago
                </a>
                <p class="muted" style="margin-top: 8px;">Genera un comprobante interno del pago realizado (no es una factura electrónica SUNAT).</p>
            @endif
        </div>
    @endif

    @if($payment->canBeCancelled())
        <details class="client-deactivate">
            <summary style="color: #dc2626;">Anular pago</summary>
            <p class="muted">El pago se marcará como anulado. La información se conserva en el historial pero no se puede volver a activar.</p>
            <form method="POST" action="{{ route('payments.cancel', $payment) }}" style="max-width: 500px;">
                @csrf @method('PATCH')
                <div style="margin-bottom: 12px;">
                    <label for="observaciones_anular">Motivo de anulación</label>
                    <textarea id="observaciones_anular" name="observaciones" rows="2" maxlength="500" placeholder="Describe el motivo de la anulación..."></textarea>
                </div>
                <button class="secondary danger" type="submit">Confirmar anulación</button>
            </form>
        </details>
    @endif
</section>

@if($payment->isPending() || $payment->isOverdue())
<section aria-labelledby="reminders-title" style="margin-top: 32px;">
    <h3 id="reminders-title" style="margin-bottom: 16px; font-size: 1.1rem; color: #087846;">Recordatorios de pago</h3>
    
    @if(empty($reminders))
        <p class="muted">No hay recordatorios registrados para este pago.</p>
    @else
        <div class="table-scroll">
            <table class="users-table client-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Mensaje</th>
                        <th>Creado por</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reminders as $reminder)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($reminder->fecha_recordatorio)->format('d/m/Y') }}</td>
                        <td>{{ $reminder->mensaje }}</td>
                        <td>{{ $reminder->creator?->nombre ?? '—' }}</td>
                        <td>
                            <span class="state {{ $reminder->atendido ? 'active' : '' }}" style="@if(!$reminder->atendido) background: #fef3c7; color: #92400e; @endif">
                                {{ $reminder->atendido ? 'Atendido' : 'Pendiente' }}
                            </span>
                        </td>
                        <td>
                            @if(!$reminder->atendido)
                                <form method="POST" action="{{ route('payments.reminders.attend', [$payment, $reminder->recordatorio_id]) }}" style="display: inline;">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="secondary" style="padding: 4px 8px; font-size: 0.85rem;">Marcar atendido</button>
                                </form>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <details class="client-deactivate" style="margin-top: 16px;">
        <summary>Crear nuevo recordatorio</summary>
        <p class="muted">Registra un recordatorio de pago para hacerle seguimiento al cliente.</p>
        <form method="POST" action="{{ route('payments.reminders.create', $payment) }}" style="max-width: 500px;">
            @csrf
            <div style="margin-bottom: 12px;">
                <label for="mensaje">Mensaje del recordatorio *</label>
                <textarea id="mensaje" name="mensaje" rows="3" maxlength="500" required placeholder="Describe el recordatorio que se enviará o registrará..."></textarea>
                <small>Mínimo 10 caracteres, máximo 500.</small>
            </div>
            <button class="action-link" type="submit">Crear recordatorio</button>
        </form>
    </details>
</section>
@endif

@endsection
