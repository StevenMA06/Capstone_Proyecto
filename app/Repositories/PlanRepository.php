<?php

namespace App\Repositories;

use App\Models\ServicePlan;

class PlanRepository
{
    public function search(string $number, ?string $state)
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $number).'%';

        return ServicePlan::query()->when($number !== '', fn ($q) => $q->whereRaw("numero_plan LIKE ? ESCAPE '!'", [$pattern]))
            ->when($state, fn ($q) => $q->where('estado', $state))
            ->orderBy('numero_plan')->orderBy('plan_id')->paginate(15)->withQueryString();
    }

    public function active()
    {
        return ServicePlan::where('estado', 'ACTIVO')->orderBy('numero_plan')->get();
    }

    public function create(array $data): ServicePlan
    {
        return ServicePlan::create($data);
    }

    public function lock(int $id): ServicePlan
    {
        return ServicePlan::lockForUpdate()->findOrFail($id);
    }
}
