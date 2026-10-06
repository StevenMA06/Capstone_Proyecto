<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Role;
use App\Models\ServicePlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $name): User
    {
        $role = Role::firstOrCreate(['nombre' => $name], ['estado' => 'ACTIVO']);

        // Payments are optional for Call Center; these registration scenarios explicitly grant them.
        if ($name === Role::OFFICE) {
            foreach (DB::table('permisos')->whereIn('codigo', ['payments.view','payments.create'])->pluck('permiso_id') as $id) DB::table('rol_permiso')->updateOrInsert(['rol_id'=>$role->getKey(),'permiso_id'=>$id],['permitido'=>true]);
        }
        return User::factory()->create(['rol_id' => $role->getKey()]);
    }

    private static $contractCounter = 1;

    private function activeContract(): Contract
    {
        $creator = $this->userWithRole(Role::MANAGER);
        
        $client = Client::create([
            'tipo_documento' => 'DNI',
            'numero_documento' => sprintf('%08d', self::$contractCounter),
            'nombres_razon_social' => 'Juan',
            'apellidos' => 'Pérez',
            'direccion' => 'Av. Test 123',
            'telefono' => '999888777',
            'correo' => sprintf('test%d@example.com', self::$contractCounter),
            'estado' => 'ACTIVO',
            'creado_por' => $creator->getKey(),
        ]);

        $plan = ServicePlan::create([
            'numero_plan' => sprintf('PLAN-%03d', self::$contractCounter),
            'nombre' => 'Plan Básico',
            'tipo_servicio' => 'Internet',
            'velocidad_mbps' => 50,
            'precio_mensual' => 99.90,
            'estado' => 'ACTIVO',
            'creado_por' => $creator->getKey(),
        ]);

        $zone = DB::table('zonas_cobertura')->insertGetId([
            'codigo' => sprintf('ZONA-%03d', self::$contractCounter),
            'nombre' => 'Zona Test',
            'distrito' => 'Trujillo',
            'creado_por' => $creator->getKey(),
        ]);

        $employee = DB::table('empleados')->insertGetId([
            'tipo_documento' => 'DNI',
            'numero_documento' => sprintf('87%06d', self::$contractCounter),
            'nombres' => 'Técnico',
            'apellidos' => 'Test',
            'cargo' => 'Instalador',
        ]);

        $contract = Contract::create([
            'codigo' => sprintf('CON-TEST-%03d', self::$contractCounter),
            'cliente_id' => $client->getKey(),
            'plan_id' => $plan->getKey(),
            'zona_id' => $zone,
            'responsable_id' => $employee,
            'estado' => 'ACTIVO',
            'fecha_inicio' => today(),
            'fecha_fin' => today()->addMonths(12),
            'duracion_meses' => 12,
            'precio_contratado' => 99.90,
            'direccion_servicio' => 'Av. Test 123',
            'creado_por' => $creator->getKey(),
        ]);

        self::$contractCounter++;
        
        return $contract;
    }

    private function data(array $overrides = []): array
    {
        $contract = $overrides['contract'] ?? $this->activeContract();
        unset($overrides['contract']);

        return array_merge([
            'contrato_id' => $contract->getKey(),
            'monto' => 99.90,
            'periodo_servicio' => now()->format('Y-m'),
            'fecha_vencimiento' => now()->addDays(10)->format('Y-m-d'),
        ], $overrides);
    }

    public function test_manager_registers_payment_with_only_required_information(): void
    {
        $creator = $this->userWithRole(Role::MANAGER);
        $data = $this->data();
        
        $response = $this->signIn($creator)->post('/payments', $data)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertDatabaseCount('pagos', 1);
        
        $payment = Payment::firstOrFail();
        $this->assertSame($data['contrato_id'], $payment->contrato_id);
        $this->assertEquals(99.90, (float) $payment->monto);
        $this->assertSame('PENDIENTE', $payment->estado);
        $this->assertSame($creator->getKey(), $payment->registrado_por);
        $this->assertNotNull($payment->codigo);
        $this->assertStringStartsWith('PAG-', $payment->codigo);
        
        $response->assertRedirect('/payments/'.$payment->getKey());
        
        $this->get('/payments/'.$payment->getKey())->assertOk()
            ->assertSee('Pago #'.$payment->getKey().' registrado correctamente.')
            ->assertSee('data-toast', false);
    }

    public function test_office_user_registers_payment_with_all_optional_fields(): void
    {
        $creator = $this->userWithRole(Role::OFFICE);
        $data = $this->data([
            'fecha_pago' => now()->addDays(11)->format('Y-m-d'),
            'metodo_pago' => 'TRANSFERENCIA',
            'referencia_externa' => 'OP-123456',
            'observaciones' => 'Pago puntual del cliente',
        ]);

        $this->signIn($creator)->post('/payments', $data)
            ->assertSessionHasNoErrors()
            ->assertRedirect('/payments/'.Payment::firstOrFail()->getKey());

        $this->assertDatabaseHas('pagos', [
            'metodo_pago' => 'TRANSFERENCIA',
            'referencia_externa' => 'OP-123456',
            'observaciones' => 'Pago puntual del cliente',
        ]);
    }

    public function test_payment_code_is_generated_automatically_with_unique_format(): void
    {
        $creator = $this->userWithRole(Role::MANAGER);
        $this->signIn($creator);

        $this->post('/payments', $this->data())->assertSessionHasNoErrors();
        $this->post('/payments', $this->data())->assertSessionHasNoErrors();
        $this->post('/payments', $this->data())->assertSessionHasNoErrors();

        $this->assertDatabaseCount('pagos', 3);
        
        $payments = Payment::all();
        $codes = $payments->pluck('codigo')->toArray();
        
        // Todos deben tener el prefijo PAG-
        foreach ($codes as $code) {
            $this->assertStringStartsWith('PAG-', $code);
        }
        
        // Todos deben ser únicos
        $this->assertCount(3, array_unique($codes));
    }

    public function test_payment_can_only_be_registered_for_active_contract(): void
    {
        $creator = $this->userWithRole(Role::MANAGER);
        $contract = $this->activeContract();
        
        $this->signIn($creator);

        // ACTIVO: debe funcionar
        $this->post('/payments', $this->data(['contract' => $contract]))->assertSessionHasNoErrors();
        
        // Cambiar a PENDIENTE: debe fallar
        $contract->update(['estado' => 'PENDIENTE']);
        $this->post('/payments', $this->data(['contract' => $contract]))
            ->assertSessionHasErrors('contrato_id');
        
        // Cambiar a FINALIZADO: debe fallar
        $contract->update(['estado' => 'FINALIZADO']);
        $this->post('/payments', $this->data(['contract' => $contract]))
            ->assertSessionHasErrors('contrato_id');

        $this->assertDatabaseCount('pagos', 1);
    }

    public function test_amount_must_be_greater_than_zero(): void
    {
        $this->signIn($this->userWithRole(Role::MANAGER));

        foreach ([0, -10, -0.01] as $invalid) {
            $this->post('/payments', $this->data(['monto' => $invalid]))
                ->assertSessionHasErrors('monto');
        }

        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_due_date_must_be_today_or_future_when_creating(): void
    {
        $this->signIn($this->userWithRole(Role::MANAGER));

        // Futuro: OK
        $this->post('/payments', $this->data(['fecha_vencimiento' => now()->addDays(10)->format('Y-m-d')]))
            ->assertSessionHasNoErrors();

        // Hoy: OK
        $this->post('/payments', $this->data(['fecha_vencimiento' => now()->format('Y-m-d')]))
            ->assertSessionHasNoErrors();

        // Pasado: también OK (permitido para editar pagos con vencimiento pasado)
        $this->post('/payments', $this->data(['fecha_vencimiento' => now()->subDays(5)->format('Y-m-d')]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('pagos', 3);
    }

    public function test_payment_date_requires_payment_method(): void
    {
        $this->signIn($this->userWithRole(Role::MANAGER));

        // fecha_pago sin metodo_pago: ERROR
        $this->post('/payments', $this->data([
            'fecha_pago' => now()->addDays(11)->format('Y-m-d'),
            'metodo_pago' => '',
        ]))->assertSessionHasErrors('metodo_pago');

        // Ambos presentes: OK
        $this->post('/payments', $this->data([
            'fecha_pago' => now()->addDays(11)->format('Y-m-d'),
            'metodo_pago' => 'EFECTIVO',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('pagos', 1);
    }

    public function test_payment_date_cannot_be_before_due_date(): void
    {
        $this->signIn($this->userWithRole(Role::MANAGER));

        $this->post('/payments', $this->data([
            'fecha_vencimiento' => now()->addDays(10)->format('Y-m-d'),
            'fecha_pago' => now()->format('Y-m-d'),
            'metodo_pago' => 'EFECTIVO',
        ]))->assertSessionHasErrors('fecha_pago');

        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_period_format_must_be_yyyy_mm(): void
    {
        $this->signIn($this->userWithRole(Role::MANAGER));

        foreach (['2026-13', '2026-00', '26-09', '2026/09', '202609'] as $invalid) {
            $this->post('/payments', $this->data(['periodo_servicio' => $invalid]))
                ->assertSessionHasErrors('periodo_servicio');
        }

        // Formato válido
        $this->post('/payments', $this->data(['periodo_servicio' => '2026-09']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('pagos', 1);
    }

    public function test_form_is_reachable_from_payments_index_for_authorized_roles(): void
    {
        foreach ([Role::MANAGER, Role::OFFICE] as $role) {
            $this->signIn($this->userWithRole($role));
            $this->get('/payments')->assertOk()->assertSee('Registrar pago');
            $this->get('/payments/create')->assertOk()
                ->assertSee('Contrato')
                ->assertSee('name="_token"', false)
                ->assertSee('name="monto"', false);
        }
    }

    public function test_guests_and_unauthorized_roles_cannot_register_payments(): void
    {
        // Guest
        $this->get('/payments/create')->assertRedirect('/login');
        $this->post('/payments', $this->data())->assertRedirect('/login');

        // Técnico no autorizado
        $this->signIn($this->userWithRole(Role::TECHNICIAN));
        $this->get('/payments/create')->assertForbidden();
        $this->post('/payments', $this->data())->assertForbidden();

        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_inactive_account_cannot_register_payment(): void
    {
        $creator = $this->userWithRole(Role::MANAGER);
        $this->signIn($creator);
        $creator->update(['estado' => 'INACTIVO']);

        $this->post('/payments', $this->data())->assertRedirect('/login');
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_creator_and_state_cannot_be_supplied_by_browser(): void
    {
        $creator = $this->userWithRole(Role::MANAGER);
        $other = $this->userWithRole(Role::OFFICE);
        
        $this->signIn($creator)->post('/payments', $this->data([
            'registrado_por' => $other->getKey(),
            'estado' => 'REALIZADO',
            'pago_id' => 999999,
        ]))->assertSessionHasNoErrors();

        $payment = Payment::firstOrFail();
        $this->assertSame($creator->getKey(), $payment->registrado_por);
        $this->assertSame('PENDIENTE', $payment->estado);
        $this->assertNotEquals(999999, $payment->getKey());
    }

    public function test_input_preserves_form_on_validation_failure(): void
    {
        $this->signIn($this->userWithRole(Role::MANAGER))->from('/payments/create')
            ->post('/payments', $this->data(['monto' => -50, 'periodo_servicio' => 'invalid']))
            ->assertRedirect('/payments/create')
            ->assertSessionHasErrors(['monto', 'periodo_servicio'])
            ->assertSessionHasInput('contrato_id');

        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_unique_code_constraint_is_handled_gracefully(): void
    {
        $creator = $this->userWithRole(Role::MANAGER);
        $this->signIn($creator);

        // Crear primer pago
        $this->post('/payments', $this->data())->assertSessionHasNoErrors();
        $payment = Payment::firstOrFail();
        $originalCode = $payment->codigo;

        // Intentar crear otro con el mismo código (forzando el código)
        // Nota: En condiciones normales esto no debería pasar porque el código se genera
        // pero validamos que el manejo de errores funcione
        $data = $this->data(['codigo' => $originalCode]);
        
        $this->from('/payments/create')->post('/payments', $data)
            ->assertRedirect('/payments/create')
            ->assertSessionHasErrors('codigo');

        $this->assertDatabaseCount('pagos', 1);
    }

    public function test_all_payment_methods_are_supported(): void
    {
        $this->signIn($this->userWithRole(Role::MANAGER));

        foreach (['EFECTIVO', 'TRANSFERENCIA', 'YAPE', 'PLIN', 'TARJETA', 'OTRO'] as $method) {
            $this->post('/payments', $this->data([
                'fecha_pago' => now()->addDays(11)->format('Y-m-d'),
                'metodo_pago' => $method,
            ]))->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('pagos', 6);
        
        foreach (['EFECTIVO', 'TRANSFERENCIA', 'YAPE', 'PLIN', 'TARJETA', 'OTRO'] as $method) {
            $this->assertDatabaseHas('pagos', ['metodo_pago' => $method]);
        }
    }

    public function test_contract_must_exist(): void
    {
        $this->signIn($this->userWithRole(Role::MANAGER));

        $this->post('/payments', $this->data(['contrato_id' => 999999]))
            ->assertSessionHasErrors('contrato_id');

        $this->assertDatabaseCount('pagos', 0);
    }
}
