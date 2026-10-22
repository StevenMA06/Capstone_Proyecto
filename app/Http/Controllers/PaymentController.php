<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Models\Payment;
use App\Services\PaymentReceiptPdf;
use App\Services\PaymentService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $payments,
        private PaymentReceiptPdf $receiptPdf
    ) {}

    /**
     * Listado de pagos con filtros (RF-PAG-05, RF-PAG-06, RF-PAG-08)
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:180'],
            'contract' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', Rule::in(['PENDIENTE', 'REALIZADO', 'VENCIDO', 'IMPAGO', 'ANULADO'])],
            'month' => ['nullable', 'date_format:Y-m'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'due_soon' => ['nullable', 'boolean'],
            'due_soon_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'overdue' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $payments = $this->payments->search($filters);
        
        // Obtener el mes para las estadísticas
        $selectedMonth = $filters['month'] ?? null; // null = todos los meses
        
        // Obtener estadísticas: si no hay mes seleccionado, obtener estadísticas globales
        if ($selectedMonth) {
            $statistics = $this->payments->getStatisticsForMonth($selectedMonth);
        } else {
            $statistics = $this->payments->getGlobalStatistics();
        }

        return view('payments.index', compact('payments', 'filters', 'statistics', 'selectedMonth'));
    }

    /**
     * Formulario para crear un nuevo pago (RF-PAG-01)
     */
    public function create(): View
    {
        $payment = new Payment;
        
        // Obtener contratos activos para el select
        $contracts = \App\Models\Contract::where('estado', 'ACTIVO')
            ->with('client')
            ->orderBy('codigo')
            ->get();

        return view('payments.create', compact('payment', 'contracts'));
    }

    /**
     * Registrar un nuevo pago (RF-PAG-01, RF-PAG-02)
     */
    public function store(StorePaymentRequest $request): RedirectResponse
    {
        try {
            $payment = $this->payments->create($request->validated(), $request->user());
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), 'uq_pago_codigo')) {
                throw $exception;
            }

            return back()->withInput()->withErrors([
                'codigo' => 'Este código de pago ya está registrado.',
            ]);
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }

        app(\App\Services\AutomaticIndicatorService::class)->complete($request, $request->user(), 'pagos', 'pago', $payment->getKey());

        return redirect()->route('payments.show', $payment)
            ->with('status', 'Pago #'.$payment->getKey().' registrado correctamente.');
    }

    /**
     * Ver detalle de un pago (RF-PAG-07)
     */
    public function show(Payment $payment): View
    {
        // Cargar relaciones
        $payment->load(['contract.client', 'contract.plan', 'registeredBy']);
        
        // Obtener recordatorios si existen (RF-PAG-12)
        $reminders = $this->payments->getReminders($payment);

        return view('payments.show', compact('payment', 'reminders'));
    }

    /**
     * Formulario para editar un pago
     */
    public function edit(Payment $payment): View|RedirectResponse
    {
        if (! $payment->canBeEdited()) {
            return redirect()->route('payments.show', $payment)
                ->with('error', 'No se puede editar un pago con estado: '.ucfirst(strtolower($payment->estado)).'.');
        }

        // Obtener contratos activos para el select
        $contracts = \App\Models\Contract::where('estado', 'ACTIVO')
            ->with('client')
            ->orderBy('codigo')
            ->get();

        return view('payments.edit', compact('payment', 'contracts'));
    }

    /**
     * Actualizar un pago existente
     */
    public function update(UpdatePaymentRequest $request, Payment $payment): RedirectResponse
    {
        try {
            // Si se marcó como realizado, usar el método específico
            if ($request->boolean('marcar_realizado') && $payment->estado !== 'REALIZADO') {
                $data = $request->validated();
                
                // Manejar método de pago personalizado
                if ($request->input('metodo_pago') === 'OTRO' && $request->filled('metodo_pago_otro')) {
                    $data['metodo_pago'] = strtoupper($request->input('metodo_pago_otro'));
                }
                
                $this->payments->markAsCompleted($payment, $data, $request->user());
                
                // Generar comprobante PDF automáticamente
                try {
                    $receiptPath = $this->receiptPdf->generate($payment);
                    $payment->constancia_ruta = $receiptPath;
                    $payment->save();
                } catch (\Exception $e) {
                    // Si falla el PDF, continuar pero sin constancia
                    \Log::warning('No se pudo generar comprobante para pago '.$payment->codigo.': '.$e->getMessage());
                }
                
                return redirect()->route('payments.index')
                    ->with('status', 'Pago registrado como realizado y comprobante generado correctamente.');
            }
            
            // Si no se marcó como realizado, solo actualizar datos
            $this->payments->update($payment, $request->validated());
            
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), 'uq_pago_codigo')) {
                throw $exception;
            }

            return back()->withInput()->withErrors([
                'codigo' => 'Este código de pago ya está registrado.',
            ]);
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }

        return redirect()->route('payments.show', $payment)
            ->with('status', 'Datos del pago actualizados correctamente.');
    }

    /**
     * Marcar pago como realizado
     */
    public function complete(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate([
            'fecha_pago' => ['required', 'date', 'before_or_equal:today'],
            'metodo_pago' => ['required', 'string', 'max:60'],
            'referencia_externa' => ['nullable', 'required_if:metodo_pago,TRANSFERENCIA', 'string', 'max:120'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ], ['referencia_externa.required_if' => 'El número de operación es obligatorio para una transferencia.']);

        try {
            $this->payments->markAsCompleted($payment, $data, $request->user());
            
            // RF-PAG-09: Generar comprobante PDF automáticamente
            $receiptPath = $this->receiptPdf->generate($payment);
            
            // RF-PAG-13: Guardar ruta del comprobante
            $payment->constancia_ruta = $receiptPath;
            $payment->save();
            
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('payments.index')
            ->with('status', 'Pago marcado como realizado y comprobante generado correctamente.');
    }

    /**
     * Anular un pago (RN-49: no elimina físicamente)
     */
    public function cancel(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate([
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->payments->cancel($payment, $data['observaciones'] ?? null);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('payments.show', $payment)
            ->with('status', 'Pago anulado. La información se conserva en el historial.');
    }

    /**
     * Generar constancia interna de pago (RF-PAG-09)
     */
    public function receipt(Payment $payment): StreamedResponse|RedirectResponse
    {
        // Si hay un comprobante subido por el usuario, mostrarlo
        if ($payment->constancia_ruta && !str_ends_with(strtolower($payment->constancia_ruta), '.pdf')) {
            $storagePath = str_replace('storage/', '', $payment->constancia_ruta);
            
            if (Storage::disk('public')->exists($storagePath)) {
                return Storage::disk('public')->response(
                    $storagePath,
                    basename($storagePath)
                );
            }
        }
        
        // Si es PDF generado, mostrarlo
        try {
            $this->payments->generateReceipt($payment);
        } catch (ValidationException $exception) {
            return redirect()->route('payments.show', $payment)
                ->with('error', $exception->errors()['payment'][0] ?? 'No se pudo generar la constancia.');
        }

        $hasPdf = $payment->constancia_ruta
            && str_ends_with(strtolower($payment->constancia_ruta), '.pdf')
            && $this->receiptPdf->exists($payment->constancia_ruta);

        if (! $hasPdf) {
            return redirect()->route('payments.show', $payment)
                ->with('error', 'No hay comprobante disponible para este pago.');
        }

        $storagePath = preg_replace('#^storage/#', '', $payment->constancia_ruta);

        return Storage::disk('public')->response(
            $storagePath,
            basename($storagePath),
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Crear recordatorio para un pago (RF-PAG-12)
     */
    public function createReminder(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate([
            'mensaje' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'mensaje.required' => 'El mensaje del recordatorio es obligatorio.',
            'mensaje.min' => 'El mensaje debe tener al menos 10 caracteres.',
            'mensaje.max' => 'El mensaje admite hasta 500 caracteres.',
        ]);

        try {
            $this->payments->createReminder($payment, $data['mensaje'], $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('payments.show', $payment)
            ->with('status', 'Recordatorio creado correctamente.');
    }

    /**
     * Marcar recordatorio como atendido
     */
    public function attendReminder(Request $request, Payment $payment, int $reminder): RedirectResponse
    {
        $attended = $this->payments->markReminderAsAttended($reminder);

        if (! $attended) {
            return back()->with('error', 'No se pudo marcar el recordatorio como atendido.');
        }

        return redirect()->route('payments.show', $payment)
            ->with('status', 'Recordatorio marcado como atendido.');
    }

    /**
     * API: Obtener precio contratado de un contrato (RF-PAG-04)
     */
    public function getContractAmount(Request $request, int $contractId)
    {
        $contract = \App\Models\Contract::find($contractId);

        if (! $contract) {
            return response()->json(['error' => 'Contrato no encontrado'], 404);
        }

        return response()->json([
            'precio_contratado' => $contract->precio_contratado,
            'codigo' => $contract->codigo,
            'estado' => $contract->estado,
        ]);
    }
}
