<?php

namespace App\Http\Controllers;

use App\Services\InstallationResourceRequestService;
use App\Support\ResourceRejectionReasons;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WarehouseRequestController extends Controller
{
    public function __construct(private InstallationResourceRequestService $requests) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'vista' => ['nullable', Rule::in(['pendientes', 'historial'])],
            'pedido' => ['nullable', 'integer', 'exists:pedidos_instalacion,pedido_id'],
        ]);
        $view = $filters['vista'] ?? 'pendientes';
        $pendingStates = ['PENDIENTE_ALMACEN', 'PENDIENTE_GERENCIA', 'AUTORIZADA'];
        $pendingCount = DB::table('pedido_solicitudes_recursos as g')->whereExists(fn ($query) => $query
            ->selectRaw('1')->from('pedido_solicitudes_recurso as s')->whereColumn('s.solicitud_grupo_id', 'g.solicitud_grupo_id')
            ->whereIn('s.estado', $pendingStates))->count();
        $records = DB::table('pedido_solicitudes_recursos as g')
            ->join('pedidos_instalacion as p', 'p.pedido_id', '=', 'g.pedido_id')
            ->join('usuarios as u', 'u.usuario_id', '=', 'g.solicitado_por')
            ->select('g.*', 'p.codigo as pedido_codigo', 'p.estado as pedido_estado', 'p.fecha_programada',
                'u.nombre_usuario as solicitante')
            ->when(filled($filters['pedido'] ?? null), fn ($query) => $query->where('g.pedido_id', $filters['pedido']))
            ->when($view === 'pendientes', fn ($query) => $query->whereExists(fn ($subquery) => $subquery
                ->selectRaw('1')->from('pedido_solicitudes_recurso as s')->whereColumn('s.solicitud_grupo_id', 'g.solicitud_grupo_id')
                ->whereIn('s.estado', $pendingStates)),
                fn ($query) => $query->whereExists(fn ($subquery) => $subquery
                    ->selectRaw('1')->from('pedido_solicitudes_recurso as s')->whereColumn('s.solicitud_grupo_id', 'g.solicitud_grupo_id')
                    ->whereNotIn('s.estado', $pendingStates)))
            ->orderByDesc('g.solicitud_grupo_id')->paginate(15)->withQueryString();

        $groupIds = $records->getCollection()->pluck('solicitud_grupo_id');
        $resources = DB::table('pedido_solicitudes_recurso as s')
            ->leftJoin('usuarios as a', 'a.usuario_id', '=', 's.resuelto_por')
            ->leftJoin('usuarios as w', 'w.usuario_id', '=', 's.atendido_por')
            ->leftJoin('usuarios as z', 'z.usuario_id', '=', 's.cerrado_por')
            ->leftJoin('equipos as e', 'e.equipo_id', '=', 's.equipo_id')
            ->leftJoin('materiales as m', 'm.material_id', '=', 's.material_id')
            ->leftJoinSub(
                DB::table('pedido_materiales as pm')->join('pedidos_instalacion as reserved_order', 'reserved_order.pedido_id', '=', 'pm.pedido_id')
                    ->whereIn('reserved_order.estado', ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO', 'EN_PROCESO'])
                    ->select('pm.material_id')->selectRaw('SUM(pm.cantidad_asignada - pm.cantidad_utilizada - pm.cantidad_devuelta) as reservado')
                    ->groupBy('pm.material_id'),
                'reserved', 'reserved.material_id', '=', 'm.material_id'
            )
            ->select('s.*', 'a.nombre_usuario as aprobador', 'w.nombre_usuario as almacenista', 'z.nombre_usuario as cerrador',
                'e.codigo as equipo_codigo', 'e.nombre as equipo_nombre', 'e.estado as equipo_estado', 'e.condicion as equipo_condicion',
                'e.tipo as equipo_tipo', 'e.modelo as equipo_modelo', 'e.numero_serie as equipo_serie',
                'm.codigo as material_codigo', 'm.nombre as material_nombre', 'm.stock_actual as material_stock',
                'm.unidad_base as material_unidad_base', 'm.unidad_empaque as material_unidad_empaque',
                'm.factor_conversion as material_factor_conversion', 'm.inventario_en_empaque as material_inventario_en_empaque')
            ->selectRaw('GREATEST(0, m.stock_actual - COALESCE(reserved.reservado, 0)) as material_stock_libre')
            ->whereIn('s.solicitud_grupo_id', $groupIds)->orderBy('s.solicitud_id')->get()->groupBy('solicitud_grupo_id');
        $records->getCollection()->transform(function ($group) use ($resources) {
            $group->resources = $resources->get($group->solicitud_grupo_id, collect());
            return $group;
        });

        $availableEquipment = DB::table('equipos')->where('estado', 'DISPONIBLE')
            ->whereNotIn('condicion', ['MALO', 'MALOGRADO'])->orderBy('nombre')->get()->groupBy('tipo');

        return view('warehouse.requests', compact('records', 'view', 'pendingCount', 'availableEquipment'));
    }

    public function fulfill(Request $request, int $solicitud)
    {
        $data = $request->validate([
            'equipo_id' => ['nullable', 'integer', 'exists:equipos,equipo_id'],
            'equipos' => ['nullable', 'array'],
            'equipos.*' => ['integer', 'exists:equipos,equipo_id'],
        ]);
        $groupId = DB::table('pedido_solicitudes_recurso')->where('solicitud_id', $solicitud)->value('solicitud_grupo_id');
        $this->requests->fulfill($request->user(), $solicitud, $data['equipo_id'] ?? null, $data['equipos'] ?? []);
        return redirect()->to(route('warehouse.requests.index', ['vista' => 'historial']).'#solicitud-grupo-'.$groupId)
            ->with('status', 'Almacén preparó y reservó en conjunto todos los recursos. Instalaciones ya puede asignar al técnico.');
    }

    public function escalate(Request $request, int $solicitud)
    {
        $groupId = DB::table('pedido_solicitudes_recurso')->where('solicitud_id', $solicitud)->value('solicitud_grupo_id');
        $this->requests->escalate($request->user(), $solicitud);
        return redirect()->to(route('warehouse.requests.index').'#solicitud-grupo-'.$groupId)->with('status', 'Solicitud completa enviada a Gerencia. La preparación de todos sus recursos espera su autorización.');
    }

    public function reject(Request $request, int $solicitud)
    {
        $data = $request->validate([
            'decision_motivo_codigo' => ['required', 'string', Rule::in(array_unique(array_merge(
                array_keys(ResourceRejectionReasons::warehouse()), array_keys(ResourceRejectionReasons::warehouseGroup())
            )))],
            'decision_observacion' => ['nullable', 'required_if:decision_motivo_codigo,OTRO', 'string', 'max:500'],
        ]);
        $groupId = DB::table('pedido_solicitudes_recurso')->where('solicitud_id', $solicitud)->value('solicitud_grupo_id');
        $this->requests->reject($request->user(), $solicitud, $data['decision_motivo_codigo'], $data['decision_observacion'] ?? null);
        return redirect()->to(route('warehouse.requests.index', ['vista' => 'historial']).'#solicitud-grupo-'.$groupId)
            ->with('status', 'Solicitud completa rechazada y conservada en el historial.');
    }
}
