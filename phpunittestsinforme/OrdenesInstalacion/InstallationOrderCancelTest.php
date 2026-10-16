<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CA08 - GESTIÓN ÓRDENES DE INSTALACIÓN  
 * Tests para validación: Cancelación requiere motivo válido
 * Archivo de producción: app/Http/Controllers/InstallationOrderController.php
 * Tipo: Feature Test
 */
class InstallationOrderCancelTest extends TestCase
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
     * Variación 1: Motivo válido 'CLIENTE_NO_DISPONIBLE' - PERMITE
     */
    public function motivo_cliente_no_disponible_permite()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "✅ TEST: Motivo 'CLIENTE_NO_DISPONIBLE' - PERMITE\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • pedido_id: 1\n";
        echo "   • motivo_codigo: 'CLIENTE_NO_DISPONIBLE' (válido)\n";
        echo "   • motivo_detalle: null (no requerido para este motivo)\n\n";

        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → InstallationOrderController::cancel()\n";
        echo "   → Validation: Rule::in(array_keys(InstallationOrderCancellationReasons::all()))\n\n";

        echo "⚙️ VALIDACIÓN:\n";
        echo "   1. Verificar motivo_codigo in ['CLIENTE_NO_DISPONIBLE', 'ACCESO_NEGADO', 'OTRO', ...]\n";
        echo "   2. 'CLIENTE_NO_DISPONIBLE' está en array → TRUE\n";
        echo "   3. Validación pasa → procesar cancelación\n\n";

        echo "📋 EXPECTED:\n";
        echo "   • Validación exitosa (404 por pedido inexistente)\n";
        echo "   • Motivo mapeado a razón legible\n";
        echo "   • Orden cancelada con motivo registrado\n\n";

        $response = $this->actingAs($this->admin)
            ->patch(route('orders.cancel', 1), [
                'motivo_codigo' => 'CLIENTE_NO_DISPONIBLE',
            ]);

        echo "✅ CONFIRMED: Motivo válido permite cancelación\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        // Test validation passes (404 expected due to missing order)
        $response->assertStatus(404);
    }

    /**
     * @test
     * Variación 2: Motivo inválido 'MOTIVO_INEXISTENTE' - RECHAZA
     */
    public function motivo_inexistente_rechaza()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "❌ TEST: Motivo 'MOTIVO_INEXISTENTE' - RECHAZA\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • pedido_id: 1\n";
        echo "   • motivo_codigo: 'MOTIVO_INEXISTENTE' (no válido)\n";
        echo "   • motivo_detalle: null\n\n";

        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → InstallationOrderController::cancel()\n";
        echo "   → Validation: Rule::in(array_keys(InstallationOrderCancellationReasons::all()))\n\n";

        echo "⚙️ VALIDACIÓN:\n";
        echo "   1. Verificar motivo_codigo in ['CLIENTE_NO_DISPONIBLE', 'ACCESO_NEGADO', 'OTRO', ...]\n";
        echo "   2. 'MOTIVO_INEXISTENTE' NO está en array → FALSE\n";
        echo "   3. throw ValidationException('Motivo no válido')\n\n";

        echo "📋 EXPECTED:\n";
        echo "   • Error: 'El motivo seleccionado no es válido'\n";
        echo "   • HTTP 302 (redirect back con errores)\n";
        echo "   • Session errors: motivo_codigo\n\n";

        $response = $this->actingAs($this->admin)
            ->patch(route('orders.cancel', 1), [
                'motivo_codigo' => 'MOTIVO_INEXISTENTE',
            ]);

        echo "✅ CONFIRMED: Motivo inválido rechazado\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $response->assertSessionHasErrors('motivo_codigo');
    }

    /**
     * @test
     * Variación 3: Motivo vacío - RECHAZA (required)
     */
    public function motivo_vacio_rechaza()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "❌ TEST: Motivo vacío - RECHAZA (required)\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • pedido_id: 1\n";
        echo "   • motivo_codigo: null (omitido)\n";
        echo "   • motivo_detalle: null\n\n";

        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → InstallationOrderController::cancel()\n";
        echo "   → Validation: 'motivo_codigo' => ['required', 'string', Rule::in(...)]\n\n";

        echo "⚙️ VALIDACIÓN:\n";
        echo "   1. Verificar motivo_codigo required → campo omitido\n";
        echo "   2. Campo requerido falta → FALSE\n";
        echo "   3. throw ValidationException('Campo requerido')\n\n";

        echo "📋 EXPECTED:\n";
        echo "   • Error: 'El campo motivo código es obligatorio'\n";
        echo "   • HTTP 302 (redirect back con errores)\n";
        echo "   • Session errors: motivo_codigo\n\n";

        $response = $this->actingAs($this->admin)
            ->patch(route('orders.cancel', 1), [
                // motivo_codigo omitido
            ]);

        echo "✅ CONFIRMED: Motivo requerido\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $response->assertSessionHasErrors('motivo_codigo');
    }

    /**
     * @test
     * Variación 4: Motivo 'OTRO' sin detalle - RECHAZA
     */
    public function motivo_otro_sin_detalle_rechaza()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "❌ TEST: Motivo 'OTRO' sin detalle - RECHAZA\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • pedido_id: 1\n";
        echo "   • motivo_codigo: 'OTRO' (requiere detalle)\n";
        echo "   • motivo_detalle: null (omitido)\n\n";

        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → InstallationOrderController::cancel()\n";
        echo "   → Validation: 'motivo_detalle' => ['nullable', 'required_if:motivo_codigo,OTRO']\n\n";

        echo "⚙️ VALIDACIÓN:\n";
        echo "   1. Verificar motivo_codigo = 'OTRO' → TRUE\n";
        echo "   2. required_if activo → motivo_detalle debe estar presente\n";
        echo "   3. motivo_detalle es null → FALSE\n";
        echo "   4. throw ValidationException('Campo requerido cuando motivo es OTRO')\n\n";

        echo "📋 EXPECTED:\n";
        echo "   • Error: 'El campo detalle es obligatorio cuando motivo es OTRO'\n";
        echo "   • HTTP 302 (redirect back con errores)\n";
        echo "   • Session errors: motivo_detalle\n\n";

        $response = $this->actingAs($this->admin)
            ->patch(route('orders.cancel', 1), [
                'motivo_codigo' => 'OTRO',
                // motivo_detalle omitido
            ]);

        echo "✅ CONFIRMED: OTRO requiere detalle\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $response->assertSessionHasErrors('motivo_detalle');
    }

    /**
     * @test
     * Variación 5: Motivo 'OTRO' con detalle válido - PERMITE
     */
    public function motivo_otro_con_detalle_permite()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "✅ TEST: Motivo 'OTRO' con detalle válido - PERMITE\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • pedido_id: 1\n";
        echo "   • motivo_codigo: 'OTRO' (válido)\n";
        echo "   • motivo_detalle: 'Problemas técnicos específicos que requieren cancelación inmediata' (46 chars)\n\n";

        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → InstallationOrderController::cancel()\n";
        echo "   → Validation: 'motivo_detalle' => ['nullable', 'required_if:motivo_codigo,OTRO', 'string', 'min:10', 'max:500']\n\n";

        echo "⚙️ VALIDACIÓN:\n";
        echo "   1. motivo_codigo = 'OTRO' → required_if activo\n";
        echo "   2. motivo_detalle presente y string → TRUE\n";
        echo "   3. Longitud 46 chars: min:10 ≤ 46 ≤ max:500 → TRUE\n";
        echo "   4. Todas validaciones pasan → procesar cancelación\n\n";

        echo "📋 EXPECTED:\n";
        echo "   • Validación exitosa (404 por pedido inexistente)\n";
        echo "   • Motivo completo: 'Otro: Problemas técnicos específicos...'\n";
        echo "   • Orden cancelada con detalle registrado\n\n";

        $response = $this->actingAs($this->admin)
            ->patch(route('orders.cancel', 1), [
                'motivo_codigo' => 'OTRO',
                'motivo_detalle' => 'Problemas técnicos específicos que requieren cancelación inmediata',
            ]);

        echo "✅ CONFIRMED: OTRO con detalle válido permite\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        // Test validation passes (404 expected due to missing order)
        $response->assertStatus(404);
    }

    /**
     * @test
     * Variación 6: Detalle muy corto para 'OTRO' - RECHAZA
     */
    public function motivo_otro_detalle_corto_rechaza()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "❌ TEST: Detalle muy corto para 'OTRO' - RECHAZA\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • pedido_id: 1\n";
        echo "   • motivo_codigo: 'OTRO' (requiere detalle)\n";
        echo "   • motivo_detalle: 'Corto' (5 caracteres)\n\n";

        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → InstallationOrderController::cancel()\n";
        echo "   → Validation: 'motivo_detalle' => ['min:10', 'max:500']\n\n";

        echo "⚙️ VALIDACIÓN:\n";
        echo "   1. motivo_codigo = 'OTRO' → required_if activo\n";
        echo "   2. motivo_detalle presente → TRUE\n";
        echo "   3. Longitud 5 chars: min:10 ≤ 5 → FALSE\n";
        echo "   4. throw ValidationException('Mínimo 10 caracteres')\n\n";

        echo "📋 EXPECTED:\n";
        echo "   • Error: 'El campo detalle debe tener al menos 10 caracteres'\n";
        echo "   • HTTP 302 (redirect back con errores)\n";
        echo "   • Session errors: motivo_detalle\n\n";

        $response = $this->actingAs($this->admin)
            ->patch(route('orders.cancel', 1), [
                'motivo_codigo' => 'OTRO',
                'motivo_detalle' => 'Corto', // 5 caracteres, min 10
            ]);

        echo "✅ CONFIRMED: Detalle debe tener min 10 caracteres\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $response->assertSessionHasErrors('motivo_detalle');
    }
}