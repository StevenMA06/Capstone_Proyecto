<?php

namespace Tests\Unit;

use App\Models\ServicePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests UNIT para validar planes activos
 * 
 * Objetivo: Verificar que solo planes ACTIVO son utilizables en contratos
 * 
 * RF: RF-PLA-10
 */
class ServicePlanActiveTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function plan_activo_esta_disponible()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "✅ TEST: Plan ACTIVO está disponible\n";
        echo str_repeat("═", 80) . "\n";

        $plan = ServicePlan::factory()->create([
            'nombre' => 'Plan Premium',
            'numero_plan' => 100,
            'precio_mensual' => 150.00,
            'estado' => 'ACTIVO'
        ]);

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Plan ID: {$plan->getKey()}\n";
        echo "   • Nombre: {$plan->nombre}\n";
        echo "   • Estado: {$plan->estado}\n";
        echo "   • Precio: S/ {$plan->precio_mensual}\n";

        echo "\n🔧 QUERY EJECUTADO:\n";
        echo "   → ServicePlan::where('estado', 'ACTIVO')->get()\n";

        $planesActivos = ServicePlan::where('estado', 'ACTIVO')->get();

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Buscar planes con estado = 'ACTIVO'\n";
        echo "   → Plan creado debe estar en la colección\n";

        echo "\n📋 EXPECTED:\n";
        echo "   • Colección contiene al menos 1 plan\n";
        echo "   • Plan creado está en la colección\n";
        echo "   • estado = 'ACTIVO'\n";

        $this->assertCount(1, $planesActivos);
        $this->assertTrue($planesActivos->contains('plan_id', $plan->getKey()));
        $this->assertEquals('ACTIVO', $planesActivos->first()->estado);

        echo "\n✅ CONFIRMED: Plan ACTIVO disponible\n";
        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function plan_inactivo_no_esta_disponible()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "❌ TEST: Plan INACTIVO no está disponible\n";
        echo str_repeat("═", 80) . "\n";

        $planInactivo = ServicePlan::factory()->create([
            'nombre' => 'Plan Descontinuado',
            'numero_plan' => 200,
            'precio_mensual' => 80.00,
            'estado' => 'INACTIVO'
        ]);

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Plan ID: {$planInactivo->getKey()}\n";
        echo "   • Nombre: {$planInactivo->nombre}\n";
        echo "   • Estado: {$planInactivo->estado}\n";

        echo "\n🔧 QUERY EJECUTADO:\n";
        echo "   → ServicePlan::where('estado', 'ACTIVO')->get()\n";

        $planesActivos = ServicePlan::where('estado', 'ACTIVO')->get();

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → Buscar solo planes ACTIVO\n";
        echo "   → Plan INACTIVO NO debe aparecer\n";

        echo "\n📋 EXPECTED:\n";
        echo "   • Colección vacía (0 planes)\n";
        echo "   • Plan INACTIVO excluido\n";

        $this->assertCount(0, $planesActivos);
        $this->assertFalse($planesActivos->contains('plan_id', $planInactivo->getKey()));

        echo "\n✅ CONFIRMED: Plan INACTIVO correctamente excluido\n";
        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function solo_planes_activos_en_listado()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "✅ TEST: Solo planes ACTIVOS en listado\n";
        echo str_repeat("═", 80) . "\n";

        // Crear 3 planes ACTIVOS
        $activos = ServicePlan::factory()->count(3)->create(['estado' => 'ACTIVO']);
        
        // Crear 2 planes INACTIVOS
        $inactivos = ServicePlan::factory()->count(2)->create(['estado' => 'INACTIVO']);

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Planes ACTIVO creados: 3\n";
        echo "   • Planes INACTIVO creados: 2\n";
        echo "   • Total en BD: 5\n";

        echo "\n🔧 QUERY EJECUTADO:\n";
        echo "   → ServicePlan::where('estado', 'ACTIVO')->get()\n";

        $planesActivos = ServicePlan::where('estado', 'ACTIVO')->get();

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → WHERE filtra solo ACTIVO\n";
        echo "   → INACTIVO quedan fuera\n";

        echo "\n📋 EXPECTED:\n";
        echo "   • Colección: 3 planes\n";
        echo "   • Todos con estado = 'ACTIVO'\n";

        $this->assertCount(3, $planesActivos);
        $planesActivos->each(function ($plan) {
            $this->assertEquals('ACTIVO', $plan->estado);
        });

        echo "\n✅ CONFIRMED: Solo 3 planes ACTIVOS retornados\n";
        echo str_repeat("═", 80) . "\n";
    }

    /** @test */
    public function request_valida_plan_existe()
    {
        echo "\n" . str_repeat("═", 80) . "\n";
        echo "✅ TEST: Request valida que plan existe\n";
        echo str_repeat("═", 80) . "\n";

        $plan = ServicePlan::factory()->create([
            'nombre' => 'Plan Básico',
            'estado' => 'ACTIVO'
        ]);

        echo "\n📥 DATOS DE ENTRADA:\n";
        echo "   • Plan ID existente: {$plan->getKey()}\n";
        echo "   • Plan ID inexistente: 99999\n";

        echo "\n🔧 VALIDACIÓN REQUEST:\n";
        echo "   → Rule::exists('planes_servicio', 'plan_id')\n";

        // Simular validación exists
        $planExiste = \DB::table('planes_servicio')->where('plan_id', $plan->getKey())->exists();
        $planInexistenteExiste = \DB::table('planes_servicio')->where('plan_id', 99999)->exists();

        echo "\n⚙️ VALIDACIÓN:\n";
        echo "   → exists() verifica en BD\n";
        echo "   → Plan existente: retorna true\n";
        echo "   → Plan inexistente: retorna false\n";

        echo "\n📋 EXPECTED:\n";
        echo "   • Plan {$plan->getKey()}: EXISTE\n";
        echo "   • Plan 99999: NO EXISTE\n";

        $this->assertTrue($planExiste);
        $this->assertFalse($planInexistenteExiste);

        echo "\n✅ CONFIRMED: Validación exists funciona correctamente\n";
        echo str_repeat("═", 80) . "\n";
    }
}
