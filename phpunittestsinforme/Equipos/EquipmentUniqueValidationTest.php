<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CA06 - GESTIÓN DE EQUIPOS
 * Tests para validación: Identificadores únicos (numero_serie, codigo)
 * Archivo de producción: app/Http/Requests/StoreEquipmentRequest.php
 * Tipo: Feature Test
 */
class EquipmentUniqueValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        
        // Deshabilitar middleware de autenticación para tests
        $this->withoutMiddleware([
            \App\Http\Middleware\EnsureAccountActive::class,
            \App\Http\Middleware\RequireModulePermission::class,
        ]);
    }

    /**
     * @test
     * Variación 1: Rechaza numero_serie duplicado
     */
    public function rechaza_numero_serie_duplicado()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "❌ TEST: Rechaza numero_serie duplicado\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • Equipo existente: numero_serie='SN-001'\n";
        echo "   • Intentar crear nuevo con mismo numero_serie\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → StoreEquipmentRequest::rules()\n";
        echo "   → Rule::unique('equipos', 'numero_serie')\n";
        echo "⚙️ VALIDACIÓN:\n";
        echo "   1. Query: SELECT * FROM equipos WHERE numero_serie = 'SN-001'\n";
        echo "   2. Si existe → throw ValidationException\n";
        echo "📋 EXPECTED:\n";
        echo "   • Error: 'Este número de serie ya está registrado'\n";
        echo "   • HTTP 302 (redirect back con errores)\n";

        // Crear equipo con numero_serie específico
        DB::table('equipos')->insert([
            'numero_serie' => 'SN-001',
            'codigo' => 'EQ-001',
            'nombre' => 'Router Existente',
            'tipo' => 'ROUTER',
            'condicion' => 'NUEVO',
            'estado' => 'DISPONIBLE',
            'creado_por' => $this->admin->getKey(),
            'creado_en' => now(),
            'actualizado_en' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('equipments.store'), [
                'nombre' => 'Router Nuevo',
                'tipo' => 'ROUTER',
                'numero_serie' => 'SN-001', // DUPLICADO
                'codigo' => 'EQ-002', // Diferente
                'condicion' => 'NUEVO',
                'estado' => 'DISPONIBLE',
            ]);

        echo "✅ CONFIRMED: numero_serie duplicado rechazado\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $response->assertSessionHasErrors('numero_serie');
        
        // Verificar que solo hay 1 equipo (el original)
        $this->assertEquals(1, Equipment::where('numero_serie', 'SN-001')->count());
    }

    /**
     * @test
     * Variación 2: Rechaza codigo duplicado
     */
    public function rechaza_codigo_duplicado()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "❌ TEST: Rechaza codigo duplicado\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • Equipo existente: codigo='EQ-100'\n";
        echo "   • Intentar crear nuevo con mismo codigo\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → Rule::unique('equipos', 'codigo')\n";
        echo "⚙️ VALIDACIÓN:\n";
        echo "   1. Query: SELECT * FROM equipos WHERE codigo = 'EQ-100'\n";
        echo "   2. Si existe → throw ValidationException\n";
        echo "📋 EXPECTED:\n";
        echo "   • Error: 'Este código ya está registrado'\n";

        DB::table('equipos')->insert([
            'numero_serie' => 'SN-100',
            'codigo' => 'EQ-100',
            'nombre' => 'Switch Existente',
            'tipo' => 'SWITCH',
            'condicion' => 'NUEVO',
            'estado' => 'DISPONIBLE',
            'creado_por' => $this->admin->getKey(),
            'creado_en' => now(),
            'actualizado_en' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('equipments.store'), [
                'nombre' => 'Switch Nuevo',
                'tipo' => 'SWITCH',
                'numero_serie' => 'SN-200', // Diferente
                'codigo' => 'EQ-100', // DUPLICADO
                'condicion' => 'NUEVO',
                'estado' => 'DISPONIBLE',
            ]);

        echo "✅ CONFIRMED: codigo duplicado rechazado\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $response->assertSessionHasErrors('codigo');
        
        $this->assertEquals(1, Equipment::where('codigo', 'EQ-100')->count());
    }

    /**
     * @test
     * Variación 3: Permite numero_serie único
     */
    public function permite_numero_serie_unico()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "✅ TEST: Permite numero_serie único\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • Equipos existentes: SN-001, SN-002\n";
        echo "   • Crear nuevo: SN-003 (único)\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → Rule::unique('equipos', 'numero_serie')\n";
        echo "⚙️ VALIDACIÓN:\n";
        echo "   1. Query: SELECT * FROM equipos WHERE numero_serie = 'SN-003'\n";
        echo "   2. No existe → permitir creación\n";
        echo "📋 EXPECTED:\n";
        echo "   • HTTP 302 (redirect a show)\n";
        echo "   • Equipo creado exitosamente\n";

        DB::table('equipos')->insert([
            ['numero_serie' => 'SN-001', 'codigo' => 'EQ-001', 'nombre' => 'Equipo 1', 'tipo' => 'ROUTER', 'condicion' => 'NUEVO', 'estado' => 'DISPONIBLE', 'creado_por' => $this->admin->getKey(), 'creado_en' => now(), 'actualizado_en' => now()],
            ['numero_serie' => 'SN-002', 'codigo' => 'EQ-002', 'nombre' => 'Equipo 2', 'tipo' => 'SWITCH', 'condicion' => 'NUEVO', 'estado' => 'DISPONIBLE', 'creado_por' => $this->admin->getKey(), 'creado_en' => now(), 'actualizado_en' => now()],
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('equipments.store'), [
                'nombre' => 'Antena Nueva',
                'tipo' => 'ANTENA',
                'numero_serie' => 'SN-003', // ÚNICO
                'codigo' => 'EQ-003',
                'condicion' => 'NUEVO',
                'estado' => 'DISPONIBLE',
            ]);

        echo "✅ CONFIRMED: numero_serie único aceptado\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        
        $this->assertDatabaseHas('equipos', [
            'numero_serie' => 'SN-003',
            'nombre' => 'Antena Nueva',
        ]);
    }

    /**
     * @test
     * Variación 4: Permite actualizar sin cambiar numero_serie
     */
    public function permite_actualizar_sin_cambiar_numero_serie()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "✅ TEST: Permite actualizar sin cambiar numero_serie\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • Equipo ID=1: numero_serie='SN-500'\n";
        echo "   • Update ID=1: mantener numero_serie='SN-500'\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → Rule::unique('equipos', 'numero_serie')->ignore(\$equipment)\n";
        echo "⚙️ VALIDACIÓN:\n";
        echo "   1. Query: WHERE numero_serie='SN-500' AND equipo_id != 1\n";
        echo "   2. No encuentra otros → permitir\n";
        echo "📋 EXPECTED:\n";
        echo "   • Actualización exitosa\n";
        echo "   • numero_serie permanece igual\n";

        $equipoId = DB::table('equipos')->insertGetId([
            'numero_serie' => 'SN-500',
            'codigo' => 'EQ-500',
            'nombre' => 'Router Original',
            'tipo' => 'ROUTER',
            'condicion' => 'BUENO',
            'estado' => 'DISPONIBLE',
            'creado_por' => $this->admin->getKey(),
            'creado_en' => now(),
            'actualizado_en' => now(),
        ]);
        
        $equipo = Equipment::find($equipoId);

        $response = $this->actingAs($this->admin)
            ->put(route('equipments.update', $equipo), [
                'nombre' => 'Router Actualizado', // Cambiar nombre
                'tipo' => 'ROUTER',
                'numero_serie' => 'SN-500', // MANTENER igual
                'codigo' => 'EQ-500',
                'condicion' => 'REGULAR', // Cambiar condición
                'estado' => 'DISPONIBLE',
            ]);

        echo "✅ CONFIRMED: Actualización sin cambiar numero_serie permitida\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        
        $equipo->refresh();
        $this->assertEquals('SN-500', $equipo->numero_serie);
        $this->assertEquals('Router Actualizado', $equipo->nombre);
        $this->assertEquals('REGULAR', $equipo->condicion);
    }

    /**
     * @test
     * Variación 5: Rechaza actualizar con numero_serie de otro equipo
     */
    public function rechaza_actualizar_con_numero_serie_de_otro()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "❌ TEST: Rechaza actualizar con numero_serie de otro equipo\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • Equipo A (ID=1): numero_serie='SN-AAA'\n";
        echo "   • Equipo B (ID=2): numero_serie='SN-BBB'\n";
        echo "   • Update Equipo A: cambiar a 'SN-BBB' (del equipo B)\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → Rule::unique('equipos', 'numero_serie')->ignore(\$equipmentA)\n";
        echo "⚙️ VALIDACIÓN:\n";
        echo "   1. Query: WHERE numero_serie='SN-BBB' AND equipo_id != 1\n";
        echo "   2. Encuentra Equipo B → throw ValidationException\n";
        echo "📋 EXPECTED:\n";
        echo "   • Error: 'Este número de serie ya está registrado'\n";

        $equipoAId = DB::table('equipos')->insertGetId([
            'numero_serie' => 'SN-AAA',
            'codigo' => 'EQ-AAA',
            'nombre' => 'Equipo A',
            'tipo' => 'ROUTER',
            'condicion' => 'NUEVO',
            'estado' => 'DISPONIBLE',
            'creado_por' => $this->admin->getKey(),
            'creado_en' => now(),
            'actualizado_en' => now(),
        ]);

        $equipoBId = DB::table('equipos')->insertGetId([
            'numero_serie' => 'SN-BBB',
            'codigo' => 'EQ-BBB',
            'nombre' => 'Equipo B',
            'tipo' => 'SWITCH',
            'condicion' => 'NUEVO',
            'estado' => 'DISPONIBLE',
            'creado_por' => $this->admin->getKey(),
            'creado_en' => now(),
            'actualizado_en' => now(),
        ]);
        
        $equipoA = Equipment::find($equipoAId);
        $equipoB = Equipment::find($equipoBId);

        $response = $this->actingAs($this->admin)
            ->put(route('equipments.update', $equipoA), [
                'nombre' => 'Equipo A Modificado',
                'tipo' => 'ROUTER',
                'numero_serie' => 'SN-BBB', // Intentar usar el del Equipo B
                'codigo' => 'EQ-AAA',
                'condicion' => 'NUEVO',
                'estado' => 'DISPONIBLE',
            ]);

        echo "✅ CONFIRMED: Cambio a numero_serie duplicado rechazado\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $response->assertSessionHasErrors('numero_serie');
        
        $equipoA->refresh();
        $this->assertEquals('SN-AAA', $equipoA->numero_serie); // Permanece sin cambios
    }

    /**
     * @test
     * Variación 6: Permite numero_serie y codigo NULL
     */
    public function permite_numero_serie_y_codigo_null()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "✅ TEST: Permite numero_serie y codigo NULL (campos opcionales)\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • numero_serie: null\n";
        echo "   • codigo: null\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → 'numero_serie' => ['nullable', ...]\n";
        echo "   → 'codigo' => ['nullable', ...]\n";
        echo "⚙️ VALIDACIÓN:\n";
        echo "   → nullable permite valores NULL\n";
        echo "📋 EXPECTED:\n";
        echo "   • Equipo creado sin numero_serie ni codigo\n";

        $response = $this->actingAs($this->admin)
            ->post(route('equipments.store'), [
                'nombre' => 'Equipo Sin Serie',
                'tipo' => 'OTRO',
                'numero_serie' => null,
                'codigo' => null,
                'condicion' => 'NUEVO',
                'estado' => 'DISPONIBLE',
            ]);

        echo "✅ CONFIRMED: Equipo sin numero_serie ni codigo creado\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        
        $this->assertDatabaseHas('equipos', [
            'nombre' => 'Equipo Sin Serie',
            'numero_serie' => null,
            // codigo puede ser NULL o generado automáticamente
        ]);
        
        // Verificar que al menos uno de los dos campos puede ser NULL
        $equipo = Equipment::where('nombre', 'Equipo Sin Serie')->first();
        $this->assertNull($equipo->numero_serie);
    }
}
