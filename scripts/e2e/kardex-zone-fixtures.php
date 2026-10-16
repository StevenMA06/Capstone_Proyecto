<?php

// Solo CLI, después de los controles de entorno/base de fixture.php.
use App\Models\Client;
use App\Models\CoverageZone;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\Material;
use App\Models\User;
use App\Services\ContractService;
use App\Services\CoverageZoneService;
use App\Services\InventoryService;
use App\Services\PlanService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

return function (string $command, string $arg, User $actor, array $clientData, array $planData): array {
    $tag = bin2hex(random_bytes(8));
    $zoneState = function (string $name): array {
        $zone = CoverageZone::where('nombre', $name)->first();
        return ['count' => CoverageZone::where('nombre', $name)->count(), 'zone' => $zone?->toArray(),
            'plans' => $zone ? DB::table('plan_zona_cobertura')->where('zona_id', $zone->getKey())->orderBy('plan_id')->get()->toArray() : [],
            'contracts' => $zone ? DB::table('contratos')->where('zona_id', $zone->getKey())->orderBy('contrato_id')->get()->toArray() : [],
            'orders' => $zone ? DB::table('pedidos_instalacion')->whereIn('contrato_id', DB::table('contratos')->where('zona_id', $zone->getKey())->select('contrato_id'))->orderBy('pedido_id')->get()->toArray() : []];
    };
    if ($command === 'kz-zone-state') return $zoneState($arg);
    if (in_array($command, ['kz-kardex', 'kz-trace'], true)) {
        $prepare = require __DIR__.'/inventory-fixtures.php';
        $material = Material::findOrFail($prepare('inventory-material', '', $actor, $clientData, $planData)['material_id']);
        $equipment = Equipment::findOrFail($prepare('inventory-equipment', '', $actor, $clientData, $planData)['equipo_id']);
        $prefix = 'QA-KAR-'.$tag;
        $material->update(['nombre' => $prefix.' Material']);
        $equipment->update(['nombre' => $prefix.' Equipo']);
        $service = app(InventoryService::class);
        $service->removeMaterialStock($material, 20, $actor, 'Salida ficticia para consultar Kardex');
        for ($i = 0; $i < ($command === 'kz-kardex' ? 22 : 3); $i++) {
            $service->addMaterialStock($material->refresh(), 1, $actor, 'Entrada ficticia para consultar Kardex');
        }
        $query = fn () => DB::table('movimientos_inventario')->where(fn ($q) => $q
            ->where('material_id', $material->getKey())->orWhere('equipo_id', $equipment->getKey()))->orderBy('movimiento_id');
        $output = ['prefix' => $prefix, 'material' => $material->refresh()->toArray(), 'equipment' => $equipment->toArray()];
        if ($command === 'kz-trace') {
            $start = CarbonImmutable::parse('2010-01-01', 'America/Lima')->addDays(random_int(0, 4000));
            $end = $start->addDay();
            $rows = DB::table('movimientos_inventario')->where('material_id', $material->getKey())->orderBy('movimiento_id')->get();
            $dates = [$start->subDay()->setTime(23, 59, 59), $start->startOfDay(), $start->setTime(12, 0), $end->setTime(23, 59, 59), $end->addDay()->startOfDay()];
            foreach ($rows as $index => $row) DB::table('movimientos_inventario')->where('movimiento_id', $row->movimiento_id)->update(['fecha_movimiento' => $dates[$index]->format('Y-m-d H:i:s')]);
            DB::table('movimientos_inventario')->where('equipo_id', $equipment->getKey())->update(['fecha_movimiento' => $start->setTime(10, 0)->format('Y-m-d H:i:s')]);
            $output += ['from' => $start->toDateString(), 'to' => $end->toDateString(),
                'display_from' => $start->format('d/m/Y'), 'display_to' => $end->format('d/m/Y')];
        }
        return $output + ['movements' => $query()->get()->toArray()];
    }
    if (! in_array($command, ['kz-zone-data', 'kz-zone', 'kz-zone-history'], true)) throw new RuntimeException('Operación Kardex/zonas desconocida.');
    $plan = app(PlanService::class)->create($planData, $actor);
    $data = ['nombre' => 'Zona QA '.$tag, 'distrito' => 'Trujillo', 'provincia' => 'Trujillo',
        'departamento' => 'La Libertad', 'descripcion' => 'Cobertura ficticia '.$tag, 'plan_ids' => [$plan->getKey()]];
    if ($command === 'kz-zone-data') return ['data' => $data, 'plan' => $plan->toArray()];
    $zone = app(CoverageZoneService::class)->create($data, $actor);
    if ($command === 'kz-zone-history') {
        $client = Client::create($clientData + ['estado' => 'PENDIENTE', 'creado_por' => $actor->getKey()]);
        app(ContractService::class)->create([
            'cliente_id' => $client->getKey(), 'plan_id' => $plan->getKey(), 'zona_id' => $zone->getKey(),
            'responsable_id' => Employee::where('numero_documento', 'QA-E2E-VENTAS')->value('empleado_id'),
            'fecha_inicio' => now('America/Lima')->toDateString(), 'duracion_meses' => 12,
        ], $actor->getKey());
    }
    return $zoneState($zone->nombre) + ['plan' => $plan->toArray()];
};
