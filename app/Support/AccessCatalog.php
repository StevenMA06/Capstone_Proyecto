<?php

namespace App\Support;

use App\Models\Role;
use Illuminate\Support\Facades\DB;

class AccessCatalog
{
    public const MODULES = [
        'dashboard' => 'Panel de Gerencia', 'office' => 'Panel de Call Center',
        'clients' => 'Clientes', 'plans' => 'Planes de servicio', 'contracts' => 'Contratos',
        'sales' => 'Ventas', 'payments' => 'Pagos', 'zones' => 'Zonas',
        'employees' => 'Empleados (solo Administrador)', 'users' => 'Usuarios',
        'roles' => 'Roles y permisos', 'user-roles' => 'Asignación de roles',
        'access' => 'Historial de accesos (solo Administrador)',
        'equipments' => 'Equipos', 'materials' => 'Materiales', 'kardex' => 'Kardex',
        'orders' => 'Pedidos de instalación', 'warehouse' => 'Solicitudes a Almacén', 'technician' => 'Ejecución de instalaciones',
        'reports' => 'Reportes', 'indicators' => 'Indicadores', 'bi' => 'Business Intelligence', 'chatbot' => 'Chatbot',
    ];

    public static function permissions(): array
    {
        $definitions = [
            'dashboard.view' => ['Acceder', 'Abrir el panel de Gerencia y consultar el resumen global de clientes, contratos, pagos, ventas e inventario.'],
            'office.view' => ['Acceder', 'Abrir el panel de Call Center con enlaces a sus funcionalidades autorizadas.'],
            'office.coordination' => ['Coordinar visitas', 'Confirmar fechas de instalación con clientes y solicitar a Gerencia los cambios posteriores, sin acceso a la gestión operativa de pedidos.'],
        ];
        foreach (['clients','plans','contracts','payments','zones','employees','users','equipments','materials'] as $module) {
            $definitions[$module.'.view'] = ['Consultar', 'Ver el listado y las fichas completas de '.self::MODULES[$module].'. No permite modificar registros.'];
            $definitions[$module.'.create'] = ['Registrar', 'Crear registros de '.self::MODULES[$module].'. Requiere consultar.'];
            $definitions[$module.'.update'] = ['Actualizar', 'Modificar los datos de '.self::MODULES[$module].'. Requiere consultar.'];
            if ($module !== 'contracts') $definitions[$module.'.status'] = ['Cambiar estado', 'Habilitar o inhabilitar registros según las acciones disponibles en '.self::MODULES[$module].'. Requiere consultar.'];
        }
        $definitions['clients.status'][1] = 'Inhabilitar clientes conservando su información. Requiere consultar.';
        $definitions['equipments.status'][1] = 'Cambiar disponibilidad, mantenimiento o inhabilitación de equipos. Requiere consultar y actualizar; respeta las asignaciones vigentes.';
        $definitions['materials.status'][1] = 'Habilitar o inhabilitar materiales desde su edición. Requiere consultar y actualizar.';
        $definitions['users.create'][1] = 'Crear una cuenta y asignar su rol inicial. Requiere consultar usuarios y administrar asignaciones de roles.';
        $definitions['equipments.create'][1] = 'Registrar equipos con su condición y estado inicial. Requiere consultar.';
        $definitions['materials.create'][1] = 'Registrar materiales con su estado y stock inicial; el ingreso genera su movimiento de inventario. Requiere consultar.';
        unset($definitions['payments.status']);
        $definitions += [
            'contracts.update' => ['Actualizar', 'Editar condiciones, adjuntar el contrato firmado, activar o cancelar contratos. Estas operaciones pueden generar ventas y pagos.'],
            'contracts.cancel.request' => ['Solicitar cancelación', 'Iniciar la cancelación de un contrato con motivo estructurado y evidencia adjunta. La solicitud requiere la decisión de otra cuenta autorizada.'],
            'contracts.cancel.inbox' => ['Consultar solicitudes de cancelación', 'Acceder a la subsección de solicitudes de cancelación, buscar y filtrar solicitudes y consultar su estado. No permite aprobar ni rechazar solicitudes.'],
            'contracts.cancel.approve' => ['Resolver cancelaciones', 'Aprobar o rechazar solicitudes documentadas desde la subsección de cancelaciones. No puedes resolver una solicitud iniciada por tu propia cuenta.'],
            'contracts.cancel.evidence' => ['Consultar evidencia de cancelación', 'Abrir el archivo de respaldo de una solicitud de cancelación conservando el registro de quién lo solicitó y resolvió.'],
            'contracts.reactivate' => ['Reactivar', 'Reactivar un contrato cancelado mediante su flujo de reactivación.'],
            'contracts.documents' => ['Descargar documentos', 'Descargar contrato PDF, borrador para firma y evidencias contractuales. Requiere consultar contratos.'],
            'contracts.assigned' => ['Consultar información asignada', 'Ver código, estado, plan y dirección de contratos vinculados a pedidos asignados al empleado. No abre otras fichas ni documentos.'],
            'sales.create' => ['Registrar venta por firma', 'Adjuntar el PDF firmado de un contrato pendiente. Al verificar su firma se activa el contrato y se registra la venta; no permite editar condiciones ni cancelar contratos.'],
            'sales.view' => ['Consultar', 'Ver las ventas y sus totales. Las ventas se generan mediante la activación de contratos; no existe un formulario independiente de venta.'],
            'payments.complete' => ['Completar pago', 'Marcar un pago como completado.'],
            'payments.cancel' => ['Anular pago', 'Anular un pago conservando su registro.'],
            'payments.reminders' => ['Gestionar recordatorios', 'Crear y atender recordatorios asociados a pagos.'],
            'payments.receipt' => ['Ver comprobante', 'Descargar el comprobante de un pago.'],
            'equipments.import' => ['Importar equipos', 'Descargar la plantilla, previsualizar, confirmar o cancelar una importación de equipos.'],
            'equipments.availability' => ['Consultar disponibilidad', 'Ver nombres y cantidades disponibles por tipo, marca y modelo; no muestra fichas ni movimientos.'],
            'equipments.assigned' => ['Consultar asignaciones', 'Ver únicamente equipos vinculados a pedidos asignados al empleado.'],
            'materials.import' => ['Importar materiales', 'Descargar la plantilla, previsualizar, confirmar o cancelar una importación de materiales.'],
            'materials.availability' => ['Consultar disponibilidad', 'Ver materiales activos, su unidad y stock disponible; no muestra fichas ni movimientos.'],
            'materials.entry' => ['Registrar entrada', 'Registrar ingresos de material y su movimiento automático en Kardex.'],
            'materials.exit' => ['Registrar salida', 'Registrar salidas de material y su movimiento automático en Kardex.'],
            'materials.adjustment' => ['Ajustar existencias', 'Corregir existencias con justificación y dejar el movimiento de ajuste en Kardex.'],
            'kardex.view' => ['Consultar todos los movimientos', 'Consultar y filtrar todos los movimientos. El Kardex se genera desde las operaciones de inventario; sus registros no se editan ni eliminan directamente.'],
            'kardex.related' => ['Consultar movimientos relacionados', 'Ver solo movimientos vinculados a pedidos asignados al empleado, sin acceso al historial global.'],
            'orders.view' => ['Consultar', 'Ver todos los pedidos de instalación y su programación.'],
            'orders.approve' => ['Aprobar reprogramaciones', 'Ver y resolver cambios de fecha pendientes en Pedidos de instalación. Requiere consultar pedidos y una cuenta distinta de quien solicitó el cambio.'],
            'orders.resources.approve' => ['Autorizar recursos de instalación', 'Ver y resolver solicitudes pendientes de autorización en Pedidos de instalación. Requiere consultar pedidos y una cuenta distinta de quien solicitó los recursos.'],
            'orders.schedule' => ['Coordinar y asignar técnicos', 'Confirmar la programación, definir el equipo técnico responsable y reabrir pedidos cancelados cuando corresponda.'],
            'orders.resources.request' => ['Solicitar recursos para una instalación', 'El personal responsable de pedidos registra los recursos comunicados por el equipo técnico. Requiere permiso para gestionar pedidos y no permite aprobar ni preparar recursos.'],
            'orders.cancel' => ['Cancelar', 'Cancelar pedidos no iniciados conservando su historial.'],
            'orders.assigned' => ['Consultar asignaciones', 'Ver únicamente pedidos asignados al empleado.'],
            'warehouse.view' => ['Consultar solicitudes de Almacén', 'Abrir Solicitudes de instalación y ver los recursos solicitados y su estado. Las autorizaciones pendientes se consultan con el permiso Autorizar recursos de instalación.'],
            'warehouse.fulfill' => ['Atender solicitudes', 'Reservar equipo y material para el pedido tras verificar la autorización que corresponda.'],
            'technician.panel' => ['Abrir panel técnico', 'Entrar al panel técnico. Requiere consultar ejecuciones; por sí solo no permite registrar trabajo.'],
            'technician.view' => ['Consultar ejecuciones', 'Ver fichas y evidencias de sus instalaciones asignadas. Para ver las de otros técnicos se necesita Supervisar.'],
            'technician.supervise' => ['Supervisar', 'Consultar ejecuciones de todos los técnicos. No permite registrar o modificar trabajo sin sus permisos de acción.'],
            'technician.start' => ['Confirmar e iniciar', 'Confirmar la asignación propia e iniciar la instalación con hora del servidor.'],
            'technician.equipment' => ['Registrar equipos utilizados', 'Vincular equipos disponibles con la instalación y registrar sus movimientos.'],
            'technician.material' => ['Registrar uso de materiales', 'Registrar el consumo de materiales, descontar stock y generar movimientos relacionados.'],
            'technician.observation' => ['Registrar observaciones e incidencias', 'Guardar observaciones e incidencias estructuradas con recurso, causa y evidencia.'],
            'technician.evidence' => ['Adjuntar fotografías', 'Adjuntar evidencias fotográficas de la instalación.'],
            'roles.view' => ['Consultar', 'Ver roles, estados y permisos configurados.'],
            'roles.manage' => ['Configurar roles', 'Crear roles, cambiar su estado y habilitar o retirar permisos. Administrador y los permisos exclusivos del propietario están protegidos.'],
            'user-roles.view' => ['Consultar', 'Buscar cuentas y consultar sus roles asignados.'],
            'user-roles.manage' => ['Asignar y retirar roles', 'Modificar los roles activos asignados a una cuenta. Solo Administrador puede asignar su propio rol o modificar cuentas propietarias.'],
            'access.view' => ['Consultar', 'Ver y filtrar accesos por usuario, fecha y resultado. Exclusivo del Administrador.'],
            'reports.view' => ['Abrir demostración', 'Abrir la pantalla de reportes de demostración; aún no genera ni exporta reportes reales.'],
            'indicators.view' => ['Abrir demostración', 'Abrir la pantalla de indicadores de demostración, sin indicadores operativos integrados.'],
            'bi.view' => ['Abrir demostración', 'Abrir el panel BI de demostración. No concede acceso a los módulos de origen.'],
            'chatbot.view' => ['Abrir interfaz', 'Abrir el chatbot actualmente en configuración.'],
            'chatbot.query' => ['Enviar consultas', 'Enviar mensajes e iniciar conversaciones en el chatbot. La respuesta actual es de demostración, sin consulta operativa integrada.'],
        ];
        // Replace the generic description, rather than granting an unrelated sales action.
        $definitions['contracts.update'][1] = 'Editar condiciones y adjuntar el contrato firmado. La cancelación se tramita por separado y requiere evidencia y aprobación de otra cuenta autorizada.';
        $result = [];
        foreach ($definitions as $code => [$action, $description]) {
            $result[$code] = ['modulo' => self::MODULES[explode('.', $code)[0]], 'accion' => $action, 'descripcion' => $description];
        }
        return $result;
    }

