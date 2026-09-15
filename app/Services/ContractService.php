<?php

namespace App\Services;

use App\Repositories\PlanRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContractService
{
    public function __construct(private PlanRepository $plans) {}

    public function create(array $data): int
    {
        return DB::transaction(function () use ($data): int {
            $plan = $this->plans->lock($data['plan_id']);
            if ($plan->estado !== 'ACTIVO') {
                throw ValidationException::withMessages(['plan_id' => 'Este plan fue inhabilitado. Selecciona otro plan activo.']);
            }
            foreach ([['clientes', 'cliente_id', 'ACTIVO'], ['zonas_cobertura', 'zona_id', 'ACTIVA'], ['empleados', 'responsable_id', 'ACTIVO']] as [$table, $field, $state]) {
                $key = $field === 'responsable_id' ? 'empleado_id' : $field;
                $record = DB::table($table)->where($key, $data[$field])->lockForUpdate()->first();
                if (! $record || $record->estado !== $state) {
                    throw ValidationException::withMessages([$field => 'El registro seleccionado ya no está activo.']);
                }
            }
            $end = CarbonImmutable::parse($data['fecha_inicio'])->addMonthsNoOverflow((int) $data['duracion_meses'])->subDay();
            if ($end->year > 9999) {
                throw ValidationException::withMessages(['duracion_meses' => 'La duración supera el rango de fechas permitido.']);
            }

            return DB::table('contratos')->insertGetId(Arr::only($data, ['codigo', 'cliente_id', 'plan_id', 'zona_id', 'responsable_id', 'fecha_inicio', 'duracion_meses', 'direccion_servicio', 'observaciones']) + [
                'fecha_fin' => $end->format('Y-m-d'), 'precio_contratado' => $plan->precio_mensual, 'estado' => 'PENDIENTE',
            ]);
        });
    }
}
