<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SpanishMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_validation_and_password_rules_are_spanish(): void
    {
        $this->assertSame('es', app()->getLocale());
        $validator = Validator::make(['page' => 'texto', 'telefono' => [], 'correo' => 'incorrecto'], [
            'page' => 'integer', 'telefono' => 'string', 'correo' => 'email',
        ]);
        $this->assertSame('El campo página debe ser un número entero.', $validator->errors()->first('page'));
        $this->assertSame('El campo teléfono debe ser un texto.', $validator->errors()->first('telefono'));
        $this->assertSame('Introduce un correo electrónico válido en correo electrónico.', $validator->errors()->first('correo'));
        $this->assertSame('La contraseña debe incluir al menos un número.', __('validation.password.numbers'));
    }

    public function test_http_error_pages_and_json_are_spanish(): void
    {
        config(['app.debug' => false]);
        foreach ([401, 403, 404, 405, 419, 429, 500, 503] as $status) {
            Route::get('/test-es-'.$status, fn () => abort($status));
            $this->get('/test-es-'.$status)->assertStatus($status)
                ->assertDontSee('Server Error')->assertDontSee('Not Found')
                ->assertDontSee('Page Expired')->assertDontSee('Too Many Requests');
            $this->getJson('/test-es-'.$status)->assertStatus($status)
                ->assertJsonStructure(['message']);
        }
        $this->get('/test-es-404')->assertSee('Página no encontrada');
        $this->getJson('/test-es-403')->assertJsonPath('message', 'No tienes permiso para realizar esta acción.');
        $this->getJson('/test-es-429')->assertJsonPath('message', 'Demasiadas solicitudes. Espera un momento e inténtalo nuevamente.');
    }

    public function test_invalid_filters_and_removed_form_notices(): void
    {
        $this->signIn(User::factory()->create(['rol_id' => \App\Models\Role::where('nombre', \App\Models\Role::ADMIN)->value('rol_id')]));
        $this->get('/employees?page=abc')->assertSessionHasErrors([
            'page' => 'El campo página debe ser un número entero.',
        ]);
        $this->get('/employees/create')->assertOk()
            ->assertDontSee('Los campos con * son obligatorios.')
            ->assertDontSee('La fecha de cese es el último día')
            ->assertSee('validation-es.js');
        $this->get('/users/create')->assertOk()
            ->assertDontSee('Los campos marcados con * son obligatorios.')
            ->assertSee('validation-es.js');
    }
}
