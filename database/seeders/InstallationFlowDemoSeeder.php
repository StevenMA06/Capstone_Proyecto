<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Services\InstallationOrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class InstallationFlowDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Los datos de demostración solo se pueden crear en APP_ENV=local.');
        }
        $actor = User::where('correo', 'iglesiascortegana.nayeli@gmail.com')->first()
            ?? User::whereHas('roles', fn ($query) => $query->where('nombre', Role::ADMIN))->first();
        $assistant = DB::table('empleados')->where('cargo', 'Asistente de ventas')->where('estado', 'ACTIVO')->first();
        $plan = DB::table('planes_servicio')->where('estado', 'ACTIVO')->first();
        $zone = DB::table('zonas_cobertura')->where('estado', 'ACTIVA')->first();
        if (! $actor || ! $assistant || ! $plan || ! $zone) {
            throw new RuntimeException('Se necesita un administrador, un asistente de ventas, un plan y una zona activos.');
        }

        $password = Str::password(20);
        $created = [];
        DB::transaction(function () use ($actor, $assistant, $plan, $zone, $password, &$created): void {
            foreach ([
                ['email' => 'camello25258@gmail.com', 'role' => Role::INSTALLATIONS,
                    'username' => 'demo_jefe_instalaciones', 'document' => '90000001', 'name' => 'Jefe Demo',
                    'last' => 'Instalaciones', 'position' => 'Jefe de Instalaciones'],
                ['email' => 'nayeli114k@gmail.com', 'role' => Role::TECHNICIAN,
                    'username' => 'demo_tecnica_campo', 'document' => '90000002', 'name' => 'Técnica Demo',
                    'last' => 'Campo', 'position' => 'Técnico de Campo'],
            ] as $person) {
                if (User::where('correo', $person['email'])->exists()) continue;
                $employeeId = DB::table('empleados')->where('numero_documento', $person['document'])->value('empleado_id');
                if (! $employeeId) {
                    $employeeId = DB::table('empleados')->insertGetId([
                        'numero_documento' => $person['document'], 'nombres' => $person['name'],
                        'apellidos' => $person['last'], 'correo' => $person['email'],
                        'cargo' => $person['position'], 'estado' => 'ACTIVO',
                    ]);
                }
                $role = Role::firstOrCreate(['nombre' => $person['role']], ['estado' => 'ACTIVO']);
                User::create([
                    'empleado_id' => $employeeId, 'rol_id' => $role->getKey(),
                    'nombre_usuario' => $person['username'], 'correo' => $person['email'],
                    'password_hash' => $password, 'estado' => 'ACTIVO',
                ]);
                $created[] = $person['email'];
            }

            $clientId = DB::table('clientes')->where('numero_documento', '90000003')->value('cliente_id');
            if (! $clientId) {
                $clientId = DB::table('clientes')->insertGetId([
                    'numero_documento' => '90000003', 'nombres_razon_social' => 'Cliente Demo',
                    'apellidos' => 'Instalación', 'telefono' => '999000003',
                    'direccion' => 'Calle de prueba 123', 'distrito' => $zone->distrito,
                    'provincia' => $zone->provincia, 'departamento' => $zone->departamento,
                    'creado_por' => $actor->getKey(), 'estado' => 'ACTIVO',
                ]);
            }
            $contractId = DB::table('contratos')->where('codigo', 'CON-DEMO-INSTALACION')->value('contrato_id');
            if (! $contractId) {
                $contractId = DB::table('contratos')->insertGetId([
                    'codigo' => 'CON-DEMO-INSTALACION', 'cliente_id' => $clientId,
                    'plan_id' => $plan->plan_id, 'zona_id' => $zone->zona_id,
                    'responsable_id' => $assistant->empleado_id,
                    'fecha_inicio' => now('America/Lima')->toDateString(),
                    'duracion_meses' => 12, 'fecha_fin' => now('America/Lima')->addYear()->toDateString(),
                    'precio_contratado' => $plan->precio_mensual,
                    'direccion_servicio' => 'Calle de prueba 123',
                    'estado' => 'ACTIVO',
                    'observaciones' => 'Solo datos de prueba del flujo de instalaciones.',
                ]);
            }
            app(InstallationOrderService::class)->createForContract(DB::table('contratos')->where('contrato_id', $contractId)->first(), $actor->getKey());
            DB::table('equipos')->updateOrInsert(['codigo' => 'EQ-DEMO-INST'], [
                'nombre' => 'Router de demostración', 'tipo' => 'Router',
                'estado' => 'DISPONIBLE', 'condicion' => 'BUENO', 'creado_por' => $actor->getKey(),
            ]);
            DB::table('materiales')->updateOrInsert(['codigo' => 'MAT-DEMO-INST'], [
                'nombre' => 'Cable de demostración', 'unidad_base' => 'm',
                'stock_actual' => '25.000', 'estado' => 'ACTIVO', 'creado_por' => $actor->getKey(),
            ]);
        }, 3);

        $this->command?->info('Pedido de prueba: '.DB::table('pedidos_instalacion')->where('contrato_id', DB::table('contratos')->where('codigo', 'CON-DEMO-INSTALACION')->value('contrato_id'))->value('codigo'));
        if ($created) {
            $this->command?->warn('Contraseña temporal de las cuentas nuevas '.implode(', ', $created).': '.$password);
            $this->command?->warn('Guarda esta contraseña ahora. No vuelve a mostrarse al repetir el seeder.');
        }
    }
}
