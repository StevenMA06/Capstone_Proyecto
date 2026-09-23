<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de Pago {{ $payment->codigo }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #1f2937;
            padding: 30px;
            background: #f3f4f6;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
        }
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        @media print {
            .receipt-container {
                box-shadow: none;
                border-radius: 0;
                padding: 20px;
            }
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #087846;
        }
        .logo {
            max-width: 180px;
            height: auto;
        }
        .company-info {
            text-align: right;
        }
        .company-info h1 {
            font-size: 16pt;
            color: #087846;
            margin-bottom: 5px;
        }
        .company-info p {
            font-size: 9pt;
            color: #6b7280;
        }
        .document-title {
            text-align: center;
            font-size: 18pt;
            font-weight: bold;
            color: #1f2937;
            margin: 25px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .receipt-number {
            text-align: center;
            font-size: 12pt;
            color: #087846;
            margin-bottom: 25px;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 12pt;
            font-weight: bold;
            color: #087846;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #d1d5db;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table td {
            padding: 8px 12px;
            border: 1px solid #e5e7eb;
        }
        table td:first-child {
            font-weight: bold;
            background-color: #f3f4f6;
            width: 40%;
        }
        .highlight-box {
            background-color: #f0fdf4;
            border: 2px solid #087846;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 25px 0;
        }
        .highlight-box .label {
            font-size: 11pt;
            color: #6b7280;
            margin-bottom: 5px;
        }
        .highlight-box .amount {
            font-size: 28pt;
            font-weight: bold;
            color: #087846;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
            text-align: center;
            font-size: 9pt;
            color: #6b7280;
        }
        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
            gap: 40px;
        }
        .signature-box {
            text-align: center;
            flex: 1;
        }
        .signature-line {
            border-top: 2px solid #1f2937;
            margin-top: 60px;
            padding-top: 8px;
            font-size: 10pt;
            font-weight: bold;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 10pt;
        }
        .status-realizado {
            background-color: #d1fae5;
            color: #065f46;
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 72pt;
            color: rgba(8, 120, 70, 0.05);
            font-weight: bold;
            z-index: -1;
            white-space: nowrap;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="watermark">LUNNYS PERU</div>

    <div class="receipt-container">
        <div class="header">
            <div>
                <img src="{{ $logo_path }}" alt="Lunnys Peru" class="logo">
            </div>
            <div class="company-info">
                <h1>Lunnys Peru</h1>
                <p>Servicios de Internet y Telecomunicaciones</p>
                <p>RUC: 20XXXXXXXXX</p>
                <p>Dirección: Lima, Perú</p>
                <p>Teléfono: (01) XXX-XXXX</p>
            </div>
        </div>

        <div class="document-title">Comprobante de Pago</div>
        <div class="receipt-number">{{ $payment->codigo }}</div>

        <div class="section">
            <div class="section-title">Información del Cliente</div>
            <table>
                <tr>
                    <td>Cliente:</td>
                    <td>{{ trim($client->nombres_razon_social . ' ' . $client->apellidos) }}</td>
                </tr>
                <tr>
                    <td>Documento:</td>
                    <td>{{ $client->tipo_documento }} {{ $client->numero_documento }}</td>
                </tr>
                @if($client->email)
                <tr>
                    <td>Email:</td>
                    <td>{{ $client->email }}</td>
                </tr>
                @endif
                @if($client->telefono)
                <tr>
                    <td>Teléfono:</td>
                    <td>{{ $client->telefono }}</td>
                </tr>
                @endif
                @if($contract->direccion_servicio)
                <tr>
                    <td>Dirección de Servicio:</td>
                    <td>{{ $contract->direccion_servicio }}</td>
                </tr>
                @endif
            </table>
        </div>

        <div class="section">
            <div class="section-title">Información del Contrato</div>
            <table>
                <tr>
                    <td>Código de Contrato:</td>
                    <td>{{ $contract->codigo }}</td>
                </tr>
                @if($plan)
                <tr>
                    <td>Plan Contratado:</td>
                    <td>{{ $plan->nombre }}</td>
                </tr>
                @endif
                <tr>
                    <td>Estado del Contrato:</td>
                    <td>{{ ucfirst(strtolower($contract->estado)) }}</td>
                </tr>
                <tr>
                    <td>Fecha de Inicio:</td>
                    <td>{{ \Carbon\Carbon::parse($contract->fecha_inicio)->format('d/m/Y') }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Detalles del Pago</div>
            <table>
                <tr>
                    <td>Período de Servicio:</td>
                    <td>{{ $payment->periodo_servicio }}</td>
                </tr>
                <tr>
                    <td>Fecha de Vencimiento:</td>
                    <td>{{ \Carbon\Carbon::parse($payment->fecha_vencimiento)->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td>Fecha de Pago:</td>
                    <td>{{ $payment->fecha_pago ? \Carbon\Carbon::parse($payment->fecha_pago)->format('d/m/Y H:i') : 'Pendiente' }}</td>
                </tr>
                <tr>
                    <td>Método de Pago:</td>
                    <td>{{ $payment->metodo_pago ? ucfirst(strtolower($payment->metodo_pago)) : 'No especificado' }}</td>
                </tr>
                @if($payment->referencia_externa)
                <tr>
                    <td>Referencia Externa:</td>
                    <td>{{ $payment->referencia_externa }}</td>
                </tr>
                @endif
                <tr>
                    <td>Estado:</td>
                    <td>
                        <span class="status-badge status-{{ strtolower($payment->estado) }}">
                            {{ ucfirst(strtolower($payment->estado)) }}
                        </span>
                    </td>
                </tr>
            </table>
        </div>

        <div class="highlight-box">
            <div class="label">MONTO TOTAL PAGADO</div>
            <div class="amount">S/ {{ number_format($payment->monto, 2) }}</div>
        </div>

        @if($payment->observaciones)
        <div class="section">
            <div class="section-title">Observaciones</div>
            <p style="padding: 10px; background-color: #f9fafb; border-left: 3px solid #087846;">
                {{ $payment->observaciones }}
            </p>
        </div>
        @endif

        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line">Firma del Cliente</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">Firma de Lunnys Peru</div>
            </div>
        </div>

        <div class="footer">
            <p>Documento generado el {{ $generated_at->format('d/m/Y H:i:s') }}</p>
            <p>Este es un comprobante interno válido para constancia de pago.</p>
            <p>&copy; {{ date('Y') }} Lunnys Peru - Todos los derechos reservados</p>
        </div>
    </div>
</body>
</html>
