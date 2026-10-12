<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:32px;color:#173d30;background-color:#fef2f2;border-radius:8px">
    <div style="background-color:#ffffff;padding:24px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.1);border-top:4px solid #dc2626">
        <h1 style="font-size:24px;color:#dc2626;margin-bottom:16px">⚠️ Pago Vencido - Acción Requerida</h1>
        
        <p style="font-size:16px;line-height:1.6">
            Estimado(a) <strong>{{ $cliente->nombres_razon_social }} {{ $cliente->apellidos }}</strong>,
        </p>
        
        <p style="font-size:16px;line-height:1.6">
            Le informamos que su pago ha <strong style="color:#dc2626">vencido</strong> y aún no hemos recibido la confirmación de su pago.
        </p>

        <div style="background-color:#fee2e2;padding:20px;border-radius:8px;margin:24px 0;border-left:4px solid #dc2626">
            <h2 style="font-size:18px;margin:0 0 12px 0;color:#991b1b">Detalles del Pago Vencido</h2>
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
                    <td style="color:#6b7280"><strong>Monto adeudado:</strong></td>
                    <td style="text-align:right;font-size:20px;font-weight:bold;color:#dc2626">
                        S/ {{ number_format($payment->monto, 2) }}
                    </td>
                </tr>
                <tr>
                    <td style="color:#6b7280"><strong>Fecha de vencimiento:</strong></td>
                    <td style="text-align:right">{{ $payment->fecha_vencimiento->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td style="color:#6b7280"><strong>Días vencidos:</strong></td>
                    <td style="text-align:right;font-weight:bold;color:#dc2626">
                        {{ $diasVencidos }} {{ $diasVencidos == 1 ? 'día' : 'días' }}
                    </td>
                </tr>
            </table>
        </div>

        <div style="background-color:#fef3c7;padding:20px;border-radius:8px;margin:24px 0;border-left:4px solid #f59e0b">
            <h3 style="font-size:16px;margin:0 0 8px 0;color:#92400e">
                🕐 Plazo de Regularización
            </h3>
            <p style="margin:0;font-size:15px;color:#92400e;line-height:1.6">
                <strong>Su servicio será suspendido automáticamente si no se regulariza el pago dentro de 
                {{ 4 - $diasVencidos }} {{ (4 - $diasVencidos) == 1 ? 'día' : 'días' }} adicionales.</strong>
            </p>
            <p style="margin:8px 0 0 0;font-size:14px;color:#92400e">
                Evite la suspensión del servicio regularizando su pago a la brevedad posible.
            </p>
        </div>

        <p style="font-size:16px;line-height:1.6">
            Le solicitamos realizar el pago lo antes posible para mantener su servicio activo sin interrupciones.
        </p>

        <div style="background-color:#eff6ff;padding:16px;border-radius:8px;margin:20px 0">
            <h3 style="font-size:16px;margin:0 0 8px 0;color:#1e40af">
                💳 Formas de Pago
            </h3>
            <p style="margin:0;font-size:14px;color:#1e3a8a;line-height:1.6">
                Puede realizar el pago mediante transferencia bancaria, Yape, Plin o efectivo en nuestras oficinas. 
                Contáctese con nosotros para coordinar.
            </p>
        </div>

        <p style="font-size:16px;line-height:1.6;margin-top:32px">
            Atentamente,<br>
            <strong style="color:#dc2626">Equipo Lunnys Perú</strong>
        </p>

        <hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0">

        <p style="font-size:13px;color:#6b7280;line-height:1.6">
            Si ya realizó el pago, por favor envíenos el comprobante para actualizar su registro. 
            Para cualquier consulta, contáctese con nuestro equipo de soporte.
        </p>
    </div>
</div>
