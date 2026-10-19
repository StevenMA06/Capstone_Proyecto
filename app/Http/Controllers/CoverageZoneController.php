<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCoverageZoneRequest;
use App\Models\CoverageZone;
use App\Repositories\CoverageZoneRepository;
use App\Services\CoverageZoneService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CoverageZoneController extends Controller
{
    public function __construct(private CoverageZoneRepository $zones, private CoverageZoneService $service) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', Rule::in(['ACTIVA', 'INACTIVA'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        return view('zones.index', [
            'records' => $this->zones->search($search, $filters['estado'] ?? null),
        ]);
    }

    public function create()
    {
        return view('zones.form', ['zone' => new CoverageZone, 'plans' => $this->zones->activePlans(), 'selectedPlans' => []]);
    }

    public function store(SaveCoverageZoneRequest $request)
    {
        $zone = $this->service->create($request->validated(), $request->user());

        return redirect()->route('zones.show', $zone)->with('status', 'Zona de cobertura registrada correctamente.');
    }

    public function show(CoverageZone $zone)
    {
        $zone->load(['creator', 'plans' => fn ($query) => $query->orderBy('nombre')]);

        return view('zones.show', compact('zone'));
    }

    public function edit(CoverageZone $zone)
    {
        return view('zones.form', [
            'zone' => $zone,
            'plans' => $this->zones->activePlans(),
            'selectedPlans' => $zone->plans()->wherePivot('estado', 'ACTIVO')->pluck('planes_servicio.plan_id')->all(),
        ]);
    }

    public function update(SaveCoverageZoneRequest $request, CoverageZone $zone)
    {
        $this->service->update($zone, $request->validated());

        return redirect()->route('zones.show', $zone)->with('status', 'Zona de cobertura actualizada correctamente.');
    }

    public function status(Request $request, CoverageZone $zone)
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $this->service->setActive($zone, (bool) $data['is_active']);

        return redirect()->route('zones.show', $zone)->with('status', $data['is_active']
            ? 'Zona de cobertura activada.'
            : 'Zona de cobertura inhabilitada. Su información histórica se conserva.');
    }
}
