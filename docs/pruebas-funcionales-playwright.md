# Ejecución del PPF con Playwright

Esta suite recorre los cuatro casos de tus módulos presentes en `PPF_Plan_Pruebas_Funcionales(1).docx`: CP-CLI-01, CP-CLI-02, CP-PLA-01 y CP-PLA-02. Cada caso incluye sus comprobaciones positivas y negativas, capturas y datos persistidos. Los otros 24 casos del documento no están automatizados por esta suite. Usuarios y Control de acceso tampoco figuran en esa versión del PPF.

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

Después ejecuta los cuatro casos:

```powershell
npm run e2e
npm run e2e:report
```

Los casos revisan redirección, datos después de recargar, registros en la base, rechazo de duplicados y ausencia de cambios parciales. El precio cero se verifica tanto con la validación nativa como con el servidor. El plan inactivo se comprueba en el selector y enviando un identificador manipulado con una sesión real.

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
