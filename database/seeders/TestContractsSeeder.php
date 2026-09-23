<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractPeriod;
use App\Models\ServicePlan;
use App\Models\User;
use App\Repositories\PaymentRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestContractsSeeder extends Seeder
{
    /**
     * Crea 3 contratos de prueba con pagos automáticos
     */
    public function run(): void
    {
        $this->command->info('🎯 Creando 3 contratos de prueba con pagos automáticos...');

        // Obtener recursos necesarios
        $cliente = Client::where('estado', 'ACTIVO')->first();
        $plan = ServicePlan::where('estado', 'ACTIVO')->first();
        $responsable = DB::table('empleados')->where('estado', 'ACTIVO')->first(); // Cambio: buscar empleado, no usuario

        if (!$cliente || !$plan || !$responsable) {
            $this->command->error('❌ No hay clientes, planes o empleados activos. Crea al menos uno de cada uno primero.');
            return;
        }

        // ============================================================
        // INSERTAR ZONAS DE COBERTURA DE PRUEBA
        // ============================================================
        $this->command->line('');
        $this->command->info('📍 Insertando zonas de cobertura de prueba...');
        
        $zonasData = [
            ['codigo' => 'ZONA-TEST-001', 'nombre' => 'Zona Centro Lima', 'distrito' => 'Lima Centro', 'estado' => 'ACTIVA', 'creado_por' => 1],
            ['codigo' => 'ZONA-TEST-002', 'nombre' => 'Zona Norte Lima', 'distrito' => 'Los Olivos', 'estado' => 'ACTIVA', 'creado_por' => 1],
            ['codigo' => 'ZONA-TEST-003', 'nombre' => 'Zona Sur Lima', 'distrito' => 'Villa El Salvador', 'estado' => 'ACTIVA', 'creado_por' => 1],
        ];

        $zonasIds = [];
        foreach ($zonasData as $zona) {
            // Verificar si ya existe
            $existente = DB::table('zonas_cobertura')->where('codigo', $zona['codigo'])->first();
            if ($existente) {
                $zonasIds[] = $existente->zona_id;
                $this->command->line("   ✓ Zona '{$zona['nombre']}' ya existe (ID: {$existente->zona_id})");
            } else {
                $zonaId = DB::table('zonas_cobertura')->insertGetId($zona);
                $zonasIds[] = $zonaId;
                $this->command->info("   ✓ Zona '{$zona['nombre']}' creada (ID: {$zonaId})");
            }
        }

        // Usar la primera zona para los contratos
        $zonaId = $zonasIds[0];
        $this->command->line('');

        $paymentRepo = app(PaymentRepository::class);

        // ============================================================
        // CONTRATO 1: 3 meses (Octubre - Diciembre 2026)
        // ============================================================
        $this->command->line('');
        $this->command->info('📋 Creando Contrato 1: 3 meses...');
        
        $contrato1 = DB::transaction(function () use ($cliente, $plan, $zonaId, $paymentRepo) {
            $codigo = 'CON-TEST-' . now()->format('YmdHis') . '-001';
            $fechaInicio = now()->startOfMonth()->addMonths(1); // Octubre 2026
            $duracion = 3;
            $fechaFin = $fechaInicio->copy()->addMonths($duracion);

            $contrato = Contract::create([
                'codigo' => $codigo,
                'cliente_id' => $cliente->cliente_id,
                'plan_id' => $plan->plan_id,
                'zona_id' => $zonaId,
                'responsable_id' => 1, // Empleado de prueba
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'duracion_meses' => $duracion,
                'precio_contratado' => 80.00,
                'direccion_servicio' => $cliente->direccion ?? 'Dirección de prueba',
                'estado' => 'ACTIVO',
            ]);

            // Crear período inicial
            $periodo = ContractPeriod::create([
                'contrato_id' => $contrato->contrato_id,
                'numero_periodo' => 1,
                'fecha_inicio_periodo' => $fechaInicio,
                'fecha_fin_periodo' => $fechaFin,
                'duracion_meses_periodo' => $duracion,
                'estado_periodo' => 'ACTIVO',
            ]);

            $contrato->update(['periodo_actual' => $periodo->periodo_id]);

            // Generar pagos automáticamente
            $paymentRepo->generateMonthlyPayments($contrato, 1); // Usuario sistema ID 1

            return $contrato;
        });

        $pagos1 = $contrato1->payments()->count();
        $this->command->info("   ✅ Contrato {$contrato1->codigo} creado");
        $this->command->line("   📅 Período: {$contrato1->fecha_inicio->format('d/m/Y')} - {$contrato1->fecha_fin->format('d/m/Y')}");
        $this->command->line("   💰 Precio: S/ {$contrato1->precio_contratado}");
        $this->command->line("   📋 Pagos generados: {$pagos1}");

        // ============================================================
        // CONTRATO 4: 1 mes VENCIDO (septiembre 2026 - vence ayer)
        // ============================================================
        $this->command->line('');
        $this->command->info('📋 Creando Contrato 4: 1 mes VENCIDO (para pruebas)...');
        
        $contrato4 = DB::transaction(function () use ($cliente, $plan, $zonaId, $paymentRepo) {
            $codigo = 'CON-TEST-' . now()->format('YmdHis') . '-004';
            $fechaInicio = now()->startOfMonth(); // Septiembre 2026
            $duracion = 1;
            $fechaFin = $fechaInicio->copy()->addMonths($duracion);

            $contrato = Contract::create([
                'codigo' => $codigo,
                'cliente_id' => $cliente->cliente_id,
                'plan_id' => $plan->plan_id,
                'zona_id' => $zonaId,
                'responsable_id' => 1,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'duracion_meses' => $duracion,
                'precio_contratado' => 50.00,
                'direccion_servicio' => $cliente->direccion ?? 'Dirección de prueba',
                'estado' => 'ACTIVO',
            ]);

            $periodo = ContractPeriod::create([
                'contrato_id' => $contrato->contrato_id,
                'numero_periodo' => 1,
                'fecha_inicio_periodo' => $fechaInicio,
                'fecha_fin_periodo' => $fechaFin,
                'duracion_meses_periodo' => $duracion,
                'estado_periodo' => 'ACTIVO',
            ]);

            $contrato->update(['periodo_actual' => $periodo->periodo_id]);

            // Generar pagos automáticamente
            $paymentRepo->generateMonthlyPayments($contrato, 1);
            
            // IMPORTANTE: Modificar la fecha de vencimiento para que sea AYER
            DB::table('pagos')
                ->where('contrato_id', $contrato->contrato_id)
                ->update([
                    'fecha_vencimiento' => now()->subDay()->format('Y-m-d'), // Ayer (22/09/2026)
                    'estado' => 'VENCIDO' // Marcar como vencido directamente
                ]);

            return $contrato;
        });

        $pagos4 = $contrato4->payments()->count();
        $this->command->info("   ✅ Contrato {$contrato4->codigo} creado (VENCIDO AYER)");
        $this->command->line("   📅 Período: {$contrato4->fecha_inicio->format('d/m/Y')} - {$contrato4->fecha_fin->format('d/m/Y')}");
        $this->command->line("   💰 Precio: S/ {$contrato4->precio_contratado}");
        $this->command->line("   📋 Pagos generados: {$pagos4} (vencimiento: AYER)");
        $this->command->line("   ⚠️  Estado: VENCIDO");


        // ============================================================
        // CONTRATO 2: 6 meses (Octubre 2026 - Marzo 2027)
        // ============================================================
        $this->command->line('');
        $this->command->info('📋 Creando Contrato 2: 6 meses...');
        
        $contrato2 = DB::transaction(function () use ($cliente, $plan, $zonaId, $paymentRepo) {
            $codigo = 'CON-TEST-' . now()->format('YmdHis') . '-002';
            $fechaInicio = now()->startOfMonth()->addMonths(1); // Octubre 2026
            $duracion = 6;
            $fechaFin = $fechaInicio->copy()->addMonths($duracion);

            $contrato = Contract::create([
                'codigo' => $codigo,
                'cliente_id' => $cliente->cliente_id,
                'plan_id' => $plan->plan_id,
                'zona_id' => $zonaId,
                'responsable_id' => 1, // Empleado de prueba
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'duracion_meses' => $duracion,
                'precio_contratado' => 120.00,
                'direccion_servicio' => $cliente->direccion ?? 'Dirección de prueba',
                'estado' => 'ACTIVO',
            ]);

            // Crear período inicial
            $periodo = ContractPeriod::create([
                'contrato_id' => $contrato->contrato_id,
                'numero_periodo' => 1,
                'fecha_inicio_periodo' => $fechaInicio,
                'fecha_fin_periodo' => $fechaFin,
                'duracion_meses_periodo' => $duracion,
                'estado_periodo' => 'ACTIVO',
            ]);

            $contrato->update(['periodo_actual' => $periodo->periodo_id]);

            // Generar pagos automáticamente
            $paymentRepo->generateMonthlyPayments($contrato, 1); // Usuario sistema ID 1

            return $contrato;
        });

        $pagos2 = $contrato2->payments()->count();
        $this->command->info("   ✅ Contrato {$contrato2->codigo} creado");
        $this->command->line("   📅 Período: {$contrato2->fecha_inicio->format('d/m/Y')} - {$contrato2->fecha_fin->format('d/m/Y')}");
        $this->command->line("   💰 Precio: S/ {$contrato2->precio_contratado}");
        $this->command->line("   📋 Pagos generados: {$pagos2}");

        // ============================================================
        // CONTRATO 3: 12 meses (Octubre 2026 - Septiembre 2027)
        // ============================================================
        $this->command->line('');
        $this->command->info('📋 Creando Contrato 3: 12 meses...');
        
        $contrato3 = DB::transaction(function () use ($cliente, $plan, $zonaId, $paymentRepo) {
            $codigo = 'CON-TEST-' . now()->format('YmdHis') . '-003';
            $fechaInicio = now()->startOfMonth()->addMonths(1); // Octubre 2026
            $duracion = 12;
            $fechaFin = $fechaInicio->copy()->addMonths($duracion);

            $contrato = Contract::create([
                'codigo' => $codigo,
                'cliente_id' => $cliente->cliente_id,
                'plan_id' => $plan->plan_id,
                'zona_id' => $zonaId,
                'responsable_id' => 1, // Empleado de prueba
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'duracion_meses' => $duracion,
                'precio_contratado' => 150.00,
                'direccion_servicio' => $cliente->direccion ?? 'Dirección de prueba',
                'estado' => 'ACTIVO',
            ]);

            // Crear período inicial
            $periodo = ContractPeriod::create([
                'contrato_id' => $contrato->contrato_id,
                'numero_periodo' => 1,
                'fecha_inicio_periodo' => $fechaInicio,
                'fecha_fin_periodo' => $fechaFin,
                'duracion_meses_periodo' => $duracion,
                'estado_periodo' => 'ACTIVO',
            ]);

            $contrato->update(['periodo_actual' => $periodo->periodo_id]);

            // Generar pagos automáticamente
            $paymentRepo->generateMonthlyPayments($contrato, 1); // Usuario sistema ID 1

            return $contrato;
        });

        $pagos3 = $contrato3->payments()->count();
        $this->command->info("   ✅ Contrato {$contrato3->codigo} creado");
        $this->command->line("   📅 Período: {$contrato3->fecha_inicio->format('d/m/Y')} - {$contrato3->fecha_fin->format('d/m/Y')}");
        $this->command->line("   💰 Precio: S/ {$contrato3->precio_contratado}");
        $this->command->line("   📋 Pagos generados: {$pagos3}");

        // ============================================================
        // RESUMEN
        // ============================================================
        $this->command->line('');
        $this->command->info('📊 RESUMEN:');
        $this->command->line("   ✅ 3 contratos creados");
        $this->command->line("   💰 Total pagos generados: " . ($pagos1 + $pagos2 + $pagos3));
        $this->command->line("   📅 Todos inician en Octubre 2026");
        $this->command->line("   ✨ Estado: ACTIVO");
        $this->command->line('');
        $this->command->info('✅ ¡Listo! Ahora puedes ver los pagos en /payments');
    }
}
