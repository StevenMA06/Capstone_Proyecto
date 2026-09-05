# Capstone_Proyecto

Login de **LUNNY’S PERU R.2.D.**, desarrollado con Laravel 13, PHP 8.4, MySQL, arquitectura en capas y patrón MVC.

## Funciones

- Formulario en español con la identidad visual de la empresa.
- Inicio de sesión, panel privado y cierre de sesión.
- Mostrar u ocultar contraseña.
- Contraseñas almacenadas como hash, protección CSRF y regeneración de sesión.
- Límite de intentos fallidos y validación de credenciales.
- Creación de usuarios mediante un comando de consola.

## Requisitos

- PHP 8.4 con las extensiones requeridas por Composer, incluida `pdo_mysql`.
- Composer 2 y MySQL 8.
- Para las pruebas predeterminadas: `pdo_sqlite` y SQLite.

Las vistas utilizan Blade y CSS directo. No necesitas Node para ejecutar el login.

## Instalación

```bash
git clone https://github.com/StevenMA06/Capstone_Proyecto.git
cd Capstone_Proyecto
composer install
```

Copia `.env.example` a `.env` y configura el puerto, usuario y contraseña de tu MySQL:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=login_capas
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_clave
```

Crea una base vacía desde MySQL usando una cuenta autorizada:

```sql
CREATE DATABASE login_capas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Después ejecuta:

```bash
php artisan key:generate
php artisan migrate
php artisan app:create-user
php artisan serve --host=127.0.0.1 --port=8000
```

Abre http://127.0.0.1:8000/login y usa la cuenta que acabas de crear. No se incluyen usuarios ni contraseñas de prueba.

## Arquitectura en capas y MVC

| Capa | Componentes | Responsabilidad |
|---|---|---|
| Presentación | Blade, AuthController, LoginRequest y rutas | Formulario, entrada y respuesta HTTP |
| Negocio | AuthService | Coordinar la autenticación y limitar intentos |
| Datos | UserRepository y User | Consultar y crear usuarios con Eloquent y MySQL |
| Integración | RepositoryUserProvider y AppServiceProvider | Conectar el repositorio con la autenticación de Laravel |

Flujo: vista → controlador → servicio → autenticación de Laravel → proveedor → repositorio → Eloquent → MySQL.

El proveedor hereda de EloquentUserProvider, que conserva la comprobación y actualización del hash y la recuperación del usuario desde la sesión. Es una separación práctica por capas sobre MVC que utiliza servicios del framework.

## Pruebas

```bash
php artisan test --compact
```

Las pruebas usan SQLite en memoria y no modifican la base configurada para la aplicación. La suite de autenticación también se verificó contra una base MySQL exclusiva de pruebas.

## Configuración de despliegue

Configura el servidor web para servir únicamente `public/`, usa HTTPS y establece `APP_DEBUG=false`. Mantén `.env` fuera del repositorio y utiliza credenciales propias del entorno. El proyecto incluye login y logout; no incluye recuperación de contraseña ni gestión de roles.
