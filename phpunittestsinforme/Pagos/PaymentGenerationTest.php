<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\CoverageZone;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\ServicePlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CA05 - GESTIÓN DE PAGOS
 * Tests para método: generateMonthlyPayments() - Generar obligaciones mensuales
 * Archivo de producción: app/Repositories/PaymentRepository.php (línea 296)
 * Tipo: Feature Test
 */
class PaymentGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected ServicePlan $plan;
    protected Client $client;
    protected Employee $employee;
    protected CoverageZone $zona;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();

        $this->plan = ServicePlan::factory()->create([
            'estado' => 'ACTIVO',
            'precio_mensual' => 150.00,
        ]);

        $this->client = Client::factory()->create([
            'estado' => 'ACTIVO',
        ]);

        $this->employee = Employee::factory()->create([
            'estado' => 'ACTIVO',
            'cargo' => 'Asistente de Ventas',
        ]);

        $this->zona = CoverageZone::factory()->create([
            'estado' => 'ACTIVA',
        ]);

        DB::table('plan_zona_cobertura')->insert([
            'plan_id' => $this->plan->getKey(),
            'zona_id' => $this->zona->getKey(),
            'estado' => 'ACTIVO',
        ]);
    }

    /**
     * @test
     * Variación 1: Contrato de 12 meses genera 12 pagos
     */
    public function contrato_12_meses_genera_12_pagos()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "✅ TEST: Contrato de 12 meses genera 12 pagos\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • duracion_meses: 12\n";
        echo "   • precio_contratado: S/ 150.00\n";
        echo "   • fecha_inicio: 2026-01-01\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → PaymentRepository::generateMonthlyPayments(contract, userId)\n";
        echo "⚙️ PROCESO:\n";
        echo "   for (\$mes = 0; \$mes < 12; \$mes++)\n";
        echo "   → Crear pago con periodo_servicio = YYYY-MM\n";
        echo "   → fecha_vencimiento = fecha_inicio + \$mes meses\n";
        echo "   → estado = 'PENDIENTE'\n";
        echo "📋 EXPECTED:\n";
        echo "   • Total pagos generados: 12\n";
        echo "   • Todos con estado PENDIENTE\n";

        $contract = Contract::factory()->create([
            'codigo' => 'CON-20260101-TEST0001',
            'cliente_id' => $this->client->getKey(),
            'plan_id' => $this->plan->getKey(),
            'zona_id' => $this->zona->getKey(),
            'responsable_id' => $this->employee->getKey(),
            'fecha_inicio' => '2026-01-01',
            'duracion_meses' => 12,
            'fecha_fin' => '2026-12-31',
            'precio_contratado' => 150.00,
            'direccion_servicio' => $this->client->direccion,
            'estado' => 'ACTIVO',
        ]);

        // Generar pagos
        $paymentRepo = app(\App\Repositories\PaymentRepository::class);
        $payments = $paymentRepo->generateMonthlyPayments($contract, $this->admin->getKey());

        echo "✅ CONFIRMED: 12 pagos generados correctamente\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $this->assertCount(12, $payments);
        
        $allPayments = Payment::where('contrato_id', $contract->getKey())->get();
        $this->assertCount(12, $allPayments);
        
        foreach ($allPayments as $payment) {
            $this->assertEquals('PENDIENTE', $payment->estado);
        }
    }

    /**
     * @test
     * Variación 2: Primer pago tiene periodo correcto
     */
    public function primer_pago_tiene_periodo_correcto()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "✅ TEST: Primer pago tiene periodo_servicio correcto\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • fecha_inicio: 2026-03-15\n";
        echo "   • duracion_meses: 6\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → \$fechaVencimiento = \$fechaInicio->copy()->addMonthsNoOverflow(0)\n";
        echo "   → \$periodoServicio = \$fechaVencimiento->format('Y-m')\n";
        echo "⚙️ PROCESO:\n";
        echo "   Primer pago (\$mes = 0):\n";
        echo "   2026-03-15 + 0 meses = 2026-03-15\n";
        echo "   periodo_servicio = '2026-03'\n";
        echo "📋 EXPECTED:\n";
        echo "   • periodo_servicio: '2026-03'\n";
        echo "   • fecha_vencimiento: '2026-03-15'\n";

        $contract = Contract::factory()->create([
            'codigo' => 'CON-20260315-TEST0002',
            'cliente_id' => $this->client->getKey(),
            'plan_id' => $this->plan->getKey(),
            'zona_id' => $this->zona->getKey(),
            'responsable_id' => $this->employee->getKey(),
            'fecha_inicio' => '2026-03-15',
            'duracion_meses' => 6,
            'fecha_fin' => '2026-09-14',
            'precio_contratado' => 150.00,
            'direccion_servicio' => $this->client->direccion,
            'estado' => 'ACTIVO',
        ]);

        $paymentRepo = app(\App\Repositories\PaymentRepository::class);
        $payments = $paymentRepo->generateMonthlyPayments($contract, $this->admin->getKey());

        $primerPago = Payment::where('contrato_id', $contract->getKey())
            ->orderBy('periodo_servicio')
            ->first();

        echo "✅ CONFIRMED: Primer pago con periodo '2026-03'\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $this->assertEquals('2026-03', $primerPago->periodo_servicio);
        $this->assertEquals('2026-03-15', $primerPago->fecha_vencimiento->format('Y-m-d'));
    }

    /**
     * @test
     * Variación 3: Último pago tiene periodo correcto
     */
    public function ultimo_pago_tiene_periodo_correcto()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "✅ TEST: Último pago tiene periodo correcto\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • fecha_inicio: 2026-01-01\n";
        echo "   • duracion_meses: 12\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → Último pago (\$mes = 11):\n";
        echo "   → \$fechaVencimiento = \$fechaInicio->copy()->addMonthsNoOverflow(11)\n";
        echo "⚙️ PROCESO:\n";
        echo "   2026-01-01 + 11 meses = 2026-12-01\n";
        echo "   periodo_servicio = '2026-12'\n";
        echo "📋 EXPECTED:\n";
        echo "   • periodo_servicio último: '2026-12'\n";

        $contract = Contract::factory()->create([
            'codigo' => 'CON-20260101-TEST0003',
            'cliente_id' => $this->client->getKey(),
            'plan_id' => $this->plan->getKey(),
            'zona_id' => $this->zona->getKey(),
            'responsable_id' => $this->employee->getKey(),
            'fecha_inicio' => '2026-01-01',
            'duracion_meses' => 12,
            'fecha_fin' => '2026-12-31',
            'precio_contratado' => 150.00,
            'direccion_servicio' => $this->client->direccion,
            'estado' => 'ACTIVO',
        ]);

        $paymentRepo = app(\App\Repositories\PaymentRepository::class);
        $paymentRepo->generateMonthlyPayments($contract, $this->admin->getKey());

        $ultimoPago = Payment::where('contrato_id', $contract->getKey())
            ->orderByDesc('periodo_servicio')
            ->first();

        echo "✅ CONFIRMED: Último pago con periodo '2026-12'\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $this->assertEquals('2026-12', $ultimoPago->periodo_servicio);
    }

    /**
     * @test
     * Variación 4: Todos los pagos tienen estado PENDIENTE
     */
    public function todos_pagos_tienen_estado_pendiente()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "✅ TEST: Todos los pagos generados tienen estado PENDIENTE\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • duracion_meses: 6\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → 'estado' => 'PENDIENTE' (al crear cada pago)\n";
        echo "⚙️ VALIDACIÓN:\n";
        echo "   → foreach (\$payments as \$payment)\n";
        echo "   → assert estado === 'PENDIENTE'\n";
        echo "📋 EXPECTED:\n";
        echo "   • 6 pagos con estado = PENDIENTE\n";

        $contract = Contract::factory()->create([
            'codigo' => 'CON-20260201-TEST0004',
            'cliente_id' => $this->client->getKey(),
            'plan_id' => $this->plan->getKey(),
            'zona_id' => $this->zona->getKey(),
            'responsable_id' => $this->employee->getKey(),
            'fecha_inicio' => '2026-02-01',
            'duracion_meses' => 6,
            'fecha_fin' => '2026-07-31',
            'precio_contratado' => 150.00,
            'direccion_servicio' => $this->client->direccion,
            'estado' => 'ACTIVO',
        ]);

        $paymentRepo = app(\App\Repositories\PaymentRepository::class);
        $paymentRepo->generateMonthlyPayments($contract, $this->admin->getKey());

        $allPayments = Payment::where('contrato_id', $contract->getKey())->get();

        $todosPendientes = $allPayments->every(fn($p) => $p->estado === 'PENDIENTE');

        echo "✅ CONFIRMED: Todos los 6 pagos están PENDIENTES\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $this->assertTrue($todosPendientes);
        $this->assertCount(6, $allPayments);
    }

    /**
     * @test
     * Variación 5: Cada pago tiene el monto correcto
     */
    public function cada_pago_tiene_monto_correcto()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "✅ TEST: Cada pago tiene el monto del contrato\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • precio_contratado: S/ 180.50\n";
        echo "   • duracion_meses: 4\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → 'monto' => \$contract->precio_contratado\n";
        echo "⚙️ VALIDACIÓN:\n";
        echo "   → Cada pago.monto === 180.50\n";
        echo "📋 EXPECTED:\n";
        echo "   • 4 pagos con monto = 180.50 cada uno\n";

        $contract = Contract::factory()->create([
            'codigo' => 'CON-20260301-TEST0005',
            'cliente_id' => $this->client->getKey(),
            'plan_id' => $this->plan->getKey(),
            'zona_id' => $this->zona->getKey(),
            'responsable_id' => $this->employee->getKey(),
            'fecha_inicio' => '2026-03-01',
            'duracion_meses' => 4,
            'fecha_fin' => '2026-06-30',
            'precio_contratado' => 180.50,
            'direccion_servicio' => $this->client->direccion,
            'estado' => 'ACTIVO',
        ]);

        $paymentRepo = app(\App\Repositories\PaymentRepository::class);
        $paymentRepo->generateMonthlyPayments($contract, $this->admin->getKey());

        $allPayments = Payment::where('contrato_id', $contract->getKey())->get();

        $todosConMontoCorrect = $allPayments->every(fn($p) => $p->monto == 180.50);

        echo "✅ CONFIRMED: Todos los pagos tienen monto S/ 180.50\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $this->assertTrue($todosConMontoCorrect);
        $this->assertCount(4, $allPayments);
    }

    /**
     * @test
     * Variación 6: No genera pagos duplicados si ya existen
     */
    public function no_genera_pagos_duplicados()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "❌ TEST: No genera pagos duplicados si ya existen\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • duracion_meses: 3\n";
        echo "   • Ejecutar generateMonthlyPayments() 2 veces\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → 1ra ejecución: genera 3 pagos\n";
        echo "   → 2da ejecución: verifica existencia con where('periodo_servicio')\n";
        echo "⚙️ VALIDACIÓN:\n";
        echo "   if (\$existingPayment) { continue; }\n";
        echo "📋 EXPECTED:\n";
        echo "   • Total pagos en BD: 3 (no 6)\n";
        echo "   • Segunda ejecución: 0 pagos nuevos\n";

        $contract = Contract::factory()->create([
            'codigo' => 'CON-20260401-TEST0006',
            'cliente_id' => $this->client->getKey(),
            'plan_id' => $this->plan->getKey(),
            'zona_id' => $this->zona->getKey(),
            'responsable_id' => $this->employee->getKey(),
            'fecha_inicio' => '2026-04-01',
            'duracion_meses' => 3,
            'fecha_fin' => '2026-06-30',
            'precio_contratado' => 150.00,
            'direccion_servicio' => $this->client->direccion,
            'estado' => 'ACTIVO',
        ]);

        $paymentRepo = app(\App\Repositories\PaymentRepository::class);
        
        // Primera ejecución
        $firstRun = $paymentRepo->generateMonthlyPayments($contract, $this->admin->getKey());
        $countAfterFirst = Payment::where('contrato_id', $contract->getKey())->count();

        // Segunda ejecución (debe saltar los existentes)
        $secondRun = $paymentRepo->generateMonthlyPayments($contract, $this->admin->getKey());
        $countAfterSecond = Payment::where('contrato_id', $contract->getKey())->count();

        echo "   • Primera ejecución: {$countAfterFirst} pagos\n";
        echo "   • Segunda ejecución: {$countAfterSecond} pagos (sin duplicar)\n";
        echo "✅ CONFIRMED: No se generaron duplicados\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $this->assertCount(3, $firstRun);
        $this->assertEmpty($secondRun);
        $this->assertEquals(3, $countAfterFirst);
        $this->assertEquals(3, $countAfterSecond);
    }
}
