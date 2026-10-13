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
            ->leftJoin('contratos as c', 'c.contrato_id', '=', 'p.contrato_id')
            ->leftJoin('clientes as cl', 'cl.cliente_id', '=', 'p.cliente_id')
            ->whereIn('p.estado', ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO'])
            ->select('p.pedido_id', 'p.codigo', 'p.estado', 'p.fecha_programada', 'p.franja_horaria',
                'p.canal_coordinacion', 'p.coordinado_en', 'p.fase_flujo', 'p.documento_cliente', 'p.tipo_instalacion', 'p.ubicacion_compartida',
                'p.plan_solicitado', 'p.disponibilidad_fecha', 'p.disponibilidad_desde', 'p.disponibilidad_hasta',
                'p.estado_cobertura', 'p.detalle_cobertura', 'c.codigo as contrato_codigo', 'c.fecha_inicio as contrato_fecha_inicio',
                'cl.nombres_razon_social as cliente_nombre', 'cl.apellidos as cliente_apellidos',
                'cl.telefono as cliente_telefono', 'cl.correo as cliente_correo')
            ->selectRaw('COALESCE(c.direccion_servicio, p.direccion_solicitada, cl.direccion) as direccion_servicio')
            ->selectRaw('COALESCE(CONCAT_WS(" ", NULLIF(cl.nombres_razon_social, ""), NULLIF(cl.apellidos, "")), p.solicitante_nombre) as nombre_solicitud')
            ->selectRaw('COALESCE(cl.telefono, p.telefono_contacto) as telefono_solicitud')
            ->selectRaw('COALESCE(cl.correo, p.correo_contacto) as correo_solicitud');
        if (filled($filters['search'] ?? null)) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($filters['search'])).'%';
            $query->where(function ($builder) use ($pattern): void {
                $builder->whereRaw("p.codigo LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("c.codigo LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhere('p.documento_cliente', 'like', $pattern)
                    ->orWhereRaw("CONCAT_WS(' ', cl.nombres_razon_social, cl.apellidos) LIKE ? ESCAPE '!'", [$pattern]);
            });
        }
        if (($filters['estado'] ?? null) === 'por_confirmar') $query->whereNull('p.fecha_programada');
        if (($filters['estado'] ?? null) === 'confirmadas') $query->whereNotNull('p.fecha_programada');

        $records = $query->orderByRaw('p.fecha_programada IS NOT NULL')->orderBy('p.fecha_programada')
            ->orderByDesc('p.pedido_id')->paginate(12)->withQueryString();
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
            'latestChanges' => $latestChanges,
            'reasonOptions' => InstallationOrderService::REPROGRAM_REASONS,
            'visitWindows' => self::VISIT_WINDOWS,
            'visitTimes' => $visitTimes,
        ]);
    }

    public function saveCoverage(Request $request, int $pedido)
    {
        $data = $request->validate([
            'estado_cobertura' => ['required', Rule::in(['VALIDADA', 'FUERA_COBERTURA'])],
            'detalle_cobertura' => ['nullable', 'string', 'max:500', 'required_if:estado_cobertura,FUERA_COBERTURA'],
        ]);
        $order = DB::table('pedidos_instalacion')->where('pedido_id', $pedido)->first();
        abort_unless($order, 404);
        if ($order->contrato_id) {
            throw ValidationException::withMessages(['cobertura' => 'Este pedido ya tiene un contrato formal vinculado.']);
        }
        if (! in_array($order->estado, ['PENDIENTE', 'PROGRAMADO', 'REPROGRAMADO'], true)) {
            throw ValidationException::withMessages(['cobertura' => 'La cobertura solo se actualiza antes de iniciar la instalación.']);
        }
        if ($order->fecha_programada && $data['estado_cobertura'] !== $order->estado_cobertura) {
            throw ValidationException::withMessages(['cobertura' => 'La cobertura ya fue validada para una visita confirmada. Gestiona un cambio de fecha o cancela el pedido antes de modificarla.']);
        }
        DB::transaction(function () use ($request, $pedido, $data): void {
            DB::table('pedidos_instalacion')->where('pedido_id', $pedido)->update([
                'estado_cobertura' => $data['estado_cobertura'],
                'detalle_cobertura' => $data['estado_cobertura'] === 'FUERA_COBERTURA' ? trim($data['detalle_cobertura']) : null,
                'actualizado_en' => now('America/Lima'),
            ]);
            DB::table('pedido_historial_eventos')->insert([
                'pedido_id' => $pedido, 'usuario_id' => $request->user()->getKey(),
                'tipo' => 'VALIDACION_COBERTURA',
                'motivo' => $data['estado_cobertura'] === 'VALIDADA' ? 'Cobertura validada.' : trim($data['detalle_cobertura']),
                'contexto' => json_encode(['estado' => $data['estado_cobertura']]), 'registrado_en' => now('America/Lima'),
            ]);
        });
        return redirect()->route('office.coordination.index', ['search' => $order->codigo])
            ->with('status', $data['estado_cobertura'] === 'VALIDADA'
                ? 'Cobertura validada. Ya puedes coordinar la fecha exacta con el cliente.'
                : 'La solicitud quedó marcada fuera de cobertura y no se podrá programar mientras ese resultado siga vigente.');
    }

    public function save(Request $request, int $pedido)
    {
        $order = DB::table('pedidos_instalacion')->where('pedido_id', $pedido)->first();
        abort_unless($order, 404);
        if (! $order->contrato_id && $order->estado_cobertura !== 'VALIDADA') {
            throw ValidationException::withMessages(['cobertura' => 'Valida primero la cobertura antes de confirmar la visita.']);
        }
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
