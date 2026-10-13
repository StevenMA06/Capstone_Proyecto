<?php
namespace Tests\Unit;
use App\Services\PretestMeasurementService;
use App\Support\IndicatorDefinitions;
use PHPUnit\Framework\TestCase;

class PretestMeasurementTest extends TestCase
{
    public function test_duration_subtracts_only_recorded_interruptions(): void
    {
        $this->assertSame(480,PretestMeasurementService::duration('2026-10-12T09:00:00','2026-10-12T09:10:00',120));
    }
    public function test_observation_can_cross_midnight(): void
    {
        $this->assertSame(120,PretestMeasurementService::duration('2026-10-12T23:59:00','2026-10-13T00:01:00',0));
    }
    public function test_interruptions_cannot_consume_the_entire_interval(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PretestMeasurementService::duration('2026-10-12T09:00:00','2026-10-12T09:10:00',600);
    }
    public function test_end_must_be_after_start(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PretestMeasurementService::duration('2026-10-12T09:10:00','2026-10-12T09:00:00',0);
    }
    public function test_external_payment_does_not_replace_reactivation(): void
    {
        $definitions=IndicatorDefinitions::all();
        $this->assertFalse($definitions['reactivacion']['validated']);
        $this->assertSame('RF-IND-03',$definitions['reactivacion']['rf']);
        $this->assertNull($definitions['pagos_externos']['rf']);
        $this->assertNotContains('reactivacion',array_keys(IndicatorDefinitions::measurable()));
        $this->assertNotContains('inventario',array_keys(IndicatorDefinitions::measurable()));
    }
    public function test_contract_finish_includes_call_center_verification(): void
    {
        $this->assertStringContainsString('Call Center verifica',IndicatorDefinitions::all()['contratos']['end']);
    }
}
