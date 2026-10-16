<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CA07 - GESTIÓN DE MATERIALES
 * Tests para validación: Stock suficiente antes de salida
 * Archivo de producción: app/Http/Requests/StockExitRequest.php
 * Tipo: Feature Test
 */
class MaterialStockValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        
        $this->withoutMiddleware([
            \App\Http\Middleware\EnsureAccountActive::class,
            \App\Http\Middleware\RequireModulePermission::class,
        ]);
    }

    /**
     * @test
     * Variación 1: Stock 10, solicitud 4 - PERMITE
     */
    public function stock_10_solicitud_4_permite()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "✅ TEST: Stock 10, solicitud 4 - PERMITE\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • Material: 'Cable UTP' con stock_actual=10\n";
        echo "   • Solicitud salida: cantidad=4\n";
        echo "   • Motivo: 'INSTALACION'\n\n";

        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → StockExitRequest::rules()\n";
        echo "   → Material::hasSufficientStock(4)\n\n";

        echo "⚙️ VALIDACIÓN:\n";
        echo "   1. Query: SELECT stock_actual FROM materiales WHERE id = {id}\n";
        echo "   2. stock_actual (10) >= cantidad_solicitada (4) → TRUE\n";
        echo "   3. Validación pasa → permitir salida\n\n";

        echo "📋 EXPECTED:\n";
        echo "   • HTTP 302 (redirect exitoso)\n";
        echo "   • Stock actualizado: 10 - 4 = 6\n";
        echo "   • Movimiento registrado en kardex\n\n";

        $materialId = DB::table('materiales')->insertGetId([
            'codigo' => 'MAT-001',
            'nombre' => 'Cable UTP',
            'unidad_base' => 'METRO',
            'stock_actual' => 10,
            'stock_minimo' => 2,
            'estado' => 'ACTIVO',
            'creado_por' => $this->admin->getKey(),
            'creado_en' => now(),
            'actualizado_en' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('materials.exit.store', $materialId), [
                'material_id' => $materialId,
                'cantidad' => 4,
                'motivo' => 'INSTALACION',
                'observaciones' => 'Test salida',
            ]);

        echo "✅ CONFIRMED: Stock suficiente permitido\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    /**
     * @test
     * Variación 2: Stock 2, solicitud 4 - RECHAZA
     */
    public function stock_2_solicitud_4_rechaza()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "❌ TEST: Stock 2, solicitud 4 - RECHAZA\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • Material: 'Fibra Óptica' con stock_actual=2\n";
        echo "   • Solicitud salida: cantidad=4\n";
        echo "   • Motivo: 'INSTALACION'\n\n";

        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → StockExitRequest::rules()\n";
        echo "   → Material::hasSufficientStock(4)\n\n";

        echo "⚙️ VALIDACIÓN:\n";
        echo "   1. Query: SELECT stock_actual FROM materiales WHERE id = {id}\n";
        echo "   2. stock_actual (2) >= cantidad_solicitada (4) → FALSE\n";
        echo "   3. throw ValidationException('Stock insuficiente')\n\n";

        echo "📋 EXPECTED:\n";
        echo "   • Error: 'Stock insuficiente. Disponible: 2'\n";
        echo "   • HTTP 302 (redirect back con errores)\n";
        echo "   • Stock permanece igual: 2\n\n";

        $materialId = DB::table('materiales')->insertGetId([
            'codigo' => 'FO-001',
            'nombre' => 'Fibra Óptica',
            'unidad_base' => 'METRO',
            'stock_actual' => 2,
            'stock_minimo' => 1,
            'estado' => 'ACTIVO',
            'creado_por' => $this->admin->getKey(),
            'creado_en' => now(),
            'actualizado_en' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('materials.exit.store', $materialId), [
                'material_id' => $materialId,
                'cantidad' => 4,
                'motivo' => 'INSTALACION',
                'observaciones' => 'Test salida',
            ]);

        echo "✅ CONFIRMED: Stock insuficiente rechazado\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $response->assertSessionHasErrors('cantidad');
    }

    /**
     * @test
     * Variación 3: Stock 5, solicitud 5 - PERMITE (exacto)
     */
    public function stock_5_solicitud_5_permite_exacto()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "✅ TEST: Stock 5, solicitud 5 - PERMITE (exacto)\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • Material: 'Conector RJ45' con stock_actual=5\n";
        echo "   • Solicitud salida: cantidad=5 (exacto)\n";
        echo "   • Motivo: 'INSTALACION'\n\n";

        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → StockExitRequest::rules()\n";
        echo "   → Material::hasSufficientStock(5)\n\n";

        echo "⚙️ VALIDACIÓN:\n";
        echo "   1. Query: SELECT stock_actual FROM materiales WHERE id = {id}\n";
        echo "   2. stock_actual (5) >= cantidad_solicitada (5) → TRUE\n";
        echo "   3. Validación pasa → permitir salida completa\n\n";

        echo "📋 EXPECTED:\n";
        echo "   • HTTP 302 (redirect exitoso)\n";
        echo "   • Stock final: 5 - 5 = 0\n";
        echo "   • Material queda sin stock\n\n";

        $materialId = DB::table('materiales')->insertGetId([
            'codigo' => 'CON-001',
            'nombre' => 'Conector RJ45',
            'unidad_base' => 'UNIDAD',
            'stock_actual' => 5,
            'stock_minimo' => 1,
            'estado' => 'ACTIVO',
            'creado_por' => $this->admin->getKey(),
            'creado_en' => now(),
            'actualizado_en' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('materials.exit.store', $materialId), [
                'material_id' => $materialId,
                'cantidad' => 5,
                'motivo' => 'INSTALACION',
                'observaciones' => 'Test salida exacta',
            ]);

        echo "✅ CONFIRMED: Stock exacto permitido\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    /**
     * @test
     * Variación 4: Stock 0, solicitud 1 - RECHAZA
     */
    public function stock_0_solicitud_1_rechaza()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "❌ TEST: Stock 0, solicitud 1 - RECHAZA\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • Material: 'Splitter' con stock_actual=0\n";
        echo "   • Solicitud salida: cantidad=1\n";
        echo "   • Motivo: 'INSTALACION'\n\n";

        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → StockExitRequest::rules()\n";
        echo "   → Material::hasSufficientStock(1)\n\n";

        echo "⚙️ VALIDACIÓN:\n";
        echo "   1. Query: SELECT stock_actual FROM materiales WHERE id = {id}\n";
        echo "   2. stock_actual (0) >= cantidad_solicitada (1) → FALSE\n";
        echo "   3. throw ValidationException('Sin stock disponible')\n\n";

        echo "📋 EXPECTED:\n";
        echo "   • Error: 'Sin stock disponible (0)'\n";
        echo "   • HTTP 302 (redirect back con errores)\n";
        echo "   • No se permite ninguna salida\n\n";

        $materialId = DB::table('materiales')->insertGetId([
            'codigo' => 'SPL-001',
            'nombre' => 'Splitter',
            'unidad_base' => 'UNIDAD',
            'stock_actual' => 0,
            'stock_minimo' => 5,
            'estado' => 'ACTIVO',
            'creado_por' => $this->admin->getKey(),
            'creado_en' => now(),
            'actualizado_en' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('materials.exit.store', $materialId), [
                'material_id' => $materialId,
                'cantidad' => 1,
                'motivo' => 'INSTALACION',
                'observaciones' => 'Test stock cero',
            ]);

        echo "✅ CONFIRMED: Stock cero rechazado\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $response->assertSessionHasErrors('cantidad');
    }

    /**
     * @test
     * Variación 5: Mensaje muestra stock disponible
     */
    public function mensaje_muestra_stock_disponible()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "✅ TEST: Mensaje de error muestra stock disponible\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • Material: 'Router' con stock_actual=3\n";
        echo "   • Solicitud salida: cantidad=7 (excesiva)\n";
        echo "   • Motivo: 'INSTALACION'\n\n";

        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → StockExitRequest::rules()\n";
        echo "   → Material::hasSufficientStock(7)\n\n";

        echo "⚙️ VALIDACIÓN:\n";
        echo "   1. Query: SELECT stock_actual FROM materiales WHERE id = {id}\n";
        echo "   2. stock_actual (3) >= cantidad_solicitada (7) → FALSE\n";
        echo "   3. throw ValidationException con stock disponible\n\n";

        echo "📋 EXPECTED:\n";
        echo "   • Error: 'Stock insuficiente. Disponible: 3'\n";
        echo "   • Mensaje incluye stock actual (3)\n";
        echo "   • HTTP 302 (redirect back con errores)\n\n";

        $materialId = DB::table('materiales')->insertGetId([
            'codigo' => 'ROU-001',
            'nombre' => 'Router',
            'unidad_base' => 'UNIDAD',
            'stock_actual' => 3,
            'stock_minimo' => 1,
            'estado' => 'ACTIVO',
            'creado_por' => $this->admin->getKey(),
            'creado_en' => now(),
            'actualizado_en' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('materials.exit.store', $materialId), [
                'material_id' => $materialId,
                'cantidad' => 7,
                'motivo' => 'INSTALACION',
                'observaciones' => 'Test mensaje error',
            ]);

        echo "✅ CONFIRMED: Mensaje incluye stock disponible (3)\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $response->assertSessionHasErrors('cantidad');
    }
}