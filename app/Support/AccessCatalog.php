<?php

namespace App\Support;

use App\Models\Role;
use Illuminate\Support\Facades\DB;

class AccessCatalog
{
    public const MODULES = [
        'dashboard' => 'Panel principal', 'clients' => 'Clientes', 'plans' => 'Planes de servicio',
        'contracts' => 'Contratos', 'sales' => 'Ventas', 'payments' => 'Pagos', 'zones' => 'Zonas',
        'employees' => 'Empleados', 'users' => 'Usuarios', 'roles' => 'Roles y permisos',
        'user-roles' => 'Usuarios y roles', 'access' => 'Historial de accesos',
        'equipments' => 'Equipos', 'materials' => 'Materiales', 'kardex' => 'Kardex',
        'installations' => 'Programación de instalaciones', 'technician' => 'Ejecución de instalaciones',
        'reports' => 'Reportes', 'indicators' => 'Indicadores', 'bi' => 'Business Intelligence', 'chatbot' => 'Chatbot',
    ];

    public static function permissions(): array
    {
        $result = [];
        foreach (self::MODULES as $module => $label) {
            $result[$module.'.view'] = ['modulo' => $label, 'accion' => 'Consultar', 'descripcion' => 'Ver '.$label];
            if (in_array($module, ['clients', 'plans', 'contracts', 'payments', 'zones', 'employees', 'users', 'roles', 'user-roles', 'equipments', 'materials', 'technician', 'chatbot'])) {
                $result[$module.'.manage'] = ['modulo' => $label, 'accion' => 'Administrar', 'descripcion' => 'Registrar, actualizar y ejecutar acciones de '.$label];
            }
        }
        $result['technician.supervise'] = ['modulo' => self::MODULES['technician'], 'accion' => 'Supervisar', 'descripcion' => 'Consultar y ejecutar todas las instalaciones'];
        return $result;
    }

    public static function defaults(string $name): array
    {
        if (in_array($name, [Role::ADMIN, Role::MANAGER], true)) return array_keys(self::permissions());
        if ($name === Role::TECHNICIAN) return ['technician.view', 'technician.manage'];
        if ($name === Role::OFFICE) return ['dashboard.view', 'clients.view', 'clients.manage', 'plans.view', 'contracts.view', 'contracts.manage', 'sales.view', 'payments.view', 'payments.manage', 'zones.view', 'reports.view'];
        return [];
    }

    public static function seedRole(Role $role): void
    {
        foreach (DB::table('permisos')->whereIn('codigo', self::defaults($role->nombre))->pluck('permiso_id') as $id) {
            DB::table('rol_permiso')->updateOrInsert(['rol_id' => $role->getKey(), 'permiso_id' => $id], ['permitido' => true]);
        }
    }

    public static function forRoute(string $name, string $method): ?string
    {
        if (in_array($name, ['logout', 'session.status', 'dashboard'])) return null;
        if ($name === 'api.contracts.amount') return 'payments.manage';
        $parts = explode('.', $name); $module = $parts[0]; $action = $parts[1] ?? '';
        if ($module === 'equipment') $module = 'equipments';
        if (! isset(self::MODULES[$module])) return null;
        $write = ! in_array($method, ['GET', 'HEAD']) || in_array($action, ['create', 'edit', 'entry', 'exit', 'adjustment', 'import', 'template', 'reactivate']);
        return $module.'.'.($write && isset(self::permissions()[$module.'.manage']) ? 'manage' : 'view');
    }
}
