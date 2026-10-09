<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Visita de instalación {{ $code }}</title>
</head>
<body style="margin:0;padding:0;background:#f2f6f3;font-family:Arial,Helvetica,sans-serif;color:#21382b;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f2f6f3;padding:30px 12px;">
        <tr><td align="center">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid #dce7df;border-radius:14px;overflow:hidden;">
                <tr><td style="padding:24px 30px;background:#155b3b;color:#ffffff;">
                    <div style="font-size:12px;font-weight:bold;letter-spacing:2px;">LUNNYS PERÚ</div>
                    <div style="margin-top:6px;font-size:13px;color:#d9eee1;">{{ $eventLabel }}</div>
                </td></tr>
                <tr><td style="padding:28px 30px 12px;">
                    <p style="margin:0 0 8px;font-size:12px;font-weight:bold;letter-spacing:1px;color:#64806d;">VISITA DE INSTALACIÓN</p>
                    <h1 style="margin:0;font-size:24px;line-height:1.3;color:#183c2b;">{{ $title }}</h1>
                    <p style="margin:12px 0 0;font-size:15px;line-height:1.6;color:#52665a;">{{ $intro }}</p>
                </td></tr>
                <tr><td style="padding:12px 30px 22px;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #dce9df;border-radius:10px;background:#f7faf8;">
                        <tr><td style="padding:16px 18px 8px;">
                            <div style="font-size:11px;color:#687b6e;">CÓDIGO DEL PEDIDO</div>
                            <div style="margin-top:4px;font-size:17px;font-weight:bold;color:#1d4a32;">{{ $code }}</div>
                        </td></tr>
                        <tr><td style="padding:8px 18px;">
                            <div style="font-size:11px;color:#687b6e;">DÍA DE LA VISITA</div>
                            <div style="margin-top:4px;font-size:15px;font-weight:bold;color:#263e30;">{{ $date }}</div>
                        </td></tr>
                        <tr><td style="padding:8px 18px 16px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr>
                                <td width="50%" valign="top"><div style="font-size:11px;color:#687b6e;">HORA DE INICIO</div><div style="margin-top:4px;font-size:15px;font-weight:bold;color:#263e30;">{{ $startTime }}</div></td>
                                <td width="50%" valign="top"><div style="font-size:11px;color:#687b6e;">FRANJA ACORDADA</div><div style="margin-top:4px;font-size:15px;font-weight:bold;color:#263e30;">{{ $timeWindow }}</div></td>
                            </tr></table>
                        </td></tr>
                    </table>
                </td></tr>
                <tr><td align="center" style="padding:0 30px 18px;">
                    <a href="{{ $loginUrl }}" style="display:inline-block;padding:13px 22px;border-radius:8px;background:#087f4f;color:#ffffff;text-decoration:none;font-size:15px;font-weight:bold;">Iniciar sesión en Lunnys</a>
                </td></tr>
                <tr><td style="padding:0 30px 26px;text-align:center;font-size:13px;line-height:1.6;color:#687b6e;">
                    Después de iniciar sesión, abre tu panel técnico para confirmar la asignación.<br>
                    <a href="{{ $orderUrl }}" style="color:#176b45;font-weight:bold;overflow-wrap:anywhere;">Abrir directamente la ficha {{ $code }}</a>
                </td></tr>
                <tr><td style="padding:15px 30px;border-top:1px solid #e7eee9;font-size:12px;line-height:1.5;color:#78877d;">
                    Si no reconoces esta asignación o el horario no coincide con lo coordinado, comunícate con el responsable de Instalaciones.
                </td></tr>
            </table>
            <div style="padding:16px 8px 0;font-size:11px;color:#87948b;">Mensaje automático del sistema de instalaciones de Lunnys Perú.</div>
        </td></tr>
    </table>
</body>
</html>
