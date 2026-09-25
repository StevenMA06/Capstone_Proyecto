# Capstone_Proyecto — LUNNY’S PERU R.2.D.

Login y gestión de usuarios con arquitectura en capas y MVC. Preparado y probado con **XAMPP 8.2.12 para Windows: PHP 8.2.12, MariaDB 10.4.32 y Laravel 12**. No requiere PHP 8.4. Composer fija su plataforma en PHP 8.2.12 para mantener las dependencias compatibles.

## Instalación con XAMPP

1. Instala XAMPP 8.2.12 y Composer 2. En Composer selecciona `C:\xampp\php\php.exe`. Si instalaste XAMPP en otra carpeta, adapta las rutas.
2. En el panel de XAMPP inicia **MySQL**. El servicio con ese nombre usa MariaDB. Si otro MySQL ocupa el puerto 3306, detén ese otro servicio o configura un puerto distinto en XAMPP y en `.env`.
3. Abre PowerShell en la carpeta del repositorio y ejecuta:

```powershell
$env:Path = "C:\xampp\php;" + $env:Path
php -v
composer install
```

`php -v` debe mostrar 8.2.12. Si Composer pide extensiones, habilita las indicadas en `C:\xampp\php\php.ini` (incluidas `fileinfo`, `mbstring`, `openssl`, `pdo_mysql` y `zip`) y reinicia Apache si está abierto. Las vistas usan Blade y CSS directo: no se necesita Node.

Si todavía no existe `.env`, copia `.env.example` con `Copy-Item .env.example .env`. Configura las credenciales reales de tu instalación:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=Db_LunnysPeru
DB_USERNAME=root
DB_PASSWORD=
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

La contraseña vacía corresponde solamente a una instalación local de XAMPP que no tenga contraseña configurada. Conserva tu contraseña si ya existe.

4. Inicia Apache para abrir `http://localhost/phpmyadmin`. Crea la base `Db_LunnysPeru` con cotejamiento `utf8mb4_unicode_ci`. También puedes ejecutar:

```sql
CREATE DATABASE IF NOT EXISTS Db_LunnysPeru
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

5. Ejecuta desde el proyecto:

```powershell
php artisan key:generate
php artisan config:clear
php artisan migrate --seed
php artisan app:create-user --role=Administrador --username=administrador
php artisan serve --host=127.0.0.1 --port=8000
```

El comando de creación pide correo y contraseña de forma interactiva. Abre `http://127.0.0.1:8000/login` e ingresa con el correo o nombre de usuario y la contraseña elegida. No se incluyen cuentas ni contraseñas predeterminadas. En instalaciones existentes conserva la `APP_KEY` y omite `key:generate`.

La migración importa las tablas del archivo **`database/sql/Db_LunnysPeru.sql`**, copia del SQL suministrado por el equipo. Si ya lo importaste con phpMyAdmin, la migración conserva las tablas existentes. No crea tablas alternativas `users` ni altera el SQL original. Las tablas existentes deben tener la estructura del archivo suministrado; `IF NOT EXISTS` no corrige estructuras distintas. La migración normaliza los roles anteriores y conserva las cuentas. El seeder agrega los cuatro roles que falten: **Administrador**, **Gerente**, **Usuario de oficina** y **Técnico**. No crea usuarios ni contraseñas predeterminadas.

No uses `migrate:fresh` en la base del equipo: elimina tablas. El rollback de esta importación se bloquea para evitar eliminar la base compartida. Los datos de una antigua tabla Laravel `users`, si existe, no se transfieren automáticamente a `usuarios`.

## Servir la aplicación con Apache de XAMPP

`artisan serve` permite trabajar desde la carpeta clonada sin configurar Apache. Para usar Apache directamente, agrega a `C:\xampp\apache\conf\extra\httpd-vhosts.conf` el contenido de `docs/apache-vhost.conf.example`, sustituyendo la ruta por la de tu clon. Comprueba que `httpd.conf` incluya ese archivo y tenga habilitado `mod_rewrite`. Agrega a `C:\Windows\System32\drivers\etc\hosts` la línea `127.0.0.1 lunnys.local`, reinicia Apache y abre `http://lunnys.local/login`.

Configura `APP_URL=http://lunnys.local` en `.env` y ejecuta `php artisan config:clear`. Apache debe servir únicamente la carpeta **public**, nunca la raíz del repositorio. La configuración de ejemplo está limitada al equipo local.