    public static function reserved(string $code): bool
    {
        return str_starts_with($code, 'employees.') || $code === 'access.view';
    }

    public static function dependencies(string $code): array
    {
        [$module, $action] = explode('.', $code, 2);
        if ($code === 'users.create') return ['users.view','user-roles.view','user-roles.manage'];
        if ($code === 'sales.create') return ['sales.view','contracts.view'];
        if ($code === 'contracts.cancel.request') return ['contracts.view'];
        if ($code === 'contracts.cancel.inbox') return ['contracts.view'];
        if ($code === 'contracts.cancel.approve') return ['contracts.view','contracts.cancel.inbox','contracts.cancel.request','contracts.cancel.evidence'];
        if ($code === 'contracts.cancel.evidence') return ['contracts.view'];
        if (in_array($code, ['equipments.status','materials.status'])) return [$module.'.view',$module.'.update'];
        if ($code === 'orders.resources.request') return ['orders.assigned', 'orders.schedule'];
        if (in_array($action, ['view', 'availability', 'assigned', 'related'])) return [];
        return [$module.'.view'];
    }

    public static function defaults(string $name): array
    {
        $all = array_keys(self::permissions());
        if ($name === Role::ADMIN) return $all;
        if ($name === Role::MANAGER) return array_values(array_filter($all, fn ($p) => ! self::reserved($p)
            // Gerencia can review warehouse requests; physically reserving/issuing
            // resources remains restricted to warehouse.fulfill.
            && $p !== 'warehouse.fulfill'
            && !in_array($p, ['office.view','technician.panel','technician.start','technician.equipment','technician.material','technician.observation','technician.evidence'])));
        if ($name === Role::OFFICE) return ['office.view','office.coordination','clients.view','clients.create','clients.update','plans.view','sales.view','sales.create','contracts.view','contracts.create','contracts.documents','zones.view','equipments.availability','materials.availability','technician.view','technician.supervise'];
        if ($name === Role::TECHNICIAN) return ['technician.panel','technician.view','technician.start','technician.equipment','technician.material','technician.observation','technician.evidence','plans.view','zones.view','contracts.assigned','equipments.assigned','kardex.related','orders.assigned'];
        if ($name === Role::WAREHOUSE) return array_values(array_filter($all, fn ($p) => (str_starts_with($p, 'equipments.') || str_starts_with($p, 'materials.') || str_starts_with($p, 'warehouse.') || $p === 'kardex.view') && !str_ends_with($p, '.assigned')));
        if ($name === Role::INSTALLATIONS) return ['technician.panel','technician.view','technician.supervise','technician.start','technician.equipment','technician.material','technician.observation','technician.evidence','clients.view','plans.view','sales.view','contracts.view','contracts.documents','payments.view','equipments.view','materials.view','kardex.view','zones.view','orders.view','orders.assigned','orders.schedule','orders.resources.request','orders.cancel'];
        return []; // TI and custom roles are configured explicitly by checkboxes.
    }

