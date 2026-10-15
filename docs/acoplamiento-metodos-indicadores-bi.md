# Integración de las propuestas de Indicadores y BI

Se revisaron Metodos_Faltantes_Indicadores.md y Metodos_Faltantes_BI.md. Son propuestas de implementación, no evidencia de completitud: buscar nombres de métodos no demuestra qué requerimientos funcionan. Sus porcentajes y resultados JSON de ejemplo no se utilizan como mediciones reales.

## Decisiones

- Se conserva el diseño: cinco procesos, Contratos como módulo (ventas como resultado), tarjetas informativas distintas de enlaces, tablas limitadas/paginadas y gráficos de barras, líneas, área, circulares y anillos.
- No se crean las tablas inglesas ni los modelos inexistentes de los ejemplos. Se usa el esquema real (empleados.empleado_id, contratos.responsable_id, fecha_registro, ventas.fecha_venta, estados en mayúsculas).
- Los métodos de tiempos reutilizan observaciones_pretest; promedio, mínimo y máximo de casos ordinarios en minutos/segundos, por fecha de fin. Las excepciones siguen separadas. Una consulta válida sin datos devuelve estado sin_datos y valores nulos, no error 404 ni un promedio cero inventado.
- Reactivación, acceso a información e inventario siguen pendientes de validación del negocio. Sus métodos exponen la limitación; su existencia no significa que esos RF estén completados. No se cambia coordinación de pedidos por duración de instalación física. El postest aún requiere eventos equivalentes validados.
- El usuario que registra una observación no se interpreta como empleado que ejecutó el proceso. El filtro por empleado de tiempos de contratos se rechaza mientras falte esa atribución.
- Las ventas se obtienen de ventas confirmadas vinculadas a contratos, una por contrato. Un contrato activo no equivale a una nueva venta del período. Cantidades y montos se muestran por separado.
- La participación agrupa por responsable real de contratos y ventas; no califica productividad ni denomina contratos activos como tasa de éxito. Los registros de pagos/Kardex atribuyen usuarios a empleados mediante usuarios.empleado_id.
- Filtros de fechas incluyen el último día completo. Series día/semana/mes incluyen intervalos vacíos. Las semanas comienzan el lunes. No se suman unidades distintas de materiales ni se deriva una tasa de retención sin historial.
- La consulta de estados del explorador se limita a los registros del período con su estado vigente; el corte actual de la pantalla permanece identificado por separado. Montos de pagos solo consideran REALIZADO y fecha_pago.

## Funciones y trazabilidad

| Requisito del DRS actualizado | Implementación | Estado |
|---|---|---|
| RF-IND-01/02/04 | AnalyticsController: avgClientRegistrationTime, avgContractRegistrationTime, avgOrderProcessingTime; IndicatorQueryService | Calcula observaciones pretest existentes |
| RF-IND-03/05/06/07 | avgReactivationTime, avgInformationAccessTime, avgInventoryRegistrationTime, inventoryTimeByType | Método disponible; límites/datos pendientes, no completado |
| RF-BI-10/11/12 | Filtros combinados y filterByProcess | Integrado |
| RF-BI-13/14 | generateChart, registrationEvolution, explorador | Integrado |
| RF-BI-15/17 | staffParticipation | Contratos y ventas por empleado; no abarca todos los procesos |
| RF-BI-03/16/18 | Sales summary y gráficos de ventas | Integrado sobre ventas confirmadas vinculadas a contratos |
| RF-BI-22/23 | avgInventoryTime, timeByModule | Pendiente de medición validada |

Los códigos de BI en los MD difieren del DRS actualizado: participación del personal corresponde a RF-BI-15, no RF-BI-12; tiempos de inventario a RF-BI-22/23, no RF-BI-14/15. Se conserva la referencia del DRS entregado.

Los endpoints JSON de indicadores están en /analytics y los de BI en /bi, autenticados y restringidos por las rutas de gestión existentes. Se aceptan desde/hasta o fecha_inicio/fecha_fin. No se incorpora el middleware module.permission inexistente ni se altera DashboardController (el panel utiliza AnalyticsController y BusinessIntelligenceController).

## Validación

AnalyticsMethodsTest comprueba sumas sin duplicación por uniones, ventas anuladas excluidas, última hora del período incluida, meses vacíos, semanas entre años, fecha de pago para recaudación, atribución usuario/empleado, combinaciones incompatibles, consultas de tiempos y preservación de los cinco procesos.

Estas pruebas automatizadas no sustituyen los casos funcionales por RF, ciclos según frecuencia real ni evidencias de ejecución solicitados por el profesor. No se declara despliegue en la empresa. Esta integración no requiere migraciones nuevas ni cambia .env.
