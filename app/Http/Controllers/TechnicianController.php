<?php

namespace App\Http\Controllers;

use App\Services\InstallationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TechnicianController extends Controller
{
    public function __construct(private InstallationService $installations) {}

    public function index(Request $request)
    {
        $states = ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO', 'EN_PROCESO', 'COMPLETADO', 'CANCELADO'];
        $data = $request->validate([
            'pedido_id' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:150'], 'fecha' => ['nullable', 'date_format:Y-m-d'],
            'estado' => ['nullable', Rule::in($states)],
        ], [], ['search' => 'búsqueda', 'fecha' => 'fecha programada', 'estado' => 'estado']);
        $query = $this->installations->assignments($request->user())
            ->join('clientes as cl', 'cl.cliente_id', '=', 'pedidos_instalacion.cliente_id')
            ->join('contratos as c', 'c.contrato_id', '=', 'pedidos_instalacion.contrato_id')
            ->leftJoin('empleados as e', 'e.empleado_id', '=', 'pedidos_instalacion.tecnico_id')
            ->select('pedidos_instalacion.*', 'cl.nombres_razon_social', 'cl.apellidos', 'c.direccion_servicio',
                'e.nombres as tecnico_nombres', 'e.apellidos as tecnico_apellidos');
        if (! empty($data['pedido_id'])) {
            $query->where('pedidos_instalacion.pedido_id', $data['pedido_id']);
        }
        if (filled($data['search'] ?? null)) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($data['search'])).'%';
            $query->where(function ($q) use ($pattern) {
                $q->whereRaw("pedidos_instalacion.codigo LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("CONCAT_WS(' ', cl.nombres_razon_social, cl.apellidos) LIKE ? ESCAPE '!'", [$pattern]);
            });
        }
        if (! empty($data['fecha'])) {
            $query->whereDate('pedidos_instalacion.fecha_programada', $data['fecha']);
        }
        if (! empty($data['estado'])) {
            $query->where('pedidos_instalacion.estado', $data['estado']);
        }

        return view('technician.index', [
            'states' => $states,
            'records' => $query->orderByRaw("CASE pedidos_instalacion.estado WHEN 'PENDIENTE' THEN 0 WHEN 'PROGRAMADO' THEN 0 WHEN 'REPROGRAMADO' THEN 0 WHEN 'EN_PROCESO' THEN 1 WHEN 'COMPLETADO' THEN 2 ELSE 3 END")
                ->orderByRaw('pedidos_instalacion.fecha_programada IS NULL')
                ->orderBy('pedidos_instalacion.fecha_programada')->orderBy('pedidos_instalacion.pedido_id')->paginate(15)->withQueryString(),
        ]);
    }

    public function show(Request $request, int $pedido)
    {
        $installation = $this->installations->find($request->user(), $pedido);
        $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'contracts_page' => ['nullable', 'integer', 'min:1']]);

        return view('technician.show', [
            'installation' => $installation,
            'client' => DB::table('clientes')->where('cliente_id', $installation->cliente_id)->first(),
            'contract' => DB::table('contratos as c')->leftJoin('planes_servicio as p', 'p.plan_id', '=', 'c.plan_id')
                ->where('c.contrato_id', $installation->contrato_id)->select('c.*', 'p.nombre as plan_nombre')->first(),
            'technician' => DB::table('empleados')->where('empleado_id', $installation->tecnico_id)->first(),
            'execution' => DB::table('ejecuciones_instalacion')->where('pedido_id', $pedido)->first(),
            'evidences' => DB::table('evidencias_instalacion')->where('pedido_id', $pedido)->orderByDesc('evidencia_id')->paginate(10)->withQueryString(),
            'contractEvidences' => DB::table('evidencias_contrato')->where('contrato_id', $installation->contrato_id)
                ->where('tipo', 'CONTRATO_FIRMADO')->orderByDesc('evidencia_contrato_id')->paginate(10, ['*'], 'contracts_page')->withQueryString(),
            'equipment' => DB::table('equipos')->whereNotIn('condicion', ['MALO', 'MALOGRADO'])->where(function ($q) use ($pedido) {
                $q->where('estado', 'DISPONIBLE')->orWhereIn('equipo_id', DB::table('pedido_equipos')->select('equipo_id')
                    ->where('pedido_id', $pedido)->where('estado_asignacion', 'ASIGNADO')->whereNull('fecha_devolucion'));
            })->orderBy('nombre')->get(),
            'materials' => DB::table('materiales')->where('estado', 'ACTIVO')->orderBy('nombre')->get(),
            'usedEquipment' => DB::table('pedido_equipos as pe')->join('equipos as e', 'e.equipo_id', '=', 'pe.equipo_id')
                ->where('pe.pedido_id', $pedido)->select('pe.*', 'e.codigo', 'e.nombre', 'e.numero_serie', 'e.marca', 'e.modelo')->get(),
            'usedMaterials' => DB::table('pedido_materiales as pm')->join('materiales as m', 'm.material_id', '=', 'pm.material_id')
                ->where('pm.pedido_id', $pedido)->select('pm.*', 'm.codigo', 'm.nombre', 'm.unidad_base', 'm.unidad_empaque', 'm.inventario_en_empaque')
                ->selectRaw('CASE WHEN m.inventario_en_empaque = 1 THEN m.unidad_empaque ELSE m.unidad_base END as unidad_medida')->get(),
        ]);
    }

    private function saved(int $id, string $message)
    {
        return redirect()->route('technician.show', $id)->with('status', $message);
    }

    public function start(Request $request, int $pedido)
    {
        $this->installations->start($request->user(), $pedido);

        return $this->saved($pedido, 'Inicio registrado. La instalación está en proceso.');
    }

    public function finish(Request $request, int $pedido)
    {
        $this->installations->finish($request->user(), $pedido);

        return $this->saved($pedido, 'Instalación completada correctamente.');
    }

    public function equipment(Request $request, int $pedido)
    {
        $this->installations->find($request->user(), $pedido);
        $data = $request->validate(['equipo_id' => ['required', 'integer', 'min:1']], [], ['equipo_id' => 'equipo']);
        $this->installations->equipment($request->user(), $pedido, $data['equipo_id']);

        return $this->saved($pedido, 'Equipo utilizado registrado correctamente.');
    }

    public function material(Request $request, int $pedido)
    {
        $this->installations->find($request->user(), $pedido);
        $data = $request->validate([
            'material_id' => ['required', 'integer', 'min:1'],
            'cantidad_utilizada' => ['required', 'numeric', 'gt:0', 'max:999999999.999', 'regex:/^\d{1,9}(\.\d{1,3})?$/D'],
        ], ['cantidad_utilizada.regex' => 'Usa un número positivo con un máximo de tres decimales.'],
            ['material_id' => 'material', 'cantidad_utilizada' => 'cantidad utilizada']);
        $this->installations->material($request->user(), $pedido, $data['material_id'], (string) $data['cantidad_utilizada']);

        return $this->saved($pedido, 'Consumo registrado y stock actualizado correctamente.');
    }

    public function observation(Request $request, int $pedido)
    {
        $this->installations->find($request->user(), $pedido);
        $data = $request->validate(['observacion_tecnica' => ['nullable', 'string', 'max:5000']], [], ['observacion_tecnica' => 'observación técnica']);
        $this->installations->observation($request->user(), $pedido, $data['observacion_tecnica'] ?? null);

        return $this->saved($pedido, 'Observación técnica guardada correctamente.');
    }

    public function store(Request $request, int $pedido)
    {
        return $this->upload($request, $pedido, false);
    }

    public function contractEvidence(Request $request, int $pedido)
    {
        return $this->upload($request, $pedido, true);
    }

    private function upload(Request $request, int $pedido, bool $contract)
    {
        $this->installations->find($request->user(), $pedido);
        $data = $request->validate([
            'evidence' => ['required', 'file', $contract ? 'mimes:pdf,jpg,jpeg,png,webp' : 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ], ['evidence.required' => 'Selecciona un archivo.', 'evidence.mimes' => $contract ? 'Usa un PDF o una imagen JPG, PNG o WEBP.' : 'Usa una imagen JPG, PNG o WEBP.',
            'evidence.max' => 'El archivo no debe superar 5 MB.'], ['evidence' => 'archivo', 'descripcion' => 'descripción']);
        $this->installations->evidence($request->user(), $pedido, $request->file('evidence'), $data['descripcion'] ?? null, $contract);

        return $this->saved($pedido, $contract ? 'Evidencia vinculada al contrato correctamente.' : 'Evidencia fotográfica registrada correctamente.');
    }

    public function download(Request $request, int $pedido, int $evidence)
    {
        $this->installations->find($request->user(), $pedido);
        $record = DB::table('evidencias_instalacion')->where('pedido_id', $pedido)->where('evidencia_id', $evidence)->first();

        return $this->downloadFile($record, 'installation-evidence');
    }

    public function downloadContract(Request $request, int $pedido, int $evidence)
    {
        $installation = $this->installations->find($request->user(), $pedido);
        $record = DB::table('evidencias_contrato')->where('contrato_id', $installation->contrato_id)
            ->where('tipo', 'CONTRATO_FIRMADO')->where('evidencia_contrato_id', $evidence)->first();

        return $this->downloadFile($record, 'installation-contracts');
    }

    public function preview(Request $request, int $pedido, int $evidence)
    {
        $this->installations->find($request->user(), $pedido);
        $record = DB::table('evidencias_instalacion')->where('pedido_id', $pedido)->where('evidencia_id', $evidence)->first();
        return $this->downloadFile($record, 'installation-evidence', true);
    }

    public function previewContract(Request $request, int $pedido, int $evidence)
    {
        $installation = $this->installations->find($request->user(), $pedido);
        $record = DB::table('evidencias_contrato')->where('contrato_id', $installation->contrato_id)
            ->where('tipo', 'CONTRATO_FIRMADO')->where('evidencia_contrato_id', $evidence)->first();
        return $this->downloadFile($record, 'installation-contracts', true);
    }

    private function downloadFile(?object $record, string $folder, bool $preview = false)
    {
        abort_unless($record && preg_match('#^'.preg_quote($folder, '#').'/[a-zA-Z0-9]+\.[a-zA-Z0-9]+$#D', $record->ruta_archivo), 404);
        abort_unless(Storage::disk('local')->exists($record->ruta_archivo), 404);
        if ($preview) {
            $mime = Storage::disk('local')->mimeType($record->ruta_archivo);
            abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);
            return Storage::disk('local')->response($record->ruta_archivo, null, [
                'Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return Storage::disk('local')->download($record->ruta_archivo, basename($record->ruta_archivo), ['X-Content-Type-Options' => 'nosniff']);
    }
}
