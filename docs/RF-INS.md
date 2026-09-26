# RF-INS · Ejecución de instalaciones

El panel técnico permite buscar la ficha por ID, iniciar el trabajo, registrar equipos y consumos, guardar observaciones, adjuntar fotografías y contratos firmados y finalizar.

| Requisitos | Implementación |
| --- | --- |
| RF-INS-01 y 02 | Asignaciones filtradas por empleado autenticado, búsqueda por ID y ficha con cliente, teléfono, dirección del contrato, plan, programación y técnico. La misma restricción se aplica a escrituras y descargas. Administrador y Gerente conservan supervisión. |
| RF-INS-03 a 05 | Inicio en ejecuciones_instalacion, con fecha/hora de Perú. Pedido y ejecución pasan a EN_PROCESO en una transacción. |
| RF-INS-06 | Equipos disponibles o previamente asignados a la misma instalación se vinculan como UTILIZADO; el equipo queda ASIGNADO. No puede usarse en dos instalaciones activas. |
| RF-INS-07 y 08 | Consumo acumulado por material, descuento de la diferencia del stock y movimiento CONSUMO en el Kardex. |
| RF-INS-09 | Observación técnica guardada en la ejecución. |
| RF-INS-10 | Fotografías privadas JPG, PNG o WEBP de hasta 5 MB, vinculadas al pedido y su ejecución. |
| RF-INS-11 | Evidencia PDF o fotografía de hasta 5 MB vinculada al contrato. Disponible en la ficha técnica y la ficha del contrato para personal autorizado. |
| RF-INS-12 a 14 | Finalización con fecha/hora de Perú; ejecución COMPLETADA y pedido COMPLETADO. La ficha queda de consulta. |

## Uso y consistencia

- El técnico debe tener una cuenta activa vinculada al empleado asignado. No puede consultar ni modificar pedidos ajenos mediante URL.
- Los consumos son totales acumulados: si se registraron 5 metros y se utilizaron 2 más, se ingresa 7. Reenviar 7 no vuelve a descontar stock. No se admiten reducciones retroactivas ni existencias negativas.
- Las operaciones bloquean las filas del pedido y del equipo o material dentro de una transacción. Inicio y finalización repetidos conservan los horarios originales.
- Es necesario iniciar antes de registrar trabajo. Los pedidos cancelados o completados no admiten modificaciones. No se exige registrar equipos o materiales cuando no se utilizan.
- Iniciar un pedido antiguo EN_PROCESO sin ejecución registra la hora actual; no inventa una hora histórica.
- Adjuntar el contrato constituye evidencia documental; no verifica una firma ni altera el proceso existente de activación contractual.
- Los archivos se guardan en el disco privado local y se descargan con autorización. No se necesita storage:link.

## Actualización

```bash
git pull origin main
php artisan optimize:clear
```

No se modifica el esquema: se utilizan las tablas existentes. Las instalaciones nuevas del proyecto deben seguir las migraciones habituales.

## Verificación

InstallationExecutionTest cubre permisos, búsqueda, estados, horarios, reenvíos, decimales, stock insuficiente, equipos exclusivos, evidencias privadas, PDF contractual y fallo de almacenamiento.
