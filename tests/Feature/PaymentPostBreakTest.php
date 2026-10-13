<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Role;
use App\Models\ServicePlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pruebas Funcionales POST para ROMPER el sistema de Pagos
 * Basado en RF-PAG: Gestión de pagos (Sección 7.7)
 * 
 * Objetivo: Probar operaciones HTTP POST/PUT/DELETE con datos inválidos
 * para encontrar vulnerabilidades y validar que el sistema rechace correctamente
 */
class PaymentPostBreakTest extends TestCase
{
    use RefreshDatabase;

    private static $contractCounter = 1;

    private function actor(string $name = Role::MANAGER): User
    {
        $role = Role::firstOrCreate(['nombre' => $name], ['estado' => 'ACTIVO']);
        return User::factory()->create(['rol_id' => $role->getKey()]);
    }

    private function activeContract(): Contract
    {
        $creator = $this->actor();
        
        $client = Client::create([
            'tipo_documento' => 'DNI',
            'numero_documento' => sprintf('1%07d', 2000000 + self::$contractCounter),
            'nombres_razon_social' => 'Juan',
            'apellidos' => 'Pérez',
            'direccion' => 'Av. Test 123',
            'telefono' => '999888777',
            'correo' => sprintf('test%d@break.com', self::$contractCounter),
            'estado' => 'ACTIVO',
            'creado_por' => $creator->getKey(),
        ]);

        $plan = ServicePlan::create([
            'numero_plan' => sprintf('PLANB-%03d', self::$contractCounter),
            'nombre' => 'Plan Break',
            'tipo_servicio' => 'Internet',
            'velocidad_mbps' => 50,
            'precio_mensual' => 99.90,
            'estado' => 'ACTIVO',
            'creado_por' => $creator->getKey(),
        ]);

        $zone = DB::table('zonas_cobertura')->insertGetId([
            'codigo' => sprintf('ZONAB-%03d', self::$contractCounter),
            'nombre' => 'Zona Break',
            'distrito' => 'Trujillo',
            'creado_por' => $creator->getKey(),
        ]);

        $employee = DB::table('empleados')->insertGetId([
            'tipo_documento' => 'DNI',
            'numero_documento' => sprintf('97%06d', self::$contractCounter),
            'nombres' => 'Técnico',
            'apellidos' => 'Break',
            'cargo' => 'Instalador',
        ]);

        $contract = Contract::create([
            'codigo' => sprintf('CON-BRK-%03d', self::$contractCounter),
            'cliente_id' => $client->getKey(),
            'plan_id' => $plan->getKey(),
            'zona_id' => $zone,
            'responsable_id' => $employee,
            'estado' => 'ACTIVO',
            'fecha_inicio' => today(),
            'fecha_fin' => today()->addMonths(12),
            'duracion_meses' => 12,
            'precio_contratado' => 99.90,
            'direccion_servicio' => 'Av. Test 123',
            'creado_por' => $creator->getKey(),
        ]);

        self::$contractCounter++;
        
        return $contract;
    }

    /** @test RF-PAG-16: POST crear pago con monto negativo debe fallar */
    public function post_payment_rechaza_monto_negativo(): void
    {
        $actor = $this->actor();
        $contract = $this->activeContract();

        $response = $this->signIn($actor)->post('/payments', [
            'contrato_id' => $contract->getKey(),
            'monto' => -100.00, // ⚠️ INTENTANDO ROMPER
            'periodo_servicio' => '2026-10',
            'fecha_vencimiento' => now()->addDays(5)->format('Y-m-d'),
        ]);

        // El sistema DEBE rechazar con error de validación
        $response->assertSessionHasErrors('monto');
    }

