<?php

namespace App\Http\Controllers;

use App\Repositories\InventoryRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OperationController extends Controller
{
    public function payments(): View
    {
        return view('operations.index', [
            'title' => 'Pagos', 'kind' => 'payments',
            'records' => DB::table('pagos')->join('contratos', 'contratos.contrato_id', '=', 'pagos.contrato_id')
                ->select('pagos.*', 'contratos.codigo as contrato_codigo')->orderByDesc('pago_id')->paginate(20),
        ]);
    }

    public function installations(): View
    {
        return view('operations.index', [
            'title' => 'Instalaciones', 'kind' => 'installations',
            'records' => DB::table('pedidos_instalacion')->join('clientes', 'clientes.cliente_id', '=', 'pedidos_instalacion.cliente_id')
                ->select('pedidos_instalacion.*', 'clientes.nombres_razon_social', 'clientes.apellidos')->orderByDesc('pedido_id')->paginate(20),
        ]);
    }

    public function equipment(InventoryRepository $inventory): View
    {
        return view('operations.index', [
            'title' => 'Equipos', 'kind' => 'equipment', 'records' => $inventory->equipment()->paginate(20),
        ]);
    }

    public function materials(InventoryRepository $inventory): View
    {
        return view('operations.index', [
            'title' => 'Materiales', 'kind' => 'materials', 'records' => $inventory->materials()->paginate(20),
        ]);
    }
}
