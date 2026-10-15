<?php
namespace Tests\Unit;

use App\Services\AnalyticsPreviewService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class AnalyticsPreviewTest extends TestCase
{
    protected function tearDown(): void { CarbonImmutable::setTestNow(); parent::tearDown(); }
    public function test_preview_totals_agree_with_monthly_chart_and_staff(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 12:00:00','America/Lima'));
        $data=(new AnalyticsPreviewService)->dashboard('2026-05-01','2026-10-14','all',['indicador'=>'ventas']);
        $this->assertGreaterThan(0,$data['items']['clientes']['count']);
        $this->assertSame($data['sales']['count'],$data['explorer']['total']);
        $this->assertSame($data['sales']['count'],$data['staff']['rows']->sum('ventas'));
        $this->assertSame($data['items']['contratos']['count'],$data['staff']['rows']->sum('contratos'));
        $this->assertSame($data['items']['clientes']['count'],array_sum($data['items']['clientes']['trend']));
    }
    public function test_preview_filters_narrow_the_sample_and_keep_only_selected_process(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 12:00:00','America/Lima'));
        $service=new AnalyticsPreviewService;
        $full=$service->dashboard('2026-05-01','2026-10-14','all');
        $subset=$service->dashboard('2026-10-01','2026-10-14','pagos');
        $this->assertSame(['pagos'],array_keys($subset['items']));
        $this->assertLessThan($full['items']['pagos']['count'],$subset['items']['pagos']['count']);
        $this->assertNull($subset['sales']);
    }
    public function test_preview_keeps_unvalidated_time_indicators_unmeasured(): void
    {
        $data=(new AnalyticsPreviewService)->dashboard('2026-01-01','2026-12-31','all');
        foreach(['inventario','reactivacion','acceso_informacion'] as $key) {
            $this->assertNull($data['pretest']['measurements'][$key]['average']);
        }
        $this->assertFalse($data['pretest']['enabled']);
        $this->assertTrue($data['pretest']['recent']->isEmpty());
    }
    public function test_preview_money_matches_sales_summary(): void
    {
        $data=(new AnalyticsPreviewService)->dashboard('2026-01-01','2026-12-31','contratos',['indicador'=>'ventas','medida'=>'monto']);
        $this->assertEquals($data['sales']['amount'],$data['explorer']['total']);
    }
}
