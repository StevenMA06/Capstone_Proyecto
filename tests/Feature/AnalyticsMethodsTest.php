<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Services\BusinessIntelligenceService;
use App\Services\PretestMeasurementService;
use App\Services\IndicatorQueryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\Schema\Blueprint;

class AnalyticsMethodsTest extends TestCase
{
    private string $fixtureDatabase;
    private string $originalConnection;
    private bool $createdDatabase=false;

    protected function setUp(): void
    {
        parent::setUp();
        // Dedicated disposable schema; no fixtures enter the application database.
        $this->originalConnection=config('database.default');
        $this->fixtureDatabase='analytics_methods_'.getmypid().'_test';
        DB::connection($this->originalConnection)->statement('CREATE DATABASE `'.$this->fixtureDatabase.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->createdDatabase=true;
        $connection=config('database.connections.'.$this->originalConnection);
        $connection['database']=$this->fixtureDatabase; $connection['url']=null;
        config(['database.connections.analytics_methods'=>$connection,'database.default'=>'analytics_methods']);
        Schema::create('empleados',function(Blueprint $t){$t->id('empleado_id');$t->string('nombres');$t->string('apellidos');$t->string('cargo');});
        Schema::create('usuarios',function(Blueprint $t){$t->id('usuario_id');$t->unsignedBigInteger('empleado_id')->nullable();});
        Schema::create('contratos',function(Blueprint $t){$t->id('contrato_id');$t->unsignedBigInteger('responsable_id');$t->dateTime('fecha_registro');$t->string('estado');});
        Schema::create('ventas',function(Blueprint $t){$t->id('venta_id');$t->unsignedBigInteger('contrato_id')->unique();$t->unsignedBigInteger('responsable_id');$t->dateTime('fecha_venta');$t->decimal('monto',12,2);$t->string('estado');});
        Schema::create('pagos',function(Blueprint $t){$t->id('pago_id');$t->unsignedBigInteger('registrado_por');$t->dateTime('creado_en');$t->dateTime('fecha_pago')->nullable();$t->decimal('monto',12,2);$t->string('estado');});
        Schema::create('clientes',function(Blueprint $t){$t->id('cliente_id');$t->dateTime('creado_en');$t->string('estado');});
        (require database_path('migrations/2026_10_13_000001_create_observaciones_pretest_table.php'))->up();
    }
    protected function tearDown(): void
    {
        if($this->createdDatabase) {
            DB::purge('analytics_methods'); config(['database.default'=>$this->originalConnection]);
            if(!preg_match('/^analytics_methods_[0-9]+_test$/D',$this->fixtureDatabase)) throw new \RuntimeException('Unsafe fixture database name.');
            DB::connection($this->originalConnection)->statement('DROP DATABASE `'.$this->fixtureDatabase.'`');
        }
        parent::tearDown();
    }
    private function seedSales(): void
    {
        DB::table('empleados')->insert(['empleado_id'=>1,'nombres'=>'Persona','apellidos'=>'Prueba','cargo'=>'Comercial']);
        foreach([1,2,3,4] as $id) DB::table('contratos')->insert(['contrato_id'=>$id,'responsable_id'=>1,'fecha_registro'=>'2001-01-15 10:00:00','estado'=>'ACTIVO']);
        foreach([[1,'2001-01-15 10:00:00',100,'CONFIRMADA'],[2,'2001-01-31 23:59:59',200,'CONFIRMADA'],[3,'2001-01-16 10:00:00',900,'ANULADA'],[4,'2001-02-01 00:00:00',500,'CONFIRMADA']] as [$id,$date,$amount,$state]) {
            DB::table('ventas')->insert(['contrato_id'=>$id,'responsable_id'=>1,'fecha_venta'=>$date,'monto'=>$amount,'estado'=>$state]);
        }
    }
    public function test_sales_and_staff_do_not_multiply_contracts_or_amounts(): void
    {
        $this->seedSales(); $service=app(BusinessIntelligenceService::class);
        $sales=$service->sales('2001-01-01','2001-01-31');
        $this->assertSame(2,$sales['count']); $this->assertSame(300.0,$sales['amount']);
        $staff=$service->staff('2001-01-01','2001-01-31');
        $this->assertSame(4,(int)$staff['rows'][0]->contratos);
        $this->assertSame(2,(int)$staff['rows'][0]->ventas);
        $this->assertSame(300.0,(float)$staff['rows'][0]->monto);
    }
    public function test_monthly_series_preserves_empty_months(): void
    {
        $this->seedSales(); $series=app(BusinessIntelligenceService::class)->chart('ventas','mes','bar','cantidad','2001-01-01','2001-03-31');
        $this->assertSame(['2001-01','2001-02','2001-03'],$series['labels']);
        $this->assertEquals([2,1,0],$series['values']);
    }
    public function test_weekly_series_uses_monday_across_year_boundary(): void
    {
        DB::table('clientes')->insert(['creado_en'=>'2000-12-31 23:59:59','estado'=>'ACTIVO']);
        $series=app(BusinessIntelligenceService::class)->chart('clientes','semana','line','cantidad','2000-12-30','2001-01-02');
        $this->assertSame(['2000-12-25','2001-01-01'],$series['labels']);
        $this->assertEquals([1,0],$series['values']);
    }
    public function test_payment_money_uses_payment_date_and_excludes_pending(): void
    {
        DB::table('pagos')->insert([
            ['registrado_por'=>1,'creado_en'=>'2000-12-01 00:00:00','fecha_pago'=>'2001-01-31 23:59:59','monto'=>150,'estado'=>'REALIZADO'],
            ['registrado_por'=>1,'creado_en'=>'2001-01-01 00:00:00','fecha_pago'=>null,'monto'=>400,'estado'=>'PENDIENTE'],
        ]);
        $series=app(BusinessIntelligenceService::class)->chart('pagos','mes','bar','monto','2001-01-01','2001-01-31');
        $this->assertSame(150.0,$series['total']);
        $this->assertSame('fecha_pago',$series['dateField']);
    }
    public function test_employee_group_resolves_user_to_employee_for_payments(): void
    {
        DB::table('empleados')->insert(['empleado_id'=>7,'nombres'=>'Persona','apellidos'=>'Prueba','cargo'=>'Atención']);
        DB::table('usuarios')->insert(['usuario_id'=>23,'empleado_id'=>7]);
        DB::table('pagos')->insert(['registrado_por'=>23,'creado_en'=>'2001-01-01 00:00:00','fecha_pago'=>null,'monto'=>100,'estado'=>'PENDIENTE']);
        $series=app(BusinessIntelligenceService::class)->chart('pagos','empleado','bar','cantidad','2001-01-01','2001-01-31');
        $this->assertStringContainsString('EMP-7',$series['labels'][0]);
        $this->assertEquals([1],$series['values']);
    }
    public function test_unsupported_chart_combination_is_rejected(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(BusinessIntelligenceService::class)->chart('clientes','estado','line','cantidad','2001-01-01','2001-01-31');
    }
    public function test_indicator_api_reuses_effective_times_and_preserves_missing_values(): void
    {
        $service=app(PretestMeasurementService::class);
        foreach([0,120] as $pause) $service->record(['token'=>(string)Str::uuid(),'indicador'=>'clientes','inicio'=>'2001-01-01T09:00:00','fin'=>'2001-01-01T09:10:00','interrupciones_segundos'=>$pause,'referencia'=>'Caso de prueba'],1);
        $query=app(IndicatorQueryService::class);
        $result=$query->measurement('clientes','2001-01-01','2001-01-31');
        $this->assertSame(9.0,$result['tiempo_promedio']['minutos']);
        $this->assertSame(8.0,$result['tiempo_minimo_minutos']); $this->assertSame(10.0,$result['tiempo_maximo_minutos']);
        $inventory=$query->inventoryByType('2001-01-01','2001-01-31');
        $this->assertSame('pendiente_validacion',$inventory['estado']);
        $this->assertNull($inventory['tiempo_promedio']['minutos']);
        $this->assertCount(3,$inventory['por_tipo']);
    }
    public function test_chart_sources_respect_five_processes(): void
    {
        $service=app(BusinessIntelligenceService::class);
        $this->assertArrayHasKey('ventas',$service->available('contratos'));
        $this->assertArrayNotHasKey('ventas',$service->available('pagos'));
        $this->assertArrayHasKey('kardex',$service->available('inventario'));
    }
    public function test_data_routes_require_authentication(): void
    {
        $this->getJson('/analytics/avg-client-registration-time')->assertUnauthorized();
        $this->getJson('/bi/generate-chart')->assertUnauthorized();
    }
    public function test_no_observations_returns_null_instead_of_zero_or_not_found(): void
    {
        $result=app(IndicatorQueryService::class)->measurement('clientes','2001-01-01','2001-01-31');
        $this->assertTrue($result['success']);
        $this->assertSame('sin_datos',$result['estado']);
        $this->assertNull($result['tiempo_promedio']['minutos']);
        $this->assertSame(0,$result['total_registros_analizados']);
    }
}
