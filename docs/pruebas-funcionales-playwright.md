# Ejecución del PPF con Playwright

Esta suite incorpora los 28 códigos del PPF: CP-CLI, CP-PLA, CP-CON, CP-PAG, CP-EQU, CP-MAT, CP-KAR, CP-ZON, CP-PED, CP-INS, CP-REP, CP-IND, CP-BI y CP-IA, cada uno con casos 01 y 02. Los casos se contrastaron con el PPF facilitado por el usuario. Incorporar el script no significa que el caso esté aprobado: consulta el resultado de su ejecución. Usuarios y Control de acceso tampoco se certifican con esta suite. Consulta `pruebas-pedidos-instalaciones.md` para Drive real y `pruebas-reportes-indicadores-bi-ia.md` para las exportaciones, Gemini y la inconsistencia de CP-IND-02.

## Preparación en Windows

Necesitas PHP 8.2 o superior con las extensiones del proyecto, Composer, MySQL/MariaDB iniciado, Node.js 22 o 24 actualizado y las dependencias PHP instaladas. No necesitas iniciar `artisan serve`: Playwright inicia su propio servidor en el puerto 8011 y lo cierra al terminar.

Desde la carpeta del proyecto:

```powershell
git pull origin main
composer install
npm ci
npm run e2e:install
Copy-Item .env.e2e.example .env.e2e
```

Copia el archivo de ejemplo solo la primera vez, para no sobrescribir tu configuración. En `.env.e2e`, ajusta host, puerto, usuario y contraseña de MySQL. Si PHP no está en PATH, indica `E2E_PHP_BIN=C:/xampp/php/php.exe`.

Mantén `E2E_DB_DATABASE=lunnys_e2e_test`. La herramienta exige un nombre terminado en `_e2e_test`, crea esa base si no existe y aplica migraciones. El usuario de MySQL necesita permiso para crear esa base (o puedes crearla vacía previamente). No importa el SQL manualmente y no uses tu base operativa. No se modifica `.env` ni se ejecuta `migrate:fresh`.

La suite prepara una cuenta ficticia de Gerencia y los datos de cada caso. Las comprobaciones de creación y edición pasan por las pantallas y controladores reales; la preparación inicial se realiza por CLI. Los datos se conservan únicamente en la base E2E. Cada ciclo genera identificadores y nombres nuevos para no confundirse con registros anteriores.

El servidor usa configuración de pruebas, correo en memoria y segundo factor deshabilitado exclusivamente en ese proceso. Esto permite probar clientes y planes; NO demuestra cumplimiento de Control de acceso ni envío real de correo. La clave de cifrado incluida es exclusiva para estos datos ficticios, nunca para producción.

## Primera ejecución

```powershell
npm run e2e:headed -- e2e/planes.spec.ts -g CP-PLA-01
```

Después ejecuta la suite:

```powershell
npm run e2e
npm run e2e:report
```

Los casos revisan redirección, datos después de recargar, registros en la base, rechazo de duplicados y ausencia de cambios parciales. El precio cero se verifica tanto con la validación nativa como con el servidor. El plan inactivo se comprueba en el selector y enviando un identificador manipulado con una sesión real.

## Contratos y pagos: cuatro casos nuevos

Instala Python 3.10 o superior y las dependencias del validador y documentos ficticios:

```powershell
python -m pip install -r scripts/e2e/requirements.txt
```

Si Python no está en PATH, configura `E2E_SIGNATURE_PYTHON=C:/ruta/a/python.exe` en `.env.e2e`. Ese mismo ejecutable se usa para generar el trazo ficticio y ejecutar el validador real de contratos. No se simula su respuesta ni se desactiva la verificación. El trazo sintético no es una firma personal ni una firma digital criptográfica.

```powershell
# Observar los cuatro casos nuevos:
npm run e2e:headed -- e2e/contratos.spec.ts e2e/pagos.spec.ts
# Ejecutar cinco ciclos de esos cuatro casos:
npm run e2e:cycles -- e2e/contratos.spec.ts e2e/pagos.spec.ts
npm run e2e:report
```

