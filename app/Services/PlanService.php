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
        return $this->plans->create($this->attributes($data) + ['estado' => 'ACTIVO', 'creado_por' => $creator->getKey()]);
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
        return Arr::only($data, ['numero_plan', 'nombre', 'tipo_servicio', 'velocidad_mbps', 'precio_mensual', 'condiciones', 'descripcion']);
    }
}
