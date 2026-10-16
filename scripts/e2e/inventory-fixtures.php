<?php

// fixture.php comprueba primero APP_ENV y la base exclusiva de pruebas.
use App\Models\Equipment;
use App\Models\Material;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;

return function (string $command, string $arg, User $actor, array $clientData, array $planData): array {
    $tag = bin2hex(random_bytes(8));
    $equipment = ['numero_serie' => 'QA-SN-'.$tag, 'nombre' => 'Router QA '.$tag,
        'tipo' => 'Router QA '.$tag, 'marca' => 'Marca ficticia', 'modelo' => 'Modelo QA',
        'condicion' => 'NUEVO', 'estado' => 'DISPONIBLE', 'observaciones' => 'Equipo ficticio E2E'];
    $material = ['nombre' => 'Cable QA '.$tag, 'descripcion' => 'Material ficticio E2E',
        'unidad_base' => 'metros', 'stock_actual' => '10', 'stock_minimo' => '2', 'estado' => 'ACTIVO'];
    $movements = fn ($field, $id) => DB::table('movimientos_inventario')->where($field, $id)->orderBy('movimiento_id')->get()->toArray();
    if ($command === 'inventory-equipment-data') return $equipment;
    if ($command === 'inventory-material-data') return $material;
    if ($command === 'inventory-equipment') return app(InventoryService::class)->registerEquipment($equipment, $actor)->toArray();
    if ($command === 'inventory-material') return app(InventoryService::class)->registerMaterial(array_replace($material, ['stock_actual' => '100']), $actor)->toArray();
    if ($command === 'inventory-equipment-state') {
        $record = Equipment::where('numero_serie', $arg)->first();
        return ['count' => Equipment::where('numero_serie', $arg)->count(), 'record' => $record?->toArray(),
            'movements' => $record ? $movements('equipo_id', $record->getKey()) : []];
    }
    if ($command === 'inventory-material-state') {
        $record = Material::where('nombre', $arg)->first();
        return ['count' => Material::where('nombre', $arg)->count(), 'record' => $record?->toArray(),
            'movements' => $record ? $movements('material_id', $record->getKey()) : []];
    }
    if ($command === 'inventory-order') {
        $state = (require __DIR__.'/commercial-fixtures.php')('commercial-contract', '', $actor, $clientData, $planData);
        $id = $state['order']->pedido_id;
        // Prerrequisito preparado para verificar disponibilidad; no certifica coordinación RF-PED.
        DB::table('pedidos_instalacion')->where('pedido_id', $id)->update([
            'fecha_programada' => now('America/Lima')->addDay()->setTime(10, 0),
            'coordinado_en' => now('America/Lima'), 'coordinado_por' => $actor->getKey(),
            'franja_horaria' => 'MANANA',
        ]);
        return ['pedido_id' => $id, 'codigo' => $state['order']->codigo];
    }
    if ($command === 'inventory-order-state') return [
        'groups' => DB::table('pedido_solicitudes_recursos')->where('pedido_id', (int) $arg)->orderBy('solicitud_grupo_id')->get()->toArray(),
        'requests' => DB::table('pedido_solicitudes_recurso')->where('pedido_id', (int) $arg)->orderBy('solicitud_id')->get()->toArray(),
        'assignments' => DB::table('pedido_equipos')->where('pedido_id', (int) $arg)->get()->toArray(),
    ];
    throw new RuntimeException('Operación de inventario E2E desconocida.');
};
