# Verificación local de la firma de contratos

El módulo genera un contrato para firma con un recuadro fijo para la firma del cliente. Al actualizar el contrato y adjuntar el PDF, un proceso local compara sus páginas con el documento correspondiente. Si el documento coincide y hay un trazo suficiente dentro del recuadro del cliente, el estado pasa automáticamente de `PENDIENTE` a `ACTIVO`. Un contrato sin firma se guarda y sigue pendiente. Un archivo ajeno o modificado fuera de esa zona se rechaza.

## Instalación

1. Instala Python 3 en el equipo que ejecuta Laravel.
2. Ejecuta `python -m pip install -r requirements-signature.txt` desde la raíz del proyecto.
3. Configura `SIGNATURE_PYTHON` en `.env` con la ruta completa del ejecutable de Python. En Windows, usa barras `/` para evitar escapes de dotenv; por ejemplo: `SIGNATURE_PYTHON=C:/Python312/python.exe`.
4. Ejecuta `php artisan migrate` y `php artisan config:clear`.

Se usan NumPy, Pillow y PDFium en el propio equipo. No hay llamadas a API ni consumo de créditos externos.

## Alcance

La verificación confirma la **presencia visual de un trazo** en el espacio del cliente. No verifica la identidad de la persona, la autenticidad de la firma ni la voluntad de firmar. Solo admite el PDF generado por este módulo, con el mismo contenido y páginas. Una impresión escaneada puede pasar si conserva buena alineación y nitidez; los escaneos inclinados, recortados o con cambios visibles quedarán pendientes de una nueva carga. Para garantías de identidad o validez legal haría falta otro proceso, como firma digital con certificado o comprobación humana.

El sistema conserva `firma_revision` y `firma_validada_en` como resultado de la verificación. La activación manual está deshabilitada. Una vez verificada la firma y activado el contrato, la opción de cancelación aparece en «Actualizar contrato». La finalización aparece allí cuando termina la vigencia. Si cambian los términos de un contrato activo, vuelve a pendiente y se solicita un documento actualizado.
