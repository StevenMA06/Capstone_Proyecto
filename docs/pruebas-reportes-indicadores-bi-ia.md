# CP-REP, CP-IND, CP-BI y CP-IA

Estos ocho casos usan las pantallas y controladores reales de main. No modifican el Word ni la lógica de producción. Los datos y permisos ficticios se preparan por CLI en una base exclusiva `_e2e_test`. No se vacía la base ni se usan las credenciales de la base operativa.

## Ejecutar

Con la preparación general de `pruebas-funcionales-playwright.md` completada, MySQL iniciado y `.env.e2e` configurado:

```powershell
git pull origin main
npm ci
python -m pip install pypdf reportlab
npm run e2e:headed -- e2e/reportes.spec.ts e2e/indicadores.spec.ts e2e/bi.spec.ts e2e/asistente.spec.ts
```

Configura `E2E_SIGNATURE_PYTHON=C:/ruta/python.exe` si Python no está en PATH. Se utiliza para abrir el PDF exportado y leer las celdas del XLSX. La lectura de Excel usa ZIP/XML de Python, independientemente del exportador PHP de la aplicación.

Para cinco ciclos, sustituye `e2e:headed` por `e2e:cycles`. No declares cinco ciclos si solo ejecutaste uno. `npm run e2e:report` abre el último reporte. Se generan capturas, videos, trazas, archivos descargados y resultados por código en `artifacts/e2e/`.

| Caso | Comprobación |
|---|---|
| CP-REP-01 | Reporte de clientes por fecha de registro; compara documentos y total con SQL independiente, incluye los límites del período, excluye registros exteriores y rechaza fechas invertidas. |
| CP-REP-02 | Descarga PDF y XLSX desde sus botones. Abre los archivos, verifica título, fechas y documentos; en XLSX comprueba también la cantidad de filas. Una cuenta con acceso limitado a Reportes no puede exportar Inventario reservado. |
| CP-IND-01 | Compara la tarjeta de clientes con registros fuente, verifica el proceso seleccionado y rechaza un rango invertido conservando el resultado válido. |
| CP-IND-02 | Verifica promedio, mínimo, máximo y cantidad de instalaciones; abre el detalle del cálculo. En Clientes se muestra «Sin medición automática» y no existe el formulario de observaciones del pretest. |
| CP-BI-01 | Suma los valores exactos del gráfico de clientes y los compara con SQL; rechaza un rango superior a cinco años. |
| CP-BI-02 | Compara el gráfico con el reporte del mismo período y criterio de fecha. Deniega tanto la vista como el endpoint analítico a una cuenta sin permiso BI. |
| CP-IA-01 | Verifica consulta vacía y acceso denegado. En modo real consulta contratos activos y materiales críticos mediante las preguntas sugeridas, comparando las respuestas con los registros. |
| CP-IA-02 | Verifica rechazo de solicitudes de contraseñas y limpieza de mensajes al crear una nueva conversación. En modo real contrasta además una respuesta sobre pagos vencidos. |

Los períodos históricos ficticios se eligen sin registros previos de clientes y mediciones en esa ventana. Se crean tres clientes dentro del período y dos fuera. Para comprobar la fórmula temporal se preparan dos ejecuciones asociadas a pedidos, con marcas sintéticas de 10 y 30 minutos en `ejecuciones_instalacion`, donde el módulo técnico almacena los tiempos. Esto verifica el cálculo y su presentación, no la captura real de tiempos ni la ejecución de instalaciones. Los casos de clientes, contratos y pagos preparados como prerrequisitos no certifican sus respectivos módulos.

## Inconsistencia de CP-IND-02 en el PPF

El título detallado y los pasos describen «Eficiencia temporal del sistema» y exigen que no aparezca «Registrar observación del pretest». Sin embargo, la descripción y el resultado esperado todavía hablan de registrar una observación y rechazar observaciones incompletas. Son recorridos distintos.

La prueba ejecuta los pasos de eficiencia temporal. Si pasan, el resultado del CP completo queda **PARCIAL (PPF inconsistente)** hasta que el responsable del documento armonice descripción, pasos y resultado esperado. No se agrega un formulario de observaciones para forzar el cumplimiento ni se modifica el Word.

## Gemini real

Por defecto `E2E_AI_MODE=deshabilitado`. Las comprobaciones locales de acceso, consulta vacía, protección de contraseñas y nueva conversación pueden ejecutarse, pero CP-IA-01/02 quedan **BLOQUEADOS** porque no se acreditan sus respuestas operativas. Un control local satisfactorio no equivale a aprobar la integración de IA.

Para probar contra el servicio real, completa en `.env.e2e`:

```dotenv
E2E_AI_MODE=real
E2E_GEMINI_API_KEY=CLAVE_EXCLUSIVA_DE_PRUEBAS
E2E_GEMINI_MODEL=MODELO_DISPONIBLE_EN_TU_CUENTA
```

Luego ejecuta:

```powershell
npm run e2e:headed -- e2e/asistente.spec.ts
```

Usa una clave con acceso al modelo elegido y cuota disponible. Esta opción hace solicitudes reales y envía el contexto ficticio de la base E2E a Gemini. No hereda la clave operativa de `.env` y no utiliza respuestas simuladas. Conserva `.env.e2e` fuera de Git.

La rama real no queda acreditada hasta ejecutarla con esas credenciales. Los errores de API, del contexto de la aplicación o las respuestas incompatibles con los registros deben quedar como fallos; no se sustituyen por respuestas inventadas. Las comprobaciones automáticas de cantidades y artículos detectan diferencias concretas, pero no evalúan exhaustivamente toda la redacción generada: revisa también la respuesta guardada como evidencia antes de completar el resultado del PPF.

El reporte conserva OK, No OK, Parcial o Bloqueado según lo observado. La suite no acredita el escenario de hardware mínimo ni otros casos que no se hayan ejecutado.

## Diferencias detectadas en main fe98407

- La ruta `reports.export` no tiene permiso asociado en `AccessCatalog::forRoute`. El middleware deniega la descarga incluso a Gerencia. Cuando esto sucede, CP-REP-02 conserva **No OK** y no afirma haber validado el PDF/XLSX que no pudo descargar.
- El cálculo temporal busca `inicio_en` y `fin_en` en `pedidos_instalacion`. El módulo técnico guarda esas marcas en `ejecuciones_instalacion`. Los datos de prueba se guardan en esa fuente real; no se agregan columnas artificiales para hacer pasar la prueba.
- La consulta inicial de materiales de `AnalyticsService` selecciona `unidad_medida`, mientras el esquema utiliza `unidad_base` y `unidad_empaque`. Si la vista inicial devuelve error, se guarda esa evidencia y el caso permanece **No OK**. Para obtener más información, la prueba intenta continuar por la URL del proceso seleccionado, sin ocultar el fallo de entrada desde el menú.

Estas diferencias no se corrigen en esta entrega de pruebas. La contradicción documental de CP-IND-02 tampoco se resuelve cambiando sus expectativas silenciosamente.