## Gestión de usuarios: RF-USU-01 a RF-USU-09

Administrador y Gerente acceden desde el panel a **Gestionar usuarios** y puede registrar, listar, actualizar, asignar roles, habilitar, inhabilitar, buscar por nombre de usuario, filtrar por rol y consultar el detalle (identificador, usuario, rol y estado).

Las pantallas de usuarios comparten el menú vertical de la plataforma, con Usuarios marcado como módulo actual. El formulario identifica los errores junto a cada campo y conserva los datos no sensibles.

| Requisito | Comportamiento disponible para Administrador y Gerente |
|---|---|
| RF-USU-01 | Registrar con usuario, correo, rol y contraseña confirmada; empleado opcional. La cuenta se crea activa y la contraseña se almacena como hash. |
| RF-USU-02 | Consultar las cuentas registradas, incluidas las inactivas, en un listado paginado. |
| RF-USU-03 | Editar los datos de una cuenta; una contraseña vacía conserva la actual. |
| RF-USU-04 | Asignar un rol activo del catálogo de la base. Los cambios de rol revocan las sesiones anteriores. |
| RF-USU-05 | Habilitar una cuenta desde su ficha y guardar el estado ACTIVO. |
| RF-USU-06 | Inhabilitar desde la ficha, guardar INACTIVO y revocar sus sesiones sin eliminar la cuenta. |
| RF-USU-07 | Buscar por nombre de usuario completo o parcial, hasta 60 caracteres. El guion bajo se interpreta literalmente. |
| RF-USU-08 | Filtrar por rol y combinarlo con la búsqueda; la paginación conserva ambos filtros. |
| RF-USU-09 | Ver identificador, nombre de usuario, rol y estado en la ficha de la cuenta. |

Estas operaciones requieren autorización de Administrador y Gerente también al acceder directamente por URL o enviar una petición al servidor. `UserManagementTest` verifica los nueve requisitos, validaciones, permisos y protección de la última cuenta activa de Administrador y Gerente.

Se usan los campos originales de `usuarios`: `usuario_id`, `nombre_usuario`, `correo`, `password_hash`, `rol_id`, `empleado_id` y `estado`. Los roles se consultan desde `roles`; la asociación a un empleado activo es opcional. El nombre completo se obtiene del empleado vinculado, cuando existe. La administración del personal está disponible en el módulo Empleados. La creación de nuevos roles no forma parte de Gestión de usuarios.

Las cuentas inactivas o bloqueadas, los roles inactivos y los empleados vinculados inactivos no pueden entrar. Se protege la última cuenta administrativa activa y se revocan las sesiones al cambiar permisos, estado o contraseña. Las operaciones se autorizan también en el servidor.

La autenticación registra intentos en `historial_accesos` y sesiones en `sesiones_usuario`. Laravel almacena el contenido de la sesión en archivos. Incluye hash de contraseñas, protección CSRF, regeneración de sesión y límite de intentos. La recuperación de contraseña no está implementada.

## Control de acceso: RF-ACC-01 a RF-ACC-26

El segundo factor está temporalmente desactivado por defecto (`ACCESS_TWO_FACTOR_ENABLED=false`). Se ingresa con correo o usuario y contraseña, sin necesitar SMTP. Se conservan las restricciones por rol, cuentas inhabilitadas, auditoría y expiración de sesión. Se pospone RF-ACC-06 y, por dependencia, el flujo RF-ACC-05 a RF-ACC-13; no se considera cumplido mientras esté desactivado.

Cuando se habilita `ACCESS_TWO_FACTOR_ENABLED=true`, el inicio de sesión exige dos pasos: correo o usuario y contraseña, seguidos de un código de seis dígitos enviado al correo registrado. Validar la contraseña no autentica al usuario ni permite entrar a rutas protegidas. La sesión y el acceso exitoso se registran únicamente después de verificar el código.

