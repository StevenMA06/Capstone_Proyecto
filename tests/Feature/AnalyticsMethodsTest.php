<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Services\BusinessIntelligenceService;
use App\Services\AnalyticsService;
use App\Services\ReportExportService;
use App\Services\PretestMeasurementService;
use App\Services\IndicatorQueryService;
use App\Services\AutomaticIndicatorService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\Schema\Blueprint;
use Carbon\CarbonImmutable;

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
        Schema::create('usuarios',function(Blueprint $t){$t->id('usuario_id');$t->unsignedBigInteger('empleado_id')->nullable();$t->string('nombre_usuario')->nullable();});
        Schema::create('contratos',function(Blueprint $t){$t->id('contrato_id');$t->unsignedBigInteger('responsable_id');$t->dateTime('fecha_registro');$t->string('estado');});
        Schema::create('ventas',function(Blueprint $t){$t->id('venta_id');$t->unsignedBigInteger('contrato_id')->unique();$t->unsignedBigInteger('responsable_id');$t->dateTime('fecha_venta');$t->decimal('monto',12,2);$t->string('estado');});
        Schema::create('pagos',function(Blueprint $t){$t->id('pago_id');$t->unsignedBigInteger('registrado_por');$t->dateTime('creado_en');$t->dateTime('fecha_pago')->nullable();$t->decimal('monto',12,2);$t->string('estado');});
        Schema::create('clientes',function(Blueprint $t){$t->id('cliente_id');$t->dateTime('creado_en');$t->string('estado');});
        Schema::create('equipos',function(Blueprint $t){$t->id('equipo_id');$t->string('estado');});
        Schema::create('materiales',function(Blueprint $t){$t->id('material_id');$t->string('nombre');$t->decimal('stock_actual',12,3);$t->decimal('stock_minimo',12,3);$t->string('unidad_medida');$t->string('unidad_base')->nullable();$t->string('unidad_empaque')->nullable();$t->decimal('factor_conversion',10,3)->nullable();$t->boolean('inventario_en_empaque')->default(false);});
        Schema::create('movimientos_inventario',function(Blueprint $t){$t->id('movimiento_id');$t->unsignedBigInteger('responsable_id')->nullable();$t->unsignedBigInteger('equipo_id')->nullable();$t->unsignedBigInteger('material_id')->nullable();$t->dateTime('fecha_movimiento');});
        Schema::create('pedidos_instalacion',function(Blueprint $t){$t->id('pedido_id');$t->dateTime('fecha_solicitud');$t->dateTime('asignado_en')->nullable();$t->string('estado');});
        (require database_path('migrations/2026_10_13_000001_create_observaciones_pretest_table.php'))->up();
        (require database_path('migrations/2026_10_21_000001_create_mediciones_automaticas_table.php'))->up();
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
    public function test_automatic_indicators_preserve_one_completed_attempt_and_sum_order_segments(): void
    {
        DB::table('usuarios')->insert(['usuario_id' => 1, 'empleado_id' => null]);
        $user = new User;
        $user->setAttribute('usuario_id', 1);
        $user->exists = true;
        $service = app(AutomaticIndicatorService::class);
        $now = now('America/Lima');
        $payload = fn (string $token) => [
            'indicador_token' => $token,
            'indicador_inicio' => $now->copy()->subMinutes(3)->toIso8601String(),
            'indicador_ultima_interaccion' => $now->copy()->subSeconds(10)->toIso8601String(),
            'indicador_activo_segundos' => 90,
            'indicador_pausa_descartada_segundos' => 90,
        ];

        $clientRequest = Request::create('/', 'POST', $payload('11a11111-1111-4111-8111-111111111111'));
        $service->complete($clientRequest, $user, 'clientes', 'cliente', 7);
        $service->complete($clientRequest, $user, 'clientes', 'cliente', 7);
        $this->assertSame(1, DB::table('mediciones_automaticas')->where('indicador', 'clientes')->count());
        $this->assertSame(90, (int) DB::table('mediciones_automaticas')->where('indicador', 'clientes')->value('duracion_activa_segundos'));
        $this->assertGreaterThan(0, (int) DB::table('mediciones_automaticas')->where('indicador', 'clientes')->value('pausa_descartada_segundos'));

        DB::table('pedidos_instalacion')->insert([
            'pedido_id' => 8, 'fecha_solicitud' => $now, 'asignado_en' => $now, 'estado' => 'PROGRAMADO',
        ]);
        $service->complete(Request::create('/', 'POST', $payload('22a22222-2222-4222-8222-222222222222')), $user,
            'pedido_recursos', 'solicitud_recurso', 81, 8, 81);
        // Dos solicitudes de recursos representan intentos distintos, incluido uno rechazado y reenviado.
        $service->complete(Request::create('/', 'POST', $payload('55a55555-5555-4555-8555-555555555555')), $user,
            'pedido_recursos', 'solicitud_recurso', 82, 8, 82);
        $service->complete(Request::create('/', 'POST', $payload('33a33333-3333-4333-8333-333333333333')), $user,
            'pedido_asignacion', 'pedido', 8, 8);

        DB::table('pedidos_instalacion')->insert([
            'pedido_id' => 9, 'fecha_solicitud' => $now, 'asignado_en' => $now, 'estado' => 'PROGRAMADO',
        ]);
        $service->complete(Request::create('/', 'POST', $payload('44a44444-4444-4444-8444-444444444444')), $user,
            'pedido_asignacion', 'pedido', 9, 9);

        $result = $service->orderProgramming($now->toDateString(), $now->toDateString());
        $this->assertSame(1, $result['count']);
        $this->assertSame(4.5, $result['average']);
        $this->assertSame('pedidos programados', $result['count_label']);
    }

    public function test_automatic_indicator_durations_are_deterministic_and_use_one_timezone(): void
    {
        DB::table('usuarios')->insert(['usuario_id' => 1, 'empleado_id' => null]);
        $user = new User;
        $user->setAttribute('usuario_id', 1);
        $user->exists = true;
        $service = app(AutomaticIndicatorService::class);
        $end = CarbonImmutable::create(2026, 10, 21, 15, 0, 0, 'America/Lima');
        $this->travelTo($end);

        try {
            $complete = function (string $token, int $referenceId, int $elapsed, int $active, int $discarded, ?int $lastOffset = null) use ($service, $user, $end): void {
                $start = $end->subSeconds($elapsed);
                $last = $end->subSeconds($lastOffset ?? 0);
                $request = Request::create('/', 'POST', [
                    'indicador_token' => $token,
                    // Exactamente el formato UTC enviado por Date.prototype.toISOString().
                    'indicador_inicio' => $start->setTimezone('UTC')->format('Y-m-d\\TH:i:s.v\\Z'),
                    'indicador_ultima_interaccion' => $last->setTimezone('UTC')->format('Y-m-d\\TH:i:s.v\\Z'),
                    'indicador_activo_segundos' => $active,
                    'indicador_pausa_descartada_segundos' => $discarded,
                ]);
                $service->complete($request, $user, 'clientes', 'cliente', $referenceId);
            };

            // Trabajo continuo: 10 s activos, sin pausa.
            $complete('a1a11111-1111-4111-8111-111111111111', 101, 10, 10, 0);
            // Inactividad menor que el umbral: los 60 s permanecen activos.
            $complete('a2a22222-2222-4222-8222-222222222222', 102, 60, 60, 0);
            // Inactividad de 150 s: el cliente acumula el límite de 120 s.
            $complete('a3a33333-3333-4333-8333-333333333333', 103, 150, 120, 30);
            // Tras una pausa de 30 s, una interacción válida agrega 20 s y el
            // guardado agrega 10 s: 150 s activos acumulados, sin reinicio.
            $complete('a4a44444-4444-4444-8444-444444444444', 104, 180, 150, 30, 0);

            $rows = DB::table('mediciones_automaticas')->where('indicador', 'clientes')->orderBy('referencia_id')->get();
            $this->assertCount(4, $rows);
            $this->assertSame([10, 60, 120, 150], $rows->pluck('duracion_activa_segundos')->map(fn ($value) => (int) $value)->all());
            $this->assertSame([0, 0, 30, 30], $rows->pluck('pausa_descartada_segundos')->map(fn ($value) => (int) $value)->all());

            $this->assertSame(0, (int) $rows[0]->pausa_descartada_segundos);
            $this->assertSame(0, (int) $rows[1]->pausa_descartada_segundos);
            $this->assertSame(30, (int) $rows[2]->pausa_descartada_segundos);
            $this->assertSame(30, (int) $rows[3]->pausa_descartada_segundos);

            // El panel usa AVG(duracion_activa_segundos), luego convierte a minutos.
            $panel = $service->registration('clientes', 'Registro de clientes', '2026-10-21', '2026-10-21', '', '', '', '');
            $this->assertSame(4, $panel['count']);
            $this->assertEqualsWithDelta(85 / 60, $panel['average'], 0.000001);
        } finally {
            $this->travelBack();
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
    public function test_inventory_snapshot_does_not_require_an_optional_material_status_column(): void
    {
        DB::table('materiales')->insert([
            ['nombre'=>'Cable de fibra','stock_actual'=>2,'stock_minimo'=>5,'unidad_medida'=>'m'],
            ['nombre'=>'Conector','stock_actual'=>8,'stock_minimo'=>2,'unidad_medida'=>'unidad'],
        ]);

        $dashboard=app(AnalyticsService::class)->dashboard('2001-01-01','2001-01-31','inventario');

        $this->assertCount(2,$dashboard['materials']);
        $this->assertSame('Cable de fibra',$dashboard['materials']->first()->nombre);
    }
    public function test_material_chart_matches_the_active_materials_report(): void
    {
        Schema::table('materiales', function (Blueprint $table): void {
            $table->string('codigo')->nullable();
            $table->string('estado')->nullable();
            $table->dateTime('creado_en')->nullable();
        });
        DB::table('materiales')->insert([
            ['codigo'=>'MAT-01','nombre'=>'Cable activo','stock_actual'=>8,'stock_minimo'=>2,'unidad_medida'=>'m','unidad_base'=>'m','estado'=>'ACTIVO','creado_en'=>'2001-01-15 09:00:00'],
            ['codigo'=>'MAT-02','nombre'=>'Cable inactivo','stock_actual'=>8,'stock_minimo'=>2,'unidad_medida'=>'m','unidad_base'=>'m','estado'=>'INACTIVO','creado_en'=>'2001-01-15 09:00:00'],
        ]);

        $chart=app(BusinessIntelligenceService::class)->chart('materiales','mes','bar','cantidad','2001-01-01','2001-01-31');
        $report=app(ReportExportService::class)->report('materiales_existencias','2001-01-01','2001-01-31');

        $this->assertSame(1.0,$chart['total']);
        $this->assertSame(1,$report['total']);
    }
    public function test_kardex_report_shows_the_responsible_name_instead_of_the_user_id(): void
    {
        Schema::table('equipos', function (Blueprint $table): void {
            $table->string('codigo')->nullable();
            $table->string('nombre')->nullable();
        });
        Schema::table('materiales', function (Blueprint $table): void {
            $table->string('codigo')->nullable();
        });
        Schema::table('movimientos_inventario', function (Blueprint $table): void {
            $table->string('tipo_movimiento')->nullable();
            $table->decimal('cantidad', 12, 3)->nullable();
        });

        DB::table('empleados')->insert(['empleado_id' => 8, 'nombres' => 'Rosa', 'apellidos' => 'Quispe', 'cargo' => 'Almacén']);
        DB::table('usuarios')->insert(['usuario_id' => 8, 'empleado_id' => 8, 'nombre_usuario' => 'rosa.quispe']);
        DB::table('equipos')->insert(['equipo_id' => 4, 'codigo' => 'EQ-004', 'nombre' => 'Router', 'estado' => 'DISPONIBLE']);
        DB::table('movimientos_inventario')->insert([
            'movimiento_id' => 1, 'responsable_id' => 8, 'equipo_id' => 4,
            'tipo_movimiento' => 'ENTRADA', 'cantidad' => 1, 'fecha_movimiento' => '2001-01-15 09:00:00',
        ]);

        $report = app(ReportExportService::class)->report('kardex_movimientos', '2001-01-01', '2001-01-31');

        $this->assertSame('Responsable', $report['columns'][7]);
        $this->assertSame('Rosa Quispe', $report['rows'][0][7]);
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

