<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Constancia de Pago - {{ $payment->codigo }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f4f7f6;
            padding: 20px;
        }
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border: 2px solid #087846;
            border-radius: 8px;
            overflow: hidden;
        }
        .receipt-header {
            background: linear-gradient(135deg, #087846 0%, #148053 100%);
            color: white;
            padding: 32px 40px;
            text-align: center;
        }
        .receipt-header h1 {
            font-size: 28px;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .receipt-header p {
            font-size: 14px;
            opacity: 0.95;
        }
        .receipt-body {
            padding: 40px;
        }
        .receipt-notice {
            background: #fef3c7;
            border-left: 4px solid #d97706;
            padding: 16px;
            margin-bottom: 32px;
            border-radius: 4px;
        }
        .receipt-notice p {
            font-size: 14px;
            color: #92400e;
            margin: 0;
        }
        .receipt-section {
            margin-bottom: 32px;
        }
        .receipt-section h2 {
            font-size: 18px;
            color: #087846;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }
        .receipt-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px 24px;
        }
        .receipt-field {
            display: flex;
            flex-direction: column;
        }
        .receipt-field.full-width {
            grid-column: 1 / -1;
        }
        .receipt-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .receipt-value {
            font-size: 16px;
            color: #1f2937;
            font-weight: 500;
        }
        .receipt-value.highlight {
            font-size: 24px;
            color: #087846;
            font-weight: 700;
        }
        .receipt-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            background: #d1fae5;
            color: #065f46;
        }
        .receipt-footer {
            background: #f9fafb;
            padding: 24px 40px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
        }
        .receipt-footer p {
            margin-bottom: 4px;
        }
        .receipt-signature {
            margin-top: 48px;
            padding-top: 16px;
            border-top: 2px solid #333;
            text-align: center;
        }
        .receipt-signature p {
            font-size: 14px;
            color: #6b7280;
            margin-top: 8px;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .receipt-container {
                border: none;
                box-shadow: none;
            }
            .no-print {
                display: none;
            }
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #087846;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
        }
        .print-button:hover {
            background: #148053;
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ Imprimir constancia</button>
    
    <div class="receipt-container">
        <header class="receipt-header">
            <h1>CONSTANCIA DE PAGO</h1>
            <p>Documento interno de comprobación de pago</p>
        </header>

        <div class="receipt-body">
            <div class="receipt-notice">
                <p><strong>IMPORTANTE:</strong> Esta constancia es un documento interno de control. No constituye una factura electrónica válida ante SUNAT.</p>
            </div>

            <section class="receipt-section">
                <h2>Información del Pago</h2>
                <div class="receipt-grid">
                    <div class="receipt-field">
                        <span class="receipt-label">Código de pago</span>
                        <span class="receipt-value">{{ $payment->codigo }}</span>
                    </div>
                    <div class="receipt-field">
                        <span class="receipt-label">Estado</span>
                        <span class="receipt-badge">{{ ucfirst(strtolower($payment->estado)) }}</span>
                    </div>
                    <div class="receipt-field">
                        <span class="receipt-label">Período de servicio</span>
                        <span class="receipt-value">{{ $payment->periodo_servicio ?? '—' }}</span>
                    </div>
                    <div class="receipt-field">
                        <span class="receipt-label">Monto pagado</span>
                        <span class="receipt-value highlight">S/ {{ number_format($payment->monto, 2) }}</span>
                    </div>
                    <div class="receipt-field">
                        <span class="receipt-label">Fecha de vencimiento</span>
                        <span class="receipt-value">{{ $payment->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</span>
                    </div>
                    <div class="receipt-field">
                        <span class="receipt-label">Fecha de pago</span>
                        <span class="receipt-value">{{ $payment->fecha_pago?->format('d/m/Y H:i') ?? '—' }}</span>
                    </div>
                    <div class="receipt-field">
                        <span class="receipt-label">Método de pago</span>
                        <span class="receipt-value">{{ $payment->metodo_pago ?? '—' }}</span>
                    </div>
                    <div class="receipt-field">
                        <span class="receipt-label">Referencia externa</span>
                        <span class="receipt-value">{{ $payment->referencia_externa ?? '—' }}</span>
                    </div>
                    @if($payment->observaciones)
                    <div class="receipt-field full-width">
                        <span class="receipt-label">Observaciones</span>
                        <span class="receipt-value">{{ $payment->observaciones }}</span>
                    </div>
                    @endif
                </div>
            </section>

            <section class="receipt-section">
                <h2>Información del Cliente</h2>
                <div class="receipt-grid">
                    <div class="receipt-field full-width">
                        <span class="receipt-label">Nombre o razón social</span>
                        <span class="receipt-value">{{ trim($client->nombres_razon_social . ' ' . $client->apellidos) }}</span>
                    </div>
                    <div class="receipt-field">
                        <span class="receipt-label">Tipo de documento</span>
                        <span class="receipt-value">{{ $client->tipo_documento }}</span>
                    </div>
                    <div class="receipt-field">
                        <span class="receipt-label">Número de documento</span>
                        <span class="receipt-value">{{ $client->numero_documento }}</span>
                    </div>
                    <div class="receipt-field full-width">
                        <span class="receipt-label">Dirección</span>
                        <span class="receipt-value">{{ $client->direccion }}</span>
                    </div>
                    @if($client->telefono)
                    <div class="receipt-field">
                        <span class="receipt-label">Teléfono</span>
                        <span class="receipt-value">{{ $client->telefono }}</span>
                    </div>
                    @endif
                    @if($client->correo)
                    <div class="receipt-field">
                        <span class="receipt-label">Correo electrónico</span>
                        <span class="receipt-value">{{ $client->correo }}</span>
                    </div>
                    @endif
                </div>
            </section>

            <section class="receipt-section">
                <h2>Información del Contrato</h2>
                <div class="receipt-grid">
                    <div class="receipt-field">
                        <span class="receipt-label">Código de contrato</span>
                        <span class="receipt-value">{{ $contract->codigo }}</span>
                    </div>
                    <div class="receipt-field">
                        <span class="receipt-label">Plan contratado</span>
                        <span class="receipt-value">{{ $plan?->nombre ?? 'Sin plan' }}</span>
                    </div>
                    @if($plan)
                    <div class="receipt-field">
                        <span class="receipt-label">Precio mensual del plan</span>
                        <span class="receipt-value">S/ {{ number_format($plan->precio_mensual, 2) }}</span>
                    </div>
                    <div class="receipt-field">
                        <span class="receipt-label">Velocidad contratada</span>
                        <span class="receipt-value">{{ $plan->velocidad_bajada }} Mbps / {{ $plan->velocidad_subida }} Mbps</span>
                    </div>
                    @endif
                </div>
            </section>

            <div class="receipt-signature">
                <div style="width: 250px; margin: 0 auto;">_________________________</div>
                <p>Firma autorizada</p>
            </div>
        </div>

        <footer class="receipt-footer">
            <p><strong>Constancia generada el:</strong> {{ $generated_at->format('d/m/Y H:i:s') }}</p>
            <p><strong>Generada por:</strong> {{ $generated_by }}</p>
            <p style="margin-top: 16px;">Este documento es de uso interno y no sustituye comprobantes de pago electrónicos según normativa SUNAT.</p>
        </footer>
    </div>
</body>
</html>