| Requisitos | Implementación |
|---|---|
| RF-ACC-01 a RF-ACC-04 | Formulario de credenciales, comprobación de contraseña, rechazo y mensaje genérico sin revelar cuentas existentes. |
| RF-ACC-05 a RF-ACC-13 | Código aleatorio almacenado como hash en `codigos_acceso_2fa`, correo SMTP, formulario de verificación, vencimiento, uso único y reenvío que sustituye el código anterior. |
| RF-ACC-14 a RF-ACC-16 | Menús y rutas autorizados por rol; cuentas, roles y empleados inactivos no acceden. Los cambios de cuenta durante el segundo factor invalidan la solicitud. |
| RF-ACC-17 a RF-ACC-19 | Cierre de sesión con retorno al login y expiración por inactividad, aplicada en servidor y comprobada desde el navegador. |
| RF-ACC-20 a RF-ACC-26 | Historial exclusivo de Administrador y Gerente con usuario, fecha, hora y resultado, búsqueda por usuario, filtros combinables de fecha y resultado, y paginación. Incluye rechazos de credenciales y códigos. |

Administrador y Gerente acceden a todos los módulos implementados. Usuario de oficina accede a clientes, planes, contratos, ventas y pagos. Técnico accede únicamente a su panel, con instalaciones asignadas y evidencias. Las rutas aplican los mismos permisos que el menú.

El código dura **5 minutos** (`ACCESS_CODE_MINUTES`, entre 1 y 10). Se permiten **5 intentos por código**, un reenvío cada **60 segundos** y un máximo de **5 envíos por usuario en 15 minutos**. La solicitud de acceso dura como máximo 15 minutos; después se vuelven a pedir las credenciales. Un error del transporte invalida el código emitido y mantiene bloqueado el acceso. Los códigos no se guardan en texto plano ni se muestran en la pantalla o en logs.

`SESSION_LIFETIME=120` establece 120 minutos sin solicitudes autenticadas. La comprobación automática del navegador no renueva ese plazo. El servidor rechaza la sesión vencida aunque JavaScript esté deshabilitado; al volver a una pestaña visible se comprueba el estado y se regresa al login si corresponde. Las fechas y los filtros del historial usan la zona horaria de la aplicación, America/Lima (UTC−5).

### Configurar el envío de códigos

