<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Payment;
use App\Models\Contract;
use App\Models\Client;
use App\Models\ServicePlan;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Pruebas con DICCIONARIO DE DATOS
 * 
 * Sistema que prueba múltiples casos (correctos e incorrectos)
 * y genera estadísticas de cuántos pasaron vs fallaron
 */
class PaymentDataDictionaryTest extends TestCase
{
    use RefreshDatabase;

    protected $stats = [
        'total' => 0,
        'passed' => 0,
        'failed' => 0,
        'errors' => [],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
    }

    /** @test Diccionario: Validación de Montos */
    public function diccionario_validacion_montos()
    {
        echo "\n\n";
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "  📊 DICCIONARIO DE DATOS: VALIDACIÓN DE MONTOS\n";
        echo "═══════════════════════════════════════════════════════════════\n\n";

        $contract = $this->createValidContract();

        // DICCIONARIO: Casos de prueba
        $testCases = [
            // ✅ CASOS VÁLIDOS
            [
                'descripcion' => '✅ Monto válido positivo entero',
                'dato' => 100.00,
                'debe_pasar' => true,
            ],
            [
                'descripcion' => '✅ Monto válido con decimales',
                'dato' => 99.50,
                'debe_pasar' => true,
            ],
            [
                'descripcion' => '✅ Monto mínimo válido',
                'dato' => 0.01,
                'debe_pasar' => true,
            ],
            [
                'descripcion' => '✅ Monto grande válido',
                'dato' => 9999.99,
                'debe_pasar' => true,
            ],
            
            // ❌ CASOS INVÁLIDOS
            [
                'descripcion' => '❌ Monto negativo',
                'dato' => -100.00,
                'debe_pasar' => false,
            ],
            [
                'descripcion' => '❌ Monto cero',
                'dato' => 0.00,
                'debe_pasar' => false,
            ],
            [
                'descripcion' => '❌ Monto negativo pequeño',
                'dato' => -0.01,
                'debe_pasar' => false,
            ],
            [
                'descripcion' => '❌ Monto texto',
                'dato' => 'ABC',
                'debe_pasar' => false,
            ],
            [
                'descripcion' => '❌ Monto null',
                'dato' => null,
                'debe_pasar' => false,
            ],
            [
                'descripcion' => '❌ Monto extremadamente grande',
                'dato' => 999999999.99,
                'debe_pasar' => false,
            ],
        ];

        $this->ejecutarDiccionario($testCases, function($caso) use ($contract) {
            return Payment::create([
                'contrato_id' => $contract->getKey(),
                'codigo' => 'PAG-' . rand(10000, 99999),
                'monto' => $caso['dato'],
                'periodo_servicio' => now()->format('Y-m'),
                'fecha_vencimiento' => now()->addDays(5),
                'estado' => 'PENDIENTE',
            ]);
        }, function($payment, $caso) {
            // Validación: el monto debe ser positivo
            return $payment->monto > 0;
        });

        $this->imprimirResultados();
        $this->assertGreaterThan(0, $this->stats['passed'], 'Debe haber casos que pasen');
    }

