<?php

namespace Tests\Unit;

use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests para las transiciones de estado de pagos
 * Fase 13: Tests para transiciones de estado de pagos
 * Valida lógica de: PENDIENTE → VENCIDO → ANULADO
 */
class PaymentStateTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
    }

    /** @test */
    public function pago_pendiente_se_identifica_correctamente()
    {
        $payment = Payment::factory()->pending()->create();

        $this->assertEquals('PENDIENTE', $payment->estado);
        $this->assertNull($payment->fecha_pago);
        $this->assertNull($payment->metodo_pago);
    }

    /** @test */
    public function pago_vencido_se_identifica_correctamente()
    {
        $payment = Payment::factory()->overdue()->create();

        $this->assertEquals('VENCIDO', $payment->estado);
        $this->assertTrue($payment->fecha_vencimiento->isPast());
    }

    /** @test */
    public function pago_anulado_se_identifica_correctamente()
    {
        $payment = Payment::factory()->cancelled()->create();

        $this->assertEquals('ANULADO', $payment->estado);
    }

    /** @test */
    public function pago_realizado_tiene_fecha_y_metodo()
    {
        $payment = Payment::factory()->paid()->create();

        $this->assertEquals('REALIZADO', $payment->estado);
        $this->assertNotNull($payment->fecha_pago);
        $this->assertNotNull($payment->metodo_pago);
        $this->assertContains($payment->metodo_pago, ['EFECTIVO', 'TRANSFERENCIA', 'YAPE', 'PLIN', 'TARJETA', 'OTRO']);
    }

    /** @test */
    public function shouldSendUpcomingReminder_identifica_pagos_proximos_a_vencer()
    {
        // Pago que vence en 5 días (dentro del rango de 7 días)
        $paymentDueSoon = Payment::factory()->create([
            'estado' => 'PENDIENTE',
            'fecha_vencimiento' => now()->addDays(5),
            'recordatorio_previo_enviado_en' => null,
        ]);

        // Pago que vence en 10 días (fuera del rango)
        $paymentFarAway = Payment::factory()->create([
            'estado' => 'PENDIENTE',
            'fecha_vencimiento' => now()->addDays(10),
            'recordatorio_previo_enviado_en' => null,
        ]);

        // Pago que ya tiene recordatorio enviado
        $paymentAlreadyNotified = Payment::factory()->create([
            'estado' => 'PENDIENTE',
            'fecha_vencimiento' => now()->addDays(5),
            'recordatorio_previo_enviado_en' => now()->subDay(),
        ]);

        $this->assertTrue($paymentDueSoon->shouldSendUpcomingReminder());
        $this->assertFalse($paymentFarAway->shouldSendUpcomingReminder());
        $this->assertFalse($paymentAlreadyNotified->shouldSendUpcomingReminder());
    }

    /** @test */
    public function shouldSendOverdueReminder_identifica_pagos_vencidos_sin_notificar()
    {
        // Pago vencido hace 6 días (dentro del rango de 5+ días)
        $paymentOverdue = Payment::factory()->create([
            'estado' => 'VENCIDO',
            'fecha_vencimiento' => now()->subDays(6),
            'recordatorio_vencido_enviado_en' => null,
        ]);

        // Pago vencido hace 3 días (fuera del rango)
        $paymentRecentlyOverdue = Payment::factory()->create([
            'estado' => 'VENCIDO',
            'fecha_vencimiento' => now()->subDays(3),
            'recordatorio_vencido_enviado_en' => null,
        ]);

        // Pago vencido ya notificado
        $paymentAlreadyNotified = Payment::factory()->create([
            'estado' => 'VENCIDO',
            'fecha_vencimiento' => now()->subDays(6),
            'recordatorio_vencido_enviado_en' => now()->subDay(),
        ]);

        $this->assertTrue($paymentOverdue->shouldSendOverdueReminder());
        $this->assertFalse($paymentRecentlyOverdue->shouldSendOverdueReminder());
        $this->assertFalse($paymentAlreadyNotified->shouldSendOverdueReminder());
    }

    /** @test */
    public function shouldBeCancelledForNonPayment_identifica_pagos_a_anular()
    {
        // Pago vencido hace 8 días (más de 7 días)
        $paymentToCancel = Payment::factory()->create([
            'estado' => 'VENCIDO',
            'fecha_vencimiento' => now()->subDays(8),
        ]);

        // Pago vencido hace 5 días (menos de 7 días)
        $paymentStillValid = Payment::factory()->create([
            'estado' => 'VENCIDO',
            'fecha_vencimiento' => now()->subDays(5),
        ]);

        // Pago pendiente (no vencido)
        $paymentPending = Payment::factory()->create([
            'estado' => 'PENDIENTE',
            'fecha_vencimiento' => now()->addDays(5),
        ]);

        $this->assertTrue($paymentToCancel->shouldBeCancelledForNonPayment());
        $this->assertFalse($paymentStillValid->shouldBeCancelledForNonPayment());
        $this->assertFalse($paymentPending->shouldBeCancelledForNonPayment());
    }

    /** @test */
    public function markUpcomingReminderSent_actualiza_timestamp_correctamente()
    {
        $payment = Payment::factory()->pending()->create([
            'recordatorio_previo_enviado_en' => null,
        ]);

        $this->assertNull($payment->recordatorio_previo_enviado_en);

        $payment->markUpcomingReminderSent();
        $payment->refresh();

        $this->assertNotNull($payment->recordatorio_previo_enviado_en);
        $this->assertTrue($payment->recordatorio_previo_enviado_en->isToday());
    }

    /** @test */
    public function markOverdueReminderSent_actualiza_timestamp_correctamente()
    {
        $payment = Payment::factory()->overdue()->create([
            'recordatorio_vencido_enviado_en' => null,
        ]);

        $this->assertNull($payment->recordatorio_vencido_enviado_en);

        $payment->markOverdueReminderSent();
        $payment->refresh();

        $this->assertNotNull($payment->recordatorio_vencido_enviado_en);
        $this->assertTrue($payment->recordatorio_vencido_enviado_en->isToday());
    }

    /** @test */
    public function markCancellationNotificationSent_actualiza_timestamp_correctamente()
    {
        $payment = Payment::factory()->cancelled()->create([
            'notificacion_anulacion_enviada_en' => null,
        ]);

        $this->assertNull($payment->notificacion_anulacion_enviada_en);

        $payment->markCancellationNotificationSent();
        $payment->refresh();

        $this->assertNotNull($payment->notificacion_anulacion_enviada_en);
        $this->assertTrue($payment->notificacion_anulacion_enviada_en->isToday());
    }

    /** @test */
    public function timestamps_de_notificaciones_evitan_envios_duplicados()
    {
        $payment = Payment::factory()->create([
            'estado' => 'PENDIENTE',
            'fecha_vencimiento' => now()->addDays(5),
            'recordatorio_previo_enviado_en' => now()->subHour(),
        ]);

        // No debe enviar porque ya tiene el timestamp
        $this->assertFalse($payment->shouldSendUpcomingReminder());
    }

    /** @test */
    public function pago_puede_cambiar_de_pendiente_a_realizado()
    {
        $payment = Payment::factory()->pending()->create();

        $payment->update([
            'estado' => 'REALIZADO',
            'fecha_pago' => now(),
            'metodo_pago' => 'EFECTIVO',
        ]);

        $this->assertEquals('REALIZADO', $payment->estado);
        $this->assertNotNull($payment->fecha_pago);
        $this->assertEquals('EFECTIVO', $payment->metodo_pago);
    }

    /** @test */
    public function periodo_servicio_tiene_formato_correcto()
    {
        $payment = Payment::factory()->create([
            'periodo_servicio' => '2026-09',
        ]);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', $payment->periodo_servicio);
    }
}