Para activar en el futuro el segundo factor, configura SMTP o la API de Brevo en tu `.env` y cambia `ACCESS_TWO_FACTOR_ENABLED=true`:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=servidor-de-tu-proveedor
MAIL_PORT=587
MAIL_USERNAME=cuenta-remitente
MAIL_PASSWORD=credencial-del-servicio
MAIL_FROM_ADDRESS=correo-remitente-verificado
MAIL_FROM_NAME="LUNNYS PERU"
ACCESS_CODE_MINUTES=5
```

Usa el host, puerto, esquema y autenticación indicados por tu proveedor (por ejemplo, `smtps` para TLS directo si lo requiere). Ejecuta `php artisan config:clear` después de modificar `.env`. El correo registrado del usuario debe ser un buzón al que pueda acceder. No subas `.env` ni credenciales a GitHub.

Con el segundo factor habilitado, fuera de pruebas, los transportes `log` y `array` se rechazan para evitar un acceso que dependa de leer códigos de registros internos. Si el proveedor de correo no está configurado o falla, aparece una advertencia y no se inicia la sesión. La suite simula el correo para comprobar destinatario y código; la entrega a un buzón real requiere verificar la configuración SMTP del entorno. No se necesita modificar el SQL ni instalar dependencias nuevas.

## Gestión de clientes: RF-CLI-01 a RF-CLI-13

**Administrador y Gerente** y **Usuario de oficina** acceden desde el panel a **Gestionar clientes**. La acción **Registrar cliente** está dentro del panel de clientes y no en el dashboard principal. El listado permite buscar por nombres, apellidos, razón social o número de documento, combinar la búsqueda con el estado y recorrer los resultados paginados. Los clientes inactivos también permanecen disponibles.

| Requisito | Comportamiento |
|---|---|
| RF-CLI-01 | Registra en la tabla original `clientes`, con confirmación e identificador. |
| RF-CLI-02 y RF-CLI-03 | Bloquea datos obligatorios vacíos y muestra un mensaje junto a cada campo, borde de error y resumen con enlaces; conserva lo ingresado. |
| RF-CLI-04 | Verifica la combinación tipo y número de documento, incluso en clientes inactivos. Al editar permite conservar el documento propio y rechaza el de otro cliente. La restricción SQL sigue protegiendo envíos simultáneos. |
| RF-CLI-05 | Busca por nombre completo, razón social o número de documento; trata `%` y `_` como caracteres literales. |
| RF-CLI-06 | Permite editar la información del cliente, conservando su identificador, autor, fecha de creación y estado. |
| RF-CLI-07 a RF-CLI-09 | Inhabilita desde la ficha, muestra el estado vigente y mantiene la información consultable. No ofrece eliminación ni reactivación. |
| RF-CLI-10 y RF-CLI-13 | Consulta contratos de cualquier estado, con plan, vigencia, importe y detalle de dirección, duración y observaciones. |
| RF-CLI-11 | Conserva y consulta pagos a través de los contratos del cliente, incluyendo pagos pendientes, realizados, vencidos y anulados. |
| RF-CLI-12 | Conserva y consulta instalaciones vinculadas, con programación, técnico y estado; admite registros aún sin técnico o fecha. |

Los datos obligatorios son tipo de documento (DNI, CE, RUC u OTRO), número de documento, nombres o razón social, apellidos, correo electrónico, teléfono y dirección. Referencia de dirección, distrito, provincia, departamento y coordenadas son opcionales. Al guardar se abre la ficha del cliente con un toast de confirmación, que se cierra automáticamente o mediante su botón. Se comprueban longitudes, formato del correo y rangos de coordenadas. El DNI exige exactamente 8 dígitos ASCII (0–9), tanto en el formulario como en el servidor, al registrar y al editar. CE, RUC y OTRO conservan sus reglas previas. No se consulta a servicios externos de identidad. El número de documento conserva sus ceros iniciales.

Al registrar, el servidor asigna `estado = ACTIVO`, `creado_por` desde la sesión y las fechas. Al editar, solo acepta los campos del perfil; no se pueden modificar desde el navegador el identificador, creador, estado o fecha de creación. La inhabilitación únicamente cambia el estado a INACTIVO y es idempotente: repetirla no altera otros datos. Tanto editar como inhabilitar dejan intactos los contratos, pagos e instalaciones existentes.

La ficha contiene las pestañas **Contratos**, **Pagos** e **Instalaciones**, paginadas por separado y limitadas al cliente seleccionado. Los datos se consultan desde las tablas existentes; crear o modificar contratos, pagos e instalaciones corresponde a sus respectivos módulos y no forma parte de la gestión de clientes. Si aún no hay registros vinculados, la pestaña muestra un mensaje vacío.

Se mantiene el flujo Blade → ClientController / StoreClientRequest → ClientService → ClientRepository → MariaDB. No requiere cambiar el SQL, agregar migraciones ni instalar nuevas dependencias. Las rutas usan sesión activa, autorización en el servidor y CSRF para operaciones de escritura.

### Comprobación manual

1. Inicia sesión con un rol autorizado y abre **Gestionar clientes**.
2. Abre **Registrar cliente** y envía el formulario vacío: los campos incompletos deben quedar identificados. Completa los datos obligatorios y guarda.
3. Intenta registrar de nuevo el mismo tipo y número de documento: debe rechazarse sin duplicar al cliente.
4. Busca por nombre o documento, abre **Ver ficha**, entra a **Editar cliente** y guarda un cambio. Verifica el dato actualizado.
5. Consulta las tres pestañas del historial. En una base con registros vinculados, verifica sus códigos, estados y pertenencia al cliente.
6. Despliega **Inhabilitar cliente** y pulsa **Confirmar inhabilitación**. La ficha debe mostrar estado inactivo y conservar los datos y las tres pestañas. El cliente debe seguir apareciendo en la búsqueda y en el filtro de inactivos.

## Capas y MVC

### Dashboard principal

El dashboard es la primera vista después de iniciar sesión para administración, gerencia y oficina, incluso si antes se intentó abrir otra URL protegida. El técnico es redirigido a su panel técnico. La raíz de la aplicación lleva al dashboard cuando hay sesión y al login para invitados.

Administrador y Gerente tienen siete ítems: clientes registrados (incluidos inactivos), contratos ACTIVO, pagos PENDIENTE o VENCIDO, instalaciones EN_PROCESO, tarjetas de Equipos y Materiales, y ventas de seis meses. Las cuatro primeras tarjetas abren sus interfaces. Equipos y Materiales abren modales con nombre, stock actual a la derecha y un enlace a su interfaz de consulta. Las interfaces de ventas, pagos, instalaciones e inventario general son consultas paginadas. Contratos incluye registro básico con planes activos. Oficina puede consultar planes, contratos, ventas y pagos; instalaciones e inventario general están restringidos a Administrador y Gerente.

Cada equipo es una unidad individual. El stock se agrupa por nombre, tipo, marca y modelo y cuenta solo unidades DISPONIBLE; los grupos con unidades asignadas o en mantenimiento siguen visibles con cero disponibles. Los inactivos se excluyen. El mínimo por grupo se configura con `EQUIPMENT_MINIMUM_STOCK` en el `.env` local (por defecto 1). Los materiales usan sus campos `stock_actual`, `stock_minimo` y `unidad_medida`, manteniendo hasta tres decimales. Las tarjetas y filas avisan cuando el stock es igual o inferior al mínimo. Los modales se cierran con el botón, Escape o clic fuera; el foco vuelve a la tarjeta.

El gráfico muestra las ventas CONFIRMADA de los últimos seis meses, incluido el mes actual hasta el momento de la consulta, agrupadas por mes en la zona horaria de la aplicación. Excluye anuladas y fechas futuras, completa meses sin ventas con cero y muestra un estado vacío si corresponde.

Los accesos respetan el rol; únicamente Administrador y Gerente reciben inventarios y estadísticas operativas y de ventas. El personal con permiso de clientes ve su indicador y acceso al módulo. BI y Chatbot figuran como Próximamente. No se incluyen cifras de demostración en la aplicación.

| Capa | Componentes | Responsabilidad |
|---|---|---|
| Presentación | Blade, controladores y Form Requests | Formularios, validación y respuestas |
| Negocio | AuthService, TwoFactorService, UserService y ClientService | Autenticación y reglas de gestión |
| Datos | UserRepository, AccessRepository, ClientRepository y modelos Eloquent | Consultas y persistencia en el esquema del equipo |
| Integración | RepositoryUserProvider, middleware y Gate | Autenticación de Laravel, sesiones y permisos |

Flujo: vista → controlador → servicio → repositorio → modelo → MariaDB.

## Pruebas

Crea una base **exclusiva de pruebas** llamada `Db_LunnysPeru_test`. La suite reconstruye su esquema, por lo que no debe contener datos que quieras conservar.

```powershell
$env:DB_PORT = '3306'
$env:DB_USERNAME = 'root'
# Si configuraste contraseña, define DB_PASSWORD en esta consola.
php artisan config:clear
php artisan test --compact
```

`phpunit.xml` selecciona `Db_LunnysPeru_test`; el arranque de las pruebas exige entorno `testing` y un nombre de base terminado en `_test`. Se verificaron **117 pruebas**, con PHP 8.2.12 y MariaDB 10.4.32 incluidos en XAMPP. Cubren autenticación, sesiones, auditoría, autorización, los nueve requisitos de usuarios y las restricciones de la base suministrada. ClientRegistrationTest y ClientManagementTest suman 30 pruebas para RF-CLI-01 a RF-CLI-13. Verifican persistencia, validación por campo, documentos duplicados y carreras de escritura, búsqueda y paginación, edición, inhabilitación, conservación exacta de los historiales y aislamiento entre clientes, estados vacíos, permisos, CSRF y escape HTML. Las pruebas de historial crean datos sintéticos en la base de pruebas; no agregan datos de demostración a la aplicación.

## Repositorio

Se incluyen código fuente, SQL del esquema y `composer.lock`. `.env`, dependencias, sesiones, registros y credenciales locales quedan fuera de Git. Para un servidor público configura HTTPS, `APP_DEBUG=false` y credenciales propias del entorno.

## Roles y cuenta owner

| Rol | Acceso |
| --- | --- |
| Administrador | Todos los módulos; administra las cuentas owner. |
| Gerente | Todos los módulos; no puede modificar credenciales ni asignar el rol Administrador. |
| Usuario de oficina | Clientes, planes de servicio, contratos, ventas y pagos. |
| Técnico | Panel técnico: únicamente instalaciones asignadas a su empleado y sus evidencias. |

La cuenta Administrador no se puede eliminar, inhabilitar ni degradar de rol. No existe eliminación de cuentas desde la interfaz. Al cambiar credenciales o permisos se revocan sesiones. Se conserva la protección de la última cuenta con acceso administrativo.

Al actualizar un entorno existente, ejecuta `php artisan migrate --force`. La migración convierte Gerencia a Gerente y Personal autorizado a Usuario de oficina, además de variantes de Técnico/Administrador. Conserva cuentas y roles retirados para referencias existentes, pero solo los cuatro roles oficiales son asignables y pueden ingresar. Conserva el bloqueo de cuentas cuyo rol anterior estaba inactivo y cierra las sesiones existentes. No convierte automáticamente un gerente en owner. Para crear el primer owner, usa el comando interactivo `php artisan app:create-user --role=Administrador --username=administrador`.

Para que un técnico vea sus asignaciones, vincula su cuenta con el empleado indicado en `pedidos_instalacion.tecnico_id`. Sin empleado asociado no verá instalaciones. Las fotografías JPG, PNG y WEBP (hasta 5 MB) se guardan en almacenamiento privado; cada consulta, carga y descarga verifica la asignación. Planes cuenta con gestión completa para Administrador y Gerente. Oficina consulta planes y puede registrar contratos básicos. Pagos mantiene su alcance de consulta.

## Estrategia de ramas y commits

Cada historia se trabaja en `feature/nombre-historia`. Los commits usan `tipo: descripción` (por ejemplo, `feat: definir permisos para los cuatro roles`). Los cambios se integran mediante Pull Request a `main`, después de verificar las pruebas y revisar los cambios. Por indicación del responsable, no se espera una revisión externa para realizar el merge. No se publican commits directamente en `main`. La verificación debe corresponder a la versión final de la PR antes del merge.

### Actualización de roles antes de iniciar sesión

Después de descargar los cambios, ejecuta desde la carpeta del proyecto:

```bash
git pull origin main
php artisan migrate
php artisan optimize:clear
```

La migración convierte Gerencia a Gerente y Personal autorizado a Usuario de oficina sin cambiar contraseñas. El login requiere los nombres oficiales; no interpreta los alias antiguos. Completa esta actualización antes de volver a iniciar sesión.

## Gestión de planes de servicio: RF-PLA-01 a RF-PLA-11

Administrador y Gerente registran, editan, activan e inhabilitan planes desde **Planes de servicio**. Usuario de oficina consulta el listado y las fichas; Técnico no accede. Los permisos se aplican también en cada ruta del servidor.

| Requisito | Comportamiento |
| --- | --- |
| RF-PLA-01 | Registro en `planes_servicio`, con estado activo y usuario creador. |
| RF-PLA-02 y RF-PLA-03 | Nombre, tipo de servicio y precio obligatorios; errores en español junto a los campos y resumen accesible. El número se genera al guardar. |
| RF-PLA-04 y RF-PLA-05 | Listado paginado y ficha completa, incluyendo descripción, condiciones, estado, creador y fechas. |
| RF-PLA-06 | Edición sin alterar el creador, el estado ni los contratos existentes. |
| RF-PLA-07 a RF-PLA-09 | Activación e inhabilitación sin eliminación; conserva información y relaciones históricas. |
| RF-PLA-10 | El registro de contratos solo muestra planes activos y revalida su estado bajo bloqueo al guardar. |
| RF-PLA-11 | Búsqueda por número de plan (coincidencia parcial literal), combinable con estado. |

El número de plan se genera automáticamente con formato `PLAN-000001` y no se puede editar. Un contador transaccional evita duplicados entre registros simultáneos y omite números ya existentes. Se conservan los números anteriores y los de planes inactivos. El precio mensual debe ser positivo, con máximo dos decimales; la velocidad es opcional y, si se indica, debe ser un entero positivo. Descripción y condiciones son opcionales, con ancho limitado a su columna y redimensionamiento solo vertical.

Para RF-PLA-10 se incluye **Contratos → Registrar contrato**, disponible para administración, gerencia y oficina. Requiere cliente, plan, zona y empleado responsable activos, código único, fecha de inicio, duración y dirección de servicio. Guarda estado PENDIENTE, calcula la fecha final como inicio + duración en meses (sin desbordamiento de mes) − un día y toma el precio vigente del plan en el servidor. Los cambios posteriores del plan no modifican el precio ya contratado. El registro inicial todavía no genera una venta; esta se crea al confirmar automáticamente el contrato firmado. Si faltan planes, clientes, zonas o empleados activos, el formulario lo indica.

La numeración automática requiere ejecutar `php artisan migrate` para crear e inicializar `secuencias_sistema`. `PlanManagementTest` y `PlanNumberAndTimezoneTest` comprueban registro, validación, numeración, ficha, edición, conservación histórica, estados, búsqueda, paginación, permisos y selección/rechazo de planes inactivos en contratos. Las pruebas y la revisión visual usan datos sintéticos en bases aisladas.

### Menú fijo y horario de Perú

El menú lateral permanece desplegado y conserva los permisos de cada rol. Laravel usa `America/Lima` y sus conexiones MySQL/MariaDB usan `-05:00`, incluyendo los valores predeterminados de fecha de la base. Los nuevos registros y filtros usan la hora de Perú. Los valores históricos no se desplazan automáticamente porque pueden proceder de distintas configuraciones horarias.

Para actualizar:

```powershell
git pull origin main
php artisan optimize:clear
php artisan migrate
```

Reinicia `artisan serve` y `schedule:work` si están ejecutándose. La migración cierra las sesiones activas e invalida los códigos pendientes una sola vez, para evitar extender su vencimiento al cambiar de UTC a Lima. Después vuelve a iniciar sesión. La verificación de esta actualización completó 115 pruebas con 1232 aserciones.

## Gestión de empleados y cese automático

Administrador y Gerente pueden registrar, buscar por nombre/documento, filtrar por estado, consultar la ficha, editar y habilitar/inhabilitar empleados desde el menú **Empleados**. No se eliminan registros ni asociaciones históricas. Gerente no puede editar ni inhabilitar manualmente al empleado vinculado a una cuenta Administrador.

El formulario exige tipo y número de documento (DNI de 8 dígitos), nombres, apellidos, cargo (Técnico de Campo o Asistente de ventas), fecha de inicio (`fecha_ingreso`) y fecha de cese (`fecha_cese`). Correo y teléfono son opcionales. Se rechazan documentos/correos duplicados y fechas de cese anteriores al inicio. Las fechas ya existen en el esquema: no se necesita importar el SQL ni agregar migraciones.

La fecha de cese es el último día de trabajo completo en **America/Lima**. Desde las 00:00 del día siguiente se inhabilitan el empleado y sus cuentas, se cierran sus sesiones y se invalidan sus códigos temporales. La regla de cese también alcanza a una cuenta Administrador vinculada al empleado; no elimina la cuenta ni desactiva el rol compartido. Los roles del resto del personal permanecen disponibles.

Solo se ofrecen empleados activos con vínculo vigente y sin otra cuenta asignada al crear usuarios. Se verifica otra vez al guardar y al habilitar cuentas. Los empleados con inicio futuro tampoco son elegibles. Los registros antiguos sin fechas se conservan; se exigen ambas fechas al editarlos. Una cuenta inactiva conserva su asociación histórica con el empleado cesado al editarla.

Cambiar las fechas no reactiva al empleado o su cuenta automáticamente: después de renovar las fechas, habilita al empleado desde su ficha y luego habilita la cuenta desde Usuarios.

Actualización:

```powershell
git pull origin main
php artisan optimize:clear
php artisan employees:expire
```

El último comando procesa ceses ya vencidos. Para ejecutar el proceso automáticamente cada minuto en desarrollo, abre otra terminal en el proyecto y mantenla abierta:

```powershell
php artisan schedule:work
```

En un servidor configura el programador del sistema para ejecutar `php artisan schedule:run` cada minuto, con el directorio de trabajo del proyecto y la ruta de PHP de ese servidor. No es necesario ejecutar ambos mecanismos simultáneamente. `php artisan schedule:list` permite verificar que `employees:expire` está registrado.

Como respaldo, cada petición web también procesa los ceses vencidos, y el control de acceso comprueba directamente la vigencia del empleado. Sin el programador, se actualizan los estados en la siguiente petición; para procesarlos aunque nadie use el sistema, el programador y la base deben permanecer disponibles.

`EmployeeManagementTest` verifica registro, validaciones, permisos, búsquedas, edición, conservación, cese al finalizar el día en Perú, cierre de sesiones, rechazo de login, exclusión de empleados no elegibles, intentos de asignación manual y reactivación explícita. La verificación se realiza con datos sintéticos en una base aislada.

## Envío de códigos por API HTTPS de Brevo

El transporte `brevo` utiliza `https://api.brevo.com/v3/smtp/email` por HTTPS (puerto 443), sin conexiones SMTP ni paquetes adicionales. Mantiene la validación TLS; no desactives la verificación de certificados. La generación, expiración, reenvío e invalidación de códigos siguen usando el flujo existente.

