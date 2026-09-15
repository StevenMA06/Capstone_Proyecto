<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePlanRequest;
use App\Models\ServicePlan;
use App\Repositories\PlanRepository;
use App\Services\PlanService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlanController extends Controller
{
    public function __construct(private PlanRepository $plans, private PlanService $service) {}

    public function index(Request $request)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:30'], 'estado' => ['nullable', Rule::in(['ACTIVO', 'INACTIVO'])], 'page' => ['nullable', 'integer', 'min:1']]);

        return view('plans.index', ['records' => $this->plans->search($filters['search'] ?? '', $filters['estado'] ?? null)]);
    }

    public function create()
    {
        return view('plans.form', ['plan' => new ServicePlan]);
    }

    public function store(SavePlanRequest $request)
    {
        try {
            $plan = $this->service->create($request->validated(), $request->user());
        } catch (UniqueConstraintViolationException $error) {
            $this->duplicate($error);
        }

        return redirect()->route('plans.show', $plan)->with('status', 'Plan registrado correctamente.');
    }

    public function show(ServicePlan $plan)
    {
        return view('plans.show', ['plan' => $plan->load('creator')]);
    }

    public function edit(ServicePlan $plan)
    {
        return view('plans.form', compact('plan'));
    }

    public function update(SavePlanRequest $request, ServicePlan $plan)
    {
        try {
            $this->service->update($plan, $request->validated());
        } catch (UniqueConstraintViolationException $error) {
            $this->duplicate($error);
        }

        return redirect()->route('plans.show', $plan)->with('status', 'Plan actualizado correctamente.');
    }

    public function status(Request $request, ServicePlan $plan)
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $this->service->setActive($plan, (bool) $data['is_active']);

        return redirect()->route('plans.show', $plan)->with('status', $data['is_active'] ? 'Plan activado.' : 'Plan inhabilitado. Su información y contratos se conservan.');
    }

    private function duplicate(UniqueConstraintViolationException $error): never
    {
        if (! str_contains($error->getMessage(), 'uq_plan_numero')) {
            throw $error;
        }
        throw ValidationException::withMessages(['numero_plan' => 'Este número de plan ya está registrado.']);
    }
}
