# CP-PED y CP-INS con Playwright

El PPF no se modifica. Los scripts conservan sus códigos y separan los prerrequisitos preparados por CLI de las acciones realizadas en el navegador. Cada ejecución genera datos ficticios nuevos en la base exclusiva `_e2e_test`; no requiere vaciar la base para repetir.

Con MySQL iniciado y `.env.e2e` configurado según `pruebas-funcionales-playwright.md`:

```powershell
git pull origin main
npm ci
npx playwright install chromium
npm run e2e:headed -- e2e/pedidos.spec.ts e2e/instalaciones.spec.ts
```

Para cinco ciclos, si el PPF exige cinco:

```powershell
npm run e2e:cycles -- e2e/pedidos.spec.ts e2e/instalaciones.spec.ts
```

| Caso | Acciones comprobadas |
|---|---|
| CP-PED-01 | Call Center confirma la visita manteniendo la fecha contractual propuesta, franja, hora y canal. Gerencia solicita equipo y material. Se intenta solicitar una cantidad superior al stock y se verifica que el servidor la rechace sin crear solicitudes. |
| CP-PED-02 | Con recursos preparados por los servicios de Almacén, Gerencia asigna un técnico libre. El mismo técnico no puede ser responsable y apoyo. No aparece para otro pedido coincidente y una solicitud forzada tampoco debe asignarlo. |
| CP-INS-01 | El técnico consulta sus asignaciones, confirma recepción, revisa recursos e inicia. Se comprueban estado, hora en Perú y persistencia. No puede iniciar antes de confirmar y otro técnico no puede acceder a la ficha ajena. |
| CP-INS-02 | Se rechaza un TXT, se previsualiza una foto PNG ficticia y se comprueba su almacenamiento local y huella SHA-256. La instalación continúa en proceso mientras falte el contrato verificado y la confirmación de Drive. El cierre completo solo se verifica en modo Drive real. |

Los preparativos usan los servicios reales para crear contratos, solicitar/autorizar/preparar recursos y asignar instalaciones. No acreditan las interfaces de esos otros procesos. Las cuentas y artículos se crean por ejecución. Las notificaciones usan el mailer `array`: no se acredita entrega real de correos. No se simulan respuestas de Drive ni se fuerzan estados CONFIRMADO/COMPLETADO en la base.

## Drive real para completar CP-INS-02

Por defecto `E2E_DRIVE_MODE=deshabilitado`: CP-INS-02 debe indicar **PARCIAL (Drive real pendiente)** si sus pasos locales pasan. No equivale a aprobar todo el CP. La parte pendiente es confirmar la foto en Drive, cargar un contrato firmado y comprobar el cierre automático.

Para ejecutarla completa, utiliza credenciales y **una carpeta exclusiva de pruebas**; esta opción realiza subidas reales de archivos ficticios. Completa en `.env.e2e`:

```dotenv
E2E_DRIVE_MODE=real
E2E_INSTALLATION_DRIVE_FOLDER_ID=ID_CARPETA_QA
E2E_INSTALLATION_DRIVE_CLIENT_ID=CLIENTE_OAUTH
E2E_INSTALLATION_DRIVE_CLIENT_SECRET=SECRETO_OAUTH
E2E_INSTALLATION_DRIVE_REFRESH_TOKEN=TOKEN_OAUTH
E2E_SIGNATURE_PYTHON=C:/ruta/a/python.exe
```

Si ya utilizas una cuenta de servicio, sustituye los tres valores OAuth por `E2E_INSTALLATION_DRIVE_CREDENTIALS_PATH=C:/ruta/credenciales-qa.json`. La cuenta debe tener permisos suficientes para crear carpetas y archivos en el destino conforme a la configuración de Drive del proyecto. No comitees credenciales.

Instala las dependencias Python requeridas por el validador real y el PDF ficticio:

```powershell
python -m pip install numpy Pillow pypdfium2 reportlab pypdf
npm run e2e:headed -- e2e/instalaciones.spec.ts -g CP-INS-02
```

El script descarga el borrador contractual de la aplicación y añade un trazo sintético en el espacio de firma del cliente. La aplicación lo valida mediante su verificador real. Esto prueba el flujo técnico con datos ficticios, no la identidad de un firmante ni una firma digital criptográfica. El script exige confirmaciones reales de Drive y cierre automático; cualquier diferencia queda como fallo. Esta rama real debe ejecutarse en tu entorno con sus credenciales antes de declarar CP-INS-02 satisfactorio.

## Evidencia y resultados

`artifacts/e2e/ultimo.json` indica el directorio final. Contiene reporte HTML, capturas, video, trazas, resultados por pasos y `resumen-ppf.json`. Abre el HTML con `npx playwright show-report RUTA/html`. Copia al PPF solamente los resultados observados. Una validación que acepta una operación prohibida es **No OK**, aunque otras acciones del caso funcionen. Un resultado parcial se conserva como parcial.

La suite no fuerza la indisponibilidad global de todo el inventario ni produce errores internos artificiales. No acredita el escenario de hardware mínimo. Para ese escenario se necesita ejecutar en el equipo o VM correspondiente y registrar sus recursos.

## Incidencia observada en main c5f6cf0

En CP-PED-01, una solicitud de material por 101 unidades con stock 100 fue aceptada y almacenada, en vez de rechazarse al solicitar recursos. La coordinación y la solicitud de cantidades válidas sí funcionaron. El caso queda **No OK** para esa condición de disponibilidad; la prueba conserva la diferencia, sin corregir la lógica de producción. Esto no afirma que Almacén permita preparar más stock: ese es otro paso, distinto de registrar la solicitud. Confirma con el responsable del PPF si se exige bloquear la solicitud o solamente su preparación antes de cambiar el resultado esperado.
