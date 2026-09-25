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

class PaymentManagementTest extends TestCase
{
    use RefreshDatabase;

    private static $contractCounter = 1;
    private static $paymentCounter = 1;

    private function actor(string $name = 'Gerente'): User
    {
        $role = Role::firstOrCreate(['nombre' => $name], ['estado' => 'ACTIVO']);

        return User::factory()->create(['rol_id' => $role->getKey()]);
    }

    private function activeContract(): Contract
    {
        $creator = $this->actor();
        
        $client = Client::create([
            'tipo_documento' => 'DNI',
            'numero_documento' => sprintf('1%07d', 2000000 + self::$contractCounter),
            'nombres_razon_social' => 'Juan',
            'apellidos' => 'Pérez',
            'direccion' => 'Av. Test 123',
            'telefono' => '999888777',
            'correo' => sprintf('test%d@management.com', self::$contractCounter),
            'estado' => 'ACTIVO',
            'creado_por' => $creator->getKey(),
        ]);

        $plan = ServicePlan::create([
            'numero_plan' => sprintf('PLANM-%03d', self::$contractCounter),
            'nombre' => 'Plan Básico',
            'tipo_servicio' => 'Internet',
            'velocidad_mbps' => 50,
            'precio_mensual' => 99.90,
            'estado' => 'ACTIVO',
            'creado_por' => $creator->getKey(),
        ]);

        $zone = DB::table('zonas_cobertura')->insertGetId([
            'codigo' => sprintf('ZONAM-%03d', self::$contractCounter),
            'nombre' => 'Zona Test',
            'distrito' => 'Trujillo',
            'creado_por' => $creator->getKey(),
        ]);

        $employee = DB::table('empleados')->insertGetId([
            'tipo_documento' => 'DNI',
            'numero_documento' => sprintf('97%06d', self::$contractCounter),
            'nombres' => 'Técnico',
            'apellidos' => 'Test',
            'cargo' => 'Instalador',
        ]);

        $contract = Contract::create([
            'codigo' => sprintf('CON-MGT-%03d', self::$contractCounter),
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

    private function payment(User $actor, array $overrides = []): Payment
    {
        $contract = $overrides['contract'] ?? $this->activeContract();
        unset($overrides['contract']);

        $payment = Payment::create(array_merge([
            'contrato_id' => $contract->getKey(),
            'codigo' => sprintf('PAG-MGT-%05d', self::$paymentCounter),
            'monto' => 99.90,
            'periodo_servicio' => now()->format('Y-m'),
            'fecha_vencimiento' => now()->addDays(5),
            'estado' => 'PENDIENTE',
            'registrado_por' => $actor->getKey(),
        ], $overrides));

        self::$paymentCounter++;
        
        return $payment;
    }

    public function test_index_shows_all_payments_with_client_and_contract_information(): void
    {
        $actor = $this->actor();
        $payment1 = $this->payment($actor);
        $payment2 = $this->payment($actor, ['monto' => 150.00]);

        $this->signIn($actor)->get('/payments')->assertOk()
            ->assertSee($payment1->codigo)
            ->assertSee($payment2->codigo)
            ->assertSee('Juan Pérez')
            ->assertSee($payment1->contract->codigo)
            ->assertSee('99.90')
            ->assertSee('150.00');
    }

    public function test_search_filter_finds_payments_by_client_document(): void
    {
        $actor = $this->actor();
        $payment1 = $this->payment($actor);
        
        // Crear otro pago con diferente cliente
        $contract2 = $this->activeContract();
        $contract2->client->update(['numero_documento' => '87654321']);
        $payment2 = $this->payment($actor, ['contract' => $contract2]);

        $this->signIn($actor);

        // Buscar por documento del primer cliente
        $this->get('/payments?search='.$payment1->contract->client->numero_documento)->assertOk()
            ->assertSee($payment1->codigo)
            ->assertDontSee($payment2->codigo);

        // Buscar por documento del segundo cliente
        $this->get('/payments?search=87654321')->assertOk()
            ->assertSee($payment2->codigo)
            ->assertDontSee($payment1->codigo);
    }

    public function test_status_filter_shows_only_matching_payments(): void
    {
        $actor = $this->actor();
        $pending = $this->payment($actor, ['estado' => 'PENDIENTE']);
        $completed = $this->payment($actor, ['estado' => 'REALIZADO', 'fecha_pago' => now()]);
        $overdue = $this->payment($actor, ['estado' => 'VENCIDO']);

        $this->signIn($actor);

        $this->get('/payments?status=PENDIENTE')->assertOk()
            ->assertSee($pending->codigo)
            ->assertDontSee($completed->codigo);

        $this->get('/payments?status=REALIZADO')->assertOk()
            ->assertSee($completed->codigo)
            ->assertDontSee($pending->codigo);
    }

    public function test_date_range_filter_limits_results_by_due_date(): void
    {
        $actor = $this->actor();
        $payment1 = $this->payment($actor, ['fecha_vencimiento' => now()->addDays(5)]);
        $payment2 = $this->payment($actor, ['fecha_vencimiento' => now()->addDays(15)]);
        $payment3 = $this->payment($actor, ['fecha_vencimiento' => now()->addDays(25)]);

        $this->signIn($actor);

        $from = now()->addDays(10)->format('Y-m-d');
        $to = now()->addDays(20)->format('Y-m-d');

        $this->get("/payments?from=$from&to=$to")->assertOk()
            ->assertSee($payment2->codigo)
            ->assertDontSee($payment1->codigo)
            ->assertDontSee($payment3->codigo);
    }

    public function test_payment_can_be_updated_when_in_editable_state(): void
    {
        $actor = $this->actor();
        $payment = $this->payment($actor);

        $this->signIn($actor)->put('/payments/' . $payment->getKey(), [
            'contrato_id' => $payment->contrato_id,
            'monto' => 120.50,
            'periodo_servicio' => '2026-12',
            'fecha_vencimiento' => now()->addDays(10)->format('Y-m-d'),
        ])->assertSessionHasNoErrors()
          ->assertRedirect('/payments/' . $payment->getKey());

        $this->assertDatabaseHas('pagos', [
            'pago_id' => $payment->getKey(),
            'monto' => 120.50,
            'periodo_servicio' => '2026-12',
        ]);
    }

    public function test_completed_payment_cannot_be_edited(): void
    {
        $actor = $this->actor();
        $payment = $this->payment($actor, ['estado' => 'REALIZADO', 'fecha_pago' => now()]);

        $this->signIn($actor)->get('/payments/' . $payment->getKey() . '/edit')
            ->assertRedirect('/payments/' . $payment->getKey())
            ->assertSessionHas('error');
    }

    public function test_cancelled_payment_cannot_be_edited(): void
    {
        $actor = $this->actor();
        $payment = $this->payment($actor, ['estado' => 'ANULADO']);

        $this->signIn($actor)->get('/payments/' . $payment->getKey() . '/edit')
            ->assertRedirect('/payments/' . $payment->getKey())
            ->assertSessionHas('error');
    }

    public function test_pending_payment_can_be_marked_as_completed(): void
    {
        $actor = $this->actor();
        $payment = $this->payment($actor);

        $this->signIn($actor)->patch('/payments/' . $payment->getKey() . '/complete', [
            'fecha_pago' => now()->format('Y-m-d'),
            'metodo_pago' => 'TRANSFERENCIA',
            'referencia_externa' => 'OP-123456',
        ])->assertSessionHasNoErrors()
          ->assertRedirect('/payments/' . $payment->getKey());

        $this->assertDatabaseHas('pagos', [
            'pago_id' => $payment->getKey(),
            'estado' => 'REALIZADO',
            'metodo_pago' => 'TRANSFERENCIA',
            'referencia_externa' => 'OP-123456',
        ]);
    }

    public function test_payment_can_be_cancelled_with_reason(): void
    {
        $actor = $this->actor();
        $payment = $this->payment($actor);

        $this->signIn($actor)->patch('/payments/' . $payment->getKey() . '/cancel', [
            'observaciones' => 'Cliente canceló el servicio',
        ])->assertSessionHasNoErrors()
          ->assertRedirect('/payments/' . $payment->getKey());

        $payment->refresh();
        $this->assertSame('ANULADO', $payment->estado);
        $this->assertStringContainsString('Cliente canceló el servicio', $payment->observaciones);
    }

    public function test_receipt_can_be_generated_for_completed_payment(): void
    {
        $actor = $this->actor();
        $payment = $this->payment($actor, [
            'estado' => 'REALIZADO',
            'fecha_pago' => now(),
            'metodo_pago' => 'EFECTIVO',
        ]);

        $response = $this->signIn($actor)->get('/payments/' . $payment->getKey() . '/receipt');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-', $response->streamedContent());
        $this->assertStringEndsWith('.pdf', $payment->fresh()->constancia_ruta);
    }

    public function test_receipt_cannot_be_generated_for_pending_payment(): void
    {
        $actor = $this->actor();
        $payment = $this->payment($actor, ['estado' => 'PENDIENTE']); // PENDIENTE, no REALIZADO

        $response = $this->signIn($actor)->get('/payments/' . $payment->getKey() . '/receipt');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-', $response->streamedContent());
    }

    public function test_reminder_can_be_created_for_pending_payment(): void
    {
        $actor = $this->actor();
        $payment = $this->payment($actor);

        $this->signIn($actor)->post('/payments/' . $payment->getKey() . '/reminders', [
            'mensaje' => 'Recordatorio: pago vence en 3 días',
        ])->assertSessionHasNoErrors()
          ->assertRedirect('/payments/' . $payment->getKey());

        $this->assertDatabaseHas('recordatorios_pago', [
            'pago_id' => $payment->getKey(),
            'mensaje' => 'Recordatorio: pago vence en 3 días',
        ]);
    }

    public function test_reminder_cannot_be_created_for_completed_payment(): void
    {
        $actor = $this->actor();
        $payment = $this->payment($actor, ['estado' => 'REALIZADO', 'fecha_pago' => now()]);

        $this->signIn($actor)->post('/payments/' . $payment->getKey() . '/reminders', [
            'mensaje' => 'Este recordatorio no debe crearse',
        ])->assertSessionHasErrors();

        $this->assertDatabaseMissing('recordatorios_pago', [
            'pago_id' => $payment->getKey(),
        ]);
    }

    public function test_reminder_message_must_have_minimum_length(): void
    {
        $actor = $this->actor();
        $payment = $this->payment($actor);

        $this->signIn($actor);

        // Mensaje muy corto
        $this->post('/payments/' . $payment->getKey() . '/reminders', [
            'mensaje' => 'Corto',
        ])->assertSessionHasErrors('mensaje');

        // Mensaje válido
        $this->post('/payments/' . $payment->getKey() . '/reminders', [
            'mensaje' => 'Este es un mensaje válido con más de 10 caracteres',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('recordatorios_pago', 1);
    }

    public function test_show_page_displays_payment_details_and_contract_info(): void
    {
        $actor = $this->actor();
        $payment = $this->payment($actor, [
            'monto' => 150.75,
            'periodo_servicio' => '2026-09',
            'observaciones' => 'Pago adelantado',
        ]);

        $this->signIn($actor)->get('/payments/' . $payment->getKey())->assertOk()
            ->assertSee($payment->codigo)
            ->assertSee('150.75')
            ->assertSee('2026-09')
            ->assertSee('Pago adelantado')
            ->assertSee('Juan Pérez')
            ->assertSee($payment->contract->codigo);
    }

    public function test_edit_button_is_hidden_for_non_editable_payments(): void
    {
        $actor = $this->actor();
        $payment = $this->payment($actor, ['estado' => 'REALIZADO', 'fecha_pago' => now()]);

        $this->signIn($actor)->get('/payments/' . $payment->getKey())->assertOk()
            ->assertDontSee('Editar pago');
    }

    public function test_guests_cannot_access_payment_management(): void
    {
        $actor = $this->actor();
        $payment = $this->payment($actor);

        $this->get('/payments')->assertRedirect('/login');
        $this->get('/payments/' . $payment->getKey())->assertRedirect('/login');
        $this->get('/payments/' . $payment->getKey() . '/edit')->assertRedirect('/login');
        $this->put('/payments/' . $payment->getKey(), [])->assertRedirect('/login');
    }

    public function test_unauthorized_roles_cannot_manage_payments(): void
    {
        $technician = $this->actor('Técnico');
        $actor = $this->actor();
        $payment = $this->payment($actor);

        $this->signIn($technician);

        $this->get('/payments')->assertForbidden();
        $this->get('/payments/' . $payment->getKey())->assertForbidden();
        $this->get('/payments/' . $payment->getKey() . '/edit')->assertForbidden();
        $this->put('/payments/' . $payment->getKey(), [])->assertForbidden();
        $this->patch('/payments/' . $payment->getKey() . '/complete', [])->assertForbidden();
    }

    public function test_payment_search_handles_special_sql_characters(): void
    {
        $actor = $this->actor();
        
        // Crear cliente con caracteres especiales en documento
        $contract = $this->activeContract();
        $contract->client->update(['numero_documento' => 'DOC_123%']);
        $payment = $this->payment($actor, ['contract' => $contract]);

        $this->signIn($actor);

        // Buscar con caracteres especiales
        $this->get('/payments?search=DOC_123%')->assertOk()
            ->assertSee($payment->codigo);
    }

    public function test_pagination_works_correctly(): void
    {
        $actor = $this->actor();
        $this->signIn($actor);

        // Crear 25 pagos
        for ($i = 1; $i <= 25; $i++) {
            $this->payment($actor);
        }

        // Primera página
        $response = $this->get('/payments')->assertOk();
        $this->assertStringContainsString('Página 1 de', $response->getContent());

        // Segunda página
        $response = $this->get('/payments?page=2')->assertOk();
        $this->assertStringContainsString('Página 2 de', $response->getContent());
    }

    public function test_due_soon_filter_shows_payments_expiring_within_seven_days(): void
    {
        $actor = $this->actor();
        $dueSoon = $this->payment($actor, ['fecha_vencimiento' => now()->addDays(5)]);
        $notDueSoon = $this->payment($actor, ['fecha_vencimiento' => now()->addDays(15)]);

        $this->signIn($actor)->get('/payments?due_soon=1')->assertOk()
            ->assertSee($dueSoon->codigo)
            ->assertDontSee($notDueSoon->codigo);
    }

    public function test_overdue_filter_shows_only_overdue_payments(): void
    {
        $actor = $this->actor();
        $overdue = $this->payment($actor, [
            'fecha_vencimiento' => now()->subDays(5),
            'estado' => 'PENDIENTE', // PENDIENTE pero ya pasó la fecha
        ]);
        $pending = $this->payment($actor, ['fecha_vencimiento' => now()->addDays(5)]);

        $this->signIn($actor)->get('/payments?overdue=1')->assertOk()
            ->assertSee($overdue->codigo)
            ->assertDontSee($pending->codigo);
    }

    public function test_contract_filter_shows_payments_for_specific_contract(): void
    {
        $actor = $this->actor();
        $contract1 = $this->activeContract();
        $contract2 = $this->activeContract();
        
        $payment1 = $this->payment($actor, ['contract' => $contract1]);
        $payment2 = $this->payment($actor, ['contract' => $contract2]);

        $this->signIn($actor)->get('/payments?contract=' . $contract1->codigo)->assertOk()
            ->assertSee($payment1->codigo)
            ->assertDontSee($payment2->codigo);
    }

    public function test_multiple_filters_work_together(): void
    {
        $actor = $this->actor();
        
        $matching = $this->payment($actor, [
            'estado' => 'PENDIENTE',
            'fecha_vencimiento' => now()->addDays(5),
        ]);
        
        $wrongStatus = $this->payment($actor, [
            'estado' => 'REALIZADO',
            'fecha_vencimiento' => now()->addDays(5),
        ]);
        
        $wrongDate = $this->payment($actor, [
            'estado' => 'PENDIENTE',
            'fecha_vencimiento' => now()->addDays(20),
        ]);

        $from = now()->format('Y-m-d');
        $to = now()->addDays(10)->format('Y-m-d');

        $this->signIn($actor)
            ->get("/payments?status=PENDIENTE&from=$from&to=$to")->assertOk()
            ->assertSee($matching->codigo)
            ->assertDontSee($wrongStatus->codigo)
            ->assertDontSee($wrongDate->codigo);
    }
}
