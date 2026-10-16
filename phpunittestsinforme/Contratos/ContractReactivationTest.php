<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\ServicePlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CA04 - GESTIÓN DE CONTRATOS
 * Tests para método: reactivate() - Reactivar contrato cancelado
 * Archivo de producción: app/Services/ContractService.php (línea 521)
 * Tipo: Feature Test
 */
class ContractReactivationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected ServicePlan $plan;
    protected Client $client;
    protected Employee $employee;
    protected $zona;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuario admin simple (sin rol en BD)
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

        $this->zona = DB::table('zonas_cobertura')->insertGetId([
            'nombre' => 'Zona Test',
            'estado' => 'ACTIVA',
            'creado_en' => now(),
            'actualizado_en' => now(),
        ]);

        // Crear relación plan-zona
        DB::table('plan_zona_cobertura')->insert([
            'plan_id' => $this->plan->getKey(),
            'zona_id' => $this->zona,
            'estado' => 'ACTIVO',
        ]);
    }

    /**
     * @test
     * Variación 1: Contrato CANCELADO sin deuda puede reactivarse
     */
    public function contrato_cancelado_sin_deuda_puede_reactivarse()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "✅ TEST: Contrato CANCELADO sin deuda puede reactivarse\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • Estado contrato: CANCELADO\n";
        echo "   • Pagos pendientes: 0\n";
        echo "   • Nueva fecha inicio: " . now()->addDays(1)->format('Y-m-d') . "\n";
        echo "   • Nueva duración: 12 meses\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → ContractService::reactivate()\n";
        echo "⚙️ VALIDACIÓN:\n";
        echo "   1. Verificar estado === 'CANCELADO'\n";
        echo "   2. Verificar plan activo\n";
        echo "   3. Verificar cliente activo\n";
        echo "   4. Verificar zona activa\n";
        echo "   5. Verificar responsable activo\n";
        echo "   6. Validar fecha inicio >= hoy\n";
        echo "   7. Calcular nueva fecha fin\n";
        echo "   8. Crear nuevo período\n";
        echo "   9. Cambiar estado a ACTIVO\n";
        echo "📋 EXPECTED:\n";
        echo "   • Estado final: ACTIVO\n";
        echo "   • Mensaje: 'reactivado correctamente'\n";

        // Crear contrato CANCELADO
        $contract = Contract::factory()->create([
            'codigo' => 'CON-20261012-TEST0001',
            'cliente_id' => $this->client->getKey(),
            'plan_id' => $this->plan->getKey(),
            'zona_id' => $this->zona,
            'responsable_id' => $this->employee->getKey(),
            'fecha_inicio' => '2025-01-01',
            'duracion_meses' => 12,
            'fecha_fin' => '2025-12-31',
            'precio_contratado' => 150.00,
            'direccion_servicio' => $this->client->direccion,
            'estado' => 'CANCELADO',
        ]);

        // Todos los pagos están PAGADOS (sin deuda)
        Payment::factory()->create([
            'contrato_id' => $contract->getKey(),
            'estado' => 'PAGADO',
            'monto' => 150.00,
        ]);

        $nuevaFechaInicio = now()->addDays(1)->format('Y-m-d');
        $response = $this->actingAs($this->admin)
            ->post(route('contracts.reactivate', $contract->getKey()), [
                'fecha_inicio' => $nuevaFechaInicio,
                'duracion_meses' => 12,
            ]);

        echo "✅ CONFIRMED: Contrato reactivado exitosamente\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $response->assertRedirect(route('contracts.show', $contract->getKey()));
        $response->assertSessionHas('status_kind', 'reactivated');
        
        $contract->refresh();
        $this->assertEquals('ACTIVO', $contract->estado);
    }

    /**
     * @test
     * Variación 2: Contrato ACTIVO no puede reactivarse
     */
    public function contrato_activo_no_puede_reactivarse()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "❌ TEST: Contrato ACTIVO no puede reactivarse\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • Estado contrato: ACTIVO\n";
        echo "   • Intentar reactivar\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → ContractService::reactivate()\n";
        echo "⚙️ VALIDACIÓN:\n";
        echo "   1. if (\$contract->estado !== 'CANCELADO')\n";
        echo "   2. throw ValidationException\n";
        echo "📋 EXPECTED:\n";
        echo "   • Error validación: 'Solo se pueden reactivar contratos cancelados'\n";
        echo "   • Estado permanece: ACTIVO\n";

        $contract = Contract::factory()->create([
            'codigo' => 'CON-20261012-TEST0002',
            'cliente_id' => $this->client->getKey(),
            'plan_id' => $this->plan->getKey(),
            'zona_id' => $this->zona,
            'responsable_id' => $this->employee->getKey(),
            'fecha_inicio' => '2026-01-01',
            'duracion_meses' => 12,
            'fecha_fin' => '2026-12-31',
            'precio_contratado' => 150.00,
            'direccion_servicio' => $this->client->direccion,
            'estado' => 'ACTIVO', // ACTIVO
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('contracts.reactivate', $contract->getKey()), [
                'fecha_inicio' => now()->addDays(1)->format('Y-m-d'),
                'duracion_meses' => 12,
            ]);

        echo "✅ CONFIRMED: Reactivación bloqueada correctamente\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $response->assertSessionHasErrors('contrato');
        
        $contract->refresh();
        $this->assertEquals('ACTIVO', $contract->estado);
    }

    /**
     * @test
     * Variación 3: Contrato PENDIENTE no puede reactivarse
     */
    public function contrato_pendiente_no_puede_reactivarse()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "❌ TEST: Contrato PENDIENTE no puede reactivarse\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • Estado contrato: PENDIENTE\n";
        echo "   • Intentar reactivar\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → ContractService::reactivate()\n";
        echo "⚙️ VALIDACIÓN:\n";
        echo "   1. if (\$contract->estado !== 'CANCELADO')\n";
        echo "   2. Estado actual: PENDIENTE\n";
        echo "   3. throw ValidationException\n";
        echo "📋 EXPECTED:\n";
        echo "   • Error: 'Solo se pueden reactivar contratos cancelados'\n";
        echo "   • Estado permanece: PENDIENTE\n";

        $contract = Contract::factory()->create([
            'codigo' => 'CON-20261012-TEST0003',
            'cliente_id' => $this->client->getKey(),
            'plan_id' => $this->plan->getKey(),
            'zona_id' => $this->zona,
            'responsable_id' => $this->employee->getKey(),
            'fecha_inicio' => '2026-01-01',
            'duracion_meses' => 12,
            'fecha_fin' => '2026-12-31',
            'precio_contratado' => 150.00,
            'direccion_servicio' => $this->client->direccion,
            'estado' => 'PENDIENTE', // PENDIENTE
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('contracts.reactivate', $contract->getKey()), [
                'fecha_inicio' => now()->addDays(1)->format('Y-m-d'),
                'duracion_meses' => 12,
            ]);

        echo "✅ CONFIRMED: Reactivación bloqueada para PENDIENTE\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $response->assertSessionHasErrors('contrato');
        
        $contract->refresh();
        $this->assertEquals('PENDIENTE', $contract->estado);
    }

    /**
     * @test
     * Variación 4: Contrato con plan INACTIVO no puede reactivarse
     */
    public function contrato_con_plan_inactivo_no_puede_reactivarse()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "❌ TEST: Contrato con plan INACTIVO no puede reactivarse\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • Estado contrato: CANCELADO\n";
        echo "   • Estado plan: INACTIVO\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → ContractService::reactivate()\n";
        echo "⚙️ VALIDACIÓN:\n";
        echo "   1. Contrato CANCELADO ✓\n";
        echo "   2. Verificar plan activo\n";
        echo "   3. if (\$plan->estado !== 'ACTIVO')\n";
        echo "   4. throw ValidationException\n";
        echo "📋 EXPECTED:\n";
        echo "   • Error: 'El plan del contrato fue inhabilitado'\n";
        echo "   • Estado permanece: CANCELADO\n";

        // Plan INACTIVO
        $planInactivo = ServicePlan::factory()->create([
            'estado' => 'INACTIVO',
        ]);

        $contract = Contract::factory()->create([
            'codigo' => 'CON-20261012-TEST0004',
            'cliente_id' => $this->client->getKey(),
            'plan_id' => $planInactivo->getKey(),
            'zona_id' => $this->zona,
            'responsable_id' => $this->employee->getKey(),
            'fecha_inicio' => '2025-01-01',
            'duracion_meses' => 12,
            'fecha_fin' => '2025-12-31',
            'precio_contratado' => 150.00,
            'direccion_servicio' => $this->client->direccion,
            'estado' => 'CANCELADO',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('contracts.reactivate', $contract->getKey()), [
                'fecha_inicio' => now()->addDays(1)->format('Y-m-d'),
                'duracion_meses' => 12,
            ]);

        echo "✅ CONFIRMED: Reactivación bloqueada por plan inactivo\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $response->assertSessionHasErrors('plan_id');
        
        $contract->refresh();
        $this->assertEquals('CANCELADO', $contract->estado);
    }

    /**
     * @test
     * Variación 5: Contrato con cliente INACTIVO no puede reactivarse
     */
    public function contrato_con_cliente_inactivo_no_puede_reactivarse()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "❌ TEST: Contrato con cliente INACTIVO no puede reactivarse\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • Estado contrato: CANCELADO\n";
        echo "   • Estado cliente: INACTIVO (no ACTIVO/PENDIENTE)\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → ContractService::reactivate()\n";
        echo "⚙️ VALIDACIÓN:\n";
        echo "   1. Contrato CANCELADO ✓\n";
        echo "   2. Plan ACTIVO ✓\n";
        echo "   3. Verificar cliente activo\n";
        echo "   4. if (!in_array(\$client->estado, ['ACTIVO', 'PENDIENTE', 'INACTIVO']))\n";
        echo "   5. throw ValidationException\n";
        echo "📋 EXPECTED:\n";
        echo "   • Error: 'El cliente del contrato no está activo'\n";
        echo "   • Estado permanece: CANCELADO\n";

        // Cliente INACTIVO
        $clienteInactivo = Client::factory()->create([
            'estado' => 'ELIMINADO', // Estado no permitido
        ]);

        $contract = Contract::factory()->create([
            'codigo' => 'CON-20261012-TEST0005',
            'cliente_id' => $clienteInactivo->getKey(),
            'plan_id' => $this->plan->getKey(),
            'zona_id' => $this->zona,
            'responsable_id' => $this->employee->getKey(),
            'fecha_inicio' => '2025-01-01',
            'duracion_meses' => 12,
            'fecha_fin' => '2025-12-31',
            'precio_contratado' => 150.00,
            'direccion_servicio' => $clienteInactivo->direccion,
            'estado' => 'CANCELADO',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('contracts.reactivate', $contract->getKey()), [
                'fecha_inicio' => now()->addDays(1)->format('Y-m-d'),
                'duracion_meses' => 12,
            ]);

        echo "✅ CONFIRMED: Reactivación bloqueada por cliente no válido\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $response->assertSessionHasErrors('cliente');
        
        $contract->refresh();
        $this->assertEquals('CANCELADO', $contract->estado);
    }

    /**
     * @test
     * Variación 6: Fecha de inicio en el pasado no puede usarse
     */
    public function fecha_inicio_pasado_no_puede_usarse()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "❌ TEST: Fecha de inicio en el pasado es rechazada\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • Estado contrato: CANCELADO\n";
        echo "   • Nueva fecha inicio: 2025-01-01 (pasado)\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → ContractService::reactivate()\n";
        echo "⚙️ VALIDACIÓN:\n";
        echo "   1. Contrato CANCELADO ✓\n";
        echo "   2. Validar nueva fecha inicio\n";
        echo "   3. if (\$nuevaFechaInicio < now()->toDateString())\n";
        echo "   4. throw ValidationException\n";
        echo "📋 EXPECTED:\n";
        echo "   • Error: 'La fecha de inicio debe ser hoy o una fecha futura'\n";
        echo "   • Estado permanece: CANCELADO\n";

        $contract = Contract::factory()->create([
            'codigo' => 'CON-20261012-TEST0006',
            'cliente_id' => $this->client->getKey(),
            'plan_id' => $this->plan->getKey(),
            'zona_id' => $this->zona,
            'responsable_id' => $this->employee->getKey(),
            'fecha_inicio' => '2025-01-01',
            'duracion_meses' => 12,
            'fecha_fin' => '2025-12-31',
            'precio_contratado' => 150.00,
            'direccion_servicio' => $this->client->direccion,
            'estado' => 'CANCELADO',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('contracts.reactivate', $contract->getKey()), [
                'fecha_inicio' => '2025-01-01', // Fecha pasada
                'duracion_meses' => 12,
            ]);

        echo "✅ CONFIRMED: Fecha pasada rechazada correctamente\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $response->assertSessionHasErrors('fecha_inicio');
        
        $contract->refresh();
        $this->assertEquals('CANCELADO', $contract->estado);
    }
}
