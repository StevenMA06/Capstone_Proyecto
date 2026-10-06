# Roles y permisos

## Actualización

Respalda la base de datos y ejecuta desde el proyecto:

```sh
git pull origin main
php artisan optimize:clear
php artisan migrate
```

No ejecutes `migrate:fresh` sobre datos reales. La migración `2026_10_05_000001_apply_access_matrix` conserva cuentas, contraseñas y asignaciones; renombra Gerente a Gerencia, Usuario de oficina a Call Center y Técnico a Técnico de campo. Agrega las tres jefaturas. Los cargos laborales son independientes de los roles.

La migración aplica los permisos iniciales de la matriz a los roles estándar, convierte permisos antiguos de los roles personalizados y reserva Empleados e Historial de accesos para Administrador. Cierra sesiones abiertas e invalida códigos pendientes: será necesario iniciar sesión nuevamente. Registra el cambio en auditoría. Para revertirla se necesita el respaldo.

## Permisos iniciales

| Rol | Acceso inicial |
| --- | --- |
| Administrador | Todas las interfaces y acciones. Rol propietario protegido. |
| Gerencia | Panel de Gerencia; administración comercial, inventario, usuarios, roles y pedidos; consulta y supervisión de instalaciones; reportes, indicadores, BI y chatbot. Sin Empleados, Historial de accesos, panel de Call Center, panel técnico ni registro de ejecución. |
| Call Center | Su panel; registrar, consultar y actualizar clientes; consultar planes y zonas; registrar y consultar contratos y ventas; disponibilidad de equipos y materiales; registrar y consultar pedidos; consultar ejecuciones. |
| Técnico de campo | Su panel y ejecución de instalaciones asignadas; información de contratos, equipos, pedidos y movimientos propios; consulta de planes y zonas. |
| Jefe de Almacén | Administración de equipos y materiales, movimientos de inventario y consulta del kardex. |
| Jefe de TI | Sin módulos iniciales: se habilitan las casillas autorizadas. |
| Jefe de Instalaciones | Panel técnico y supervisión de ejecuciones; administrar pedidos; consultar clientes, planes, ventas, contratos, pagos, equipos, materiales, kardex y zonas. |

Las celdas «Según permiso» no conceden acceso por defecto. Gerencia o Administrador habilitan las casillas correspondientes. Los roles estándar, salvo Administrador, son configurables: los permisos guardados determinan su acceso efectivo.

## Alcance de las casillas

`AccessCatalog` contiene las descripciones mostradas en pantalla. Se distinguen consulta, registro, actualización, cambio de estado y acciones particulares: importar equipos; entradas, salidas y ajustes de materiales; completar/anular pagos y recordatorios; descargar documentos; programar/cancelar pedidos; iniciar/finalizar instalaciones, consumir recursos y adjuntar evidencias.

- Las acciones requieren su consulta; el formulario selecciona las dependencias y el servidor las valida. Registrar usuarios requiere también asignar roles.
- Consultar no habilita escritura. Los botones, formularios, rutas directas y solicitudes verifican permisos. Las rutas autenticadas no clasificadas se deniegan.
- La ficha del cliente solo muestra historiales cuyos módulos están autorizados. El inventario no muestra kardex sin su permiso.
- Disponibilidad abre una vista limitada del stock. Información asignada no abre fichas generales de contratos, equipos o pedidos. Kardex relacionado limita movimientos a pedidos del técnico.
- Consultar ejecuciones muestra las propias; Supervisar amplía la consulta a todas. Las casillas de ejecución habilitan cada operación por separado. Gerencia y Call Center consultan sin registrar ejecución por defecto.
- Registrar venta por firma permite adjuntar el PDF de un contrato pendiente. La validación existente de firma activa el contrato y genera la venta; no concede edición de condiciones ni cancelación.
- Cambiar estado de equipos/materiales utiliza el formulario de edición y requiere Actualizar. Completar y anular pagos son permisos independientes.

## Pedidos y paneles

Inicio redirige al panel de Gerencia, Call Center o técnico según los permisos; sin panel autorizado muestra los módulos disponibles. Varios roles unen sus permisos activos.

Pedidos permite registrar un pedido de un contrato activo sin otro pedido vigente. Programar permite asignar un técnico de campo laboralmente vigente y una fecha; reprogramar exige motivo. Solo se cancelan pedidos no iniciados y sin equipos todavía asignados. La ejecución conserva sus controles de asignación, estado, evidencias y existencias.

## Administración y límites

Empleados e Historial de accesos son exclusivos de Administrador y no se habilitan en otros roles. Solo Administrador puede asignar su rol o modificar cuentas propietarias. El rol propietario no se retira de su cuenta. Debe quedar una cuenta vigente capaz de administrar usuarios, roles y asignaciones.

Los cambios de permisos se aplican en la siguiente solicitud. Una cuenta sin roles activos no puede entrar. Un rol activo sin permisos puede autenticarse, pero no obtiene módulos implícitamente. El cese laboral sigue inhabilitando al empleado y sus cuentas.

`usuarios.rol_id` conserva el rol principal; la autorización usa `usuario_rol`. Editar el rol principal conserva roles adicionales. La auditoría registra actor, acción, entidad, valores anteriores/nuevos y fecha/hora. Los cambios directos mediante SQL no ejecutan estas protecciones: utiliza interfaces o servicios.

Reportes, indicadores, BI y chatbot conservan sus pantallas de demostración actuales; sus permisos no implementan análisis ni consultas de IA sobre datos reales. El catálogo lo indica. No se modifican Brevo ni el segundo factor.
