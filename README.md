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
php artisan app:create-user --role=Gerencia --username=gerencia
php artisan serve --host=127.0.0.1 --port=8000
```

El comando de creación pide correo y contraseña de forma interactiva. Abre `http://127.0.0.1:8000/login` e ingresa con el correo o nombre de usuario y la contraseña elegida. No se incluyen cuentas ni contraseñas predeterminadas. En instalaciones existentes conserva la `APP_KEY` y omite `key:generate`.

La migración importa las tablas del archivo **`database/sql/Db_LunnysPeru.sql`**, copia del SQL suministrado por el equipo. Si ya lo importaste con phpMyAdmin, la migración conserva las tablas existentes. No crea tablas alternativas `users` ni altera el SQL original. Las tablas existentes deben tener la estructura del archivo suministrado; `IF NOT EXISTS` no corrige estructuras distintas. El seeder agrega únicamente los roles iniciales que falten: **Gerencia** y **Personal autorizado**.

No uses `migrate:fresh` en la base del equipo: elimina tablas. El rollback de esta importación se bloquea para evitar eliminar la base compartida. Los datos de una antigua tabla Laravel `users`, si existe, no se transfieren automáticamente a `usuarios`.

## Servir la aplicación con Apache de XAMPP

`artisan serve` permite trabajar desde la carpeta clonada sin configurar Apache. Para usar Apache directamente, agrega a `C:\xampp\apache\conf\extra\httpd-vhosts.conf` el contenido de `docs/apache-vhost.conf.example`, sustituyendo la ruta por la de tu clon. Comprueba que `httpd.conf` incluya ese archivo y tenga habilitado `mod_rewrite`. Agrega a `C:\Windows\System32\drivers\etc\hosts` la línea `127.0.0.1 lunnys.local`, reinicia Apache y abre `http://lunnys.local/login`.

Configura `APP_URL=http://lunnys.local` en `.env` y ejecuta `php artisan config:clear`. Apache debe servir únicamente la carpeta **public**, nunca la raíz del repositorio. La configuración de ejemplo está limitada al equipo local.

## Gestión de usuarios: RF-USU-01 a RF-USU-09

Gerencia accede desde el panel a **Gestionar usuarios** y puede registrar, listar, actualizar, asignar roles, habilitar, inhabilitar, buscar por nombre de usuario, filtrar por rol y consultar el detalle (identificador, usuario, rol y estado).

Se usan los campos originales de `usuarios`: `usuario_id`, `nombre_usuario`, `correo`, `password_hash`, `rol_id`, `empleado_id` y `estado`. Los roles se consultan desde `roles`; la asociación a un empleado activo es opcional. El nombre completo se obtiene del empleado vinculado, cuando existe. La administración de empleados y la creación de nuevos roles no forman parte de este módulo.

Las cuentas inactivas o bloqueadas, los roles inactivos y los empleados vinculados inactivos no pueden entrar. Se protege la última Gerencia activa y se revocan las sesiones al cambiar permisos, estado o contraseña. Las operaciones se autorizan también en el servidor.

La autenticación registra intentos en `historial_accesos` y sesiones en `sesiones_usuario`. Laravel almacena el contenido de la sesión en archivos. Incluye hash de contraseñas, protección CSRF, regeneración de sesión y límite de intentos. La recuperación de contraseña no está implementada.

## Control de acceso: RF-ACC-01 a RF-ACC-26

El inicio de sesión exige dos pasos: correo o usuario y contraseña, seguidos de un código de seis dígitos enviado al correo registrado. Validar la contraseña no autentica al usuario ni permite entrar a rutas protegidas. La sesión y el acceso exitoso se registran únicamente después de verificar el código.

| Requisitos | Implementación |
|---|---|
| RF-ACC-01 a RF-ACC-04 | Formulario de credenciales, comprobación de contraseña, rechazo y mensaje genérico sin revelar cuentas existentes. |
| RF-ACC-05 a RF-ACC-13 | Código aleatorio almacenado como hash en `codigos_acceso_2fa`, correo SMTP, formulario de verificación, vencimiento, uso único y reenvío que sustituye el código anterior. |
| RF-ACC-14 a RF-ACC-16 | Menús y rutas autorizados por rol; cuentas, roles y empleados inactivos no acceden. Los cambios de cuenta durante el segundo factor invalidan la solicitud. |
| RF-ACC-17 a RF-ACC-19 | Cierre de sesión con retorno al login y expiración por inactividad, aplicada en servidor y comprobada desde el navegador. |
| RF-ACC-20 a RF-ACC-26 | Historial exclusivo de Gerencia con usuario, fecha, hora y resultado, búsqueda por usuario, filtros combinables de fecha y resultado, y paginación. Incluye rechazos de credenciales y códigos. |

Gerencia administra usuarios, clientes e historial de accesos. Personal autorizado accede al dashboard y a clientes. Los módulos futuros del menú siguen siendo referencias sin rutas habilitadas.

El código dura **5 minutos** (`ACCESS_CODE_MINUTES`, entre 1 y 10). Se permiten **5 intentos por código**, un reenvío cada **60 segundos** y un máximo de **5 envíos por usuario en 15 minutos**. La solicitud de acceso dura como máximo 15 minutos; después se vuelven a pedir las credenciales. Un error del transporte invalida el código emitido y mantiene bloqueado el acceso. Los códigos no se guardan en texto plano ni se muestran en la pantalla o en logs.