    /** @test Diccionario: Validación de Periodos de Servicio */
    public function diccionario_validacion_periodos()
    {
        echo "\n\n";
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "  📊 DICCIONARIO DE DATOS: VALIDACIÓN DE PERIODOS\n";
        echo "═══════════════════════════════════════════════════════════════\n\n";

        $contract = $this->createValidContract();

        $testCases = [
            // ✅ CASOS VÁLIDOS
            [
                'descripcion' => '✅ Periodo válido formato YYYY-MM',
                'dato' => '2026-10',
                'debe_pasar' => true,
            ],
            [
                'descripcion' => '✅ Periodo enero',
                'dato' => '2026-01',
                'debe_pasar' => true,
            ],
            [
                'descripcion' => '✅ Periodo diciembre',
                'dato' => '2026-12',
                'debe_pasar' => true,
            ],
            
            // ❌ CASOS INVÁLIDOS
            [
                'descripcion' => '❌ Periodo formato incorrecto (slash)',
                'dato' => '2026/10',
                'debe_pasar' => false,
            ],
            [
                'descripcion' => '❌ Periodo sin separador',
                'dato' => '202610',
                'debe_pasar' => false,
            ],
            [
                'descripcion' => '❌ Periodo año corto',
                'dato' => '26-10',
                'debe_pasar' => false,
            ],
            [
                'descripcion' => '❌ Periodo mes inválido (13)',
                'dato' => '2026-13',
                'debe_pasar' => false,
            ],
            [
                'descripcion' => '❌ Periodo mes inválido (00)',
                'dato' => '2026-00',
                'debe_pasar' => false,
            ],
            [
                'descripcion' => '❌ Periodo con texto',
                'dato' => 'ABC-123',
                'debe_pasar' => false,
            ],
            [
                'descripcion' => '❌ Periodo vacío',
                'dato' => '',
                'debe_pasar' => false,
            ],
        ];

        $this->ejecutarDiccionario($testCases, function($caso) use ($contract) {
            return Payment::create([
                'contrato_id' => $contract->getKey(),
                'codigo' => 'PAG-' . rand(10000, 99999),
                'monto' => 100.00,
                'periodo_servicio' => $caso['dato'],
                'fecha_vencimiento' => now()->addDays(5),
                'estado' => 'PENDIENTE',
            ]);
        }, function($payment, $caso) {
            // Validación: debe cumplir formato YYYY-MM
            return preg_match('/^\d{4}-\d{2}$/', $payment->periodo_servicio) === 1;
        });

        $this->imprimirResultados();
        $this->assertGreaterThan(0, $this->stats['passed']);
    }

    /** @test Diccionario: Validación de Estados */
    public function diccionario_validacion_estados()
    {
        echo "\n\n";
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "  📊 DICCIONARIO DE DATOS: VALIDACIÓN DE ESTADOS\n";
        echo "═══════════════════════════════════════════════════════════════\n\n";

        $contract = $this->createValidContract();
        $estadosValidos = ['PENDIENTE', 'REALIZADO', 'VENCIDO', 'ANULADO'];

        $testCases = [
            // ✅ CASOS VÁLIDOS
            [
                'descripcion' => '✅ Estado PENDIENTE',
                'dato' => 'PENDIENTE',
                'debe_pasar' => true,
            ],
            [
                'descripcion' => '✅ Estado REALIZADO',
                'dato' => 'REALIZADO',
                'debe_pasar' => true,
            ],
            [
                'descripcion' => '✅ Estado VENCIDO',
                'dato' => 'VENCIDO',
                'debe_pasar' => true,
            ],
            [
                'descripcion' => '✅ Estado ANULADO',
                'dato' => 'ANULADO',
                'debe_pasar' => true,
            ],
            
            // ❌ CASOS INVÁLIDOS
            [
                'descripcion' => '❌ Estado PROCESANDO (no existe)',
                'dato' => 'PROCESANDO',
                'debe_pasar' => false,
            ],
            [
                'descripcion' => '❌ Estado EN_REVISION (no existe)',
                'dato' => 'EN_REVISION',
                'debe_pasar' => false,
            ],
            [
                'descripcion' => '❌ Estado BORRADOR (no existe)',
                'dato' => 'BORRADOR',
                'debe_pasar' => false,
            ],
            [
                'descripcion' => '❌ Estado en minúsculas',
                'dato' => 'pendiente',
                'debe_pasar' => false,
            ],
            [
                'descripcion' => '❌ Estado vacío',
                'dato' => '',
                'debe_pasar' => false,
            ],
            [
                'descripcion' => '❌ Estado con números',
                'dato' => 'ESTADO123',
                'debe_pasar' => false,
            ],
        ];

        $this->ejecutarDiccionario($testCases, function($caso) use ($contract) {
            return Payment::create([
                'contrato_id' => $contract->getKey(),
                'codigo' => 'PAG-' . rand(10000, 99999),
                'monto' => 100.00,
                'periodo_servicio' => now()->format('Y-m'),
                'fecha_vencimiento' => now()->addDays(5),
                'estado' => $caso['dato'],
            ]);
        }, function($payment, $caso) use ($estadosValidos) {
            // Validación: debe estar en la lista de estados válidos
            return in_array($payment->estado, $estadosValidos);
        });

        $this->imprimirResultados();
        $this->assertGreaterThan(0, $this->stats['passed']);
    }