    public static function seedRole(Role $role): void
    {
        foreach (DB::table('permisos')->whereIn('codigo', self::defaults($role->nombre))->pluck('permiso_id') as $id) {
            DB::table('rol_permiso')->updateOrInsert(['rol_id' => $role->getKey(), 'permiso_id' => $id], ['permitido' => true]);
        }
    }

    public static function forRoute(string $name, string $method = 'GET'): ?string
    {
        if (in_array($name, ['logout','session.status','dashboard'])) return null;
        if ($name === 'dashboard.management') return 'dashboard.view';
        if ($name === 'dashboard.office') return 'office.view';
        if (in_array($name, ['office.coordination.index', 'office.coordination.save', 'office.coordination.coverage'], true)) return 'office.coordination';
        if ($name === 'sales.confirm') return 'sales.create';
        if ($name === 'api.contracts.amount') return 'payments.view';
        if ($name === 'executions.index') return 'technician.view';
        if ($name === 'technician.index') return 'technician.panel';
        if ($name === 'installations.index') return 'orders.view';
        if ($name === 'equipment.index') return 'equipments.view';
        [$module, $action] = array_pad(explode('.', $name, 2), 2, '');
        $special = [
            'contracts.pdf'=>'contracts.documents','contracts.draft'=>'contracts.documents','contracts.evidence'=>'contracts.documents',
            'contracts.cancellation.request'=>'contracts.cancel.request',
            'contracts.cancellations'=>'contracts.cancel.inbox',
            'contracts.cancellation.approve'=>'contracts.cancel.approve','contracts.cancellation.reject'=>'contracts.cancel.approve',
            'contracts.cancellation.evidence'=>'contracts.cancel.evidence',
            'payments.receipt'=>'payments.receipt','payments.reminders.create'=>'payments.reminders','payments.reminders.attend'=>'payments.reminders',
            'user-roles.update'=>'user-roles.manage','roles.store'=>'roles.manage','roles.update'=>'roles.manage',
            'chatbot.query'=>'chatbot.query','chatbot.new'=>'chatbot.query',
            'equipments.kardex'=>'kardex.view','materials.kardex'=>'kardex.view',
        ];
        if (isset($special[$name])) return $special[$name];
        if ($module === 'technician') {
            if ($action === 'evidence.store') return 'technician.evidence';
            if ($action === 'confirm') return 'technician.start';
            if ($action === 'incident') return 'technician.observation';
            if ($action === 'incident.download') return 'technician.view';
            if ($action === 'syncEvidence') return 'technician.evidence';
            if (in_array($action, ['start','equipment','material','observation'])) return 'technician.'.$action;
            if (in_array($action, ['show','contract.preview','contract.download','evidence.preview','evidence.download'])) return 'technician.view';
            return null;
        }
        if ($module === 'equipments' && (str_starts_with($action, 'import') || $action === 'template')) return 'equipments.import';
        if ($module === 'materials' && (str_starts_with($action, 'import') || $action === 'template')) return 'materials.import';
        if ($module === 'materials' && in_array(explode('.', $action)[0], ['entry','exit','adjustment'])) return 'materials.'.explode('.', $action)[0];
        if ($module === 'contracts' && str_starts_with($action, 'reactivate')) return 'contracts.reactivate';
        if (in_array($name, ['orders.reprogram.approve', 'orders.reprogram.reject'], true)) return 'orders.approve';
        if ($name === 'orders.reprogram.evidence') return 'orders.view';
        if (in_array($name, ['orders.resources.approve', 'orders.resources.reject'], true)) return 'orders.resources.approve';
        if ($name === 'orders.resources.request') return 'orders.resources.request';
        if (in_array($name, ['orders.equipment', 'orders.material'], true)) return 'orders.schedule';
        if ($name === 'warehouse.requests.index') return 'warehouse.view';
        if ($name === 'office.coordination.contract') return 'office.coordination';
        if (in_array($name, ['warehouse.requests.fulfill', 'warehouse.requests.reject', 'warehouse.requests.escalate'], true)) return 'warehouse.fulfill';
        $suffix = match ($action) {
            'index','show','cancelled','lookup' => 'view', 'create','store' => 'create', 'edit','update' => 'update',
            'deactivate','status' => 'status', 'cancel','complete','schedule','availability','assigned','related' => $action,
            default => null,
        };
        $code = $suffix ? $module.'.'.$suffix : null;
        return $code && isset(self::permissions()[$code]) ? $code : null;
    }
}