| Caso | Comprobaciones |
|---|---|
| CP-CON-01 | Registro por interfaz, código único, firma procesada por el validador real, contrato pendiente sin pedido completado y activación después de preparar ese prerrequisito. |
| CP-CON-02 | Solicitud con motivo y respaldo, autoaprobación bloqueada en interfaz y servidor, aprobación desde otra cuenta, cancelación del pedido y conservación de responsables, rol, fechas y evidencia. |
| CP-PAG-01 | Registro y búsqueda, errores de periodo/fecha/monto, método obligatorio, confirmación, redirección prevista en el PPF y PDF cuyos datos coinciden con el pago y contrato. |
| CP-PAG-02 | Comando real de vencimientos, contraste del umbral de cuatro días del PPF, conservación de pagos realizados, rechazo de método vacío, efectivo sin operación y transferencia con operación obligatoria. |

Los fixtures preparan datos únicos en la base E2E. En CP-CON-01 preparan el pedido completado; en pagos preparan un contrato activo y obligaciones de prueba. Estos prerrequisitos NO acreditan instalaciones, firma de los contratos preparados para pagos ni Drive. No se usan credenciales ni solicitudes reales a Google Drive. No se inyectan errores internos de servidor o del generador PDF: esos escenarios de contingencia quedan fuera de esta ejecución y no deben declararse validados.

### Diferencias del PPF con main observadas al preparar los casos

Registro histórico de las primeras ejecuciones. Las diferencias de pagos se abordan en `correcciones-casos-ppf.md`: confirmación al listado, operación obligatoria y VENCIDO al cuarto día. El comando conserva IMPAGO a partir del quinto día y corrige su sintaxis. Repite las pruebas para obtener el resultado de tu revisión actual.

Las pruebas mantienen las expectativas del documento mediante aserciones que permiten continuar y reunir evidencia antes de informar `No OK`:

- CP-PAG-01 espera volver al listado tras confirmar. El controlador actual redirige a la ficha del pago.
- CP-PAG-02 describe `VENCIDO` después de cuatro días. El comando actual lo asigna al día siguiente; al superar cuatro días cambia a `IMPAGO`.
- CP-PAG-02 exige número de operación para transferencia. El formulario y el backend actuales permiten omitirlo.
- El comando `UpdateOverduePayments.php` contiene un error de sintaxis que impide ejecutarlo. La prueba conserva el error como `No OK`; sus pagos vencidos iniciales están preparados explícitamente para poder probar también la confirmación por interfaz.
- La generación de comprobantes se prueba con las dependencias reales de Composer instaladas. Si falta DomPDF en el entorno, la confirmación puede guardar el pago y fallar antes de producir el PDF; la prueba no presenta esa situación como comprobante generado.

Un resultado rojo en estas aserciones identifica una diferencia funcional real, no un fallo técnico del script. Antes de registrar el resultado como satisfactorio hay que corregir el sistema o acordar una actualización del PPF. Esta entrega añade pruebas; no modifica esas reglas ni el documento Word.

## Equipos y materiales

```powershell
npm run e2e:headed -- e2e/equipos.spec.ts e2e/materiales.spec.ts
npm run e2e:cycles -- e2e/equipos.spec.ts e2e/materiales.spec.ts
npm run e2e:report
```

Estos casos no requieren API de DNI ni Python. Necesitan las dependencias del proyecto instaladas y la configuración MySQL exclusiva de `.env.e2e`. Cada ciclo genera series y nombres nuevos, sin borrar los datos de otros casos.

| Caso | Comprobaciones |
|---|---|
| CP-EQU-01 | Obligatorios, registro por interfaz, estado Disponible, código automático, ficha y listado, entrada al Kardex y rechazo de serie duplicada sin alterar el original. |
| CP-EQU-02 | Búsqueda por serie, cambio Nuevo → Regular, inhabilitación confirmada y conservación de la ficha, exclusión del selector y rechazo por servidor de una solicitud manipulada. Se conserva un equipo disponible de control para comprobar que el selector funciona. |
| CP-MAT-01 | Obligatorios, registro con código automático, stock inicial 10 metros, ingreso de 5 → stock 15, Kardex con fecha y responsable, rechazo de ingresos 0 y -1 en navegador y servidor sin cambios parciales. |
| CP-MAT-02 | Salida 20 de 100 metros → stock 80, registro en Kardex y rechazo de salidas 0, -1 y 81 sin cambiar stock ni movimientos. |

