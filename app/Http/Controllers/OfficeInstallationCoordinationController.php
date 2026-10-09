<?php

namespace App\Http\Controllers;

use App\Services\InstallationOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OfficeInstallationCoordinationController extends Controller
{
    private const VISIT_WINDOWS = [
        '08:00 a 12:00' => ['08:00', '12:00'],
        '14:00 a 18:00' => ['14:00', '18:00'],
    ];

    public function __construct(private InstallationOrderService $orders) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'estado' => ['nullable', Rule::in(['por_confirmar', 'confirmadas'])],
        ]);
        $query = DB::table('pedidos_instalacion as p')
            ->join('contratos as c', 'c.contrato_id', '=', 'p.contrato_id')
            ->join('clientes as cl', 'cl.cliente_id', '=', 'p.cliente_id')
            ->whereIn('c.estado', ['PENDIENTE', 'ACTIVO'])
            ->whereIn('p.estado', ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO'])
            ->select('p.pedido_id', 'p.codigo', 'p.estado', 'p.fecha_programada', 'p.franja_horaria',
                'p.canal_coordinacion', 'p.coordinado_en', 'p.fase_flujo', 'c.codigo as contrato_codigo', 'c.fecha_inicio as contrato_fecha_inicio',
                'c.direccion_servicio', 'cl.nombres_razon_social as cliente_nombre', 'cl.apellidos as cliente_apellidos',
                'cl.telefono as cliente_telefono', 'cl.correo as cliente_correo');
        if (filled($filters['search'] ?? null)) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($filters['search'])).'%';
            $query->where(function ($builder) use ($pattern): void {
                $builder->whereRaw("p.codigo LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("c.codigo LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("CONCAT_WS(' ', cl.nombres_razon_social, cl.apellidos) LIKE ? ESCAPE '!'", [$pattern]);
            });
        }
        if (($filters['estado'] ?? null) === 'por_confirmar') $query->whereNull('p.fecha_programada');
        if (($filters['estado'] ?? null) === 'confirmadas') $query->whereNotNull('p.fecha_programada');

        $records = $query->orderByRaw('p.fecha_programada IS NOT NULL')->orderBy('p.fecha_programada')
            ->orderByDesc('p.pedido_id')->paginate(12)->withQueryString();
        $unplannedContracts = DB::table('contratos as c')
            ->join('clientes as cl', 'cl.cliente_id', '=', 'c.cliente_id')
            ->leftJoin('pedidos_instalacion as p', 'p.contrato_id', '=', 'c.contrato_id')
            ->whereNull('p.pedido_id')->whereIn('c.estado', ['PENDIENTE', 'ACTIVO'])
            ->when(filled($filters['search'] ?? null), function ($builder) use ($filters): void {
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($filters['search'])).'%';
                $builder->where(fn ($builder) => $builder->whereRaw("c.codigo LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("CONCAT_WS(' ', cl.nombres_razon_social, cl.apellidos) LIKE ? ESCAPE '!'", [$pattern]));
            })
            ->when(($filters['estado'] ?? null) === 'confirmadas', fn ($builder) => $builder->whereRaw('1 = 0'))
            ->select('c.contrato_id', 'c.codigo', 'c.direccion_servicio', 'c.estado', 'c.fecha_inicio as contrato_fecha_inicio',
                'cl.nombres_razon_social as cliente_nombre', 'cl.apellidos as cliente_apellidos',
                'cl.telefono as cliente_telefono', 'cl.correo as cliente_correo')
            ->orderByDesc('c.contrato_id')->limit(12)->get();
        $latestChanges = DB::table('pedido_reprogramaciones')
            ->whereIn('pedido_id', collect($records->items())->pluck('pedido_id'))
            ->orderByDesc('reprogramacion_id')
            ->get(['pedido_id', 'estado', 'fecha_nueva', 'franja_nueva', 'decision_observacion'])
            ->unique('pedido_id')->keyBy('pedido_id');
        $visitTimes = [];
        foreach (self::VISIT_WINDOWS as $window => [$start, $end]) {
            $startMinutes = (int) substr($start, 0, 2) * 60;
            $endMinutes = (int) substr($end, 0, 2) * 60;
            for ($minute = $startMinutes; $minute < $endMinutes; $minute += 30) {
                $visitTimes[$window][] = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
            }
        }

        return view('access.coordination', [
            'records' => $records,
            'unplannedContracts' => $unplannedContracts,
            'latestChanges' => $latestChanges,
            'reasonOptions' => InstallationOrderService::REPROGRAM_REASONS,
            'visitWindows' => self::VISIT_WINDOWS,
            'visitTimes' => $visitTimes,
        ]);
    }

    public function saveContract(Request $request, int $contrato)
    {
        $request->validate(['aceptacion_cliente' => ['accepted']], [
            'aceptacion_cliente.accepted' => 'Confirma que el cliente aceptó la visita y las condiciones antes de crear el pedido.',
        ]);
        return DB::transaction(function () use ($request, $contrato) {
            $contract = DB::table('contratos')->where('contrato_id', $contrato)->lockForUpdate()->first();
            abort_unless($contract, 404);
            if (! in_array($contract->estado, ['PENDIENTE', 'ACTIVO'], true)) {
                throw ValidationException::withMessages(['contrato' => 'El contrato no permite programar una instalación.']);
            }
            if (DB::table('pedidos_instalacion')->where('contrato_id', $contrato)->exists()) {
                throw ValidationException::withMessages(['contrato' => 'Este contrato ya tiene un pedido. Abre su visita en la lista para continuar.']);
            }
            $pedido = $this->orders->createForContract($contract, $request->user()->getKey());
            $response = $this->save($request, $pedido);
            DB::afterCommit(fn () => app(\App\Services\InstallationDriveService::class)->syncVerifiedContract($pedido));
            return $response;
        }, 3);
    }

    public function save(Request $request, int $pedido)
    {
        $data = $request->validate([
            'fecha_visita' => ['required_without:fecha_programada', 'nullable', 'date_format:Y-m-d'],
            'hora_inicio' => ['required_with:fecha_visita', 'nullable', 'date_format:H:i'],
            'fecha_programada' => ['required_without:fecha_visita', 'nullable', 'date', 'after:now'],
            'franja_horaria' => ['required', Rule::in(array_keys(self::VISIT_WINDOWS))],
            'canal_coordinacion' => ['required', 'in:LLAMADA,WHATSAPP,CORREO,PRESENCIAL'],
            'detalle_coordinacion' => ['nullable', 'string', 'max:500'],
            'motivo_reprogramacion' => ['nullable', 'string', 'max:500'],
            'motivo_codigo' => ['nullable', Rule::in(array_keys(InstallationOrderService::REPROGRAM_REASONS))],
            'evidencia_acuerdo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'fecha_visita.required_without' => 'Selecciona el día acordado con el cliente.',
            'fecha_visita.date_format' => 'Selecciona un día válido.',
            'hora_inicio.required_with' => 'Selecciona la hora de inicio de la visita.',
            'hora_inicio.date_format' => 'Selecciona una hora de inicio válida.',
            'franja_horaria.required' => 'Selecciona una franja horaria.',
            'franja_horaria.in' => 'Selecciona la franja de mañana o tarde disponible.',
        ]);
        $dateTime = filled($data['fecha_visita'] ?? null)
            ? $data['fecha_visita'].' '.$data['hora_inicio']
            : $data['fecha_programada'];
        $visit = Carbon::parse($dateTime, 'America/Lima');
        [$start, $end] = self::VISIT_WINDOWS[$data['franja_horaria']];
        $time = $visit->format('H:i');
        if ($time < $start || $time >= $end) {
            throw ValidationException::withMessages(['hora_inicio' => 'La hora de inicio debe estar dentro de la franja elegida.']);
        }
        if (! $visit->greaterThan(now('America/Lima'))) {
            throw ValidationException::withMessages(['fecha_visita' => 'Elige una fecha y hora futuras.']);
        }
        $data['fecha_programada'] = $visit->format('Y-m-d H:i');
        $pending = $this->orders->coordinate($request->user(), $pedido, $data, $request->file('evidencia_acuerdo'));

        return redirect()->route('office.coordination.index', ['search' => DB::table('pedidos_instalacion')->where('pedido_id', $pedido)->value('codigo')])
            ->with('status', $pending
                ? 'Solicitud enviada a Gerencia. La fecha actual se mantiene y el aviso al técnico se enviará solo si Gerencia aprueba.'
                : 'Fecha confirmada con el cliente y registrada en el pedido.');
    }
}
