<?php

namespace App\Repositories;

use App\Models\InventoryMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class InventoryRepository
{
    public function equipment(): Builder
    {
        return DB::table('equipos')->where('estado', '!=', 'INACTIVO')
            ->select('nombre', 'tipo', 'marca', 'modelo')
            ->selectRaw("SUM(CASE WHEN estado = 'DISPONIBLE' THEN 1 ELSE 0 END) as stock_actual")
            ->selectRaw('? as stock_minimo', [config('inventory.equipment_minimum')])
            ->groupBy('nombre', 'tipo', 'marca', 'modelo')
            ->orderBy('nombre')->orderBy('tipo')->orderBy('marca')->orderBy('modelo');
    }

    public function materials(): Builder
    {
        return DB::table('materiales')->where('estado', 'ACTIVO')
            ->select('codigo', 'nombre', 'unidad_base', 'unidad_empaque', 'factor_conversion', 'inventario_en_empaque', 'stock_actual', 'stock_minimo')
            ->selectRaw('CASE WHEN inventario_en_empaque = 1 THEN unidad_empaque ELSE unidad_base END as unidad_medida')
            ->orderBy('nombre')->orderBy('codigo');
    }

    /**
     * Crear movimiento de inventario
     *
     * @param array<string, mixed> $data
     */
    public function createMovement(array $data): InventoryMovement
    {
        return InventoryMovement::create($data);
    }

    /**
     * Buscar movimientos con filtros
     */
    public function searchMovements(array $filters = []): LengthAwarePaginator
    {
        $query = InventoryMovement::query()
            ->with(['equipment', 'material', 'responsible']);

        // Filtro por tipo de artículo (Equipo/Material)
        if (!empty($filters['item_type'])) {
            if ($filters['item_type'] === 'equipment') {
                $query->whereNotNull('equipo_id');
            } elseif ($filters['item_type'] === 'material') {
                $query->whereNotNull('material_id');
            }
        }

        // Filtro por tipo de movimiento
        if (!empty($filters['tipo_movimiento'])) {
            $query->where('tipo_movimiento', $filters['tipo_movimiento']);
        }

        // Filtro por código o número de serie
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('equipment', function ($eq) use ($search) {
                    $eq->where('codigo', 'LIKE', "%{$search}%")
                       ->orWhere('numero_serie', 'LIKE', "%{$search}%")
                       ->orWhere('nombre', 'LIKE', "%{$search}%");
                })->orWhereHas('material', function ($mt) use ($search) {
                    $mt->where('codigo', 'LIKE', "%{$search}%")
                       ->orWhere('nombre', 'LIKE', "%{$search}%");
                });
            });
        }

        // Filtro por rango de fechas
        if (!empty($filters['fecha_desde'])) {
            $query->whereDate('fecha_movimiento', '>=', $filters['fecha_desde']);
        }

        if (!empty($filters['fecha_hasta'])) {
            $query->whereDate('fecha_movimiento', '<=', $filters['fecha_hasta']);
        }

        // Filtro por responsable
        if (!empty($filters['responsable_id'])) {
            $query->where('responsable_id', $filters['responsable_id']);
        }

        // Filtro por equipo específico
        if (!empty($filters['equipo_id'])) {
            $query->where('equipo_id', $filters['equipo_id']);
        }

        // Filtro por material específico
        if (!empty($filters['material_id'])) {
            $query->where('material_id', $filters['material_id']);
        }

        // Ordenar por más reciente primero
        return $query->recent()->paginate(20)->withQueryString();
    }

    /**
     * Obtener movimientos de un equipo específico
     */
    public function getEquipmentMovements(int $equipmentId): LengthAwarePaginator
    {
        return InventoryMovement::query()
            ->with(['responsible'])
            ->forEquipment($equipmentId)
            ->recent()
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * Obtener movimientos de un material específico
     */
    public function getMaterialMovements(int $materialId): LengthAwarePaginator
    {
        return InventoryMovement::query()
            ->with(['responsible'])
            ->forMaterial($materialId)
            ->recent()
            ->paginate(15)
            ->withQueryString();
    }
}