El pedido y su visita coordinada se preparan por CLI para CP-EQU-02: ese prerrequisito no certifica RF-PED ni instalaciones. Las creaciones y cambios de inventario evaluados se realizan mediante la interfaz real. Los materiales del escenario utilizan metros, sin empaques; no se acredita la variante condicional de conversión de empaques. El título de CP-MAT-02 dice «Salida más ajuste», pero sus pasos y resultados describen únicamente salidas: esta prueba sigue esos pasos y no declara probado el ajuste manual.

La comprobación de mensajes obligatorios conserva la expectativa del PPF. Si el JavaScript bloquea el registro pero deja vacíos los mensajes de los campos, el caso queda `No OK`; la prueba continúa para verificar el registro válido y las demás restricciones. También se envía una solicitud inválida al servidor con la sesión real, comprobando respuesta 422 y ausencia de registros nuevos. No se confunde el rechazo correcto del servidor con una advertencia visible en el formulario.

## Kardex y zonas de cobertura

```powershell
npm run e2e:headed -- e2e/kardex.spec.ts e2e/zonas.spec.ts
npm run e2e:cycles -- e2e/kardex.spec.ts e2e/zonas.spec.ts
npm run e2e:report
```

No requieren Python, SMTP, API de DNI ni Drive; utilizan las dependencias normales del proyecto y la base exclusiva de `.env.e2e`.

Las vistas se compilan en `storage/framework/views-e2e`, excluido de Git. Así la ejecución no depende de las vistas compiladas del entorno operativo ni necesita borrar su caché.

| Caso | Comprobaciones |
|---|---|
| CP-KAR-01 | Historial con equipos y materiales de control, filtro Materiales, combinación Materiales/Entrada, limpieza de filtros y filtro Equipos. Recorre todas las páginas de cada resultado; los datos superan una página. |
| CP-KAR-02 | Búsqueda por código y nombre, limpieza, fechas inclusivas, exclusión de movimientos anteriores/posteriores y búsqueda sin coincidencias. |
| CP-ZON-01 | Obligatorios en navegador y servidor, registro activo con código automático y plan asociado, búsqueda en listado y verificación de cobertura. |
| CP-ZON-02 | Inhabilitación sin eliminar la zona, sus asociaciones, contrato ni pedido previos; ficha del contrato conservada, ausencia de cobertura y exclusión del selector de contratos. Rechaza un identificador de zona inactiva manipulado sin insertar otro contrato. |

Los movimientos del Kardex se preparan por CLI mediante los servicios de inventario reales. El filtro de búsqueda auxiliar de CP-KAR-01 delimita un conjunto ficticio que contiene ambos artículos y varios movimientos, evitando mezclar los datos de otras ejecuciones. En CP-KAR-02 se preparan fechas ficticias conocidas: antes del rango, inicio a las 00:00, interior, final a las 23:59:59 y después del rango. La prueba de fechas recorre todo el resultado, incluso otros datos que coincidan legítimamente con ese período. Estos prerrequisitos no certifican el registro de movimientos por la interfaz de otros módulos.

El contrato y pedido históricos de CP-ZON-02 también son prerrequisitos preparados por CLI, no certificaciones adicionales de RF-CON/RF-PED. No se inyectan errores internos para los escenarios genéricos del PPF. Si una vista devuelve HTTP 500 al abrirse, se conserva la captura y el estado como `No OK`; las comprobaciones posteriores de ese caso no se declaran ejecutadas ni satisfactorias. No se modifica el Word.

## Consulta de DNI

El modo predeterminado es `E2E_DNI_MODE=simulado`: Playwright intercepta únicamente la respuesta del botón Consultar DNI con nombres ficticios. El registro, la validación y la persistencia del cliente siguen siendo reales. El reporte marca CP-CLI-01 como `PARCIAL (DNI simulado)`, aunque el script pase; NO lo presentes como integración validada con API Perú.

Para verificar API Perú real, configura en `.env.e2e`:

```dotenv
E2E_DNI_MODE=real
E2E_DNI=TU_DNI_DE_PRUEBA_AUTORIZADO
E2E_APIPERU_API_KEY=TU_CLAVE
```

Usa un DNI autorizado para la prueba y que no exista en la base E2E. Ejecuta CP-CLI-01 una vez:

```powershell
npm run e2e:headed -- e2e/clientes.spec.ts -g CP-CLI-01
```

