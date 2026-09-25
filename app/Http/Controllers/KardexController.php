<?php

namespace App\Http\Controllers;

use App\Repositories\InventoryRepository;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KardexController extends Controller
{
    public function __construct(private InventoryRepository $kardex) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'item_type' => ['nullable', Rule::in(['equipment', 'material'])],
            'tipo_movimiento' => ['nullable', Rule::in(['ENTRADA', 'SALIDA', 'ASIGNACION', 'CONSUMO', 'DEVOLUCION', 'AJUSTE'])],
            'search' => ['nullable', 'string', 'max:180'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'responsable_id' => ['nullable', 'integer'],
            'equipo_id' => ['nullable', 'integer'],
            'material_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $movements = $this->kardex->searchMovements($filters);

        // Estadísticas por tipo de movimiento
        $statistics = [
            'ENTRADA' => 0,
            'SALIDA' => 0,
            'ASIGNACION' => 0,
            'CONSUMO' => 0,
            'DEVOLUCION' => 0,
            'AJUSTE' => 0,
        ];

        return view('kardex.index', compact('movements', 'statistics', 'filters'));
    }
}
