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

La autenticación registra intentos en `historial_accesos` y sesiones en `sesiones_usuario`. Laravel almacena el contenido de la sesión en archivos. Incluye hash de contraseñas, protección CSRF, regeneración de sesión y límite de intentos. El esquema suministrado contiene tablas para otros módulos y para 2FA; esas funcionalidades y la recuperación de contraseña no están implementadas.

## Capas y MVC

| Capa | Componentes | Responsabilidad |
|---|---|---|
| Presentación | Blade, controladores y Form Requests | Formularios, validación y respuestas |
| Negocio | AuthService y UserService | Autenticación y reglas de gestión |
| Datos | UserRepository, AccessRepository y modelos Eloquent | Consultas y persistencia en el esquema del equipo |
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

`phpunit.xml` selecciona `Db_LunnysPeru_test`; el arranque de las pruebas exige entorno `testing` y un nombre de base terminado en `_test`. Se verificaron **30 pruebas, 171 aserciones**, con PHP 8.2.12 y MariaDB 10.4.32 incluidos en XAMPP. Cubren autenticación, sesiones, auditoría, autorización, los nueve requisitos y las restricciones de la base suministrada.

## Repositorio

Se incluyen código fuente, SQL del esquema y `composer.lock`. `.env`, dependencias, sesiones, registros y credenciales locales quedan fuera de Git. Para un servidor público configura HTTPS, `APP_DEBUG=false` y credenciales propias del entorno.
