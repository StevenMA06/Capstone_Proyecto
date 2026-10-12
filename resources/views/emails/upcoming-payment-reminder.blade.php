<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:32px;color:#173d30;background-color:#f9fafb;border-radius:8px">
    <div style="background-color:#ffffff;padding:24px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
        <h1 style="font-size:24px;color:#059669;margin-bottom:16px">Recordatorio de Pago Próximo</h1>
        
        <p style="font-size:16px;line-height:1.6">
            Estimado(a) <strong>{{ $cliente->nombres_razon_social }} {{ $cliente->apellidos }}</strong>,
        </p>
        
        <p style="font-size:16px;line-height:1.6">
            Le recordamos que tiene un pago próximo a vencer para su servicio de internet con Lunnys Perú.
        </p>

        <div style="background-color:#ecfdf5;padding:20px;border-radius:8px;margin:24px 0;border-left:4px solid #059669">
            <h2 style="font-size:18px;margin:0 0 12px 0;color:#047857">Detalles del Pago</h2>
            <table style="width:100%;font-size:15px;line-height:1.8">
                <tr>
                    <td style="color:#6b7280"><strong>Código de pago:</strong></td>
                    <td style="text-align:right">{{ $payment->codigo }}</td>
                </tr>
                <tr>
                    <td style="color:#6b7280"><strong>Contrato:</strong></td>
                    <td style="text-align:right">{{ $contrato->codigo }}</td>
                </tr>
                <tr>
                    <td style="color:#6b7280"><strong>Plan:</strong></td>
                    <td style="text-align:right">{{ $plan->nombre ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td style="color:#6b7280"><strong>Período:</strong></td>
                    <td style="text-align:right">{{ $payment->periodo_servicio }}</td>
                </tr>
                <tr>
                    <td style="color:#6b7280"><strong>Monto:</strong></td>
                    <td style="text-align:right;font-size:20px;font-weight:bold;color:#059669">
                        S/ {{ number_format($payment->monto, 2) }}
                    </td>
                </tr>
                <tr>
                    <td style="color:#6b7280"><strong>Fecha de vencimiento:</strong></td>
                    <td style="text-align:right;font-weight:bold;color:#dc2626">
                        {{ $payment->fecha_vencimiento->format('d/m/Y') }}
                    </td>
                </tr>
                <tr>
                    <td style="color:#6b7280"><strong>Días restantes:</strong></td>
                    <td style="text-align:right;font-weight:bold">
                        {{ $diasHastaVencimiento }} {{ $diasHastaVencimiento == 1 ? 'día' : 'días' }}
                    </td>
                </tr>
            </table>
        </div>

        <p style="font-size:16px;line-height:1.6">
            Para evitar interrupciones en su servicio, le solicitamos realizar el pago antes de la fecha de vencimiento.
        </p>

        <div style="background-color:#fef3c7;padding:16px;border-radius:8px;margin:20px 0;border-left:4px solid #f59e0b">
            <p style="margin:0;font-size:14px;color:#92400e">
                <strong>Importante:</strong> Si su pago no es confirmado dentro de los 4 días posteriores al vencimiento, 
                su servicio será suspendido automáticamente. Un día después de la suspensión, su contrato será cancelado.
            </p>
        </div>

        <p style="font-size:16px;line-height:1.6">
            Para realizar su pago, puede contactarse con nosotros a través de nuestros canales de atención.
        </p>

        <p style="font-size:16px;line-height:1.6;margin-top:32px">
            Gracias por su preferencia,<br>
            <strong style="color:#059669">Equipo Lunnys Perú</strong>
        </p>

        <hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0">

        <p style="font-size:13px;color:#6b7280;line-height:1.6">
            Este es un correo automático. Si ya realizó el pago, por favor ignore este mensaje. 
            Si tiene alguna consulta, contáctese con nuestro equipo de soporte.
        </p>
    </div>
</div>