1. En Brevo abre **Configuración > SMTP y API > API Keys & MCP** y genera una **clave API**, diferente de la clave SMTP. Verifica el remitente y, si está activado el control de IP para API, autoriza la IP pública del servidor o equipo que enviará. No compartas la clave ni la subas a GitHub.
2. Actualiza el proyecto con `git pull origin main`. Copia las variables de `.env.brevo.example` a tu `.env` existente; conserva la base de datos, APP_KEY y demás valores. No reemplaces todo tu `.env`.

```dotenv
MAIL_MAILER=brevo
BREVO_API_KEY="TU_CLAVE_API"
MAIL_FROM_ADDRESS=tu-remitente-verificado@example.com
MAIL_FROM_NAME="Lunnys Perú"
ACCESS_TWO_FACTOR_ENABLED=false
ACCESS_CODE_MINUTES=5
```

Las variables SMTP (`MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD`) no se utilizan con `MAIL_MAILER=brevo`.

3. Limpia la configuración y prueba con una dirección real tuya (sin barras antes del @):

```powershell
php artisan optimize:clear
php artisan app:mail-test destinatario@example.com
```

El comando confirma aceptación por el proveedor, no llegada a la bandeja. Comprueba el correo y spam, y consulta los registros transaccionales de Brevo si no llega. El comando rechaza `log`/`array`, destinatarios inválidos y clave ausente. Muestra errores seguros sin revelar la clave o el cuerpo del mensaje.

