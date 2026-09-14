<?php

namespace App\Repositories;

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
            ->select('codigo', 'nombre', 'unidad_medida', 'stock_actual', 'stock_minimo')
            ->orderBy('nombre')->orderBy('codigo');
    }
}
