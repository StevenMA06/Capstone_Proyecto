<?php

// Solo se carga desde fixture.php después de comprobar entorno y base exclusivos.
use App\Models\Client;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return function (string $command, string $arg, User $actor, array $clientData, array $planData): array {
    $snapshot = function (int $id): array {
        $contract = DB::table('contratos')->where('contrato_id', $id)->first();
        if (! $contract) throw new RuntimeException('Contrato de prueba no encontrado.');
        $request = DB::table('solicitudes_cancelacion_contrato')->where('contrato_id', $id)->latest('solicitud_cancelacion_id')->first();
        return [
            'contract' => $contract,
            'code_count' => DB::table('contratos')->where('codigo', $contract->codigo)->count(),
            'order' => DB::table('pedidos_instalacion')->where('contrato_id', $id)->first(),
            'client_state' => Client::findOrFail($contract->cliente_id)->estado,
            'cancellation' => $request,
            'evidence_exists' => $request && Storage::disk('local')->exists($request->evidencia_ruta),
            'signed_pdf_exists' => $contract->pdf_contrato_ruta && Storage::disk('local')->exists($contract->pdf_contrato_ruta),
        ];
    };
    if ($command === 'commercial-contract-state') return $snapshot((int) $arg);
    if ($command === 'commercial-complete-order') {
        // Prerrequisito del CP de contratos: no certifica RF-INS ni Google Drive.
        if (DB::table('pedidos_instalacion')->where('contrato_id', (int) $arg)->update(['estado' => 'COMPLETADO']) !== 1) {
            throw new RuntimeException('Se esperaba exactamente un pedido asociado.');
        }
        return $snapshot((int) $arg);
    }
    if ($command === 'commercial-payment-state') {
        $payment = Payment::findOrFail((int) $arg);
        return ['payment' => $payment->toArray(), 'receipt_exists' => $payment->constancia_ruta && app(App\Services\PaymentReceiptPdf::class)->exists($payment->constancia_ruta)];
    }
    if ($command === 'commercial-payment-count') return ['count' => Payment::where('contrato_id', (int) $arg)->count()];
    if ($command === 'commercial-overdue') {
        try {
            $code = Artisan::call(App\Console\Commands\UpdateOverduePayments::class);
            return ['ok' => $code === 0, 'command' => 'payments:update-overdue', 'output' => Artisan::output(), 'today' => now('America/Lima')->toDateString()];
        } catch (Throwable $error) {
            // Es resultado de la funcionalidad bajo prueba; conservarlo sin fingir actualización.
            return ['ok' => false, 'command' => 'payments:update-overdue', 'error' => $error->getMessage(), 'file' => basename($error->getFile()), 'line' => $error->getLine()];
        }
    }
    if ($command === 'commercial-reviewer') {
        $role = Role::where('nombre', Role::MANAGER)->firstOrFail();
        $reviewer = User::updateOrCreate(['nombre_usuario' => 'qa_e2e_revisor'], [
            'rol_id' => $role->getKey(), 'correo' => 'revisor.e2e@example.test',
            'password_hash' => 'PruebasLunnys2026', 'estado' => 'ACTIVO', 'empleado_id' => null,
        ]);
        $reviewer->roles()->sync([$role->getKey()]);
        return ['usuario_id' => $reviewer->getKey(), 'nombre_usuario' => $reviewer->nombre_usuario];
    }
    if (! in_array($command, ['commercial-data', 'commercial-contract', 'commercial-payment-data'], true)) {
        throw new RuntimeException('Operación comercial E2E desconocida.');
    }
    $client = Client::create($clientData + ['creado_por' => $actor->getKey(), 'estado' => 'PENDIENTE']);
    $plan = app(App\Services\PlanService::class)->create($planData, $actor);
    $zoneId = DB::table('zonas_cobertura')->where('codigo', 'QA-E2E')->value('zona_id');
    DB::table('plan_zona_cobertura')->insert(['plan_id' => $plan->getKey(), 'zona_id' => $zoneId, 'estado' => 'ACTIVO']);
    $data = [
        'cliente_id' => $client->getKey(), 'plan_id' => $plan->getKey(), 'zona_id' => $zoneId,
        'responsable_id' => Employee::where('numero_documento', 'QA-E2E-VENTAS')->value('empleado_id'),
        'fecha_inicio' => now('America/Lima')->toDateString(), 'duracion_meses' => 12,
    ];
    if ($command === 'commercial-data') return $data;
    $id = app(App\Services\ContractService::class)->create($data, $actor->getKey());
    if ($command === 'commercial-contract') return $snapshot($id);
    // El contrato activo es un prerrequisito de pagos, no una activación certificada por este fixture.
    DB::table('contratos')->where('contrato_id', $id)->update(['estado' => 'ACTIVO']);
    $makePayment = function (int $daysAgo, string $state = 'PENDIENTE') use ($id, $actor) {
        return Payment::create([
            'contrato_id' => $id, 'codigo' => 'QA-PAG-'.bin2hex(random_bytes(8)),
            'periodo_servicio' => now('America/Lima')->format('Y-m'),
            'fecha_vencimiento' => now('America/Lima')->subDays($daysAgo)->toDateString(),
            'monto' => '79.90', 'estado' => $state, 'registrado_por' => $actor->getKey(),
            'fecha_pago' => $state === 'REALIZADO' ? now('America/Lima') : null,
            'metodo_pago' => $state === 'REALIZADO' ? 'EFECTIVO' : null,
        ])->toArray();
    };
    return [
        'contract' => $snapshot($id)['contract'], 'today' => now('America/Lima')->toDateString(),
        'period' => now('America/Lima')->format('Y-m'),
        'overdue' => $makePayment(4, 'VENCIDO'), 'recent' => $makePayment(1), 'paid' => $makePayment(4, 'REALIZADO'),
        'missing_operation' => $makePayment(4, 'VENCIDO'),
    ];
};
