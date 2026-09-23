<?php

namespace App\Repositories;

use App\Models\CoverageZone;
use App\Models\ServicePlan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CoverageZoneRepository
{
    public function search(string $location, ?string $state)
    {
        return CoverageZone::query()
            ->withCount(['plans as active_plans_count' => fn ($query) => $query
                ->where('planes_servicio.estado', 'ACTIVO')
                ->where('plan_zona_cobertura.estado', 'ACTIVO')])
            ->when($location !== '', fn ($query) => $this->locationScope($query, $location))
            ->when($state, fn ($query) => $query->where('estado', $state))
            ->orderBy('departamento')->orderBy('provincia')->orderBy('distrito')->orderBy('nombre')
            ->paginate(15)->withQueryString();
    }

    public function coverageMatches(string $location): Collection
    {
        if ($location === '') {
            return collect();
        }

        return CoverageZone::query()
            ->where('estado', 'ACTIVA')
            ->where(fn ($query) => $this->locationScope($query, $location))
            ->with(['plans' => fn ($query) => $query
                ->where('planes_servicio.estado', 'ACTIVO')
                ->where('plan_zona_cobertura.estado', 'ACTIVO')
                ->orderBy('planes_servicio.nombre')])
            ->orderBy('nombre')->limit(20)->get();
    }

    public function activePlans(): Collection
    {
        return ServicePlan::where('estado', 'ACTIVO')->orderBy('nombre')->orderBy('numero_plan')->get();
    }

    public function create(array $attributes): CoverageZone
    {
        return CoverageZone::create($attributes);
    }

    public function lock(int $id): CoverageZone
    {
        return CoverageZone::lockForUpdate()->findOrFail($id);
    }

    public function syncPlans(CoverageZone $zone, array $planIds): void
    {
        $activePlanIds = ServicePlan::where('estado', 'ACTIVO')->whereIn('plan_id', $planIds)->pluck('plan_id')->all();

        DB::table('plan_zona_cobertura')->where('zona_id', $zone->getKey())
            ->whereIn('plan_id', ServicePlan::where('estado', 'ACTIVO')->select('plan_id'))
            ->update(['estado' => 'INACTIVO']);

        foreach ($activePlanIds as $planId) {
            DB::table('plan_zona_cobertura')->upsert([
                ['plan_id' => $planId, 'zona_id' => $zone->getKey(), 'estado' => 'ACTIVO', 'asignado_en' => now()],
            ], ['plan_id', 'zona_id'], ['estado']);
        }
    }

    private function locationScope($query, string $location)
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $location).'%';

        return $query->where(function ($query) use ($pattern): void {
            foreach (['codigo', 'nombre', 'distrito', 'provincia', 'departamento', 'descripcion'] as $column) {
                $query->orWhereRaw("{$column} LIKE ? ESCAPE '!'", [$pattern]);
            }
        });
    }
}