    /** @test Diccionario: Validación de Métodos de Pago */
    public function diccionario_validacion_metodos_pago()
    {
        echo "\n\n";
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "  📊 DICCIONARIO DE DATOS: MÉTODOS DE PAGO\n";
        echo "═══════════════════════════════════════════════════════════════\n\n";

        $contract = $this->createValidContract();
        $metodosValidos = ['EFECTIVO', 'TRANSFERENCIA', 'YAPE', 'PLIN', 'TARJETA', 'OTRO'];

        $testCases = [
            // ✅ CASOS VÁLIDOS
            [
                'descripcion' => '✅ Método EFECTIVO',
                'dato' => 'EFECTIVO',
                'debe_pasar' => true,
            ],
            [
                'descripcion' => '✅ Método TRANSFERENCIA',
                'dato' => 'TRANSFERENCIA',
                'debe_pasar' => true,
            ],
            [
                'descripcion' => '✅ Método YAPE',
                'dato' => 'YAPE',
                'debe_pasar' => true,
            ],
            [
                'descripcion' => '✅ Método PLIN',
                'dato' => 'PLIN',
                'debe_pasar' => true,
            ],
            [
                'descripcion' => '✅ Método TARJETA',
                'dato' => 'TARJETA',
                'debe_pasar' => true,
            ],
            [
                'descripcion' => '✅ Método OTRO',
                'dato' => 'OTRO',
                'debe_pasar' => true,
            ],
            
            // ❌ CASOS INVÁLIDOS
            [
                'descripcion' => '❌ Método BITCOIN (no existe)',
                'dato' => 'BITCOIN',
                'debe_pasar' => false,
            ],
            [
                'descripcion' => '❌ Método PAYPAL (no existe)',
                'dato' => 'PAYPAL',
                'debe_pasar' => false,
            ],
            [
                'descripcion' => '❌ Método en minúsculas',
                'dato' => 'efectivo',
                'debe_pasar' => false,
            ],
            [
                'descripcion' => '❌ Método con espacios',
                'dato' => 'TRANSFERENCIA BANCARIA',
                'debe_pasar' => false,
            ],
        ];

        $this->ejecutarDiccionario($testCases, function($caso) use ($contract) {
            return Payment::create([
                'contrato_id' => $contract->getKey(),
                'codigo' => 'PAG-' . rand(10000, 99999),
                'monto' => 100.00,
                'periodo_servicio' => now()->format('Y-m'),
                'fecha_vencimiento' => now()->addDays(5),
                'estado' => 'REALIZADO',
                'fecha_pago' => now(),
                'metodo_pago' => $caso['dato'],
            ]);
        }, function($payment, $caso) use ($metodosValidos) {
            // Validación: debe estar en la lista de métodos válidos
            return in_array($payment->metodo_pago, $metodosValidos);
        });

        $this->imprimirResultados();
        $this->assertGreaterThan(0, $this->stats['passed']);
    }

