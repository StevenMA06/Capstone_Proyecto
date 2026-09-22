<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReactivateContractRequest;
use App\Http\Requests\StoreContractRequest;
use App\Services\ContractPdf;
use App\Services\ContractService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContractController extends Controller
{
    private const STATES = ['PENDIENTE', 'ACTIVO', 'FINALIZADO', 'CANCELADO'];

    public function __construct(private ContractService $contracts) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:180'],
            'plan_id' => ['nullable', 'integer', 'exists:planes_servicio,plan_id'],
            'estado' => ['nullable', Rule::in(self::STATES)],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'orden' => ['nullable', Rule::in(['recientes', 'plan_desc', 'estado_desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return view('contracts.index', [
            'records' => $this->contracts->search($filters),
            'plans' => $this->contracts->allPlans(),
            'states' => self::STATES,
        ]);
    }

    public function create()
    {
        return view('contracts.form', $this->contracts->formData());
    }

    public function store(StoreContractRequest $request)
    {
        $id = $this->contracts->create($request->validated());

        return redirect()->route('contracts.show', $id)->with('status', 'Contrato registrado correctamente. Código generado automáticamente.');
    }

    public function show(int $contract)
    {
        return view('contracts.show', ['contract' => $this->contracts->detail($contract)]);
    }

    public function edit(int $contract)
    {
        return view('contracts.form', $this->contracts->formData($contract));
    }

    public function update(StoreContractRequest $request, int $contract)
    {
        $feedback = $this->contracts->update($contract, $request->validated());

        return redirect()->route('contracts.show', $contract)
            ->with('status', $feedback['message'])
            ->with('status_kind', $feedback['kind']);
    }

    public function pdf(int $contract, ContractPdf $pdf)
    {
        $record = $this->contracts->detail($contract);
        $filename = 'contrato-'.preg_replace('/[^A-Za-z0-9._-]/', '_', $record->codigo).'.pdf';
        if ($record->pdf_contrato_ruta && Storage::disk('local')->exists($record->pdf_contrato_ruta)) {
            return Storage::disk('local')->download($record->pdf_contrato_ruta, $filename);
        }

        return response($pdf->render($record), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function draft(int $contract, ContractPdf $pdf)
    {
        $record = $this->contracts->detail($contract);
        $record->estado = 'PENDIENTE';
        $filename = 'borrador-'.preg_replace('/[^A-Za-z0-9._-]/', '_', $record->codigo).'.pdf';

        return response($pdf->render($record), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Mostrar listado de contratos cancelados disponibles para reactivación
     */
    public function cancelled()
    {
        return view('contracts.cancelled', [
            'contracts' => $this->contracts->getCancelledContracts(),
        ]);
    }

    /**
     * Mostrar formulario de reactivación de contrato
     */
    public function reactivateForm(int $contract)
    {
        try {
            $data = $this->contracts->reactivationFormData($contract);

            return view('contracts.reactivate', $data);
        } catch (ValidationException $exception) {
            return redirect()->route('contracts.show', $contract)
                ->with('error', $exception->errors()['contrato'][0] ?? 'No se puede reactivar este contrato.');
        }
    }

    /**
     * Reactivar un contrato cancelado
     */
    public function reactivate(ReactivateContractRequest $request, int $contract)
    {
        try {
            $feedback = $this->contracts->reactivate(
                $contract,
                $request->validated(),
                $request->user()->getKey()
            );

            return redirect()->route('contracts.show', $contract)
                ->with('status', $feedback['message'])
                ->with('status_kind', $feedback['kind']);
        } catch (ValidationException $exception) {
            return back()
                ->withInput()
                ->withErrors($exception->errors());
        }
    }
}
