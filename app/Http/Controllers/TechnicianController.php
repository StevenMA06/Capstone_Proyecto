<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\InstallationService;
use App\Services\InstallationOrderService;
use App\Services\InstallationDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TechnicianController extends Controller
{
    public function __construct(private InstallationService $installations, private InstallationOrderService $orders, private InstallationDriveService $drive) {}

    public function index(Request $request)
    {
        $states = ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO', 'EN_PROCESO', 'COMPLETADO', 'CANCELADO'];
        $data = $request->validate([
            'pedido_id' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:150'], 'fecha' => ['nullable', 'date_format:Y-m-d'],
            'estado' => ['nullable', Rule::in($states)],
        ], [], ['search' => 'búsqueda', 'fecha' => 'fecha programada', 'estado' => 'estado']);
        $query = $this->installations->assignments($request->user())
            ->leftJoin('clientes as cl', 'cl.cliente_id', '=', 'pedidos_instalacion.cliente_id')
            ->leftJoin('contratos as c', 'c.contrato_id', '=', 'pedidos_instalacion.contrato_id')
            ->leftJoin('empleados as e', 'e.empleado_id', '=', 'pedidos_instalacion.tecnico_id')
            ->leftJoin('empleados as e2', 'e2.empleado_id', '=', 'pedidos_instalacion.tecnico_secundario_id')
            ->select('pedidos_instalacion.*', 'cl.nombres_razon_social', 'cl.apellidos',
                'e.nombres as tecnico_nombres', 'e.apellidos as tecnico_apellidos',
                'e2.nombres as segundo_tecnico_nombres', 'e2.apellidos as segundo_tecnico_apellidos');
        $query->selectRaw('COALESCE(c.direccion_servicio, pedidos_instalacion.direccion_solicitada, cl.direccion) as direccion_servicio')
            ->selectRaw('COALESCE(CONCAT_WS(" ", NULLIF(cl.nombres_razon_social, ""), NULLIF(cl.apellidos, "")), pedidos_instalacion.solicitante_nombre) as nombre_solicitud');
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

        $records = $query->orderByRaw("CASE pedidos_instalacion.estado WHEN 'PENDIENTE' THEN 0 WHEN 'PROGRAMADO' THEN 0 WHEN 'REPROGRAMADO' THEN 0 WHEN 'EN_PROCESO' THEN 1 WHEN 'COMPLETADO' THEN 2 ELSE 3 END")
                ->orderByRaw('pedidos_instalacion.fecha_programada IS NULL')
                ->orderBy('pedidos_instalacion.fecha_programada')->orderBy('pedidos_instalacion.pedido_id')->paginate(15)->withQueryString();
        $cancellationEvents = DB::table('pedido_historial_eventos as ev')
            ->leftJoin('usuarios as u', 'u.usuario_id', '=', 'ev.usuario_id')
            ->where('ev.tipo', 'CANCELACION')->whereIn('ev.pedido_id', collect($records->items())->pluck('pedido_id'))
            ->orderByDesc('ev.registrado_en')->get(['ev.pedido_id', 'ev.motivo', 'ev.registrado_en', 'u.nombre_usuario'])
            ->groupBy('pedido_id')->map(fn ($events) => $events->first());
        return view('technician.index', [
            'states' => $states,
            'records' => $records,
            'resourcesPrepared' => collect($records->items())->filter(fn ($record) => $this->installations->resourcesPreparedForStart((int) $record->pedido_id))->pluck('pedido_id'),
            'pendingReprogramming' => DB::table('pedido_reprogramaciones')->where('estado', 'SOLICITADA')
                ->whereIn('pedido_id', collect($records->items())->pluck('pedido_id'))->pluck('pedido_id'),
            'cancellationEvents' => $cancellationEvents,
        ]);
    }

    public function show(Request $request, int $pedido)
    {
        $installation = $this->installations->find($request->user(), $pedido);
        app(\App\Services\InstallationCompletionService::class)->tryCompleteAfterDriveSync($pedido);
        $installation = $this->installations->find($request->user(), $pedido);
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $contract = DB::table('contratos as c')->leftJoin('planes_servicio as p', 'p.plan_id', '=', 'c.plan_id')
            ->where('c.contrato_id', $installation->contrato_id)->select('c.*', 'p.nombre as plan_nombre')->first();
        $contractSync = DB::table('evidencias_drive_instalacion')->where('pedido_id', $pedido)
            ->where('tipo', 'CONTRATO_VALIDADO')->where('evidencia_id', $installation->contrato_id)
            ->where('estado', 'CONFIRMADO')->whereNotNull('drive_file_id')->first();
        $contractReady = $contract?->firma_revision === 'VERIFICADA' && $contract->pdf_contrato_ruta
            && Storage::disk('local')->exists($contract->pdf_contrato_ruta)
            && $contractSync && $contractSync->source_sha256 === hash_file('sha256', Storage::disk('local')->path($contract->pdf_contrato_ruta));
        $cancellationEvent = DB::table('pedido_historial_eventos as ev')
            ->leftJoin('usuarios as u', 'u.usuario_id', '=', 'ev.usuario_id')
            ->where('ev.pedido_id', $pedido)->where('ev.tipo', 'CANCELACION')
            ->orderByDesc('ev.registrado_en')->select('ev.motivo', 'ev.registrado_en', 'u.nombre_usuario')->first();

        return view('technician.show', [
            'installation' => $installation,
            'cancellationEvent' => $cancellationEvent,
            'client' => DB::table('clientes')->where('cliente_id', $installation->cliente_id)->first(),
            'contract' => $contract,
            'contractReady' => (bool) $contractReady,
            'resourcesPrepared' => ! $installation->fase_flujo || $this->installations->resourcesPreparedForStart($pedido),
            'technician' => DB::table('empleados')->where('empleado_id', $installation->tecnico_id)->first(),
            'secondTechnician' => $installation->tecnico_secundario_id ? DB::table('empleados')->where('empleado_id', $installation->tecnico_secundario_id)->first() : null,
            'coordinator' => $installation->coordinado_por ? User::with('employee')->find($installation->coordinado_por) : null,
            'assigner' => $installation->asignado_por ? User::with('employee')->find($installation->asignado_por) : null,
            'execution' => DB::table('ejecuciones_instalacion')->where('pedido_id', $pedido)->first(),
            'evidences' => DB::table('evidencias_instalacion')->where('pedido_id', $pedido)->orderByDesc('evidencia_id')->paginate(10)->withQueryString(),
            'usedEquipment' => DB::table('pedido_equipos as pe')->join('equipos as e', 'e.equipo_id', '=', 'pe.equipo_id')
                ->where('pe.pedido_id', $pedido)->select('pe.*', 'e.codigo', 'e.nombre', 'e.numero_serie', 'e.marca', 'e.modelo')->get(),
            'usedMaterials' => DB::table('pedido_materiales as pm')->join('materiales as m', 'm.material_id', '=', 'pm.material_id')
                ->where('pm.pedido_id', $pedido)->select('pm.*', 'm.codigo', 'm.nombre', 'm.unidad_base', 'm.unidad_empaque', 'm.inventario_en_empaque')
                ->selectRaw('CASE WHEN m.inventario_en_empaque = 1 THEN m.unidad_empaque ELSE m.unidad_base END as unidad_medida')->get(),
            'incidents' => DB::table('incidencias_instalacion')->where('pedido_id', $pedido)->orderByDesc('incidencia_id')->get(),
            'driveStatus' => DB::table('evidencias_drive_instalacion')->where('pedido_id', $pedido)->get()->groupBy('tipo'),
            'driveConfigured' => $this->drive->configured(),
            'driveAuthorizationExpired' => $this->drive->authorizationExpired($pedido),
            'pendingReprogramming' => DB::table('pedido_reprogramaciones')->where('pedido_id', $pedido)
                ->where('estado', 'SOLICITADA')->first(),
            'latestApprovedReprogramming' => DB::table('pedido_reprogramaciones')->where('pedido_id', $pedido)
                ->where('estado', 'APROBADA')->orderByDesc('reprogramacion_id')->first(),
        ]);
    }

    private function saved(int $id, string $message, ?string $section = null)
    {
        $url = route('technician.show', $id).($section ? '#'.$section : '');

        return redirect()->to($url)->with('status', $message);
    }

    public function start(Request $request, int $pedido)
    {
        $this->installations->start($request->user(), $pedido);

        return $this->saved($pedido, 'Inicio registrado. La instalación está en proceso.');
    }

    public function confirm(Request $request, int $pedido)
    {
        $this->orders->confirm($request->user(), $pedido);
        return $this->saved($pedido, 'Asignación confirmada. Registra el inicio al llegar al lugar de instalación.');
    }

    public function incident(Request $request, int $pedido)
    {
        $this->installations->find($request->user(), $pedido);
        $data = $request->validate([
            'tipo' => ['required', 'in:AVERIA,EXTRAVIO,DESPERDICIO,OTRO'],
            'recurso_tipo' => ['required', 'in:EQUIPO,MATERIAL,OTRO'],
            'recurso_id' => ['nullable', 'integer', 'min:1'],
            'recurso_descripcion' => ['required', 'string', 'max:200'],
            'cantidad' => ['nullable', 'numeric', 'gt:0', 'max:999999999.999'],
            'causa' => ['required', 'string', 'max:500'],
            'observacion' => ['required', 'string', 'max:5000'],
            'evidencia' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);
        $file = $request->file('evidencia');
        unset($data['evidencia']);
        $this->installations->incident($request->user(), $pedido, $data, $file);
        return $this->saved($pedido, 'Incidencia registrada para revisión del jefe de Instalaciones.');
    }

    public function syncEvidence(Request $request, int $pedido)
    {
        $count = $this->installations->syncPending($request->user(), $pedido);
        $message = $this->drive->authorizationExpired($pedido)
            ? 'La autorización de Google Drive expiró o fue revocada. El responsable del sistema debe reconectarla antes de reintentar.'
            : ($count ? 'Evidencias confirmadas en Google Drive: '.$count.'.'
                : 'Los archivos siguen pendientes en Drive. Comprueba la conexión o informa al responsable del sistema.');
        return $this->saved($pedido, $message, 'instalacion-evidencias')
            ->with('evidence_result', 'DRIVE');
    }

    public function downloadIncident(Request $request, int $pedido, int $incident)
    {
        $this->installations->find($request->user(), $pedido);
        $record = DB::table('incidencias_instalacion')->where('pedido_id', $pedido)->where('incidencia_id', $incident)->first();
        abort_unless($record && $record->evidencia_ruta && preg_match('#^installation-incidents/[a-zA-Z0-9]+\.[a-zA-Z0-9]+$#D', $record->evidencia_ruta)
            && Storage::disk('local')->exists($record->evidencia_ruta), 404);
        return Storage::disk('local')->download($record->evidencia_ruta, $record->evidencia_nombre ?: basename($record->evidencia_ruta));
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
        return $this->upload($request, $pedido);
    }

    private function upload(Request $request, int $pedido)
    {
        $this->installations->find($request->user(), $pedido);
        $data = $request->validate([
            'evidence' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ], ['evidence.required' => 'Selecciona un archivo.', 'evidence.mimes' => 'Usa una imagen JPG, PNG o WEBP.',
            'evidence.max' => 'El archivo no debe superar 5 MB.'], ['evidence' => 'archivo', 'descripcion' => 'descripción']);
        $this->installations->evidence($request->user(), $pedido, $request->file('evidence'), $data['descripcion'] ?? null);
        $state = DB::table('evidencias_drive_instalacion')->where('pedido_id', $pedido)
            ->where('tipo', 'FOTO')->orderByDesc('sincronizacion_id')->value('estado');
        return $this->saved($pedido, $state === 'CONFIRMADO'
            ? 'Foto guardada en la carpeta del pedido y confirmada en Google Drive.'
            : ($this->drive->authorizationExpired($pedido)
                ? 'Foto guardada en el sistema. Google Drive rechazó la conexión: el responsable debe renovar la autorización para enviarla.'
                : 'Foto guardada en el sistema. Falta confirmar su envío a Google Drive.'), 'instalacion-fotos')
            ->with('evidence_result', 'FOTO');
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
