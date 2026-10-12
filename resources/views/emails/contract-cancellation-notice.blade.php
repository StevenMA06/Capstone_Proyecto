<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:32px;color:#173d30;background-color:#fef2f2;border-radius:8px">
    <div style="background-color:#ffffff;padding:24px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.1);border-top:6px solid #dc2626">
        <h1 style="font-size:26px;color:#dc2626;margin-bottom:16px;text-align:center">🚫 Cancelación de Servicio</h1>
        
        <p style="font-size:16px;line-height:1.6">
            Estimado(a) <strong>{{ $cliente->nombres_razon_social }} {{ $cliente->apellidos }}</strong>,
        </p>
        
        <p style="font-size:16px;line-height:1.6">
            Lamentamos informarle que su contrato de servicio con Lunnys Perú ha sido 
            <strong style="color:#dc2626">cancelado automáticamente</strong> debido a la falta de pago.
        </p>

        <div style="background-color:#fee2e2;padding:20px;border-radius:8px;margin:24px 0;border-left:4px solid #dc2626">
            <h2 style="font-size:18px;margin:0 0 12px 0;color:#991b1b">Detalles de la Cancelación</h2>
            <table style="width:100%;font-size:15px;line-height:1.8">
                <tr>
                    <td style="color:#6b7280"><strong>Contrato:</strong></td>
                    <td style="text-align:right;font-weight:bold">{{ $contract->codigo }}</td>
                </tr>
                <tr>
                    <td style="color:#6b7280"><strong>Plan:</strong></td>
                    <td style="text-align:right">{{ $plan->nombre ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td style="color:#6b7280"><strong>Fecha de inicio:</strong></td>
                    <td style="text-align:right">{{ \Carbon\Carbon::parse($contract->fecha_inicio)->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td style="color:#6b7280"><strong>Fecha de cancelación:</strong></td>
                    <td style="text-align:right;font-weight:bold;color:#dc2626">
                        {{ now()->format('d/m/Y H:i') }}
                    </td>
                </tr>
                <tr>
                    <td style="color:#6b7280"><strong>Motivo:</strong></td>
                    <td style="text-align:right;color:#dc2626">Falta de pago</td>
                </tr>
            </table>
        </div>

        <div style="background-color:#fef3c7;padding:20px;border-radius:8px;margin:24px 0">
            <h3 style="font-size:16px;margin:0 0 12px 0;color:#92400e">
                💰 Pago Anulado
            </h3>
            <table style="width:100%;font-size:14px;line-height:1.6">
                <tr>
                    <td style="color:#6b7280">Código de pago:</td>
                    <td style="text-align:right;font-weight:bold">{{ $payment->codigo }}</td>
                </tr>
                <tr>
                    <td style="color:#6b7280">Período:</td>
                    <td style="text-align:right">{{ $payment->periodo_servicio }}</td>
                </tr>
                <tr>
                    <td style="color:#6b7280">Fecha de vencimiento:</td>
                    <td style="text-align:right">{{ $payment->fecha_vencimiento->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td style="color:#6b7280">Monto:</td>
                    <td style="text-align:right;font-weight:bold">S/ {{ number_format($payment->monto, 2) }}</td>
                </tr>
            </table>
            <p style="margin:12px 0 0 0;font-size:13px;color:#92400e">
                Este pago superó el período de tolerancia de 4 días y fue marcado como IMPAGO. Tras 1 día adicional, el contrato fue cancelado automáticamente.
            </p>
        </div>

        <p style="font-size:16px;line-height:1.6">
            A partir de este momento, su servicio de internet ha sido <strong>desactivado</strong>.
        </p>

        <div style="background-color:#dbeafe;padding:20px;border-radius:8px;margin:24px 0;border-left:4px solid #2563eb">
            <h3 style="font-size:16px;margin:0 0 12px 0;color:#1e40af">
                🔄 ¿Desea Reactivar el Servicio?
            </h3>
            <p style="margin:0;font-size:15px;color:#1e3a8a;line-height:1.6">
                Si desea reactivar su servicio, puede contactarse con nosotros para regularizar su situación 
                y establecer un nuevo contrato. Nuestro equipo estará encantado de ayudarle.
            </p>
        </div>

        <p style="font-size:16px;line-height:1.6">
            Lamentamos la cancelación del servicio. Esperamos poder atenderle nuevamente en el futuro.
        </p>

        <div style="background-color:#f3f4f6;padding:16px;border-radius:8px;margin:24px 0">
            <h3 style="font-size:15px;margin:0 0 8px 0;color:#374151">
                📞 Contacto
            </h3>
            <p style="margin:0;font-size:14px;color:#4b5563;line-height:1.6">
                Para consultas o reactivación del servicio, contáctese con nuestro equipo a través de 
                nuestros canales oficiales de atención al cliente.
            </p>
        </div>

        <p style="font-size:16px;line-height:1.6;margin-top:32px">
            Atentamente,<br>
            <strong style="color:#dc2626">Equipo Lunnys Perú</strong>
        </p>

        <hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0">

        <p style="font-size:13px;color:#6b7280;line-height:1.6">
            Este es un correo automático generado por el sistema de gestión de contratos. 
            Si considera que esta cancelación fue realizada por error, contáctese inmediatamente con nuestro equipo de soporte.
        </p>
    </div>
</div>