4. Solo después de recibir la prueba, cambia `ACCESS_TWO_FACTOR_ENABLED=true`, ejecuta `php artisan optimize:clear` y reinicia `artisan serve` si está abierto. Inicia sesión con una cuenta cuyo correo registrado puedas consultar: se requiere el código antes de acceder. No se necesitan migraciones.

Errores API: HTTP 401 indica revisar la clave API; 403, permisos/IP autorizada/estado de cuenta; 429, límites de envío. Los fallos de conexión HTTPS requieren revisar la red y el almacén de certificados de PHP; no se solventan desactivando TLS. No hay reintentos automáticos de envío para evitar duplicados. Ante un fallo se invalida el código generado y el acceso queda bloqueado.

La integración está probada con respuestas HTTP simuladas, incluyendo destinatario, cuerpo, autenticación de API, errores y acceso con segundo factor. La entrega real requiere la clave y el remitente de tu cuenta y debe verificarse en tu entorno.

## Finalización automática de contratos

Un contrato `ACTIVO` pasa a `FINALIZADO` desde las 00:00 del día siguiente a `fecha_fin`, según la zona horaria `America/Lima`. Los contratos `PENDIENTE` y `CANCELADO` no cambian. La finalización ya no se selecciona manualmente en el formulario; la baja anticipada continúa usando `CANCELADO`.

