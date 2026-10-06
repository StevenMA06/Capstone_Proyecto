<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InstallationOrderController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['page'=>['nullable','integer','min:1']]);
        $query=DB::table('pedidos_instalacion as p')->join('contratos as c','c.contrato_id','=','p.contrato_id')
            ->leftJoin('empleados as e','e.empleado_id','=','p.tecnico_id')
            ->select('p.*','c.codigo as contrato_codigo','c.direccion_servicio')->selectRaw("CONCAT_WS(' ',e.nombres,e.apellidos) as tecnico_nombre");
        if ($request->routeIs('orders.assigned')) $query->where('p.tecnico_id',$request->user()->empleado_id ?? 0);
        return view('orders.index',[
            'records'=>$query->orderByDesc('p.pedido_id')->paginate(20),
            'contracts'=>$request->user()->hasPermission('orders.create') ? DB::table('contratos')->where('estado','ACTIVO')->get(['contrato_id','codigo']) : collect(),
            'technicians'=>$request->user()->hasPermission('orders.schedule') ? Employee::eligible()->where('cargo','Técnico de Campo')->get() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $data=$request->validate(['contrato_id'=>['required','integer','exists:contratos,contrato_id'],'observaciones'=>['nullable','string','max:500']]);
        DB::transaction(function () use ($data,$request) {
            $contract=DB::table('contratos')->where('contrato_id',$data['contrato_id'])->lockForUpdate()->first();
            if (!$contract || $contract->estado!=='ACTIVO') throw ValidationException::withMessages(['contrato_id'=>'Selecciona un contrato activo.']);
            if (DB::table('pedidos_instalacion')->where('contrato_id',$contract->contrato_id)->whereNotIn('estado',['CANCELADO','COMPLETADO'])->exists()) throw ValidationException::withMessages(['contrato_id'=>'El contrato ya tiene un pedido vigente.']);
            DB::table('pedidos_instalacion')->insert(['codigo'=>'INS-'.Str::upper(Str::random(12)),'contrato_id'=>$contract->contrato_id,'cliente_id'=>$contract->cliente_id,'estado'=>'PENDIENTE','creado_por'=>$request->user()->getKey(),'observaciones'=>$data['observaciones'] ?? null,'fecha_solicitud'=>now()]);
        },3);
        return redirect()->route('orders.index')->with('status','Pedido registrado correctamente.');
    }

    public function schedule(Request $request,int $pedido)
    {
        $data=$request->validate(['tecnico_id'=>['required','integer','exists:empleados,empleado_id'],'fecha_programada'=>['required','date','after_or_equal:now'],'motivo_reprogramacion'=>['nullable','string','max:500']]);
        DB::transaction(function () use ($pedido,$data) {
            $order=$this->editable($pedido);
            $employee=Employee::lockForUpdate()->findOrFail($data['tecnico_id']);
            if (!$employee->isEligible() || $employee->cargo!=='Técnico de Campo') throw ValidationException::withMessages(['tecnico_id'=>'Selecciona un técnico de campo con vínculo laboral vigente.']);
            if ($order->fecha_programada && blank($data['motivo_reprogramacion'] ?? null)) throw ValidationException::withMessages(['motivo_reprogramacion'=>'Indica el motivo de reprogramación.']);
            DB::table('pedidos_instalacion')->where('pedido_id',$pedido)->update($data+['fecha_original'=>$order->fecha_original ?? $data['fecha_programada'],'estado'=>$order->fecha_programada ? 'REPROGRAMADO':'PROGRAMADO']);
        },3);
        return redirect()->route('orders.index')->with('status','Técnico y programación guardados correctamente.');
    }

    public function cancel(int $pedido)
    {
        DB::transaction(function () use ($pedido) {
            $this->editable($pedido);
            if (DB::table('pedido_equipos')->where('pedido_id', $pedido)->where('estado_asignacion', 'ASIGNADO')->exists()) throw ValidationException::withMessages(['pedido' => 'Libera los equipos asignados antes de cancelar el pedido.']);
            DB::table('pedidos_instalacion')->where('pedido_id',$pedido)->update(['estado'=>'CANCELADO']);
        },3);
        return redirect()->route('orders.index')->with('status','Pedido cancelado. Su información se conserva.');
    }

    private function editable(int $id): object
    {
        $order=DB::table('pedidos_instalacion')->where('pedido_id',$id)->lockForUpdate()->first();
        abort_unless($order,404);
        if (!in_array($order->estado,['PENDIENTE','PROGRAMADO','REPROGRAMADO'])) throw ValidationException::withMessages(['pedido'=>'Solo se pueden cambiar pedidos no iniciados.']);
        return $order;
    }
}
