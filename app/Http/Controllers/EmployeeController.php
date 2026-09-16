<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveEmployeeRequest;
use App\Models\Employee;
use App\Services\EmployeeService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120'], 'estado' => ['nullable', Rule::in(['ACTIVO', 'INACTIVO'])], 'page' => ['nullable', 'integer', 'min:1']]);
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['search'] ?? '').'%';
        $records = Employee::query()->where(fn ($q) => $q->whereRaw("CONCAT(nombres, ' ', apellidos) LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("numero_documento LIKE ? ESCAPE '!'", [$pattern]))
            ->when($filters['estado'] ?? null, fn ($q, $state) => $q->where('estado', $state))
            ->orderBy('apellidos')->orderBy('empleado_id')->paginate(15)->withQueryString();

        return view('employees.index', compact('records'));
    }

    public function create()
    {
        return view('employees.form', ['employee' => new Employee]);
    }

    public function store(SaveEmployeeRequest $request, EmployeeService $service)
    {
        return $this->save($request, $service);
    }

    public function show(Employee $employee)
    {
        return view('employees.show', compact('employee'));
    }

    public function edit(Employee $employee)
    {
        Gate::authorize('edit-employee', $employee);

        return view('employees.form', compact('employee'));
    }

    public function update(SaveEmployeeRequest $request, Employee $employee, EmployeeService $service)
    {
        return $this->save($request, $service, $employee);
    }

    public function status(Request $request, Employee $employee, EmployeeService $service)
    {
        Gate::authorize('edit-employee', $employee);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $service->setActive($employee, (bool) $data['is_active']);

        return redirect()->route('employees.show', $employee)->with('status', $data['is_active'] ? 'Empleado habilitado. Las cuentas se habilitan por separado en Usuarios.' : 'Empleado y cuentas asociadas inhabilitados.');
    }

    private function save(SaveEmployeeRequest $request, EmployeeService $service, ?Employee $employee = null)
    {
        try {
            $record = $service->save($employee, $request->validated());
        } catch (UniqueConstraintViolationException $error) {
            if (str_contains($error->getMessage(), 'uq_empleados_documento')) {
                throw ValidationException::withMessages(['numero_documento' => 'Este documento ya está registrado.']);
            }
            if (str_contains($error->getMessage(), 'uq_empleados_correo')) {
                throw ValidationException::withMessages(['correo' => 'Este correo ya está registrado.']);
            }
            throw $error;
        }

        return redirect()->route('employees.show', $record)->with('status', $record->hasEnded() ? 'Empleado guardado e inhabilitado por fecha de cese. Sus cuentas también están inhabilitadas.' : 'Empleado guardado correctamente.');
    }
}