La tarea `contracts:finalize` se ejecuta cada minuto mediante el programador de Laravel. En desarrollo, mantén `php artisan schedule:work` abierto; en un servidor, programa `php artisan schedule:run` cada minuto. Si el programador no está activo, la siguiente solicitud web también procesa los contratos vencidos. Puedes verificar la tarea con `php artisan schedule:list` o ejecutarla manualmente con `php artisan contracts:finalize`. Este cambio no requiere migración de base de datos.

## Gestión de ventas: RF-VEN-01 a RF-VEN-19

El menú **Ventas** está disponible para Administrador, Gerente y Usuario de oficina. Al detectar la firma del cliente y activar el contrato, se registra una sola venta por contrato en la tabla existente `ventas`. La fecha de venta es el momento de la confirmación; el monto es el **precio mensual contratado** y el responsable es el empleado asignado al contrato. Si se cancela el contrato o se modifican sus condiciones y vuelve a quedar pendiente de firma, la venta pasa a ANULADA. Una nueva confirmación actualiza ese mismo registro a CONFIRMADA. La finalización por vencimiento conserva la venta confirmada.

El panel permite combinar cliente, fechas, estado de venta y empleado, muestra cantidad y monto acumulado, gráficos por empleado, período y estado, y un listado paginado con enlaces al detalle y PDF del contrato. Los segmentos y la leyenda del gráfico circular activan o desactivan estados; **Restablecer** limpia los filtros. Los gráficos y el listado se calculan con los mismos filtros, excepto que el gráfico circular conserva ambos estados para poder volver a seleccionarlos.

Después de actualizar el proyecto, ejecuta `php artisan migrate`. La migración de ventas recupera los contratos ya verificados que tengan fecha de validación y todavía no tengan venta; no cambia la estructura de la base. `SaleManagementTest` y `ContractSignatureTest` comprueban filtros, permisos, creación automática y anulación.