    /** @test RF-PAG-16: POST crear pago con monto cero debe fallar */
    public function post_payment_rechaza_monto_cero(): void
    {
        $actor = $this->actor();
        $contract = $this->activeContract();

        $response = $this->signIn($actor)->post('/payments', [
            'contrato_id' => $contract->getKey(),
            'monto' => 0.00, // ⚠️ INTENTANDO ROMPER
            'periodo_servicio' => '2026-10',
            'fecha_vencimiento' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('monto');
    }

    /** @test RF-PAG-03: POST con periodo servicio inválido debe fallar */
    public function post_payment_rechaza_periodo_invalido(): void
    {
        $actor = $this->actor();
        $contract = $this->activeContract();

        $invalidPeriods = [
            'ABC-123',
            '2026/10',
            '202610',
            '26-10',
            '2026-13', // Mes inválido
            '2026-00', // Mes inválido
        ];

        foreach ($invalidPeriods as $invalidPeriod) {
            $response = $this->signIn($actor)->post('/payments', [
                'contrato_id' => $contract->getKey(),
                'monto' => 100.00,
                'periodo_servicio' => $invalidPeriod, // ⚠️ INTENTANDO ROMPER
                'fecha_vencimiento' => now()->addDays(5)->format('Y-m-d'),
            ]);

            $response->assertSessionHasErrors('periodo_servicio');
        }
    }

    /** @test RF-PAG-02: POST con contrato inexistente debe fallar */
    public function post_payment_rechaza_contrato_inexistente(): void
    {
        $actor = $this->actor();

        $response = $this->signIn($actor)->post('/payments', [
            'contrato_id' => 99999, // ⚠️ ID que no existe
            'monto' => 100.00,
            'periodo_servicio' => '2026-10',
            'fecha_vencimiento' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('contrato_id');
    }

    /** @test RF-PAG-04: POST con fecha vencimiento pasada debe fallar */
    public function post_payment_rechaza_fecha_vencimiento_pasada(): void
    {
        $actor = $this->actor();
        $contract = $this->activeContract();

        $response = $this->signIn($actor)->post('/payments', [
            'contrato_id' => $contract->getKey(),
            'monto' => 100.00,
            'periodo_servicio' => '2026-10',
            'fecha_vencimiento' => now()->subDays(10)->format('Y-m-d'), // ⚠️ Fecha pasada
        ]);

        $response->assertSessionHasErrors('fecha_vencimiento');
    }

    /** @test RF-PAG-16: POST sin campos obligatorios debe fallar */
    public function post_payment_rechaza_campos_faltantes(): void
    {
        $actor = $this->actor();

        // Sin contrato_id
        $response = $this->signIn($actor)->post('/payments', [
            'monto' => 100.00,
            'periodo_servicio' => '2026-10',
            'fecha_vencimiento' => now()->addDays(5)->format('Y-m-d'),
        ]);
        $response->assertSessionHasErrors('contrato_id');

        // Sin monto
        $contract = $this->activeContract();
        $response = $this->signIn($actor)->post('/payments', [
            'contrato_id' => $contract->getKey(),
            'periodo_servicio' => '2026-10',
            'fecha_vencimiento' => now()->addDays(5)->format('Y-m-d'),
        ]);
        $response->assertSessionHasErrors('monto');

        // Sin periodo_servicio
        $response = $this->signIn($actor)->post('/payments', [
            'contrato_id' => $contract->getKey(),
            'monto' => 100.00,
            'fecha_vencimiento' => now()->addDays(5)->format('Y-m-d'),
        ]);
        $response->assertSessionHasErrors('periodo_servicio');

        // Sin fecha_vencimiento
        $response = $this->signIn($actor)->post('/payments', [
            'contrato_id' => $contract->getKey(),
            'monto' => 100.00,
            'periodo_servicio' => '2026-10',
        ]);
        $response->assertSessionHasErrors('fecha_vencimiento');
    }

    /** @test RF-PAG-17: POST confirmar pago sin fecha_pago debe fallar */
    public function post_complete_payment_rechaza_sin_fecha(): void
    {
        $actor = $this->actor();
        $contract = $this->activeContract();
        
        $payment = Payment::create([
            'contrato_id' => $contract->getKey(),
            'codigo' => 'PAG-BREAK-001',
            'monto' => 99.90,
            'periodo_servicio' => '2026-10',
            'fecha_vencimiento' => now()->addDays(5),
            'estado' => 'PENDIENTE',
            'registrado_por' => $actor->getKey(),
        ]);

        $response = $this->signIn($actor)->patch('/payments/' . $payment->getKey() . '/complete', [
            'metodo_pago' => 'EFECTIVO',
            // ⚠️ Falta fecha_pago
        ]);

        $response->assertSessionHasErrors('fecha_pago');
    }

    /** @test RF-PAG-17: POST confirmar pago sin método debe fallar */
    public function post_complete_payment_rechaza_sin_metodo(): void
    {
        $actor = $this->actor();
        $contract = $this->activeContract();
        
        $payment = Payment::create([
            'contrato_id' => $contract->getKey(),
            'codigo' => 'PAG-BREAK-002',
            'monto' => 99.90,
            'periodo_servicio' => '2026-10',
            'fecha_vencimiento' => now()->addDays(5),
            'estado' => 'PENDIENTE',
            'registrado_por' => $actor->getKey(),
        ]);

        $response = $this->signIn($actor)->patch('/payments/' . $payment->getKey() . '/complete', [
            'fecha_pago' => now()->format('Y-m-d'),
            // ⚠️ Falta metodo_pago
        ]);

        $response->assertSessionHasErrors('metodo_pago');
    }

    /** @test RF-PAG-17: POST confirmar con método inválido debe fallar */
    public function post_complete_payment_rechaza_metodo_invalido(): void
    {
        $actor = $this->actor();
        $contract = $this->activeContract();
        
        $payment = Payment::create([
            'contrato_id' => $contract->getKey(),
            'codigo' => 'PAG-BREAK-003',
            'monto' => 99.90,
            'periodo_servicio' => '2026-10',
            'fecha_vencimiento' => now()->addDays(5),
            'estado' => 'PENDIENTE',
            'registrado_por' => $actor->getKey(),
        ]);

        $response = $this->signIn($actor)->patch('/payments/' . $payment->getKey() . '/complete', [
            'fecha_pago' => now()->format('Y-m-d'),
            'metodo_pago' => 'BITCOIN', // ⚠️ INTENTANDO ROMPER: método no válido
        ]);

        $response->assertSessionHasErrors('metodo_pago');
    }

    /** @test RF-PAG-21: PUT editar pago REALIZADO debe fallar */
    public function put_payment_rechaza_modificar_realizado(): void
    {
        $actor = $this->actor();
        $contract = $this->activeContract();
        
        $payment = Payment::create([
            'contrato_id' => $contract->getKey(),
            'codigo' => 'PAG-BREAK-004',
            'monto' => 99.90,
            'periodo_servicio' => '2026-10',
            'fecha_vencimiento' => now()->addDays(5),
            'estado' => 'REALIZADO', // Ya está pagado
            'fecha_pago' => now(),
            'metodo_pago' => 'EFECTIVO',
            'registrado_por' => $actor->getKey(),
        ]);

        // ⚠️ INTENTANDO ROMPER: modificar pago realizado
        $response = $this->signIn($actor)->put('/payments/' . $payment->getKey(), [
            'contrato_id' => $contract->getKey(),
            'monto' => 999.99, // Intentando cambiar monto
            'periodo_servicio' => '2026-11',
            'fecha_vencimiento' => now()->addDays(10)->format('Y-m-d'),
        ]);

        // Debe redirigir con error
        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Verificar que NO se modificó
        $this->assertDatabaseHas('pagos', [
            'pago_id' => $payment->getKey(),
            'monto' => 99.90, // Monto original
        ]);
    }

    /** @test RF-PAG-21: PUT editar pago ANULADO debe fallar */
    public function put_payment_rechaza_modificar_anulado(): void
    {
        $actor = $this->actor();
        $contract = $this->activeContract();
        
        $payment = Payment::create([
            'contrato_id' => $contract->getKey(),
            'codigo' => 'PAG-BREAK-005',
            'monto' => 99.90,
            'periodo_servicio' => '2026-10',
            'fecha_vencimiento' => now()->addDays(5),
            'estado' => 'ANULADO', // Anulado
            'registrado_por' => $actor->getKey(),
        ]);

        // ⚠️ INTENTANDO ROMPER: modificar pago anulado
        $response = $this->signIn($actor)->put('/payments/' . $payment->getKey(), [
            'contrato_id' => $contract->getKey(),
            'monto' => 150.00,
            'periodo_servicio' => '2026-11',
            'fecha_vencimiento' => now()->addDays(10)->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    /** @test RF-ACC-14: Usuario sin permisos no debe acceder */
    public function usuario_sin_permisos_no_puede_crear_pagos(): void
    {
        $technician = $this->actor(Role::TECHNICIAN);
        $contract = $this->activeContract();

        $response = $this->signIn($technician)->post('/payments', [
            'contrato_id' => $contract->getKey(),
            'monto' => 100.00,
            'periodo_servicio' => '2026-10',
            'fecha_vencimiento' => now()->addDays(5)->format('Y-m-d'),
        ]);

        // Debe ser rechazado (403 Forbidden)
        $response->assertForbidden();
    }

    /** @test RF-ACC-01: Usuario no autenticado no debe acceder */
    public function invitado_no_puede_crear_pagos(): void
    {
        $contract = $this->activeContract();

        $response = $this->post('/payments', [
            'contrato_id' => $contract->getKey(),
            'monto' => 100.00,
            'periodo_servicio' => '2026-10',
            'fecha_vencimiento' => now()->addDays(5)->format('Y-m-d'),
        ]);

        // Debe redirigir al login
        $response->assertRedirect('/login');
    }

    /** @test RF-PAG-16: POST con SQL Injection debe ser bloqueado */
    public function post_payment_bloquea_sql_injection(): void
    {
        $actor = $this->actor();
        $contract = $this->activeContract();

        // ⚠️ INTENTANDO ROMPER: SQL Injection
        $response = $this->signIn($actor)->post('/payments', [
            'contrato_id' => $contract->getKey(),
            'monto' => "100.00'; DROP TABLE pagos; --",
            'periodo_servicio' => '2026-10',
            'fecha_vencimiento' => now()->addDays(5)->format('Y-m-d'),
        ]);

        // Debe rechazar con error de validación
        $response->assertSessionHasErrors();

        // Verificar que la tabla sigue existiendo
        $this->assertTrue(
            DB::getSchemaBuilder()->hasTable('pagos'),
            'La tabla pagos fue eliminada - SQL Injection exitoso!'
        );
    }

    /** @test RF-PAG-16: POST con XSS debe ser sanitizado */
    public function post_payment_sanitiza_xss_en_observaciones(): void
    {
        $actor = $this->actor();
        $contract = $this->activeContract();

        $xssPayload = '<script>alert("XSS")</script>';

        $response = $this->signIn($actor)->post('/payments', [
            'contrato_id' => $contract->getKey(),
            'monto' => 100.00,
            'periodo_servicio' => '2026-10',
            'fecha_vencimiento' => now()->addDays(5)->format('Y-m-d'),
            'observaciones' => $xssPayload, // ⚠️ INTENTANDO ROMPER
        ]);

        // Si el pago fue creado, verificar que el XSS fue sanitizado
        if ($response->isRedirection()) {
            $payment = Payment::latest()->first();
            
            if ($payment && $payment->observaciones) {
                $this->assertStringNotContainsString(
                    '<script>',
                    $payment->observaciones,
                    'El sistema permitió XSS en observaciones'
                );
            }
        }
    }

    /** @test RF-PAG-16: POST masivo debe validar cada registro */
    public function post_batch_payments_valida_cada_registro(): void
    {
        $actor = $this->actor();
        $contract = $this->activeContract();

        $batchData = [
            // Registro válido
            [
                'contrato_id' => $contract->getKey(),
                'monto' => 100.00,
                'periodo_servicio' => '2026-10',
                'fecha_vencimiento' => now()->addDays(5)->format('Y-m-d'),
            ],
            // ⚠️ Registro inválido: monto negativo
            [
                'contrato_id' => $contract->getKey(),
                'monto' => -50.00,
                'periodo_servicio' => '2026-11',
                'fecha_vencimiento' => now()->addDays(35)->format('Y-m-d'),
            ],
            // ⚠️ Registro inválido: periodo inválido
            [
                'contrato_id' => $contract->getKey(),
                'monto' => 150.00,
                'periodo_servicio' => 'INVALID',
                'fecha_vencimiento' => now()->addDays(65)->format('Y-m-d'),
            ],
        ];

        $created = 0;
        $failed = 0;

        foreach ($batchData as $data) {
            $response = $this->signIn($actor)->post('/payments', $data);
            
            if ($response->isRedirection() && !$response->getSession()->has('errors')) {
                $created++;
            } else {
                $failed++;
            }
        }

        // Debe crear solo el válido y rechazar los inválidos
        $this->assertEquals(1, $created, 'Debe crear solo registros válidos');
        $this->assertEquals(2, $failed, 'Debe rechazar registros inválidos');
    }

    /** @test RF-PAG-17: DELETE pago REALIZADO debe fallar */
    public function delete_payment_rechaza_pago_realizado(): void
    {
        $actor = $this->actor();
        $contract = $this->activeContract();
        
        $payment = Payment::create([
            'contrato_id' => $contract->getKey(),
            'codigo' => 'PAG-BREAK-006',
            'monto' => 99.90,
            'periodo_servicio' => '2026-10',
            'fecha_vencimiento' => now()->addDays(5),
            'estado' => 'REALIZADO',
            'fecha_pago' => now(),
            'metodo_pago' => 'EFECTIVO',
            'registrado_por' => $actor->getKey(),
        ]);

        // ⚠️ INTENTANDO ROMPER: eliminar pago realizado
        $response = $this->signIn($actor)->delete('/payments/' . $payment->getKey());

        // Debe ser rechazado
        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Verificar que NO fue eliminado
        $this->assertDatabaseHas('pagos', [
            'pago_id' => $payment->getKey(),
        ]);
    }

    /** @test RF-PAG-22: POST anular con observaciones vacías debe fallar */
    public function post_cancel_payment_requiere_observaciones(): void
    {
        $actor = $this->actor();
        $contract = $this->activeContract();
        
        $payment = Payment::create([
            'contrato_id' => $contract->getKey(),
            'codigo' => 'PAG-BREAK-007',
            'monto' => 99.90,
            'periodo_servicio' => '2026-10',
            'fecha_vencimiento' => now()->addDays(5),
            'estado' => 'PENDIENTE',
            'registrado_por' => $actor->getKey(),
        ]);

        $response = $this->signIn($actor)->patch('/payments/' . $payment->getKey() . '/cancel', [
            'observaciones' => '', // ⚠️ Vacío
        ]);

        $response->assertSessionHasErrors('observaciones');
    }
}
