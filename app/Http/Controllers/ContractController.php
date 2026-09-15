<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContractRequest;
use App\Repositories\PlanRepository;
use App\Services\ContractService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContractController extends Controller
{
    public function create(PlanRepository $plans)
    {
        return view('contracts.create', [
            'plans' => $plans->active(),
            'clients' => DB::table('clientes')->where('estado', 'ACTIVO')->orderBy('nombres_razon_social')->get(),
            'zones' => DB::table('zonas_cobertura')->where('estado', 'ACTIVA')->orderBy('nombre')->get(),
            'employees' => DB::table('empleados')->where('estado', 'ACTIVO')->orderBy('nombres')->get(),
        ]);
    }

    public function store(StoreContractRequest $request, ContractService $contracts)
    {
        try {
            $contracts->create($request->validated());
        } catch (UniqueConstraintViolationException $error) {
            if (! str_contains($error->getMessage(), 'uq_contrato_codigo')) {
                throw $error;
            }
            throw ValidationException::withMessages(['codigo' => 'Este código de contrato ya está registrado.']);
        }

        return redirect()->route('contracts.index')->with('status', 'Contrato registrado correctamente.');
    }
}