`SESSION_LIFETIME=120` establece 120 minutos sin solicitudes autenticadas. La comprobación automática del navegador no renueva ese plazo. El servidor rechaza la sesión vencida aunque JavaScript esté deshabilitado; al volver a una pestaña visible se comprueba el estado y se regresa al login si corresponde. Las fechas y los filtros del historial usan la zona horaria de la aplicación, actualmente UTC, indicada en pantalla.

### Configurar el envío de códigos

Antes de iniciar sesión, configura un servidor SMTP real en tu `.env`:

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

Fuera de pruebas, los transportes `log` y `array` se rechazan para evitar un acceso que dependa de leer códigos de registros internos. Si SMTP no está configurado o falla, aparece una advertencia y no se inicia la sesión. La suite simula el correo para comprobar destinatario y código; la entrega a un buzón real requiere verificar la configuración SMTP del entorno. No se necesita modificar el SQL ni instalar dependencias nuevas.

## Gestión de clientes: RF-CLI-01 a RF-CLI-13

**Gerencia** y **Personal autorizado** acceden desde el panel a **Gestionar clientes**. La acción **Registrar cliente** está dentro del panel de clientes y no en el dashboard principal. El listado permite buscar por nombres, apellidos, razón social o número de documento, combinar la búsqueda con el estado y recorrer los resultados paginados. Los clientes inactivos también permanecen disponibles.

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

Los datos obligatorios son tipo de documento (DNI, CE, RUC u OTRO), número de documento, nombres o razón social y dirección. Apellidos, correo, teléfono, referencia de dirección, distrito, provincia, departamento y coordenadas son opcionales, conforme al SQL. Se comprueban longitudes, formato del correo cuando se proporciona y rangos de coordenadas. El DNI exige exactamente 8 dígitos ASCII (0–9), tanto en el formulario como en el servidor, al registrar y al editar. CE, RUC y OTRO conservan sus reglas previas. No se consulta a servicios externos de identidad. El número de documento conserva sus ceros iniciales.

Al registrar, el servidor asigna `estado = ACTIVO`, `creado_por` desde la sesión y las fechas. Al editar, solo acepta los campos del perfil; no se pueden modificar desde el navegador el identificador, creador, estado o fecha de creación. La inhabilitación únicamente cambia el estado a INACTIVO y es idempotente: repetirla no altera otros datos. Tanto editar como inhabilitar dejan intactos los contratos, pagos e instalaciones existentes.

La ficha contiene las pestañas **Contratos**, **Pagos** e **Instalaciones**, paginadas por separado y limitadas al cliente seleccionado. Los datos se consultan desde las tablas existentes; crear o modificar contratos, pagos e instalaciones corresponde a sus respectivos módulos y no forma parte de la gestión de clientes. Si aún no hay registros vinculados, la pestaña muestra un mensaje vacío.

Se mantiene el flujo Blade → ClientController / StoreClientRequest → ClientService → ClientRepository → MariaDB. No requiere cambiar el SQL, agregar migraciones ni instalar nuevas dependencias. Las rutas usan sesión activa, autorización en el servidor y CSRF para operaciones de escritura.

### Comprobación manual

1. Inicia sesión con uno de los dos roles y abre **Gestionar clientes**.
2. Abre **Registrar cliente** y envía el formulario vacío: los campos incompletos deben quedar identificados. Completa los datos obligatorios y guarda.
3. Intenta registrar de nuevo el mismo tipo y número de documento: debe rechazarse sin duplicar al cliente.
4. Busca por nombre o documento, abre **Ver ficha**, entra a **Editar cliente** y guarda un cambio. Verifica el dato actualizado.
5. Consulta las tres pestañas del historial. En una base con registros vinculados, verifica sus códigos, estados y pertenencia al cliente.
6. Despliega **Inhabilitar cliente** y pulsa **Confirmar inhabilitación**. La ficha debe mostrar estado inactivo y conservar los datos y las tres pestañas. El cliente debe seguir apareciendo en la búsqueda y en el filtro de inactivos.

## Capas y MVC

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

`phpunit.xml` selecciona `Db_LunnysPeru_test`; el arranque de las pruebas exige entorno `testing` y un nombre de base terminado en `_test`. Se verificaron **76 pruebas, 745 aserciones**, con PHP 8.2.12 y MariaDB 10.4.32 incluidos en XAMPP. Cubren autenticación, sesiones, auditoría, autorización, los nueve requisitos de usuarios y las restricciones de la base suministrada. ClientRegistrationTest y ClientManagementTest suman 29 pruebas para RF-CLI-01 a RF-CLI-13. Verifican persistencia, validación por campo, documentos duplicados y carreras de escritura, búsqueda y paginación, edición, inhabilitación, conservación exacta de los historiales y aislamiento entre clientes, estados vacíos, permisos, CSRF y escape HTML. Las pruebas de historial crean datos sintéticos en la base de pruebas; no agregan datos de demostración a la aplicación.

## Repositorio

Se incluyen código fuente, SQL del esquema y `composer.lock`. `.env`, dependencias, sesiones, registros y credenciales locales quedan fuera de Git. Para un servidor público configura HTTPS, `APP_DEBUG=false` y credenciales propias del entorno.
