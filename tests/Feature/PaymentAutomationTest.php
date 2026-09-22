<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractPeriod;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests para automatización de pagos y contratos
 * Fase 14: Tests para automatización
 * Valida commands: UpdateOverduePayments, CancelContractsForNonPayment, SendPaymentReminders
 */
class PaymentAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
    }

    /** @test */
    public function command_update_overdue_payments_cambia_pendiente_a_vencido()
    {
        // Crear pago pendiente con fecha vencida
        $payment = Payment::factory()->create([
            'estado' => 'PENDIENTE',
            'fecha_vencimiento' => now()->subDay(),
        ]);

        $this->assertEquals('PENDIENTE', $payment->estado);

        // Ejecutar command
        $this->artisan('payments:update-overdue')
            ->assertExitCode(0);

        // Verificar que cambió a VENCIDO
        $payment->refresh();
        $this->assertEquals('VENCIDO', $payment->estado);
    }

    /** @test */
    public function command_update_overdue_payments_cambia_vencido_a_anulado_despues_de_7_dias()
    {
        // Crear pago vencido hace 8 días
        $payment = Payment::factory()->create([
            'estado' => 'VENCIDO',
            'fecha_vencimiento' => now()->subDays(8),
        ]);

        $this->assertEquals('VENCIDO', $payment->estado);

        // Ejecutar command
        $this->artisan('payments:update-overdue')
            ->assertExitCode(0);

        // Verificar que cambió a ANULADO
        $payment->refresh();
        $this->assertEquals('ANULADO', $payment->estado);
    }

    /** @test */
    public function command_update_overdue_payments_no_afecta_pagos_realizados()
    {
        // Crear pago realizado aunque esté vencido
        $payment = Payment::factory()->create([
            'estado' => 'REALIZADO',
            'fecha_vencimiento' => now()->subDays(10),
            'fecha_pago' => now()->subDays(11),
            'metodo_pago' => 'EFECTIVO',
        ]);

        $this->assertEquals('REALIZADO', $payment->estado);

        // Ejecutar command
        $this->artisan('payments:update-overdue')
            ->assertExitCode(0);

        // Verificar que NO cambió
        $payment->refresh();
        $this->assertEquals('REALIZADO', $payment->estado);
    }

    /** @test */
    public function command_update_overdue_payments_respeta_dry_run()
    {
        // Crear pago pendiente vencido
        $payment = Payment::factory()->create([
            'estado' => 'PENDIENTE',
            'fecha_vencimiento' => now()->subDay(),
        ]);

        // Ejecutar con --dry-run
        $this->artisan('payments:update-overdue', ['--dry-run' => true])
            ->assertExitCode(0);

        // Verificar que NO cambió
        $payment->refresh();
        $this->assertEquals('PENDIENTE', $payment->estado);
    }

    /** @test */
    public function command_cancel_contracts_cancela_contratos_con_pagos_anulados()
    {
        // Crear contrato activo con pago anulado
        $contract = Contract::factory()->active()->create();
        
        $period = ContractPeriod::factory()->create([
            'contrato_id' => $contract->contrato_id,
            'estado_periodo' => 'ACTIVO',
        ]);

        $contract->update(['periodo_actual' => $period->periodo_id]);

        Payment::factory()->create([
            'contrato_id' => $contract->contrato_id,
            'estado' => 'ANULADO',
        ]);

        $this->assertEquals('ACTIVO', $contract->estado);

        // Ejecutar command
        $this->artisan('contracts:cancel-for-non-payment')
            ->assertExitCode(0);

        // Verificar que el contrato fue cancelado
        $contract->refresh();
        $this->assertEquals('CANCELADO', $contract->estado);

        // Verificar que el período fue cancelado
        $period->refresh();
        $this->assertEquals('CANCELADO', $period->estado_periodo);
        $this->assertEquals('AUTOMATICO_FALTA_PAGO', $period->origen_cancelacion);
    }

    /** @test */
    public function command_cancel_contracts_no_afecta_contratos_sin_pagos_anulados()
    {
        // Crear contrato activo con pago pendiente
        $contract = Contract::factory()->active()->create();

        Payment::factory()->create([
            'contrato_id' => $contract->contrato_id,
            'estado' => 'PENDIENTE',
        ]);

        $this->assertEquals('ACTIVO', $contract->estado);

        // Ejecutar command
        $this->artisan('contracts:cancel-for-non-payment')
            ->assertExitCode(0);

        // Verificar que el contrato sigue ACTIVO
        $contract->refresh();
        $this->assertEquals('ACTIVO', $contract->estado);
    }

    /** @test */
    public function command_cancel_contracts_respeta_dry_run()
    {
        // Crear contrato activo con pago anulado
        $contract = Contract::factory()->active()->create();
        
        $period = ContractPeriod::factory()->create([
            'contrato_id' => $contract->contrato_id,
            'estado_periodo' => 'ACTIVO',
        ]);

        $contract->update(['periodo_actual' => $period->periodo_id]);

        Payment::factory()->create([
            'contrato_id' => $contract->contrato_id,
            'estado' => 'ANULADO',
        ]);

        // Ejecutar con --dry-run
        $this->artisan('contracts:cancel-for-non-payment', ['--dry-run' => true])
            ->assertExitCode(0);

        // Verificar que NO cambió
        $contract->refresh();
        $this->assertEquals('ACTIVO', $contract->estado);
    }

    /** @test */
    public function command_send_reminders_identifica_pagos_proximos_a_vencer()
    {
        // Crear pago que vence en 5 días sin recordatorio enviado
        $payment = Payment::factory()->create([
            'estado' => 'PENDIENTE',
            'fecha_vencimiento' => now()->addDays(5),
            'recordatorio_previo_enviado_en' => null,
        ]);

        // Ejecutar command con dry-run (para no enviar correos reales)
        $this->artisan('payments:send-reminders', ['--dry-run' => true])
            ->expectsOutput('MODO DRY-RUN: No se enviarán correos ni se actualizarán registros')
            ->assertExitCode(0);

        // En dry-run no debe marcar como enviado
        $payment->refresh();
        $this->assertNull($payment->recordatorio_previo_enviado_en);
    }

    /** @test */
    public function command_send_reminders_identifica_pagos_vencidos()
    {
        // Crear pago vencido hace 6 días sin recordatorio enviado
        $payment = Payment::factory()->create([
            'estado' => 'VENCIDO',
            'fecha_vencimiento' => now()->subDays(6),
            'recordatorio_vencido_enviado_en' => null,
        ]);

        // Ejecutar command con dry-run
        $this->artisan('payments:send-reminders', ['--dry-run' => true])
            ->expectsOutput('MODO DRY-RUN: No se enviarán correos ni se actualizarán registros')
            ->assertExitCode(0);

        // En dry-run no debe marcar como enviado
        $payment->refresh();
        $this->assertNull($payment->recordatorio_vencido_enviado_en);
    }

    /** @test */
    public function flujo_completo_pendiente_a_vencido_a_anulado_a_contrato_cancelado()
    {
        // 1. Crear contrato activo con pago pendiente
        $contract = Contract::factory()->active()->create();
        
        $period = ContractPeriod::factory()->create([
            'contrato_id' => $contract->contrato_id,
            'estado_periodo' => 'ACTIVO',
        ]);

        $contract->update(['periodo_actual' => $period->periodo_id]);

        $payment = Payment::factory()->create([
            'contrato_id' => $contract->contrato_id,
            'estado' => 'PENDIENTE',
            'fecha_vencimiento' => now()->subDays(10), // Vencido hace 10 días
        ]);

        // 2. Ejecutar update-overdue (debe cambiar a VENCIDO y luego a ANULADO)
        $this->artisan('payments:update-overdue')->assertExitCode(0);
        
        $payment->refresh();
        $this->assertEquals('ANULADO', $payment->estado);

        // 3. Ejecutar cancel-contracts (debe cancelar el contrato)
        $this->artisan('contracts:cancel-for-non-payment')->assertExitCode(0);

        $contract->refresh();
        $period->refresh();
        
        $this->assertEquals('CANCELADO', $contract->estado);
        $this->assertEquals('CANCELADO', $period->estado_periodo);
        $this->assertEquals('AUTOMATICO_FALTA_PAGO', $period->origen_cancelacion);
    }

    /** @test */
    public function pagos_pendientes_no_vencidos_no_son_afectados()
    {
        // Crear pago pendiente futuro
        $payment = Payment::factory()->create([
            'estado' => 'PENDIENTE',
            'fecha_vencimiento' => now()->addDays(15),
        ]);

        // Ejecutar command
        $this->artisan('payments:update-overdue')->assertExitCode(0);

        // Verificar que sigue PENDIENTE
        $payment->refresh();
        $this->assertEquals('PENDIENTE', $payment->estado);
    }

    /** @test */
    public function pagos_vencidos_recientes_no_son_anulados()
    {
        // Crear pago vencido hace solo 3 días (menos de 7)
        $payment = Payment::factory()->create([
            'estado' => 'VENCIDO',
            'fecha_vencimiento' => now()->subDays(3),
        ]);

        // Ejecutar command
        $this->artisan('payments:update-overdue')->assertExitCode(0);

        // Verificar que sigue VENCIDO
        $payment->refresh();
        $this->assertEquals('VENCIDO', $payment->estado);
    }
}
