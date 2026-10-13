# Medición temporal del pretest

Fuente: LEVANTAMIENTO_PRETEST.docx (entregado el 12/10/2026).

Las observaciones se registran manualmente desde Indicadores, por Gerencia. Corresponden al trabajo actual observado, no al tiempo automático de uso del sistema web. No se crean datos de ejemplo ni se deducen duraciones a partir de creado_en.

## Límites validados

- Clientes: primer ingreso del DNI en Mikrowisp → clic en Guardar con los datos completos.
- Contratos: inicio del llenado físico posterior a la instalación → evidencia enviada y verificada conforme por Call Center. La firma sola no cierra el intervalo.
- Pagos externos (complementario): inicio del procesamiento del comprobante disponible → Registrar pago.
- Pedidos: inicio de gestión con datos disponibles → fecha coordinada, técnicos asignados y programación comunicada. No incluye ejecución física.

RF-IND-03 se refiere a reactivación después de regularizar el pago y no se reemplaza por pagos externos. Reactivación, acceso a información operativa (RF-IND-05) e inventario (RF-IND-06/07) quedan pendientes de límites validados. El postest necesitará eventos equivalentes confirmados; no se modifica automáticamente el flujo operativo actual.

## Cálculo y trazabilidad

Tiempo efectivo = fin − inicio − interrupciones ajenas al proceso. Promedio = suma de tiempos efectivos / casos ordinarios, convertido a minutos. Los casos excepcionales se conservan separados, con observación obligatoria. El filtro temporal utiliza la fecha de fin, en America/Lima. Las observaciones guardan referencia, usuario, versión de definición y token único para evitar duplicar un doble envío. La evidencia se confirma mediante declaración del observador; no se verifica WhatsApp automáticamente.

La migración 2026_10_13_000001_create_observaciones_pretest_table.php agrega exclusivamente la tabla de observaciones. No modifica clientes, contratos, pagos ni inventario. La sección informa si la tabla aún no está habilitada. No se deben mezclar resultados pretest y postest.
