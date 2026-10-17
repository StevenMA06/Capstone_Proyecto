# Correcciones de CP-PAG-01/02, CP-EQU-01, CP-MAT-01 y CP-PED-01

Esta entrega corrige el programa y mantiene los criterios del PPF. No modifica el Word ni requiere migraciones.

- La confirmación de un pago vuelve al listado, tanto por edición como por el endpoint de confirmación.
- Una transferencia necesita número de operación. Se valida en el formulario, el FormRequest, el endpoint de confirmación y el servicio para impedir que otra ruta omita la regla. Efectivo continúa sin exigir operación.
- El comando `payments:update-overdue` aplica días calendario de Perú: hasta tres días de atraso permanece PENDIENTE; a los cuatro pasa a VENCIDO; al superar cuatro pasa a IMPAGO, conservando ese estado utilizado por el flujo existente de impago de contratos. No modifica pagos REALIZADO, ANULADO ni otros estados. `--dry-run` no guarda cambios; repetir la ejecución no vuelve a modificar pagos ya procesados.
- Equipos y Materiales rellenan y muestran el mensaje obligatorio aunque el elemento de error ya exista vacío. Se identifica también el campo con `aria-invalid`.
- Las solicitudes de materiales validan el stock físico menos asignaciones sin consumir/devolver y solicitudes pendientes/autorizadas. El material se bloquea durante la transacción para coordinar solicitudes concurrentes. Si falta stock se revierte la solicitud completa, incluido el equipo y su grupo. La interfaz muestra la misma disponibilidad descontando solicitudes pendientes. El stock físico se descuenta en el consumo, no en la solicitud.

## Verificación

```powershell
git pull origin main
php artisan optimize:clear
npm run e2e:headed -- e2e/pagos.spec.ts e2e/equipos.spec.ts e2e/materiales.spec.ts e2e/pedidos.spec.ts -g "CP-(PAG-0[12]|EQU-01|MAT-01|PED-01)"
```

Playwright utiliza una base exclusiva `_e2e_test` y datos ficticios nuevos. Se comprueban persistencia, mensajes, retorno al listado, comprobantes PDF, rechazo de transferencia sin operación en servidor, límites de tres/cuatro/cinco días, simulación, repetición del comando y reversión completa de solicitudes sin stock.

Las incidencias documentadas en las guías anteriores describen las ejecuciones históricas. Solo una nueva ejecución satisfactoria acredita su resolución. Los casos de Reportes, Indicadores, BI e integraciones externas quedan fuera de esta corrección.

La suite PHPUnit heredada de `PaymentAutomationTest` no pudo ejecutarse: utiliza `Payment::factory()`, que el modelo actual no implementa. No se considera aprobada. Los límites temporales y la confirmación se verifican mediante los casos E2E y los servicios/comando reales, sin sustituirlos por respuestas simuladas.
