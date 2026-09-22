<?php

namespace Tests\Unit;

use App\Models\Contract;
use App\Models\ContractPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests para el modelo ContractPeriod y su lógica de negocio
 * Fase 12: Tests para períodos contractuales
 */
class ContractPeriodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
    }

    /** @test */
    public function periodo_puede_ser_creado_con_datos_validos()
    {
        $contract = Contract::factory()->create();
        $user = User::factory()->create();

        $period = ContractPeriod::create([
            'contrato_id' => $contract->contrato_id,
            'numero_periodo' => 1,
            'fecha_inicio_periodo' => now(),
            'fecha_fin_periodo' => now()->addMonths(3),
            'duracion_meses_periodo' => 3,
            'estado_periodo' => 'ACTIVO',
        ]);

        $this->assertDatabaseHas('periodos_contractuales', [
            'periodo_id' => $period->periodo_id,
            'contrato_id' => $contract->contrato_id,
            'numero_periodo' => 1,
            'estado_periodo' => 'ACTIVO',
        ]);
    }

    /** @test */
    public function metodo_isActive_retorna_true_para_periodo_activo()
    {
        $period = ContractPeriod::factory()->create([
            'estado_periodo' => 'ACTIVO',
        ]);

        $this->assertTrue($period->isActive());
        $this->assertFalse($period->isCancelled());
        $this->assertFalse($period->isFinalized());
    }

    /** @test */
    public function metodo_isCancelled_retorna_true_para_periodo_cancelado()
    {
        $period = ContractPeriod::factory()->create([
            'estado_periodo' => 'CANCELADO',
            'fecha_cancelacion' => now(),
            'motivo_cancelacion' => 'Prueba de cancelación',
        ]);

        $this->assertTrue($period->isCancelled());
        $this->assertFalse($period->isActive());
        $this->assertFalse($period->isFinalized());
    }

    /** @test */
    public function metodo_isFinalized_retorna_true_para_periodo_finalizado()
    {
        $period = ContractPeriod::factory()->create([
            'estado_periodo' => 'FINALIZADO',
        ]);

        $this->assertTrue($period->isFinalized());
        $this->assertFalse($period->isActive());
        $this->assertFalse($period->isCancelled());
    }

    /** @test */
    public function metodo_wasCancelledByNonPayment_identifica_cancelacion_automatica()
    {
        $period = ContractPeriod::factory()->create([
            'estado_periodo' => 'CANCELADO',
            'origen_cancelacion' => 'AUTOMATICO_FALTA_PAGO',
            'fecha_cancelacion' => now(),
        ]);

        $this->assertTrue($period->wasCancelledByNonPayment());
        $this->assertFalse($period->wasCancelledManually());
    }

    /** @test */
    public function metodo_wasCancelledManually_identifica_cancelacion_manual()
    {
        $user = User::factory()->create();
        
        $period = ContractPeriod::factory()->create([
            'estado_periodo' => 'CANCELADO',
            'origen_cancelacion' => 'MANUAL',
            'cancelado_por' => $user->usuario_id,
            'fecha_cancelacion' => now(),
        ]);

        $this->assertTrue($period->wasCancelledManually());
        $this->assertFalse($period->wasCancelledByNonPayment());
    }

    /** @test */
    public function metodo_getDurationInDays_calcula_duracion_correctamente()
    {
        $startDate = now();
        $endDate = now()->addDays(90);

        $period = ContractPeriod::factory()->create([
            'fecha_inicio_periodo' => $startDate,
            'fecha_fin_periodo' => $endDate,
        ]);

        $this->assertEquals(90, $period->getDurationInDays());
    }

    /** @test */
    public function metodo_hasExpired_identifica_periodo_vencido()
    {
        $expiredPeriod = ContractPeriod::factory()->create([
            'fecha_fin_periodo' => now()->subDays(1),
            'estado_periodo' => 'ACTIVO',
        ]);

        $activePeriod = ContractPeriod::factory()->create([
            'fecha_fin_periodo' => now()->addDays(30),
            'estado_periodo' => 'ACTIVO',
        ]);

        $this->assertTrue($expiredPeriod->hasExpired());
        $this->assertFalse($activePeriod->hasExpired());
    }

    /** @test */
    public function relacion_contract_funciona_correctamente()
    {
        $contract = Contract::factory()->create();
        $period = ContractPeriod::factory()->create([
            'contrato_id' => $contract->contrato_id,
        ]);

        $this->assertInstanceOf(Contract::class, $period->contract);
        $this->assertEquals($contract->contrato_id, $period->contract->contrato_id);
    }

    /** @test */
    public function relacion_cancelledBy_funciona_correctamente()
    {
        $user = User::factory()->create();
        $period = ContractPeriod::factory()->create([
            'estado_periodo' => 'CANCELADO',
            'cancelado_por' => $user->usuario_id,
        ]);

        $this->assertInstanceOf(User::class, $period->cancelledBy);
        $this->assertEquals($user->usuario_id, $period->cancelledBy->usuario_id);
    }

    /** @test */
    public function contrato_puede_tener_multiples_periodos()
    {
        $contract = Contract::factory()->create();
        
        ContractPeriod::factory()->create([
            'contrato_id' => $contract->contrato_id,
            'numero_periodo' => 1,
            'estado_periodo' => 'FINALIZADO',
        ]);

        ContractPeriod::factory()->create([
            'contrato_id' => $contract->contrato_id,
            'numero_periodo' => 2,
            'estado_periodo' => 'ACTIVO',
        ]);

        $contract = $contract->fresh();
        $this->assertCount(2, $contract->periods);
    }

    /** @test */
    public function currentPeriod_retorna_el_periodo_actual_del_contrato()
    {
        $contract = Contract::factory()->create(['periodo_actual' => null]);
        
        $oldPeriod = ContractPeriod::factory()->create([
            'contrato_id' => $contract->contrato_id,
            'numero_periodo' => 1,
            'estado_periodo' => 'FINALIZADO',
        ]);

        $currentPeriod = ContractPeriod::factory()->create([
            'contrato_id' => $contract->contrato_id,
            'numero_periodo' => 2,
            'estado_periodo' => 'ACTIVO',
        ]);

        $contract->update(['periodo_actual' => $currentPeriod->periodo_id]);
        $contract = $contract->fresh();

        $this->assertNotNull($contract->currentPeriod);
        $this->assertEquals($currentPeriod->periodo_id, $contract->currentPeriod->periodo_id);
        $this->assertEquals(2, $contract->currentPeriod->numero_periodo);
    }
}
