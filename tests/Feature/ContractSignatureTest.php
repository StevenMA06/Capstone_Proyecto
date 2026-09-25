<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use App\Services\ContractPdf;
use App\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\Support\ClientHistoryFixtures;
use Tests\TestCase;

class ContractSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_a_detected_customer_mark_activates_the_contract(): void
    {
        $python = getenv('SIGNATURE_PYTHON') ?: 'python';
        $probe = new Process([$python, '-c', 'import numpy, pypdfium2, PIL, pypdf, reportlab']);
        try {
            $probe->run();
        } catch (\Throwable) {
            $this->markTestSkipped('Instala las dependencias Python para ejecutar la prueba visual.');
        }
        if (! $probe->isSuccessful()) {
            $this->markTestSkipped('Instala las dependencias Python para ejecutar la prueba visual.');
        }
        config()->set('contracts.signature_python', $python);
        Storage::fake('local');
        $actor = User::factory()->create(['rol_id' => Role::where('nombre', Role::OFFICE)->value('rol_id')]);
        $client = Client::create([
            'tipo_documento' => 'DNI', 'numero_documento' => '76543210',
            'nombres_razon_social' => 'Ana', 'apellidos' => 'Pérez',
            'direccion' => 'Calle Uno', 'distrito' => 'Trujillo', 'creado_por' => $actor->getKey(),
        ]);
        ClientHistoryFixtures::attach($client, $actor, 'RF-SIGN', 1);
        DB::table('empleados')->update(['cargo' => 'Asistente de Ventas']);
        $plan = DB::table('planes_servicio')->where('estado', 'ACTIVO')->first();
        $zone = DB::table('zonas_cobertura')->where('estado', 'ACTIVA')->first();
        $employee = DB::table('empleados')->where('estado', 'ACTIVO')->first();
        $data = [
            'cliente_id' => $client->getKey(), 'plan_id' => $plan->plan_id,
            'zona_id' => $zone->zona_id, 'responsable_id' => $employee->empleado_id,
            'fecha_inicio' => '2026-10-01', 'duracion_meses' => 12,
        ];
        $this->signIn($actor)->post('/contracts', $data)->assertSessionHasNoErrors();
        $contract = DB::table('contratos')->orderByDesc('contrato_id')->first();
        $draft = app(ContractPdf::class)->render(app(ContractService::class)->detail($contract->contrato_id));

        $this->put('/contracts/'.$contract->contrato_id, $data + [
            'documento_pdf' => UploadedFile::fake()->createWithContent('sin-firma.pdf', $draft),
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('contratos', ['contrato_id' => $contract->contrato_id, 'estado' => 'PENDIENTE', 'firma_revision' => 'SIN_FIRMA']);
        $this->assertDatabaseMissing('ventas', ['contrato_id' => $contract->contrato_id]);
        $this->patch('/contracts/'.$contract->contrato_id.'/status', ['estado' => 'ACTIVO'])->assertNotFound();
        $this->put('/contracts/'.$contract->contrato_id, $data + [
            'documento_pdf' => UploadedFile::fake()->createWithContent('otro.pdf', "%PDF-1.4\nDocumento ajeno"),
        ])->assertSessionHasErrors('documento_pdf');
        $this->assertDatabaseHas('contratos', ['contrato_id' => $contract->contrato_id, 'estado' => 'PENDIENTE', 'firma_revision' => 'SIN_FIRMA']);
        $otherContract = app(ContractService::class)->detail($contract->contrato_id);
        $otherContract->codigo = 'CON-20260917-OTRO1234';
        $this->put('/contracts/'.$contract->contrato_id, $data + [
            'documento_pdf' => UploadedFile::fake()->createWithContent('otro-contrato.pdf', app(ContractPdf::class)->render($otherContract)),
        ])->assertSessionHasErrors([
            'documento_pdf' => 'El PDF corresponde al contrato CON-20260917-OTRO1234, pero estás actualizando '.$contract->codigo.'. Descarga y firma el contrato correcto.',
        ]);

        $input = tempnam(sys_get_temp_dir(), 'draft-');
        $output = tempnam(sys_get_temp_dir(), 'signed-');
        try {
            file_put_contents($input, $draft);
            (new Process([$python, base_path('tests/Support/make_signed_contract.py'), $input, $output, 'responsible']))->mustRun();
            $this->put('/contracts/'.$contract->contrato_id, $data + [
                'documento_pdf' => UploadedFile::fake()->createWithContent('solo-responsable.pdf', file_get_contents($output)),
            ])->assertSessionHasNoErrors();
            $this->assertDatabaseHas('contratos', ['contrato_id' => $contract->contrato_id, 'estado' => 'PENDIENTE', 'firma_revision' => 'SIN_FIRMA']);
            $process = new Process([$python, base_path('tests/Support/make_signed_contract.py'), $input, $output, 'scan']);
            $process->mustRun();
            $this->put('/contracts/'.$contract->contrato_id, $data + [
                'documento_pdf' => UploadedFile::fake()->createWithContent('firmado.pdf', file_get_contents($output)),
            ])->assertSessionHasNoErrors()->assertSessionHas('status_kind', 'activated');
            $this->assertDatabaseHas('contratos', ['contrato_id' => $contract->contrato_id, 'estado' => 'ACTIVO', 'firma_revision' => 'VERIFICADA']);
            $this->assertDatabaseHas('ventas', [
                'contrato_id' => $contract->contrato_id, 'cliente_id' => $client->getKey(),
                'responsable_id' => $employee->empleado_id, 'monto' => $plan->precio_mensual,
                'estado' => 'CONFIRMADA',
            ]);
            $this->assertDatabaseCount('ventas', 1);
            $this->get('/contracts/'.$contract->contrato_id)->assertOk()->assertSee('Descargar PDF adjunto')->assertDontSee('Descargar contrato para firma');
            $this->get('/contracts/'.$contract->contrato_id.'/edit')->assertOk()->assertSee('PDF adjunto guardado')->assertSee('Descargar adjunto')->assertSee('name="estado"', false);
            (new Process([$python, base_path('tests/Support/make_signed_contract.py'), $input, $output, 'alter']))->mustRun();
            $this->put('/contracts/'.$contract->contrato_id, $data + [
                'documento_pdf' => UploadedFile::fake()->createWithContent('alterado.pdf', file_get_contents($output)),
            ])->assertSessionHasErrors('documento_pdf');
            $this->assertDatabaseHas('contratos', ['contrato_id' => $contract->contrato_id, 'estado' => 'ACTIVO', 'firma_revision' => 'VERIFICADA']);
            $this->put('/contracts/'.$contract->contrato_id, $data + ['estado' => 'FINALIZADO'])
                ->assertSessionHasErrors('estado');
            $this->put('/contracts/'.$contract->contrato_id, $data + ['estado' => 'CANCELADO', 'motivo_cancelacion' => 'Solicitud del cliente'])
                ->assertSessionHasNoErrors()->assertSessionHas('status_kind', 'cancelled');
            $this->assertDatabaseHas('contratos', ['contrato_id' => $contract->contrato_id, 'estado' => 'CANCELADO', 'firma_revision' => 'VERIFICADA']);
            $this->assertDatabaseHas('ventas', ['contrato_id' => $contract->contrato_id, 'estado' => 'ANULADA']);
            $this->assertDatabaseCount('ventas', 1);
            $this->get('/contracts/'.$contract->contrato_id.'/edit')->assertForbidden();
        } finally {
            @unlink($input);
            @unlink($output);
        }
    }
}
