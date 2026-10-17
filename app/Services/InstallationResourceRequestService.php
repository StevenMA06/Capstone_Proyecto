<?php

namespace App\Services;

use App\Models\User;
use App\Support\ResourceRejectionReasons;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InstallationResourceRequestService
{
    public function __construct(private InstallationOrderService $orders) {}

    public function createRequestGroup(User $actor, int $orderId, bool $includesEquipment = false, bool $includesMaterials = false): int
    {
        abort_unless($actor->hasPermission('orders.resources.request'), 403);
        $order = $this->validatedOrder($orderId, $actor);
        if (! $includesEquipment && ! $includesMaterials) $this->fail('recurso', 'Selecciona equipo, material o ambos antes de enviar la solicitud.');
        if (! $this->hasAvailableEquipment() && ! DB::table('pedido_equipos')->where('pedido_id', $orderId)
            ->whereIn('estado_asignacion', ['ASIGNADO', 'UTILIZADO'])->whereNull('fecha_devolucion')->exists()
            && ! DB::table('pedido_solicitudes_recurso')->where('pedido_id', $orderId)->where('tipo', 'EQUIPO')
                ->whereIn('estado', ['PENDIENTE_ALMACEN', 'PENDIENTE_GERENCIA', 'AUTORIZADA'])->exists()) {
            $this->fail('equipo_id', 'No hay equipos disponibles. La solicitud a Almacén estará habilitada después del reabastecimiento de stock.');
        }
        return (int) DB::table('pedido_solicitudes_recursos')->insertGetId([
            'pedido_id' => $orderId,
            'solicitado_por' => $actor->getKey(),
            'solicitado_en' => now('America/Lima'),
            'incluye_equipo' => $includesEquipment,
            'incluye_material' => $includesMaterials,
        ]);
    }

    public function requestEquipmentType(User $actor, int $orderId, string $requestedType, ?int $groupId = null): void
    {
        abort_unless($actor->hasPermission('orders.resources.request'), 403);
        DB::transaction(function () use ($actor, $orderId, $requestedType, $groupId): void {
            $order = $this->validatedOrder($orderId, $actor);
            $type = DB::table('equipos')->where('tipo', trim($requestedType))->value('tipo');
            if (! $type) $this->fail('equipo_tipo', 'Selecciona un tipo de equipo registrado.');
            if (! $this->hasAvailableEquipment($type)) {
                $this->fail('equipo_tipo', 'No hay equipos disponibles de este tipo. Espera el reabastecimiento de stock.');
            }
            $open = DB::table('pedido_solicitudes_recurso')->where('pedido_id', $orderId)->where('tipo', 'EQUIPO')
                ->whereIn('estado', ['PENDIENTE_ALMACEN', 'PENDIENTE_GERENCIA', 'AUTORIZADA'])
                ->where(function ($query) use ($type): void {
                    $query->where('equipo_tipo_solicitado', $type)
                        ->orWhereIn('equipo_id', DB::table('equipos')->select('equipo_id')->where('tipo', $type));
                })->exists();
            if ($open) $this->fail('equipo_tipo', 'Ya existe una solicitud abierta de este tipo de equipo para el pedido.');
            $groupId = $this->ensureGroup($actor, $orderId, $groupId);
            DB::table('pedido_solicitudes_recurso')->insert([
                'pedido_id' => $orderId, 'solicitud_grupo_id' => $groupId, 'tipo' => 'EQUIPO', 'equipo_tipo_solicitado' => $type,
                'cantidad' => '1.000',
                'estado' => $actor->hasPermission('warehouse.fulfill') ? 'PENDIENTE_GERENCIA' : 'PENDIENTE_ALMACEN',
                'solicitado_por' => $actor->getKey(), 'solicitado_en' => now('America/Lima'),
            ]);
        }, 3);
    }

    public function request(User $actor, int $orderId, string $type, int $resourceId, string $amount = '1', ?int $groupId = null): void
    {
        abort_unless($actor->hasPermission('orders.resources.request'), 403);
        DB::transaction(function () use ($actor, $orderId, $type, $resourceId, $amount, $groupId): void {
            $order = $this->validatedOrder($orderId, $actor);
            if (! in_array($type, ['EQUIPO', 'MATERIAL'], true)) $this->fail('recurso', 'Tipo de recurso inválido.');
            $column = $type === 'EQUIPO' ? 'equipo_id' : 'material_id';
            $table = $type === 'EQUIPO' ? 'equipos' : 'materiales';
            $item = DB::table($table)->where($column, $resourceId)->lockForUpdate()->first();
            if (! $item || ($type === 'EQUIPO' && ($item->estado !== 'DISPONIBLE' || in_array($item->condicion, ['MALO', 'MALOGRADO'], true)))
                || ($type === 'MATERIAL' && $item->estado !== 'ACTIVO')) {
                $this->fail($column, 'Selecciona un recurso disponible.');
            }
            if ($type === 'EQUIPO' && DB::table('pedido_equipos')->where('pedido_id', $orderId)->where('equipo_id', $resourceId)
                ->whereIn('estado_asignacion', ['ASIGNADO', 'UTILIZADO'])->whereNull('fecha_devolucion')->exists()) {
                $this->fail('equipo_id', 'Ese equipo ya está reservado para el pedido.');
            }
            $millis = (int) round((float) $amount * 1000);
            if ($millis < 1 || ($type === 'EQUIPO' && $millis !== 1000)) $this->fail('cantidad_asignada', 'Indica una cantidad válida.');
            if ($type === 'MATERIAL') {
                $reserved = DB::table('pedido_materiales as pm')
                    ->join('pedidos_instalacion as p', 'p.pedido_id', '=', 'pm.pedido_id')
                    ->where('pm.material_id', $resourceId)
                    ->whereIn('p.estado', ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO', 'EN_PROCESO'])
                    ->selectRaw('COALESCE(SUM(pm.cantidad_asignada - pm.cantidad_utilizada - pm.cantidad_devuelta), 0) as total')->lockForUpdate()->value('total');
                $pending = DB::table('pedido_solicitudes_recurso')->where('material_id', $resourceId)
                    ->whereIn('estado', ['PENDIENTE_ALMACEN', 'PENDIENTE_GERENCIA', 'AUTORIZADA'])->lockForUpdate()->sum('cantidad');
                $free = (int) round(((float) $item->stock_actual - (float) $reserved - (float) $pending) * 1000);
                if ($millis > $free) $this->fail('cantidad_asignada', 'Stock insuficiente. La cantidad solicitada supera el stock libre; espera el reabastecimiento.');
            }
            if ($type === 'MATERIAL' && ($item->inventario_en_empaque ?? false) && $millis % 1000 !== 0) {
                $unit = $item->unidad_empaque ?: 'empaques';
                $this->fail('cantidad_asignada', 'Este material se controla por empaques completos. Indica un número entero de '.$unit.'.');
            }
            if (DB::table('pedido_solicitudes_recurso')->where('pedido_id', $orderId)->where($column, $resourceId)
                ->whereIn('estado', ['PENDIENTE_ALMACEN', 'PENDIENTE_GERENCIA', 'AUTORIZADA'])->exists()) {
                $this->fail($column, 'Ya existe una solicitud abierta de este recurso para el pedido.');
            }
            if ($type === 'EQUIPO' && DB::table('pedido_solicitudes_recurso')->whereIn('estado', ['PENDIENTE_ALMACEN', 'PENDIENTE_GERENCIA', 'AUTORIZADA'])
                ->where('equipo_tipo_solicitado', $item->tipo)->exists()) {
                $this->fail('equipo_id', 'Almacén ya está revisando una solicitud de este tipo de equipo. Espera esa decisión antes de solicitar una unidad.');
            }
            $groupId = $this->ensureGroup($actor, $orderId, $groupId);
            DB::table('pedido_solicitudes_recurso')->insert([
                'pedido_id' => $orderId,
                'solicitud_grupo_id' => $groupId,
                'tipo' => $type,
                'equipo_id' => $type === 'EQUIPO' ? $resourceId : null,
                'material_id' => $type === 'MATERIAL' ? $resourceId : null,
                'cantidad' => number_format($millis / 1000, 3, '.', ''),
                'estado' => $actor->hasPermission('warehouse.fulfill') ? 'PENDIENTE_GERENCIA' : 'PENDIENTE_ALMACEN',
                'solicitado_por' => $actor->getKey(),
                'solicitado_en' => now('America/Lima'),
            ]);
        }, 3);
    }

    public function canRequest(User $actor): bool
    {
        return $actor->hasPermission('orders.resources.request');
    }

    private function hasAvailableEquipment(?string $type = null): bool
    {
        return DB::table('equipos as e')->where('e.estado', 'DISPONIBLE')
            ->whereNotIn('e.condicion', ['MALO', 'MALOGRADO'])
            ->when($type, fn ($query) => $query->where('e.tipo', $type))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('pedido_solicitudes_recurso as s')
                ->whereIn('s.estado', ['PENDIENTE_ALMACEN', 'PENDIENTE_GERENCIA', 'AUTORIZADA'])
                ->where(fn ($request) => $request->whereColumn('s.equipo_id', 'e.equipo_id')
                    ->orWhereRaw('s.equipo_tipo_solicitado COLLATE utf8mb4_unicode_ci = e.tipo COLLATE utf8mb4_unicode_ci')))
            ->exists();
    }

    public function canRequestForOrder(User $actor, object $order): bool
    {
        if (! $this->canRequest($actor)) return false;

        // The office records the resources communicated by the field team.
        return $actor->hasPermission('orders.schedule');
    }

    public function approve(User $actor, int $requestId): void
    {
        abort_unless($actor->hasPermission('orders.resources.approve'), 403);
        DB::transaction(function () use ($actor, $requestId): void {
            $items = $this->lockGroupItems($requestId);
            if ($items->contains(fn ($item) => $item->estado !== 'PENDIENTE_GERENCIA')) {
                $this->fail('solicitud', 'La solicitud ya fue resuelta o no todos sus recursos esperan a Gerencia.');
            }
            if ($items->contains(fn ($item) => (int) $item->solicitado_por === (int) $actor->getKey())) {
                $this->fail('solicitud', 'Otra cuenta de Gerencia debe autorizar tu solicitud.');
            }
            $request = $items->first();
            $this->orders->editable((int) $request->pedido_id);
            DB::table('pedido_solicitudes_recurso')->whereIn('solicitud_id', $items->pluck('solicitud_id'))->update([
                'estado' => 'AUTORIZADA', 'resuelto_por' => $actor->getKey(), 'resuelto_en' => now('America/Lima'),
            ]);
        }, 3);
    }

    public function escalate(User $actor, int $requestId): void
    {
        abort_unless($actor->hasPermission('warehouse.fulfill'), 403);
        DB::transaction(function () use ($actor, $requestId): void {
            $items = $this->lockGroupItems($requestId);
            if ($items->contains(fn ($item) => $item->estado !== 'PENDIENTE_ALMACEN'
                || (int) $item->solicitado_por !== (int) $actor->getKey())) {
                $this->fail('solicitud', 'Esta solicitud no necesita autorización adicional o ya cambió de estado.');
            }
            DB::table('pedido_solicitudes_recurso')->whereIn('solicitud_id', $items->pluck('solicitud_id'))
                ->update(['estado' => 'PENDIENTE_GERENCIA']);
        }, 3);
    }

    public function reject(User $actor, int $requestId, string $reasonCode, ?string $detail = null): void
    {
        DB::transaction(function () use ($actor, $requestId, $reasonCode, $detail): void {
            $items = $this->lockGroupItems($requestId);
            $request = $items->first();
            $manager = $items->every(fn ($item) => $item->estado === 'PENDIENTE_GERENCIA')
                && $actor->hasPermission('orders.resources.approve');
            $warehouse = $items->every(fn ($item) => in_array($item->estado, ['PENDIENTE_ALMACEN', 'AUTORIZADA'], true))
                && $actor->hasPermission('warehouse.fulfill');
            abort_unless($manager || $warehouse, 403);
            if ($items->contains(fn ($item) => (int) $item->solicitado_por === (int) $actor->getKey())
                && $items->contains(fn ($item) => ! $item->resuelto_por)) {
                $this->fail('solicitud', 'No puedes resolver una solicitud que registraste tú sin autorización de Gerencia.');
            }
            $hasMaterial = $items->contains('tipo', 'MATERIAL');
            $options = $manager ? ResourceRejectionReasons::management($hasMaterial ? null : 'EQUIPO')
                : ($items->count() > 1 ? ResourceRejectionReasons::warehouseGroup() : ResourceRejectionReasons::warehouse($request->tipo));
            if (! array_key_exists($reasonCode, $options)) $this->fail('decision_motivo_codigo', 'Selecciona un motivo válido para esta decisión.');
            if ($manager && $reasonCode === 'CANTIDAD_NO_JUSTIFICADA' && ! $hasMaterial) {
                $this->fail('decision_motivo_codigo', 'Ese motivo solo aplica cuando la solicitud incluye material.');
            }
            if ($warehouse && $reasonCode === 'DISPONIBILIDAD_INSUFICIENTE') {
                $hasShortage = $items->contains(function ($item): bool {
                    if ($item->tipo === 'EQUIPO') {
                        $query = DB::table('equipos')->where('estado', 'DISPONIBLE')->whereNotIn('condicion', ['MALO', 'MALOGRADO']);
                        if ($item->equipo_tipo_solicitado) $query->where('tipo', $item->equipo_tipo_solicitado);
                        else $query->where('equipo_id', $item->equipo_id);
                        return ! $query->exists();
                    }
                    $stock = (float) DB::table('materiales')->where('material_id', $item->material_id)->value('stock_actual');
                    $reserved = (float) DB::table('pedido_materiales as pm')->join('pedidos_instalacion as p', 'p.pedido_id', '=', 'pm.pedido_id')
                        ->where('pm.material_id', $item->material_id)->whereIn('p.estado', ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO', 'EN_PROCESO'])
                        ->selectRaw('COALESCE(SUM(pm.cantidad_asignada - pm.cantidad_utilizada - pm.cantidad_devuelta), 0) as total')->value('total');
                    return (int) round(max(0, $stock - $reserved) * 1000) < (int) round((float) $item->cantidad * 1000);
                });
                if (! $hasShortage) $this->fail('decision_motivo_codigo', 'Hay disponibilidad suficiente. Revisa los recursos antes de rechazar la solicitud.');
            }
            if ($warehouse && $items->count() === 1 && $reasonCode === 'SIN_DISPONIBILIDAD') {
                $equipment = DB::table('equipos')->where('estado', 'DISPONIBLE')->whereNotIn('condicion', ['MALO', 'MALOGRADO'])
                    ->when($request->equipo_tipo_solicitado, fn ($query) => $query->where('tipo', $request->equipo_tipo_solicitado),
                        fn ($query) => $query->where('equipo_id', $request->equipo_id));
                if ($equipment->exists()) $this->fail('decision_motivo_codigo', 'Hay unidades disponibles. Revisa el recurso antes de rechazarlo por falta de disponibilidad.');
            }
            if ($warehouse && $items->count() === 1 && $reasonCode === 'STOCK_INSUFICIENTE') {
                $stock = (float) DB::table('materiales')->where('material_id', $request->material_id)->value('stock_actual');
                $reserved = (float) DB::table('pedido_materiales as pm')
                    ->join('pedidos_instalacion as p', 'p.pedido_id', '=', 'pm.pedido_id')
                    ->where('pm.material_id', $request->material_id)
                    ->whereIn('p.estado', ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO', 'EN_PROCESO'])
                    ->selectRaw('COALESCE(SUM(pm.cantidad_asignada - pm.cantidad_utilizada - pm.cantidad_devuelta), 0) as total')
                    ->value('total');
                if ((int) round(max(0, $stock - $reserved) * 1000) >= (int) round((float) $request->cantidad * 1000)) {
                    $this->fail('decision_motivo_codigo', 'Hay stock suficiente para la cantidad solicitada. Revisa el recurso antes de rechazarlo por falta de stock.');
                }
            }
            $detail = trim($detail ?? '');
            if ($reasonCode === ResourceRejectionReasons::OTHER && $detail === '') {
                $this->fail('decision_observacion', 'Explica el motivo cuando eliges «Otro motivo».');
            }
            $changes = ['estado' => 'RECHAZADA', 'decision_motivo_codigo' => $reasonCode,
                'decision_observacion' => $detail !== '' ? $detail : null];
            if ($items->contains(fn ($item) => $item->estado === 'AUTORIZADA')) {
                $changes += ['cerrado_por' => $actor->getKey(), 'cerrado_en' => now('America/Lima')];
            } else {
                $changes += ['resuelto_por' => $actor->getKey(), 'resuelto_en' => now('America/Lima')];
            }
            DB::table('pedido_solicitudes_recurso')->whereIn('solicitud_id', $items->pluck('solicitud_id'))->update($changes);
        }, 3);
    }

    public function fulfill(User $actor, int $requestId, ?int $equipmentId = null, array $equipmentIds = []): void
    {
        abort_unless($actor->hasPermission('warehouse.fulfill'), 403);
        DB::transaction(function () use ($actor, $requestId, $equipmentId, $equipmentIds): void {
            $items = $this->lockGroupItems($requestId);
            $request = $items->first();
            if ($items->contains(fn ($item) => ! in_array($item->estado, ['PENDIENTE_ALMACEN', 'AUTORIZADA'], true))) {
                $this->fail('solicitud', 'La solicitud todavía no se puede atender.');
            }
            if ($items->contains(fn ($item) => (int) $item->solicitado_por === (int) $actor->getKey()
                && ($item->estado !== 'AUTORIZADA' || ! $item->resuelto_por || (int) $item->resuelto_por === (int) $actor->getKey()))) {
                $this->fail('solicitud', 'Gerencia debe autorizar la preparación cuando tú solicitaste los recursos.');
            }
            $this->orders->editable((int) $request->pedido_id);
            $selectedEquipment = [];
            foreach ($items as $item) {
                if ($item->tipo === 'EQUIPO') {
                    $selectedId = $item->equipo_tipo_solicitado
                        ? ($equipmentIds[$item->solicitud_id] ?? ($items->count() === 1 ? $equipmentId : null))
                        : (int) $item->equipo_id;
                    if (! $selectedId) $this->fail('equipo_ids.'.$item->solicitud_id, 'Selecciona la unidad de equipo que reservarás.');
                    if ($item->equipo_tipo_solicitado && DB::table('equipos')->where('equipo_id', $selectedId)->value('tipo') !== $item->equipo_tipo_solicitado) {
                        $this->fail('equipo_ids.'.$item->solicitud_id, 'La unidad seleccionada no corresponde al tipo solicitado.');
                    }
                    $this->orders->reserveEquipment($actor, (int) $item->pedido_id, (int) $selectedId);
                    $selectedEquipment[$item->solicitud_id] = (int) $selectedId;
                } else {
                    $existing = DB::table('pedido_materiales')->where('pedido_id', $item->pedido_id)
                        ->where('material_id', $item->material_id)->first();
                    $total = (int) round((float) ($existing?->cantidad_asignada ?? 0) * 1000)
                        + (int) round((float) $item->cantidad * 1000);
                    $this->orders->reserveMaterial($actor, (int) $item->pedido_id, (int) $item->material_id,
                        number_format($total / 1000, 3, '.', ''));
                }
            }
            foreach ($items as $item) {
                DB::table('pedido_solicitudes_recurso')->where('solicitud_id', $item->solicitud_id)->update([
                'estado' => 'ENTREGADA', 'equipo_id' => $item->tipo === 'EQUIPO' ? ($selectedEquipment[$item->solicitud_id] ?? $item->equipo_id) : null,
                'atendido_por' => $actor->getKey(), 'atendido_en' => now('America/Lima'),
            ]);
            }
        }, 3);
    }

    private function validatedOrder(int $orderId, ?User $actor = null): object
    {
        $order = $this->orders->editable($orderId);
        $actor ??= auth()->user();
        abort_unless($actor && $this->canRequestForOrder($actor, $order), 403,
            'Solo una cuenta responsable de gestionar pedidos puede registrar los recursos para Almacén.');
        if ($order->contrato_id && ! in_array(DB::table('contratos')->where('contrato_id', $order->contrato_id)->value('estado'), ['PENDIENTE', 'ACTIVO'], true)) {
            $this->fail('pedido', 'El contrato está cancelado o finalizado.');
        }
        if (! $order->fecha_programada || ($order->fase_flujo && ! $order->coordinado_en)) {
            $this->fail('pedido', 'Primero confirma la fecha y franja de la visita con el cliente.');
        }
        return $order;
    }

    private function ensureGroup(User $actor, int $orderId, ?int $groupId): int
    {
        if (! $groupId) return (int) DB::table('pedido_solicitudes_recursos')->insertGetId([
            'pedido_id' => $orderId, 'solicitado_por' => $actor->getKey(), 'solicitado_en' => now('America/Lima'),
        ]);
        $group = DB::table('pedido_solicitudes_recursos')->where('solicitud_grupo_id', $groupId)->lockForUpdate()->first();
        abort_unless($group && (int) $group->pedido_id === $orderId && (int) $group->solicitado_por === (int) $actor->getKey(), 404);
        return $groupId;
    }

    private function lockGroupItems(int $requestId): \Illuminate\Support\Collection
    {
        $requested = DB::table('pedido_solicitudes_recurso')->where('solicitud_id', $requestId)->first();
        abort_unless($requested, 404);
        $query = DB::table('pedido_solicitudes_recurso')->where('solicitud_grupo_id', $requested->solicitud_grupo_id ?? 0);
        if (! $requested->solicitud_grupo_id) $query->where('solicitud_id', $requestId);
        return $query->orderBy('solicitud_id')->lockForUpdate()->get();
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
