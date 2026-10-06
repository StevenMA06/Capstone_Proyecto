<?php

namespace App\Http\Controllers;

use App\Repositories\InventoryRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScopedAccessController extends Controller
{
    private function orders(Request $request)
    {
        return DB::table('pedidos_instalacion')->select('pedido_id')->where('tecnico_id', $request->user()->empleado_id ?? 0);
    }

    public function contracts(Request $request)
    {
        $records=DB::table('contratos as c')->leftJoin('planes_servicio as p','p.plan_id','=','c.plan_id')
            ->whereIn('c.contrato_id',DB::table('pedidos_instalacion')->select('contrato_id')->where('tecnico_id',$request->user()->empleado_id ?? 0))
            ->select('c.codigo','c.estado','p.nombre as plan','c.direccion_servicio')->orderBy('c.codigo')->paginate(20);
        return view('access.scoped',['title'=>'Contratos asignados','columns'=>['codigo'=>'Contrato','estado'=>'Estado','plan'=>'Plan','direccion_servicio'=>'Dirección de servicio'],'records'=>$records]);
    }

    public function equipment(Request $request)
    {
        $records=DB::table('pedido_equipos as pe')->join('equipos as e','e.equipo_id','=','pe.equipo_id')
            ->whereIn('pe.pedido_id',$this->orders($request))->select('pe.pedido_id','e.codigo','e.nombre','e.numero_serie','pe.estado_asignacion')->orderBy('pe.pedido_id')->paginate(20);
        return view('access.scoped',['title'=>'Equipos de mis instalaciones','columns'=>['pedido_id'=>'Pedido','codigo'=>'Código','nombre'=>'Equipo','numero_serie'=>'Serie','estado_asignacion'=>'Estado'],'records'=>$records]);
    }

    public function movements(Request $request)
    {
        $records=DB::table('movimientos_inventario as m')->leftJoin('equipos as e','e.equipo_id','=','m.equipo_id')->leftJoin('materiales as a','a.material_id','=','m.material_id')
            ->whereIn('m.pedido_id',$this->orders($request))->select('m.pedido_id','m.fecha_movimiento','m.tipo_movimiento','m.cantidad')->selectRaw('COALESCE(e.nombre,a.nombre) as articulo')->orderByDesc('m.movimiento_id')->paginate(20);
        return view('access.scoped',['title'=>'Movimientos de mis instalaciones','columns'=>['pedido_id'=>'Pedido','fecha_movimiento'=>'Fecha','tipo_movimiento'=>'Movimiento','articulo'=>'Artículo','cantidad'=>'Cantidad'],'records'=>$records]);
    }

    public function equipmentAvailability(InventoryRepository $inventory)
    {
        return view('access.scoped',['title'=>'Disponibilidad de equipos','columns'=>['nombre'=>'Equipo','tipo'=>'Tipo','marca'=>'Marca','modelo'=>'Modelo','stock_actual'=>'Disponibles'],'records'=>$inventory->equipment()->paginate(20)]);
    }

    public function materialAvailability(InventoryRepository $inventory)
    {
        return view('access.scoped',['title'=>'Disponibilidad de materiales','columns'=>['codigo'=>'Código','nombre'=>'Material','unidad_medida'=>'Unidad','stock_actual'=>'Stock disponible'],'records'=>$inventory->materials()->paginate(20)]);
    }
}