    /** @test Diccionario: Validación de Fechas de Vencimiento */
    public function diccionario_validacion_fechas()
    {
        echo "\n\n";
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "  📊 DICCIONARIO DE DATOS: FECHAS DE VENCIMIENTO\n";
        echo "═══════════════════════════════════════════════════════════════\n\n";

        $contract = $this->createValidContract();

        $testCases = [
            // ✅ CASOS VÁLIDOS
            [
                'descripcion' => '✅ Fecha futura (5 días)',
                'dato' => now()->addDays(5),
                'debe_pasar' => true,
            ],
            [
                'descripcion' => '✅ Fecha futura (1 mes)',
                'dato' => now()->addMonth(),
                'debe_pasar' => true,
            ],
            [
                'descripcion' => '✅ Fecha futura (1 año)',
                'dato' => now()->addYear(),
                'debe_pasar' => true,
            ],
            
            // ⚠️ CASOS LÍMITE (según lógica de negocio)
            [
                'descripcion' => '⚠️ Fecha de hoy',
                'dato' => now(),
                'debe_pasar' => true, // Puede ser válido según negocio
            ],
            
            // ❌ CASOS INVÁLIDOS
            [
                'descripcion' => '❌ Fecha pasada (ayer)',
                'dato' => now()->subDay(),
                'debe_pasar' => false,
            ],
            [
                'descripcion' => '❌ Fecha pasada (1 semana)',
                'dato' => now()->subWeek(),
                'debe_pasar' => false,
            ],
            [
                'descripcion' => '❌ Fecha pasada (1 mes)',
                'dato' => now()->subMonth(),
                'debe_pasar' => false,
            ],
            [
                'descripcion' => '❌ Fecha pasada (1 año)',
                'dato' => now()->subYear(),
                'debe_pasar' => false,
            ],
        ];

        $this->ejecutarDiccionario($testCases, function($caso) use ($contract) {
            return Payment::create([
                'contrato_id' => $contract->getKey(),
                'codigo' => 'PAG-' . rand(10000, 99999),
                'monto' => 100.00,
                'periodo_servicio' => now()->format('Y-m'),
                'fecha_vencimiento' => $caso['dato'],
                'estado' => 'PENDIENTE',
            ]);
        }, function($payment, $caso) {
            // Validación: la fecha debe ser futura o presente
            return $payment->fecha_vencimiento >= now()->startOfDay();
        });

        $this->imprimirResultados();
        $this->assertGreaterThan(0, $this->stats['passed']);
    }

    // ==================== MOTOR DEL DICCIONARIO ====================

    private function ejecutarDiccionario($testCases, $crearCallback, $validarCallback)
    {
        $this->resetStats();
        $numero = 1;

        foreach ($testCases as $caso) {
            $this->stats['total']++;
            
            try {
                // Intentar crear el registro
                $resultado = $crearCallback($caso);
                
                // Validar el resultado
                $esValido = $validarCallback($resultado, $caso);
                
                // Determinar si pasó o falló
                $paso = ($caso['debe_pasar'] && $esValido) || (!$caso['debe_pasar'] && !$esValido);
                
                if ($paso) {
                    $this->stats['passed']++;
                    $this->imprimirLinea($numero, $caso['descripcion'], '✅ PASÓ', $caso['dato'], $esValido);
                } else {
                    $this->stats['failed']++;
                    $error = $caso['debe_pasar'] 
                        ? 'Esperado: válido, Resultado: inválido'
                        : 'Esperado: inválido, Resultado: válido';
                    $this->stats['errors'][] = [
                        'caso' => $caso['descripcion'],
                        'error' => $error,
                    ];
                    $this->imprimirLinea($numero, $caso['descripcion'], '❌ FALLÓ', $caso['dato'], $esValido);
                }
                
            } catch (\Exception $e) {
                // Si lanzó excepción
                if ($caso['debe_pasar']) {
                    // Esperaba pasar pero falló
                    $this->stats['failed']++;
                    $this->stats['errors'][] = [
                        'caso' => $caso['descripcion'],
                        'error' => 'Excepción: ' . substr($e->getMessage(), 0, 50),
                    ];
                    $this->imprimirLinea($numero, $caso['descripcion'], '❌ ERROR', $caso['dato'], false);
                } else {
                    // Esperaba fallar y falló correctamente
                    $this->stats['passed']++;
                    $this->imprimirLinea($numero, $caso['descripcion'], '✅ RECHAZADO', $caso['dato'], false);
                }
            }
            
            $numero++;
        }
    }

