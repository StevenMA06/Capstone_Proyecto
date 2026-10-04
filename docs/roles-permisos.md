# Roles y permisos

## Actualización

Conserva una copia de seguridad de la base de datos antes de aplicar la migración. Desde la carpeta del proyecto:

```sh
git pull origin main
php artisan optimize:clear
php artisan migrate
```

No ejecutes `migrate:fresh` sobre datos reales. La migración conserva las cuentas y copia su rol actual a `usuario_rol`. Reutiliza `permisos` y `rol_permiso`, y crea `auditoria_permisos`. El retroceso automático de esta migración está bloqueado para evitar perder asignaciones.

## Administración

- **Roles y permisos:** crear roles, configurar su consulta y administración por módulo, o inhabilitarlos sin borrar su historia.
- **Usuarios y roles:** buscar una cuenta y asignarle varios roles activos. El cargo laboral se muestra como referencia y no determina los permisos.
- Los permisos efectivos son la unión de los roles activos. El menú muestra una sola entrada por módulo. Las rutas y operaciones también verifican permisos en el servidor.
- Consultar permite ver listados y fichas; administrar permite las operaciones de escritura del módulo. Administrar requiere consultar. La supervisión de instalaciones permite ver asignaciones de otros técnicos; sin ella, el técnico solo ve las propias.
- Administrador y Gerente conservan acceso completo y son roles protegidos. Solo Administrador puede asignar el rol Administrador o modificar una cuenta propietaria; ese rol no se puede retirar del propietario.
- Los cambios se aplican en la siguiente solicitud, incluso con una sesión abierta. Una cuenta sin roles activos no puede ingresar. Un rol activo sin permisos no concede acceso implícito a módulos.
- Se impide dejar el sistema sin una cuenta vigente capaz de administrar usuarios, roles y asignaciones. El vencimiento laboral automático sigue inhabilitando al empleado y sus cuentas.

## Compatibilidad y auditoría

`usuarios.rol_id` se mantiene como rol principal para compatibilidad. La autorización utiliza `usuario_rol`. Editar el rol principal conserva los roles adicionales; para revisar el conjunto completo utiliza Usuarios y roles. Las modificaciones directas por SQL no ejecutan estas reglas ni generan auditoría: utiliza las pantallas o servicios de la aplicación.

La tabla `auditoria_permisos` conserva actor, acción, entidad, valores anteriores y nuevos, y fecha/hora. Las operaciones automáticas pueden tener actor nulo. No se modifican las credenciales ni la configuración de Brevo o del segundo factor.

Los permisos habilitan funcionalidades existentes; no implementan módulos futuros por sí mismos.
