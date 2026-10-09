<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\InstallationOrderService;
use App\Services\InstallationResourceRequestService;
use App\Support\ResourceRejectionReasons;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InstallationOrderController extends Controller
{
    public function __construct(private InstallationOrderService $orders, private InstallationResourceRequestService $resources) {}

    public function index(Request $request)
    {
        $states = ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO', 'EN_PROCESO', 'COMPLETADO', 'CANCELADO'];
        $filters = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:150'],
            'estado' => ['nullable', Rule::in($states)],
            'reprogramacion' => ['nullable', Rule::in(['pendiente'])],
            'recursos' => ['nullable', Rule::in(['pendiente'])],
        ]);
        $assignedOnly = $request->routeIs('orders.assigned');
        $query = DB::table('pedidos_instalacion as p')
            ->join('contratos as c', 'c.contrato_id', '=', 'p.contrato_id')
            ->join('clientes as cl', 'cl.cliente_id', '=', 'p.cliente_id')
            ->leftJoin('empleados as e', 'e.empleado_id', '=', 'p.tecnico_id')
            ->leftJoin('usuarios as a', 'a.usuario_id', '=', 'p.asignado_por')
            ->leftJoin('usuarios as co', 'co.usuario_id', '=', 'p.coordinado_por')
            ->leftJoin('empleados as ae', 'ae.empleado_id', '=', 'a.empleado_id')
            ->leftJoin('empleados as ce', 'ce.empleado_id', '=', 'co.empleado_id')
            ->select('p.*', 'c.codigo as contrato_codigo', 'c.direccion_servicio', 'c.fecha_inicio as contrato_fecha_inicio', 'c.estado as contrato_estado',
                'cl.nombres_razon_social as cliente_nombre', 'cl.apellidos as cliente_apellidos',
                'cl.telefono as cliente_telefono', 'cl.correo as cliente_correo',
                'a.nombre_usuario as asignador_usuario', 'co.nombre_usuario as coordinador_usuario')
            ->selectRaw("CONCAT_WS(' ', e.nombres, e.apellidos) as tecnico_nombre")
            ->selectRaw("NULLIF(TRIM(CONCAT_WS(' ', ae.nombres, ae.apellidos)), '') as asignador_nombre")
            ->selectRaw("NULLIF(TRIM(CONCAT_WS(' ', ce.nombres, ce.apellidos)), '') as coordinador_nombre");
        if ($assignedOnly) {
            $query->where('p.tecnico_id', $request->user()->empleado_id ?? 0);
        }
        if (filled($filters['search'] ?? null)) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($filters['search'])).'%';
            $query->where(function ($builder) use ($pattern): void {
                $builder->whereRaw("p.codigo LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("c.codigo LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("CONCAT_WS(' ', cl.nombres_razon_social, cl.apellidos) LIKE ? ESCAPE '!'", [$pattern]);
            });
        }
        if (filled($filters['estado'] ?? null)) {
            $query->where('p.estado', $filters['estado']);
        }
        if (($filters['reprogramacion'] ?? null) === 'pendiente') {
            $query->whereExists(fn ($builder) => $builder->selectRaw('1')->from('pedido_reprogramaciones as r')
                ->whereColumn('r.pedido_id', 'p.pedido_id')->where('r.estado', 'SOLICITADA'));
        }
        if (($filters['recursos'] ?? null) === 'pendiente') {
            $query->whereExists(fn ($builder) => $builder->selectRaw('1')->from('pedido_solicitudes_recurso as s')
                ->whereColumn('s.pedido_id', 'p.pedido_id')->where('s.estado', 'PENDIENTE_GERENCIA'));
        }
        $counts = DB::table('pedidos_instalacion')
            ->when($assignedOnly, fn ($builder) => $builder->where('tecnico_id', $request->user()->empleado_id ?? 0))
            ->select('estado')->selectRaw('COUNT(*) as total')->groupBy('estado')->pluck('total', 'estado');
        $records = $query->orderByRaw("CASE p.estado WHEN 'PENDIENTE' THEN 0 WHEN 'REPROGRAMADO' THEN 1 WHEN 'PROGRAMADO' THEN 2 WHEN 'EN_PROCESO' THEN 3 ELSE 4 END")
            ->orderByRaw('p.fecha_programada IS NULL')->orderBy('p.fecha_programada')->orderByDesc('p.pedido_id')
            ->paginate(12)->withQueryString();
        $ids = collect($records->items())->pluck('pedido_id');
        $reprogramming = DB::table('pedido_reprogramaciones as r')
            ->join('usuarios as s', 's.usuario_id', '=', 'r.solicitado_por')
            ->leftJoin('usuarios as a', 'a.usuario_id', '=', 'r.resuelto_por')
            ->whereIn('r.pedido_id', $ids)->orderByDesc('r.reprogramacion_id')
            ->get(['r.*', 's.nombre_usuario as solicitante_nombre', 'a.nombre_usuario as aprobador_nombre'])->groupBy('pedido_id');
        $assignedEquipment = DB::table('pedido_equipos as pe')->join('equipos as e', 'e.equipo_id', '=', 'pe.equipo_id')
            ->whereIn('pe.pedido_id', $ids)->whereIn('pe.estado_asignacion', ['ASIGNADO', 'UTILIZADO'])
            ->whereNull('pe.fecha_devolucion')->get(['pe.pedido_id', 'e.codigo', 'e.nombre', 'e.tipo', 'e.modelo', 'e.numero_serie'])->groupBy('pedido_id');
        $assignedMaterials = DB::table('pedido_materiales as pm')->join('materiales as m', 'm.material_id', '=', 'pm.material_id')
            ->whereIn('pm.pedido_id', $ids)->whereRaw('pm.cantidad_asignada > pm.cantidad_devuelta')
            ->get(['pm.pedido_id', 'pm.material_id', 'm.codigo', 'm.nombre', 'm.unidad_base', 'm.unidad_empaque', 'm.factor_conversion',
                'm.inventario_en_empaque', 'pm.cantidad_asignada'])->groupBy('pedido_id');
        $resourceRequests = DB::table('pedido_solicitudes_recurso as s')
            ->join('usuarios as u', 'u.usuario_id', '=', 's.solicitado_por')
            ->leftJoin('usuarios as a', 'a.usuario_id', '=', 's.resuelto_por')
            ->leftJoin('usuarios as w', 'w.usuario_id', '=', 's.atendido_por')
            ->leftJoin('usuarios as z', 'z.usuario_id', '=', 's.cerrado_por')
            ->leftJoin('equipos as e', 'e.equipo_id', '=', 's.equipo_id')
            ->leftJoin('materiales as m', 'm.material_id', '=', 's.material_id')
            ->whereIn('s.pedido_id', $ids)->orderByDesc('s.solicitud_id')
            ->get(['s.*', 'u.nombre_usuario as solicitante', 'a.nombre_usuario as aprobador',
                'w.nombre_usuario as almacenista', 'z.nombre_usuario as cerrador', 'e.codigo as equipo_codigo', 'e.nombre as equipo_nombre',
                'e.modelo as equipo_modelo', 'e.numero_serie as equipo_serie',
                'm.codigo as material_codigo', 'm.nombre as material_nombre', 'm.unidad_base as material_unidad_base',
                'm.unidad_empaque as material_unidad_empaque', 'm.factor_conversion as material_factor_conversion',
                'm.inventario_en_empaque as material_inventario_en_empaque'])->groupBy('pedido_id');
        $lifecycleEvents = DB::table('pedido_historial_eventos as h')
            ->leftJoin('usuarios as u', 'u.usuario_id', '=', 'h.usuario_id')
            ->whereIn('h.pedido_id', $ids)->orderBy('h.registrado_en')
            ->get(['h.*', 'u.nombre_usuario as usuario_nombre'])->groupBy('pedido_id');

        return view('orders.index', [
            'records' => $records,
            'reprogramming' => $reprogramming,
            'pendingReprogrammingCount' => DB::table('pedido_reprogramaciones')->where('estado', 'SOLICITADA')->count(),
            'reasonOptions' => InstallationOrderService::REPROGRAM_REASONS,
            'states' => $states,
            'counts' => $counts,
            'assignedEquipment' => $assignedEquipment,
            'assignedMaterials' => $assignedMaterials,
            'resourceRequests' => $resourceRequests,
            'lifecycleEvents' => $lifecycleEvents,
            'pendingResourceApprovalCount' => DB::table('pedido_solicitudes_recursos as g')->whereExists(fn ($query) => $query
                ->selectRaw('1')->from('pedido_solicitudes_recurso as s')->whereColumn('s.solicitud_grupo_id', 'g.solicitud_grupo_id')
                ->where('s.estado', 'PENDIENTE_GERENCIA'))->count(),
            'technicians' => $request->user()->hasPermission('orders.schedule')
                ? Employee::eligible()->where('cargo', 'Técnico de Campo')->get() : collect(),
            'equipmentOptions' => $request->user()->hasPermission('orders.schedule')
                ? DB::table('equipos as e')->where('e.estado', 'DISPONIBLE')->whereNotIn('e.condicion', ['MALO', 'MALOGRADO'])
                    ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('pedido_solicitudes_recurso as s')
                        ->whereIn('s.estado', ['PENDIENTE_ALMACEN', 'PENDIENTE_GERENCIA', 'AUTORIZADA'])
                        ->where(fn ($requestQuery) => $requestQuery->whereColumn('s.equipo_id', 'e.equipo_id')
                            ->orWhereColumn('s.equipo_tipo_solicitado', 'e.tipo')))
                    ->orderBy('e.tipo')->orderBy('e.modelo')->orderBy('e.numero_serie')->get('e.*') : collect(),
            'materials' => $request->user()->hasPermission('orders.schedule')
                ? DB::table('materiales as m')->leftJoinSub(
                    DB::table('pedido_materiales as pm')->join('pedidos_instalacion as p', 'p.pedido_id', '=', 'pm.pedido_id')
                        ->whereIn('p.estado', ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO', 'EN_PROCESO'])
                        ->select('pm.material_id')->selectRaw('SUM(pm.cantidad_asignada - pm.cantidad_utilizada - pm.cantidad_devuelta) as reservado')
                        ->groupBy('pm.material_id'),
                    'r', 'r.material_id', '=', 'm.material_id'
                )->where('m.estado', 'ACTIVO')->select('m.*')
                    ->selectRaw('GREATEST(0, m.stock_actual - COALESCE(r.reservado, 0)) as stock_libre')
                    ->orderBy('m.nombre')->get() : collect(),
        ]);
    }

    public function approveReprogramming(Request $request, int $pedido, int $reprogramacion)
    {
        return $this->resolveReprogramming($request, $pedido, $reprogramacion, true);
    }

    public function rejectReprogramming(Request $request, int $pedido, int $reprogramacion)
    {
        return $this->resolveReprogramming($request, $pedido, $reprogramacion, false);
    }

    private function resolveReprogramming(Request $request, int $pedido, int $reprogramacion, bool $approve)
    {
        $data = $request->validate([
            'decision_observacion' => [$approve ? 'nullable' : 'required', 'string', 'max:500'],
            'evidencia_revisada' => [$approve ? 'accepted' : 'nullable'],
        ]);
        $this->orders->resolveReprogramming($request->user(), $pedido, $reprogramacion,
            $approve, $data['decision_observacion'] ?? null);

        $notification = '';
        if ($approve) {
            $order = DB::table('pedidos_instalacion')->where('pedido_id', $pedido)->first(['tecnico_id', 'notificado_en']);
            if ($order->tecnico_id) {
                $notification = $order->notificado_en
                    ? ' El aviso al técnico fue procesado por el correo configurado.'
                    : ' Atención: no se pudo procesar el aviso al técnico. Revisa el correo configurado y el registro de errores.';
            }
        }

        return redirect()->route('orders.index', ['search' => DB::table('pedidos_instalacion')->where('pedido_id', $pedido)->value('codigo')])
            ->with('status', $approve
                ? 'Gerencia aprobó la reprogramación. Se actualizó la fecha y el estado del pedido.'.$notification
                : 'Gerencia rechazó la solicitud. La programación vigente no cambió.');
    }

    public function downloadReprogrammingEvidence(int $pedido, int $reprogramacion)
    {
        $change = DB::table('pedido_reprogramaciones')->where('pedido_id', $pedido)
            ->where('reprogramacion_id', $reprogramacion)->first();
        abort_unless($change, 404);
        abort_unless($this->orders->validEvidence($change), 409, 'La evidencia falta o cambió.');

        return Storage::disk('local')->download($change->evidencia_ruta, $change->evidencia_nombre);
    }

    public function schedule(Request $request, int $pedido)
    {
        $data = $request->validate([
            'tecnico_id' => ['required', 'integer', 'exists:empleados,empleado_id'],
            'motivo_reprogramacion' => ['nullable', 'string', 'max:500'],
            'fecha_programada' => ['nullable', 'date', 'after:now'],
        ]);
        $this->orders->assign($request->user(), $pedido, $data['tecnico_id'],
            $data['motivo_reprogramacion'] ?? null, $data['fecha_programada'] ?? null);

        $code = DB::table('pedidos_instalacion')->where('pedido_id', $pedido)->value('codigo');
        return redirect()->to(route('orders.index', ['search' => $code]).'#pedido-'.$pedido)
            ->with('status', 'Recursos preparados y técnico asignado. El aviso al técnico se procesará según la configuración de correo.');
    }

    public function equipment(Request $request, int $pedido)
    {
        $data = $request->validate([
            'equipo_tipo' => ['required_without:equipo_id', 'nullable', 'string', 'max:80', Rule::exists('equipos', 'tipo')],
            'equipo_id' => ['required_without:equipo_tipo', 'nullable', 'integer', 'exists:equipos,equipo_id'],
        ]);
        if (! empty($data['equipo_tipo'])) {
            $this->resources->requestEquipmentType($request->user(), $pedido, $data['equipo_tipo']);
        } else {
            $this->resources->request($request->user(), $pedido, 'EQUIPO', $data['equipo_id']);
        }
        $code = DB::table('pedidos_instalacion')->where('pedido_id', $pedido)->value('codigo');
        return redirect()->to(route('orders.index', ['search' => $code]).'#solicitar-recursos-'.$pedido)
            ->with('status', 'Solicitud de equipo registrada. Revisa si falta solicitar material. Almacén debe atenderla; si ambos cargos pertenecen a tu cuenta, primero la autoriza Gerencia.')
            ->with('resource_request_order', $pedido);
    }

    public function requestResources(Request $request, int $pedido)
    {
        $materials = $request->input('materials', []);
        $materials = is_array($materials) ? array_values(array_filter($materials,
            fn ($item) => is_array($item) && filled($item['material_id'] ?? null))) : [];
        if ($materials === [] && filled($request->input('material_id'))) {
            $materials[] = ['material_id' => $request->input('material_id'),
                'cantidad_asignada' => $request->input('cantidad_asignada')];
        }
        $request->merge(['materials' => $materials]);
        $data = $request->validate([
            'equipo_id' => ['nullable', 'integer', 'exists:equipos,equipo_id'],
            'materials' => ['nullable', 'array', 'max:30'],
            'materials.*.material_id' => ['required', 'integer', 'distinct:strict', 'exists:materiales,material_id'],
            'materials.*.cantidad_asignada' => ['required', 'numeric', 'gt:0', 'max:999999999.999', 'regex:/^\d{1,9}(\.\d{1,3})?$/D'],
        ]);
        $materialRequests = $data['materials'] ?? [];
        if (blank($data['equipo_id'] ?? null) && $materialRequests === []) {
            throw ValidationException::withMessages(['recurso' => 'Selecciona equipo, material o ambos antes de enviar la solicitud.']);
        }

        DB::transaction(function () use ($request, $pedido, $data, $materialRequests): void {
            $groupId = $this->resources->createRequestGroup($request->user(), $pedido);
            if (filled($data['equipo_id'] ?? null)) {
                $this->resources->request($request->user(), $pedido, 'EQUIPO', (int) $data['equipo_id'], '1', $groupId);
            }
            foreach ($materialRequests as $material) {
                $this->resources->request($request->user(), $pedido, 'MATERIAL', (int) $material['material_id'],
                    (string) $material['cantidad_asignada'], $groupId);
            }
        }, 3);

        $parts = array_filter([filled($data['equipo_id'] ?? null) ? 'equipo' : null,
            $materialRequests !== [] ? count($materialRequests).' '.(count($materialRequests) === 1 ? 'material' : 'materiales') : null]);
        $code = DB::table('pedidos_instalacion')->where('pedido_id', $pedido)->value('codigo');

        return redirect()->to(route('orders.index', ['search' => $code]).'#solicitar-recursos-'.$pedido)
            ->with('status', 'Solicitud conjunta de '.implode(' y ', $parts).' registrada. Gerencia y Almacén resolverán los recursos en conjunto; el detalle de cada uno aparece en el pedido.')
            ->with('resource_request_order', $pedido);
    }

    public function material(Request $request, int $pedido)
    {
        $data = $request->validate([
            'material_id' => ['required', 'integer', 'exists:materiales,material_id'],
            'cantidad_asignada' => ['required', 'numeric', 'gt:0', 'max:999999999.999', 'regex:/^\d{1,9}(\.\d{1,3})?$/D'],
        ]);
        $this->resources->request($request->user(), $pedido, 'MATERIAL', $data['material_id'], (string) $data['cantidad_asignada']);
        $code = DB::table('pedidos_instalacion')->where('pedido_id', $pedido)->value('codigo');
        return redirect()->to(route('orders.index', ['search' => $code]).'#solicitar-recursos-'.$pedido)
            ->with('status', 'Solicitud de material registrada. Revisa si falta solicitar equipo. Almacén debe atenderla; si ambos cargos pertenecen a tu cuenta, primero la autoriza Gerencia.')
            ->with('resource_request_order', $pedido);
    }

    public function approveResource(Request $request, int $pedido, int $solicitud)
    {
        abort_unless(DB::table('pedido_solicitudes_recurso')->where('pedido_id', $pedido)->where('solicitud_id', $solicitud)->exists(), 404);
        $this->resources->approve($request->user(), $solicitud);
        return redirect()->to(route('orders.index', ['search' => DB::table('pedidos_instalacion')->where('pedido_id', $pedido)->value('codigo')]).'#pedido-'.$pedido)
            ->with('status', 'Gerencia autorizó la solicitud completa. Almacén ya puede preparar en conjunto los recursos solicitados.');
    }

    public function rejectResource(Request $request, int $pedido, int $solicitud)
    {
        abort_unless(DB::table('pedido_solicitudes_recurso')->where('pedido_id', $pedido)->where('solicitud_id', $solicitud)->exists(), 404);
        $data = $request->validate([
            'decision_motivo_codigo' => ['required', 'string', Rule::in(array_keys(ResourceRejectionReasons::management()))],
            'decision_observacion' => ['nullable', 'required_if:decision_motivo_codigo,OTRO', 'string', 'max:500'],
        ]);
        $this->resources->reject($request->user(), $solicitud, $data['decision_motivo_codigo'], $data['decision_observacion'] ?? null);
        return redirect()->to(route('orders.index', ['search' => DB::table('pedidos_instalacion')->where('pedido_id', $pedido)->value('codigo')]).'#pedido-'.$pedido)
            ->with('status', 'Gerencia rechazó la solicitud completa. No se preparó ninguno de sus recursos.');
    }

    public function cancel(Request $request, int $pedido)
    {
        $data = $request->validate(['motivo_cancelacion' => ['required', 'string', 'min:5', 'max:500']]);
        $this->orders->cancelOrder($pedido, $request->user()->getKey(), trim($data['motivo_cancelacion']));

        return redirect()->route('orders.index')->with('status', 'Pedido cancelado. Su información se conserva.');
    }

    public function reopen(Request $request, int $pedido)
    {
        $data = $request->validate(['motivo_reapertura' => ['required', 'string', 'min:5', 'max:500']]);
        $this->orders->reopenOrder($request->user(), $pedido, trim($data['motivo_reapertura']));

        return redirect()->to(route('orders.index', ['search' => DB::table('pedidos_instalacion')->where('pedido_id', $pedido)->value('codigo')]).'#pedido-'.$pedido)
            ->with('status', 'Pedido reabierto. Volvió a coordinación y conserva el historial de cancelación.');
    }
}