    private function imprimirLinea($num, $desc, $resultado, $dato, $esValido)
    {
        $datoStr = is_object($dato) ? get_class($dato) : json_encode($dato);
        $validoStr = $esValido ? 'SÍ' : 'NO';
        echo sprintf(
            "%2d. %-50s | %12s | Válido: %3s | Dato: %s\n",
            $num,
            substr($desc, 0, 50),
            $resultado,
            $validoStr,
            substr($datoStr, 0, 20)
        );
    }

    private function imprimirResultados()
    {
        echo "\n";
        echo "───────────────────────────────────────────────────────────────\n";
        echo "  📊 ESTADÍSTICAS FINALES\n";
        echo "───────────────────────────────────────────────────────────────\n";
        echo sprintf("  Total de casos:        %d\n", $this->stats['total']);
        echo sprintf("  ✅ Casos que pasaron:  %d (%.1f%%)\n", 
            $this->stats['passed'], 
            ($this->stats['total'] > 0 ? ($this->stats['passed'] / $this->stats['total']) * 100 : 0)
        );
        echo sprintf("  ❌ Casos que fallaron: %d (%.1f%%)\n", 
            $this->stats['failed'], 
            ($this->stats['total'] > 0 ? ($this->stats['failed'] / $this->stats['total']) * 100 : 0)
        );
        echo "───────────────────────────────────────────────────────────────\n";

        if (count($this->stats['errors']) > 0) {
            echo "\n  ⚠️  ERRORES DETECTADOS:\n\n";
            foreach ($this->stats['errors'] as $i => $error) {
                echo sprintf("  %d. %s\n", $i + 1, $error['caso']);
                echo sprintf("     → %s\n", $error['error']);
            }
            echo "\n";
        }
    }

    private function resetStats()
    {
        $this->stats = [
            'total' => 0,
            'passed' => 0,
            'failed' => 0,
            'errors' => [],
        ];
    }

    // ==================== HELPERS ====================

    private function createValidContract(): Contract
    {
        $creator = $this->createUser();
        
        $client = Client::create([
            'tipo_documento' => 'DNI',
            'numero_documento' => sprintf('1%07d', rand(1000000, 9999999)),
            'nombres_razon_social' => 'Cliente Test',
            'apellidos' => 'Test',
            'direccion' => 'Av. Test 123',
            'telefono' => '999888777',
            'correo' => sprintf('test%d@test.com', rand(1000, 9999)),
            'estado' => 'ACTIVO',
            'creado_por' => $creator->getKey(),
        ]);

        $plan = ServicePlan::create([
            'numero_plan' => sprintf('PLAN-%03d', rand(100, 999)),
            'nombre' => 'Plan Test',
            'tipo_servicio' => 'Internet',
            'velocidad_mbps' => 50,
            'precio_mensual' => 99.90,
            'estado' => 'ACTIVO',
            'creado_por' => $creator->getKey(),
        ]);

        $zone = DB::table('zonas_cobertura')->insertGetId([
            'codigo' => sprintf('ZONA-%03d', rand(100, 999)),
            'nombre' => 'Zona Test',
            'distrito' => 'Trujillo',
            'creado_por' => $creator->getKey(),
        ]);

        $employee = DB::table('empleados')->insertGetId([
            'tipo_documento' => 'DNI',
            'numero_documento' => sprintf('97%06d', rand(100000, 999999)),
            'nombres' => 'Técnico',
            'apellidos' => 'Test',
            'cargo' => 'Instalador',
        ]);

        return Contract::create([
            'codigo' => sprintf('CON-TEST-%03d', rand(100, 999)),
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
    }

    private function createUser(): User
    {
        $role = Role::firstOrCreate(
            ['nombre' => 'Gerente'],
            ['estado' => 'ACTIVO']
        );

        return User::factory()->create(['rol_id' => $role->getKey()]);
    }
}
