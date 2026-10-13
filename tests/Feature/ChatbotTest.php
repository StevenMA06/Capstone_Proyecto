<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Equipment;
use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $restrictedUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear roles y permisos
        $this->seedRolesAndPermissions();

        // Usuario con todos los permisos
        $this->user = User::factory()->create();
        $adminRole = Role::where('nombre', 'Administrador')->first();
        $this->user->roles()->attach($adminRole);

        // Usuario con permisos limitados (solo clientes)
        $this->restrictedUser = User::factory()->create();
        $restrictedRole = Role::where('nombre', 'Vendedor')->first();
        $this->restrictedUser->roles()->attach($restrictedRole);

        // Crear datos de prueba
        $this->seedTestData();
    }

    private function seedRolesAndPermissions(): void
    {
        // Crear permisos
        $permissions = [
            'ver_clientes',
            'ver_contratos',
            'ver_pagos',
            'ver_ventas',
            'ver_equipos',
            'ver_materiales',
            'ver_instalaciones',
        ];

        foreach ($permissions as $permissionName) {
            Permission::create(['nombre' => $permissionName]);
        }

        // Crear rol Administrador con todos los permisos
        $adminRole = Role::create(['nombre' => 'Administrador']);
        $adminRole->permissions()->attach(Permission::all());

        // Crear rol Vendedor con permisos limitados
        $vendedorRole = Role::create(['nombre' => 'Vendedor']);
        $vendedorRole->permissions()->attach(
            Permission::whereIn('nombre', ['ver_clientes', 'ver_contratos', 'ver_ventas'])->get()
        );
    }

    private function seedTestData(): void
    {
        Client::factory()->count(10)->create(['estado' => 'ACTIVO']);
        Contract::factory()->count(5)->create(['estado' => 'ACTIVO']);
        Payment::factory()->count(15)->create();
        Equipment::factory()->count(20)->create(['estado' => 'DISPONIBLE']);
        Material::factory()->count(10)->create(['estado' => 'ACTIVO']);
    }

    /** @test */
    public function usuario_autenticado_puede_acceder_al_chatbot()
    {
        $response = $this->actingAs($this->user)->get('/chatbot');

        $response->assertStatus(200);
        $response->assertViewIs('chatbot.index');
    }

    /** @test */
    public function usuario_no_autenticado_no_puede_acceder_al_chatbot()
    {
        $response = $this->get('/chatbot');

        $response->assertRedirect('/login');
    }

    // ==========================================
    // RF-IA-03: queryOperationalData
    // ==========================================

    /** @test */
    public function puede_consultar_datos_operativos_de_clientes()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/chatbot/operational-data', [
                'query' => '¿Cuántos clientes tengo?',
                'module' => 'clientes'
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'module' => 'clientes'
        ]);
        $response->assertJsonStructure([
            'success',
            'query',
            'module',
            'data' => [
                'total_clientes',
                'clientes_activos',
                'clientes_inactivos',
                'nuevos_mes_actual'
            ],
            'timestamp'
        ]);
    }

    /** @test */
    public function puede_consultar_datos_operativos_de_contratos()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/chatbot/operational-data', [
                'query' => 'Dame información de contratos',
                'module' => 'contratos'
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'total_contratos',
                'contratos_activos',
                'contratos_finalizados',
                'contratos_cancelados'
            ]
        ]);
    }

    /** @test */
    public function usuario_sin_permisos_no_puede_consultar_pagos()
    {
        $response = $this->actingAs($this->restrictedUser)
            ->postJson('/chatbot/operational-data', [
                'query' => 'Datos de pagos',
                'module' => 'pagos'
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'reason' => 'permission_denied'
        ]);
    }

    /** @test */
    public function rechaza_modulo_invalido()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/chatbot/operational-data', [
                'query' => 'Datos',
                'module' => 'modulo_inexistente'
            ]);

        $response->assertStatus(422); // Validation error
    }

    // ==========================================
    // RF-IA-04: queryInventory
    // ==========================================

    /** @test */
    public function puede_consultar_inventario_de_equipos()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/chatbot/inventory', [
                'query' => '¿Cuántos equipos hay?',
                'item_type' => 'equipos'
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'item_type' => 'equipos'
        ]);
        $response->assertJsonStructure([
            'data' => [
                'equipos' => [
                    'total_equipos',
                    'disponibles',
                    'asignados',
                    'mantenimiento',
                    'inhabilitados',
                    'por_tipo'
                ]
            ]
        ]);
    }

    /** @test */
    public function puede_consultar_inventario_de_materiales()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/chatbot/inventory', [
                'query' => 'Estado de materiales',
                'item_type' => 'materiales'
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'materiales' => [
                    'total_materiales',
                    'bajo_stock',
                    'sin_stock',
                    'valor_total_inventario',
                    'materiales_bajo_stock'
                ]
            ]
        ]);
    }

    /** @test */
    public function puede_consultar_inventario_con_filtros()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/chatbot/inventory', [
                'query' => 'Equipos disponibles',
                'item_type' => 'equipos',
                'filters' => [
                    'estado' => 'DISPONIBLE'
                ]
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'equipos' => [
                    'resultados'
                ]
            ]
        ]);
    }

    /** @test */
    public function usuario_sin_permisos_no_puede_consultar_inventario()
    {
        // Usuario sin permisos de equipos ni materiales
        $noAccessUser = User::factory()->create();
        
        $response = $this->actingAs($noAccessUser)
            ->postJson('/chatbot/inventory', [
                'query' => 'Inventario',
                'item_type' => 'equipos'
            ]);

        $response->assertStatus(403);
    }

    // ==========================================
    // RF-IA-08: checkPermissions
    // ==========================================

    /** @test */
    public function verifica_permisos_correctamente_para_usuario_con_acceso()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/chatbot/check-permissions', [
                'module' => 'contratos'
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'has_permission' => true,
            'module' => 'contratos',
            'message' => 'Acceso permitido'
        ]);
    }

    /** @test */
    public function verifica_permisos_correctamente_para_usuario_sin_acceso()
    {
        $response = $this->actingAs($this->restrictedUser)
            ->postJson('/chatbot/check-permissions', [
                'module' => 'pagos'
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'has_permission' => false,
            'module' => 'pagos'
        ]);
    }

    /** @test */
    public function rechaza_modulo_no_reconocido_en_check_permissions()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/chatbot/check-permissions', [
                'module' => 'modulo_falso'
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'has_permission' => false
        ]);
    }

    /** @test */
    public function rechaza_usuario_no_autenticado_en_check_permissions()
    {
        $response = $this->postJson('/chatbot/check-permissions', [
            'module' => 'contratos'
        ]);

        $response->assertStatus(401);
    }

    // ==========================================
    // RF-IA-05: queryIndicators
    // ==========================================

    /** @test */
    public function puede_consultar_indicadores_de_pagos()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/chatbot/indicators', [
                'query' => 'Indicadores de pagos',
                'indicator_type' => 'pagos'
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'indicators' => [
                'pagos' => [
                    'pendientes',
                    'vencidos',
                    'realizados',
                    'tasa_cumplimiento',
                    'monto_por_cobrar'
                ]
            ]
        ]);
    }

    /** @test */
    public function puede_consultar_todos_los_indicadores()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/chatbot/indicators', [
                'query' => 'Todos los indicadores'
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'indicators' => [
                'ventas',
                'instalaciones',
                'pagos'
            ]
        ]);
    }

    // ==========================================
    // RF-IA-06: queryBI
    // ==========================================

    /** @test */
    public function puede_consultar_panel_general_de_bi()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/chatbot/bi', [
                'query' => 'Dashboard general',
                'panel' => 'general'
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'dashboard_data' => [
                'clientes_totales',
                'contratos_activos',
                'ventas_mes',
                'instalaciones_completadas',
                'pagos_pendientes',
                'equipos_disponibles'
            ]
        ]);
    }

    /** @test */
    public function puede_consultar_panel_de_ventas()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/chatbot/bi', [
                'query' => 'Panel de ventas',
                'panel' => 'ventas'
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'dashboard_data' => [
                'ventas_hoy',
                'ventas_semana',
                'ventas_mes',
                'monto_total_mes'
            ]
        ]);
    }

    // ==========================================
    // RF-IA-07: reportNoData
    // ==========================================

    /** @test */
    public function reporta_ausencia_de_datos_correctamente()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/chatbot/no-data', [
                'query' => 'Datos del año 2020',
                'reason' => 'periodo_sin_datos'
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'reason_code' => 'periodo_sin_datos'
        ]);
        $response->assertJsonStructure([
            'message',
            'sugerencias'
        ]);
    }

    /** @test */
    public function reporta_sin_registros()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/chatbot/no-data', [
                'query' => 'Búsqueda sin resultados',
                'reason' => 'sin_registros'
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'message' => 'No se encontraron registros que coincidan con la consulta'
        ]);
    }

    // ==========================================
    // RF-IA-09: showDataSource
    // ==========================================

    /** @test */
    public function muestra_fuente_de_datos_correctamente()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/chatbot/data-source', [
                'query' => '¿De dónde vienen estos datos?',
                'module' => 'contratos'
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data_source' => [
                'module' => 'contratos',
                'tabla_origen' => 'contratos',
                'modelo_laravel' => 'Contract'
            ]
        ]);
        $response->assertJsonStructure([
            'data_source' => [
                'ultima_actualizacion',
                'tipo_base_datos',
                'nota'
            ]
        ]);
    }

    /** @test */
    public function rechaza_modulo_invalido_en_data_source()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/chatbot/data-source', [
                'query' => 'Fuente',
                'module' => 'modulo_invalido'
            ]);

        $response->assertStatus(422); // Validation error
    }

    // ==========================================
    // RF-IA-10: startNewConversation
    // ==========================================

    /** @test */
    public function puede_iniciar_nueva_conversacion()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/chatbot/start-conversation');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true
        ]);
        $response->assertJsonStructure([
            'success',
            'session_id',
            'user_id',
            'started_at',
            'message',
            'accessible_modules',
            'capabilities'
        ]);
    }

    /** @test */
    public function nueva_conversacion_retorna_modulos_accesibles()
    {
        $response = $this->actingAs($this->restrictedUser)
            ->postJson('/chatbot/start-conversation');

        $response->assertStatus(200);
        $data = $response->json();
        
        // El vendedor solo debe tener acceso a clientes, contratos y ventas
        $this->assertContains('clientes', $data['accessible_modules']);
        $this->assertContains('contratos', $data['accessible_modules']);
        $this->assertContains('ventas', $data['accessible_modules']);
        $this->assertNotContains('pagos', $data['accessible_modules']);
    }

    /** @test */
    public function nueva_conversacion_rechaza_usuario_no_autenticado()
    {
        $response = $this->postJson('/chatbot/start-conversation');

        $response->assertStatus(401);
    }

    // ==========================================
    // Tests de Rate Limiting
    // ==========================================

    /** @test */
    public function respeta_rate_limiting()
    {
        // Hacer 61 peticiones (límite es 60 por minuto)
        for ($i = 0; $i < 61; $i++) {
            $response = $this->actingAs($this->user)
                ->postJson('/chatbot/operational-data', [
                    'query' => "Consulta $i",
                    'module' => 'clientes'
                ]);
            
            if ($i < 60) {
                $response->assertStatus(200);
            } else {
                // La petición 61 debe ser rechazada
                $response->assertStatus(429); // Too Many Requests
            }
        }
    }

    // ==========================================
    // Tests de Validación
    // ==========================================

    /** @test */
    public function valida_campos_requeridos_en_operational_data()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/chatbot/operational-data', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['query', 'module']);
    }

    /** @test */
    public function valida_campos_requeridos_en_check_permissions()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/chatbot/check-permissions', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['module']);
    }

    /** @test */
    public function valida_tipos_de_datos_en_inventory()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/chatbot/inventory', [
                'query' => 'Inventario',
                'item_type' => 'tipo_invalido'
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['item_type']);
    }
}