Si falta clave o DNI válido, queda Bloqueado. Si la API rechaza la consulta o no devuelve datos, la prueba falla y registra evidencia: no se sustituye automáticamente por una respuesta simulada. Para volver a repetir el mismo DNI, utiliza otra base exclusiva terminada en `_e2e_test` o prepara una copia limpia de la base de pruebas. Las capturas de esta ejecución pueden contener datos personales: conserva solo la evidencia necesaria y no la publiques con claves ni sesiones.

## Ciclos y evidencias

Para cinco ciclos técnicos reproducibles con datos ficticios, usa modo simulado:

```powershell
npm run e2e:cycles
npm run e2e:report
```

Cinco es una propuesta de ejecución; ajústala según el docente, por ejemplo `npm run e2e -- --repeat-each=3`. Un único worker evita interferencias. No se reintentan automáticamente los fallos: cada ciclo conserva su resultado original.

Cada ejecución crea una carpeta distinta en `artifacts/e2e/<fecha-hora>/`:

- `html/`: reporte navegable con pasos, capturas, videos y trazas.
- `evidencias/`: archivos de cada caso y ciclo.
- `resultados.json`: resultado original de Playwright.
- `resumen-ppf.json`: códigos, ciclos, estados y duración.
- `tabla-ppf.md`: tabla para copiar al punto 4 del Word; no reemplaza el PPF.
- `ambiente.json`: versiones y recursos observados del equipo.

`artifacts/e2e/ultimo.json` identifica el último reporte. Configuración, sesiones y evidencias están excluidas de Git; las capturas no se suben al repositorio.

## Cómo completar la sección 4 del Word

Conserva los códigos del punto 2 y registra para cada caso/ciclo: fecha, ejecutor, datos iniciales, resultado observado, OK/No OK/Bloqueado/Parcial, ruta de evidencia e incidencia. Un rechazo esperado es OK; una operación prohibida aceptada es No OK. Un caso omitido por falta de prerrequisitos queda Bloqueado, nunca aprobado.

La tabla generada solo incluye los casos realmente ejecutados; los otros casos del PPF deben seguir pendientes hasta ejecutar sus scripts. No copies resultados del otro grupo.

Texto de estrategia para adaptar al ambiente real:

> La ejecución funcional se realiza con Playwright Test y Chromium sobre una base exclusiva de pruebas. Se preparan cuentas con permisos y datos ficticios antes de la ejecución. Los casos se identifican con los códigos de la matriz de trazabilidad y se dividen en pasos que comprueban el funcionamiento esperado y sus validaciones. Se ejecutan secuencialmente los ciclos acordados, sin reintentos automáticos, y se conservan reportes HTML, capturas, videos, trazas y resultados estructurados. Los resultados obtenidos se registran después de cada ejecución y se vinculan con sus evidencias. Las integraciones simuladas se identifican como parciales y se verifican separadamente contra el servicio real.

La sección 4 también contempla un escenario de recursos mínimos. Esta suite NO limita CPU/RAM del servidor. Para acreditarlo, ejecuta la misma suite en una VM o equipo con recursos definidos, registra esos límites y usa `E2E_SCENARIO=minimo` como etiqueta. La etiqueta por sí sola no aplica límites. No declares hardware mínimo validado sin esa segunda ejecución y sus mediciones.

## Solución de problemas

### Error al preparar la base de pruebas

Si aparece `Access denied ... (using password: NO)`, MySQL rechazó una conexión sin contraseña. `.env.e2e` tiene credenciales independientes de `.env`: completa `E2E_DB_USERNAME` y `E2E_DB_PASSWORD` con el usuario y contraseña de MySQL, manteniendo `E2E_DB_DATABASE=lunnys_e2e_test`. No copies el nombre de la base operativa. Si la clave tiene espacios o `#`, escríbela entre comillas. Vuelve a ejecutar `npm run e2e:cycles`; no requiere limpiar la caché de Laravel porque la suite utiliza su propia configuración.

El script conserva los errores de PHP/MySQL y los muestra antes de ejecutar casos. Un fallo en la preparación no se considera una prueba funcional aprobada.

## Referencias

- https://playwright.dev/docs/test-webserver
- https://playwright.dev/docs/test-cli
- https://playwright.dev/docs/test-reporters
- https://playwright.dev/docs/mock
