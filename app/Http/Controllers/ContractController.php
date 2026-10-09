<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReactivateContractRequest;
use App\Http\Requests\StoreContractRequest;
use App\Services\ContractPdf;
use App\Services\ContractService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $id = $this->contracts->create($request->validated(), (int) $request->user()->getKey());

        return redirect()->route('contracts.show', $id)->with('status', 'Contrato registrado correctamente. Su pedido de instalación ya está disponible para coordinar la visita.');
    }

    public function show(Request $request, int $contract)
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);

        return view('contracts.show', [
            'contract' => $this->contracts->detail($contract),
            'fieldEvidences' => DB::table('evidencias_contrato')->where('contrato_id', $contract)
                ->where('tipo', 'CONTRATO_FIRMADO')->orderByDesc('evidencia_contrato_id')->paginate(10),
        ]);
    }

    public function evidence(int $contract, int $evidence)
    {
        $this->contracts->detail($contract);
        $record = DB::table('evidencias_contrato')->where('contrato_id', $contract)
            ->where('evidencia_contrato_id', $evidence)->where('tipo', 'CONTRATO_FIRMADO')->first();
        abort_unless($record && preg_match('#^installation-contracts/[a-zA-Z0-9]+\.[a-zA-Z0-9]+$#D', $record->ruta_archivo), 404);
        abort_unless(Storage::disk('local')->exists($record->ruta_archivo), 404);

        return Storage::disk('local')->download($record->ruta_archivo, basename($record->ruta_archivo), ['X-Content-Type-Options' => 'nosniff']);
    }

    public function edit(int $contract)
    {
        abort_if($this->contracts->detail($contract)->estado === 'CANCELADO', 403, 'Los contratos cancelados son únicamente de consulta.');

        return view('contracts.form', $this->contracts->formData($contract));
    }

    public function update(StoreContractRequest $request, int $contract)
    {
        $feedback = $this->contracts->update($contract, $request->validated(), $request->user()->getKey());

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
        $filename = 'contrato-'.preg_replace('/[^A-Za-z0-9._-]/', '_', $record->codigo).'-para-firma.pdf';

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
