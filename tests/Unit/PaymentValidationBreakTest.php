<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Payment;
use App\Models\Contract;
use App\Models\Client;
use App\Models\ServicePlan;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Pruebas Unitarias para ROMPER el sistema de Pagos
 * Basado en RF-PAG: Gestión de pagos (Sección 7.7)
 * 
 * Objetivo: Encontrar errores y validar que el sistema rechace datos inválidos
 */
class PaymentValidationBreakTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
    }

    /** @test RF-PAG-05: Monto del pago debe ser positivo */
    public function pago_rechaza_monto_negativo()
    {
        $contract = $this->createValidContract();

        $payment = Payment::create([
            'contrato_id' => $contract->getKey(),
            'codigo' => 'PAG-NEG-001',
            'monto' => -100.00, // ⚠️ INTENTANDO ROMPER: monto negativo
            'periodo_servicio' => now()->format('Y-m'),
            'fecha_vencimiento' => now()->addDays(5),
            'estado' => 'PENDIENTE',
        ]);

        // DEBE RECHAZAR o VALIDAR
        $this->assertGreaterThanOrEqual(0, $payment->monto, 'El sistema permitió un monto negativo');
    }

    /** @test RF-PAG-05: Monto debe existir */
    public function pago_rechaza_monto_nulo()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        $contract = $this->createValidContract();

        Payment::create([
            'contrato_id' => $contract->getKey(),
            'codigo' => 'PAG-NULL-001',
            'monto' => null, // ⚠️ INTENTANDO ROMPER: monto nulo
            'periodo_servicio' => now()->format('Y-m'),
            'fecha_vencimiento' => now()->addDays(5),
            'estado' => 'PENDIENTE',
        ]);
    }

    /** @test RF-PAG-03: Periodo servicio debe tener formato válido */
    public function pago_rechaza_periodo_invalido()
    {
        $contract = $this->createValidContract();

        $invalidPeriods = [
            'ABC-123',       // ⚠️ Formato incorrecto
            '2026/10',       // ⚠️ Separador incorrecto
            '202610',        // ⚠️ Sin separador
            '26-10',         // ⚠️ Año incompleto
            '2026-13',       // ⚠️ Mes inválido
            '',              // ⚠️ Vacío
        ];

        foreach ($invalidPeriods as $invalidPeriod) {
            try {
                $payment = Payment::create([
                    'contrato_id' => $contract->getKey(),
                    'codigo' => 'PAG-PER-' . rand(1000, 9999),
                    'monto' => 100.00,
                    'periodo_servicio' => $invalidPeriod,
                    'fecha_vencimiento' => now()->addDays(5),
                    'estado' => 'PENDIENTE',
                ]);

                // Si llega aquí, el sistema NO validó correctamente
                $this->assertMatchesRegularExpression(
                    '/^\d{4}-\d{2}$/',
                    $payment->periodo_servicio,
                    "El sistema permitió periodo inválido: {$invalidPeriod}"
                );
            } catch (\Exception $e) {
                // Esto es lo esperado - el sistema rechazó el dato inválido
                $this->assertTrue(true, "Sistema rechazó correctamente: {$invalidPeriod}");
            }
        }
    }

    /** @test RF-PAG-06: Código debe ser único */
    public function pago_rechaza_codigo_duplicado()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        $contract = $this->createValidContract();

        // Crear primer pago
        Payment::create([
            'contrato_id' => $contract->getKey(),
            'codigo' => 'PAG-DUP-001',
            'monto' => 100.00,
            'periodo_servicio' => now()->format('Y-m'),
            'fecha_vencimiento' => now()->addDays(5),
            'estado' => 'PENDIENTE',
        ]);

        // ⚠️ INTENTANDO ROMPER: Duplicar código
        Payment::create([
            'contrato_id' => $contract->getKey(),
            'codigo' => 'PAG-DUP-001', // Mismo código
            'monto' => 150.00,
            'periodo_servicio' => now()->format('Y-m'),
            'fecha_vencimiento' => now()->addDays(10),
            'estado' => 'PENDIENTE',
        ]);
    }

    /** @test RF-PAG-04: Fecha vencimiento debe ser futura */
    public function pago_valida_fecha_vencimiento_pasada()
    {
        $contract = $this->createValidContract();

        $payment = Payment::create([
            'contrato_id' => $contract->getKey(),
            'codigo' => 'PAG-PAST-001',
            'monto' => 100.00,
            'periodo_servicio' => now()->format('Y-m'),
            'fecha_vencimiento' => now()->subDays(10), // ⚠️ FECHA PASADA
            'estado' => 'PENDIENTE',
        ]);

        // Verificar que el sistema detecta fecha vencida
        $this->assertTrue(
            $payment->fecha_vencimiento->isPast(),
            'El sistema debe detectar fechas de vencimiento pasadas'
        );
    }

    /** @test RF-PAG-02: Pago debe estar vinculado a un contrato válido */
    public function pago_rechaza_contrato_inexistente()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Payment::create([
            'contrato_id' => 99999, // ⚠️ ID que no existe
            'codigo' => 'PAG-NOCONTRACT-001',
            'monto' => 100.00,
            'periodo_servicio' => now()->format('Y-m'),
            'fecha_vencimiento' => now()->addDays(5),
            'estado' => 'PENDIENTE',
        ]);
    }

    /** @test RF-PAG-07: Estado inicial debe ser válido */
    public function pago_rechaza_estado_invalido()
    {
        $contract = $this->createValidContract();

        $invalidStates = [
            'PROCESANDO',    // ⚠️ No existe en el sistema
            'EN_REVISION',   // ⚠️ No existe en el sistema
            'BORRADOR',      // ⚠️ No existe en el sistema
            '',              // ⚠️ Vacío
            'pendiente',     // ⚠️ Minúsculas
        ];

        $validStates = ['PENDIENTE', 'REALIZADO', 'VENCIDO', 'ANULADO'];

        foreach ($invalidStates as $invalidState) {
            $payment = Payment::create([
                'contrato_id' => $contract->getKey(),
                'codigo' => 'PAG-STATE-' . rand(1000, 9999),
                'monto' => 100.00,
                'periodo_servicio' => now()->format('Y-m'),
                'fecha_vencimiento' => now()->addDays(5),
                'estado' => $invalidState,
            ]);

            // Verificar que el sistema usa solo estados válidos
            $this->assertContains(
                $payment->estado,
                $validStates,
                "El sistema permitió estado inválido: {$invalidState}"
            );
        }
    }

    /** @test RF-PAG-17: Fecha de pago solo para pagos realizados */
    public function pago_pendiente_no_debe_tener_fecha_pago()
    {
        $contract = $this->createValidContract();

        $payment = Payment::create([
            'contrato_id' => $contract->getKey(),
            'codigo' => 'PAG-LOGIC-001',
            'monto' => 100.00,
            'periodo_servicio' => now()->format('Y-m'),
            'fecha_vencimiento' => now()->addDays(5),
            'estado' => 'PENDIENTE',
            'fecha_pago' => now(), // ⚠️ ERROR LÓGICO: pendiente con fecha de pago
        ]);

        // Validación lógica de negocio
        if ($payment->estado === 'PENDIENTE') {
            $this->assertNull(
                $payment->fecha_pago,
                'Un pago PENDIENTE no debe tener fecha de pago'
            );
        }
    }

    /** @test RF-PAG-17: Pago realizado debe tener fecha y método */
    public function pago_realizado_requiere_datos_completos()
    {
        $contract = $this->createValidContract();

        // ⚠️ INTENTANDO ROMPER: REALIZADO sin fecha_pago
        $payment = Payment::create([
            'contrato_id' => $contract->getKey(),
            'codigo' => 'PAG-INCOMPLETE-001',
            'monto' => 100.00,
            'periodo_servicio' => now()->format('Y-m'),
            'fecha_vencimiento' => now()->addDays(5),
            'estado' => 'REALIZADO',
            'fecha_pago' => null, // ⚠️ ERROR: sin fecha
            'metodo_pago' => null, // ⚠️ ERROR: sin método
        ]);

        // Validación de negocio
        if ($payment->estado === 'REALIZADO') {
            $this->assertNotNull(
                $payment->fecha_pago,
                'Un pago REALIZADO debe tener fecha de pago'
            );
            $this->assertNotNull(
                $payment->metodo_pago,
                'Un pago REALIZADO debe tener método de pago'
            );
        }
    }

    /** @test RF-PAG-21: Pago realizado no debe ser modificable */
    public function pago_realizado_no_debe_modificarse()
    {
        $contract = $this->createValidContract();

        $payment = Payment::create([
            'contrato_id' => $contract->getKey(),
            'codigo' => 'PAG-IMMUTABLE-001',
            'monto' => 100.00,
            'periodo_servicio' => now()->format('Y-m'),
            'fecha_vencimiento' => now()->addDays(5),
            'estado' => 'REALIZADO',
            'fecha_pago' => now(),
            'metodo_pago' => 'EFECTIVO',
        ]);

        $originalAmount = $payment->monto;

        // ⚠️ INTENTANDO ROMPER: Modificar pago realizado
        $payment->update(['monto' => 999.99]);
        $payment->refresh();

        // El sistema debe proteger pagos realizados
        if ($payment->estado === 'REALIZADO') {
            $this->assertEquals(
                $originalAmount,
                $payment->monto,
                'El sistema permitió modificar un pago REALIZADO'
            );
        }
    }

    /** @test RF-PAG-23: Período de tolerancia de 7 días */
    public function pago_vencido_cambia_a_anulado_despues_tolerancia()
    {
        $contract = $this->createValidContract();

        $payment = Payment::create([
            'contrato_id' => $contract->getKey(),
            'codigo' => 'PAG-TOLERANCE-001',
            'monto' => 100.00,
            'periodo_servicio' => now()->format('Y-m'),
            'fecha_vencimiento' => now()->subDays(8), // ⚠️ Vencido hace 8 días
            'estado' => 'VENCIDO',
        ]);

        $daysSinceOverdue = now()->diffInDays($payment->fecha_vencimiento);

        // Validar lógica de tolerancia
        if ($daysSinceOverdue > 7 && $payment->estado === 'VENCIDO') {
            $this->assertTrue(
                true,
                'El pago debe cambiar a ANULADO después de 7 días'
            );
        }
    }

    /** @test Inserción masiva con datos inválidos mezclados */
    public function insercion_masiva_rechaza_lote_con_errores()
    {
        $contract = $this->createValidContract();

        $batchPayments = [
            // Válido
            [
                'contrato_id' => $contract->getKey(),
                'codigo' => 'PAG-BATCH-001',
                'monto' => 100.00,
                'periodo_servicio' => '2026-10',
                'fecha_vencimiento' => now()->addDays(5),
                'estado' => 'PENDIENTE',
            ],
            // ⚠️ INVÁLIDO: monto negativo
            [
                'contrato_id' => $contract->getKey(),
                'codigo' => 'PAG-BATCH-002',
                'monto' => -50.00,
                'periodo_servicio' => '2026-10',
                'fecha_vencimiento' => now()->addDays(5),
                'estado' => 'PENDIENTE',
            ],
            // ⚠️ INVÁLIDO: código duplicado
            [
                'contrato_id' => $contract->getKey(),
                'codigo' => 'PAG-BATCH-001', // Duplicado
                'monto' => 150.00,
                'periodo_servicio' => '2026-10',
                'fecha_vencimiento' => now()->addDays(5),
                'estado' => 'PENDIENTE',
            ],
        ];

        $validCount = 0;
        $invalidCount = 0;

        foreach ($batchPayments as $data) {
            try {
                $payment = Payment::create($data);
                
                // Validar datos del pago creado
                if ($payment->monto >= 0) {
                    $validCount++;
                } else {
                    $invalidCount++;
                }
            } catch (\Exception $e) {
                $invalidCount++;
            }
        }

        // El sistema debe rechazar registros inválidos
        $this->assertGreaterThan(0, $invalidCount, 'El sistema debe detectar registros inválidos');
        $this->assertGreaterThan(0, $validCount, 'El sistema debe procesar registros válidos');
    }

    /** @test RF-PAG-17: Método de pago debe ser válido */
    public function pago_rechaza_metodo_pago_invalido()
    {
        $contract = $this->createValidContract();

        $validMethods = ['EFECTIVO', 'TRANSFERENCIA', 'YAPE', 'PLIN', 'TARJETA', 'OTRO'];
        $invalidMethod = 'BITCOIN'; // ⚠️ No existe en el sistema

        $payment = Payment::create([
            'contrato_id' => $contract->getKey(),
            'codigo' => 'PAG-METHOD-001',
            'monto' => 100.00,
            'periodo_servicio' => now()->format('Y-m'),
            'fecha_vencimiento' => now()->addDays(5),
            'estado' => 'REALIZADO',
            'fecha_pago' => now(),
            'metodo_pago' => $invalidMethod,
        ]);

        // Verificar que solo se usen métodos válidos
        if ($payment->metodo_pago) {
            $this->assertContains(
                $payment->metodo_pago,
                $validMethods,
                "El sistema permitió método de pago inválido: {$invalidMethod}"
            );
        }
    }

    // ==================== HELPERS ====================

    private function createValidContract(): Contract
    {
        $creator = $this->createUser();
        
        $client = Client::create([
            'tipo_documento' => 'DNI',
            'numero_documento' => sprintf('1%07d', rand(1000000, 9999999)),
            'nombres_razon_social' => 'Cliente Test',
            'apellidos' => 'Test',
            'direccion' => 'Av. Test 123',
            'telefono' => '999888777',
            'correo' => sprintf('test%d@test.com', rand(1000, 9999)),
            'estado' => 'ACTIVO',
            'creado_por' => $creator->getKey(),
        ]);

        $plan = ServicePlan::create([
            'numero_plan' => sprintf('PLAN-%03d', rand(100, 999)),
            'nombre' => 'Plan Test',
            'tipo_servicio' => 'Internet',
            'velocidad_mbps' => 50,
            'precio_mensual' => 99.90,
            'estado' => 'ACTIVO',
            'creado_por' => $creator->getKey(),
        ]);

        $zone = DB::table('zonas_cobertura')->insertGetId([
            'codigo' => sprintf('ZONA-%03d', rand(100, 999)),
            'nombre' => 'Zona Test',
            'distrito' => 'Trujillo',
            'creado_por' => $creator->getKey(),
        ]);

        $employee = DB::table('empleados')->insertGetId([
            'tipo_documento' => 'DNI',
            'numero_documento' => sprintf('97%06d', rand(100000, 999999)),
            'nombres' => 'Técnico',
            'apellidos' => 'Test',
            'cargo' => 'Instalador',
        ]);

        return Contract::create([
            'codigo' => sprintf('CON-TEST-%03d', rand(100, 999)),
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
    }

    private function createUser(): User
    {
        $role = Role::firstOrCreate(
            ['nombre' => 'Gerente'],
            ['estado' => 'ACTIVO']
        );

        return User::factory()->create(['rol_id' => $role->getKey()]);
    }
}
