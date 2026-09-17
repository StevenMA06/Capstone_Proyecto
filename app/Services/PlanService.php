<?php

namespace App\Services;

use App\Models\ServicePlan;
use App\Models\User;
use App\Repositories\PlanRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PlanService
{
    public function __construct(private PlanRepository $plans) {}

    public function create(array $data, User $creator): ServicePlan
    {
        return DB::transaction(function () use ($data, $creator) {
            $counter = DB::table('secuencias_sistema')->where('nombre', 'planes')->lockForUpdate()->first();
            if (! $counter) {
                throw new \RuntimeException('Ejecuta php artisan migrate para inicializar la numeración de planes.');
            }
            $next = (int) $counter->valor;
            do {
                $number = 'PLAN-'.str_pad((string) ++$next, 6, '0', STR_PAD_LEFT);
            } while (ServicePlan::where('numero_plan', $number)->exists());
            DB::table('secuencias_sistema')->where('nombre', 'planes')->update(['valor' => $next]);

            return $this->plans->create($this->attributes($data) + ['numero_plan' => $number, 'estado' => 'ACTIVO', 'creado_por' => $creator->getKey()]);
        }, 3);
    }

    public function update(ServicePlan $plan, array $data): ServicePlan
    {
        return DB::transaction(function () use ($plan, $data) {
            $locked = $this->plans->lock($plan->getKey());
            $locked->fill($this->attributes($data))->save();

            return $locked;
        });
    }

    public function setActive(ServicePlan $plan, bool $active): void
    {
        DB::transaction(function () use ($plan, $active): void {
            $locked = $this->plans->lock($plan->getKey());
            $locked->estado = $active ? 'ACTIVO' : 'INACTIVO';
            $locked->save();
        });
    }

    private function attributes(array $data): array
    {
        return Arr::only($data, ['nombre', 'tipo_servicio', 'velocidad_mbps', 'precio_mensual', 'condiciones', 'descripcion']);
    }
}
