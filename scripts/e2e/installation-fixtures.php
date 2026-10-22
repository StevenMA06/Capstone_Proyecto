<?php

// Solo desde fixture.php: entorno testing y base exclusiva previamente comprobados.
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\InstallationOrderService;
use App\Services\InstallationResourceRequestService;
use App\Services\InstallationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return function (string $command, string $arg, User $actor, array $clientData, array $planData): array {
    if ($command === 'pi-state') {
        $id = (int) $arg;
        $order = DB::table('pedidos_instalacion')->where('pedido_id', $id)->first();
        if (!$order) throw new RuntimeException('Pedido ficticio no encontrado.');
        $photos = DB::table('evidencias_instalacion')->where('pedido_id', $id)->get()->map(function ($photo) {
            $photo->local_exists = Storage::disk('local')->exists($photo->ruta_archivo);
            $photo->local_sha256 = $photo->local_exists ? hash_file('sha256', Storage::disk('local')->path($photo->ruta_archivo)) : null;
            return $photo;
        });
        return ['order' => $order, 'execution' => DB::table('ejecuciones_instalacion')->where('pedido_id', $id)->first(),
            'groups' => DB::table('pedido_solicitudes_recursos')->where('pedido_id', $id)->get()->toArray(),
            'requests' => DB::table('pedido_solicitudes_recurso')->where('pedido_id', $id)->get()->toArray(),
            'photos' => $photos->toArray(), 'drive' => DB::table('evidencias_drive_instalacion')->where('pedido_id', $id)->get()->toArray(),
            'contract' => DB::table('contratos')->where('contrato_id', $order->contrato_id)->first(),
            'timezone' => config('app.timezone'), 'now' => now('America/Lima')->format('Y-m-d H:i:s')];
    }
    if (!in_array($command, ['pi-coordination', 'pi-assignment', 'pi-start', 'pi-evidence'], true)) throw new RuntimeException('Operación de instalaciones E2E desconocida.');
    $tag = bin2hex(random_bytes(6));
    $makeUser = function (string $name, string $roleName, bool $technician = false) use ($tag): User {
        $role = Role::where('nombre', $roleName)->firstOrFail();
        $employee = $technician ? Employee::create(['tipo_documento' => 'OTRO', 'numero_documento' => substr($name, 0, 1).$tag,
            'nombres' => 'Técnico QA', 'apellidos' => $tag, 'cargo' => 'Técnico de Campo', 'estado' => 'ACTIVO',
            'correo' => $name.'.'.$tag.'@example.test', 'telefono' => '900111222', 'fecha_ingreso' => now()->subDay()->toDateString()]) : null;
        $user = User::create(['nombre_usuario' => 'qa_'.$name.'_'.$tag, 'correo' => $name.'.'.$tag.'@example.test',
            'password_hash' => 'PruebasLunnys2026', 'estado' => 'ACTIVO', 'rol_id' => $role->getKey(), 'empleado_id' => $employee?->getKey()]);
        $user->roles()->sync([$role->getKey()]);
        return $user;
    };
    $office = $makeUser('office', Role::OFFICE);
    $tech = $makeUser('tech', Role::TECHNICIAN, true);
    $other = $makeUser('other', Role::TECHNICIAN, true);
    $warehouse = $makeUser('warehouse', Role::WAREHOUSE);
    $requester = $makeUser('requester', Role::INSTALLATIONS);
    $requester->roles()->syncWithoutDetaching([Role::where('nombre', Role::WAREHOUSE)->value('rol_id')]);
    $reviewer = $makeUser('reviewer', Role::MANAGER);
    $orders = app(InstallationOrderService::class);
    $resources = app(InstallationResourceRequestService::class);
    $makeOrder = function (int $days = 1) use ($actor, $office, $warehouse, $reviewer, $clientData, $planData, $orders, $resources, $command): array {
        $clientData['numero_documento'] = (string) random_int(80000000, 89999999);
        $planData['nombre'] .= ' '.bin2hex(random_bytes(4));
        $state = (require __DIR__.'/commercial-fixtures.php')('commercial-contract', '', $actor, $clientData, $planData);
        $order = $state['order'];
        $day = now('America/Lima')->addDays($days)->toDateString();
        // Fecha contractual es un prerrequisito ficticio; la coordinación se ejecuta por UI en PED-01.
        DB::table('contratos')->where('contrato_id', $order->contrato_id)->update(['fecha_inicio' => $day]);
        $equipment = (require __DIR__.'/inventory-fixtures.php')('inventory-equipment', '', $actor, $clientData, $planData);
        $material = (require __DIR__.'/inventory-fixtures.php')('inventory-material', '', $actor, $clientData, $planData);
        if ($command !== 'pi-coordination') {
            $orders->coordinate($office, $order->pedido_id, ['fecha_programada' => $day.' 09:00:00', 'franja_horaria' => '08:00 a 12:00', 'canal_coordinacion' => 'LLAMADA']);
            $group = $resources->createRequestGroup($actor, $order->pedido_id, true, true);
            $resources->request($actor, $order->pedido_id, 'EQUIPO', $equipment['equipo_id'], '1', $group);
            $resources->request($actor, $order->pedido_id, 'MATERIAL', $material['material_id'], '10', $group);
            $first = DB::table('pedido_solicitudes_recurso')->where('solicitud_grupo_id', $group)->first();
            if ($first->estado === 'PENDIENTE_GERENCIA') $resources->approve($reviewer, $first->solicitud_id);
            $resources->fulfill($warehouse, $first->solicitud_id);
        }
        return ['id' => $order->pedido_id, 'code' => $order->codigo, 'contract_id' => $order->contrato_id,
            'day' => $day, 'equipment' => $equipment, 'material' => $material];
    };
    $first = $makeOrder();
    $second = $makeOrder($command === 'pi-assignment' ? 1 : 2);
    if (in_array($command, ['pi-start', 'pi-evidence'], true)) {
        $orders->assign($actor, $first['id'], $tech->empleado_id);
        $orders->assign($actor, $second['id'], $tech->empleado_id);
        if ($command === 'pi-evidence') {
            $orders->confirm($tech, $first['id']);
            app(InstallationService::class)->start($tech, $first['id']);
        }
    }
    return ['first' => $first, 'second' => $second, 'office' => $office->nombre_usuario,
        'warehouse' => $warehouse->nombre_usuario, 'requester' => $requester->nombre_usuario, 'reviewer' => $reviewer->nombre_usuario,
        'tech' => $tech->nombre_usuario, 'tech_id' => $tech->empleado_id, 'other' => $other->nombre_usuario,
        'other_id' => $other->empleado_id];
};
