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
    private const CANCELLATION_CATEGORIES = [
        'Solicitud del cliente', 'Imposibilidad técnica', 'Cambio de titularidad',
        'Acuerdo entre las partes', 'Otro',
    ];

    public function __construct(
        private ContractService $contracts,
        private \App\Services\InstallationCompletionService $installationCompletion
    ) {}

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
            'cancellationWorkflowReady' => $this->contracts->cancellationWorkflowReady(),
            'pendingCancellationCount' => $request->user()->hasPermission('contracts.cancel.inbox')
                ? $this->contracts->pendingCancellationCount() : 0,
            'cancellationCategories' => self::CANCELLATION_CATEGORIES,
        ]);
    }

    public function cancellationRequests(Request $request)
    {
        abort_unless($request->user()->hasPermission('contracts.cancel.inbox'), 403);
        abort_unless($this->contracts->cancellationWorkflowReady(), 503, 'La bandeja estará disponible cuando se complete la actualización de la base de datos.');
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:180'],
            'estado' => ['nullable', Rule::in(['PENDIENTE', 'APROBADA', 'RECHAZADA', 'CERRADA'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return view('contracts.cancellations', [
            'requests' => $this->contracts->cancellationRequests($filters),
            'states' => ['PENDIENTE' => 'Pendientes', 'APROBADA' => 'Aprobadas', 'RECHAZADA' => 'Rechazadas', 'CERRADA' => 'Cerradas automáticamente'],
            'pendingCancellationCount' => $this->contracts->pendingCancellationCount(),
        ]);
    }

    public function create(Request $request)
    {
        return view('contracts.form', $this->contracts->formData(null, $request->user()) + [
            'selectedClientId' => $request->integer('cliente_id') ?: null,
        ]);
    }

    public function store(StoreContractRequest $request)
    {
        $data = $request->validated();
        if ($request->user()->isAdministrator()) {
            $data['responsable_id'] = (int) ($this->contracts->automaticResponsibleFor($request->user()) ?? 0);
        } elseif (! $request->user()->isManager()) {
            $data['responsable_id'] = (int) $request->user()->empleado_id;
        }
        $id = $this->contracts->create($data, (int) $request->user()->getKey());

        return redirect()->route('contracts.show', $id)
            ->with('status', 'Contrato registrado correctamente. Su pedido de instalación ya está disponible para coordinar la visita.');
    }

    public function show(Request $request, int $contract)
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $record = $this->contracts->detail($contract);
        $showFieldEvidenceHistory = $this->installationCompletion->hasVerifiedCompletionEvidence($contract);

        return view('contracts.show', [
            'contract' => $record,
            'cancellationHistory' => $this->contracts->cancellationHistory($contract),
            'cancellationWorkflowReady' => $this->contracts->cancellationWorkflowReady(),
            'cancellationCategories' => self::CANCELLATION_CATEGORIES,
            'showFieldEvidenceHistory' => $showFieldEvidenceHistory,
            'fieldEvidences' => $showFieldEvidenceHistory
                ? DB::table('evidencias_contrato')->where('contrato_id', $contract)
                    ->where('tipo', 'CONTRATO_FIRMADO')->orderByDesc('evidencia_contrato_id')->paginate(10)
                : null,
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
        $record = $this->contracts->detail($contract);
        abort_if($record->estado === 'CANCELADO', 403, 'Los contratos cancelados son únicamente de consulta.');
        if ($this->contracts->cancellationWorkflowReady()
            && DB::table('solicitudes_cancelacion_contrato')->where('contrato_id', $contract)->where('estado', 'PENDIENTE')->exists()) {
            return redirect()->route('contracts.show', $contract)
                ->withErrors(['contrato' => 'El contrato está bloqueado mientras Gerencia revisa la solicitud de cancelación.']);
        }

        return view('contracts.form', $this->contracts->formData($contract, request()->user()) + [
            'cancellationWorkflowReady' => $this->contracts->cancellationWorkflowReady(),
            'cancellationCategories' => self::CANCELLATION_CATEGORIES,
        ]);
    }

    public function update(StoreContractRequest $request, int $contract)
    {
        $data = $request->validated();
        if ($request->user()->isAdministrator() || ! $request->user()->isManager()) {
            $data['responsable_id'] = (int) $this->contracts->detail($contract)->responsable_id;
        }
        $feedback = $this->contracts->update($contract, $data, $request->user()->getKey());

        return redirect()->route('contracts.show', $contract)
            ->with('status', $feedback['message'])
            ->with('status_kind', $feedback['kind']);
    }

    public function requestCancellation(Request $request, int $contract)
    {
        abort_unless($request->user()->hasPermission('contracts.cancel.request'), 403);
        $this->ensureCancellationWorkflowReady();
        $data = $request->validate([
            'categoria' => ['required', Rule::in(self::CANCELLATION_CATEGORIES)],
            'explicacion' => ['required', 'string', 'min:15', 'max:400'],
            'evidencia' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'categoria.required' => 'Selecciona el motivo de la cancelación.',
            'explicacion.min' => 'Explica el caso con al menos 15 caracteres.',
            'evidencia.required' => 'Adjunta un documento que respalde la solicitud.',
            'evidencia.mimes' => 'Adjunta un PDF o una imagen JPG, PNG o WEBP.',
            'evidencia.max' => 'La evidencia no debe superar 10 MB.',
        ]);

        $this->contracts->requestCancellation($contract, $data, $request->file('evidencia'), (int) $request->user()->getKey());

        return redirect()->route('contracts.show', $contract)->with('status', 'Solicitud registrada. El contrato no cambiará hasta que otra cuenta autorizada revise la evidencia.');
    }

    public function approveCancellation(Request $request, int $contract, int $cancellationRequest)
    {
        abort_unless($request->user()->hasPermission('contracts.cancel.approve'), 403);
        $this->ensureCancellationWorkflowReady();
        try {
            $this->contracts->approveCancellation($contract, $cancellationRequest, (int) $request->user()->getKey());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('contracts.show', $contract)->with('status', 'Cancelación aprobada. El contrato y su pedido asociado quedaron cancelados con el motivo y los responsables registrados.');
    }

    public function rejectCancellation(Request $request, int $contract, int $cancellationRequest)
    {
        abort_unless($request->user()->hasPermission('contracts.cancel.approve'), 403);
        $this->ensureCancellationWorkflowReady();
        $data = $request->validate([
            'decision_motivo' => ['required', 'string', 'min:10', 'max:500'],
        ], ['decision_motivo.required' => 'Explica por qué se rechaza la solicitud.', 'decision_motivo.min' => 'La explicación debe tener al menos 10 caracteres.']);
        try {
            $this->contracts->rejectCancellation($contract, $cancellationRequest,
                (int) $request->user()->getKey(), $data['decision_motivo']);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('contracts.show', $contract)->with('status', 'Solicitud de cancelación rechazada. El contrato conserva su estado.');
    }

    public function cancellationEvidence(Request $request, int $contract, int $cancellationRequest)
    {
        abort_unless($request->user()->hasPermission('contracts.cancel.evidence'), 403);
        $this->ensureCancellationWorkflowReady();
        $record = DB::table('solicitudes_cancelacion_contrato')->where('contrato_id', $contract)
            ->where('solicitud_cancelacion_id', $cancellationRequest)->first();
        abort_unless($record && preg_match('#^contract-cancellations/[a-f0-9-]+\.(pdf|jpg|jpeg|png|webp)$#D', $record->evidencia_ruta), 404);
        abort_unless(Storage::disk('local')->exists($record->evidencia_ruta), 404);
        abort_unless(hash_equals($record->evidencia_sha256, hash_file('sha256', Storage::disk('local')->path($record->evidencia_ruta))), 409, 'La evidencia no coincide con su huella de integridad.');

        return Storage::disk('local')->download($record->evidencia_ruta, $record->evidencia_nombre, ['X-Content-Type-Options' => 'nosniff']);
    }

    private function ensureCancellationWorkflowReady(): void
    {
        abort_unless($this->contracts->cancellationWorkflowReady(), 503,
            'La gestión de cancelaciones requiere aplicar primero las migraciones pendientes de la base de datos.');
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
