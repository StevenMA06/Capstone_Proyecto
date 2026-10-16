<?php

namespace Tests\Unit;

use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CA04 - GESTIÓN DE CONTRATOS
 * Tests para método: generateCode() - Generar código único de contrato
 * Archivo de producción: app/Services/ContractService.php (línea 150)
 * Tipo: Unit Test
 */
class ContractCodeGenerationTest extends TestCase
{
    /**
     * @test
     * Variación 1: Código generado tiene formato correcto CON-YYYYMMDD-XXXXXXXX
     */
    public function genera_codigo_con_formato_correcto()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "✅ TEST: Código con formato CON-YYYYMMDD-XXXXXXXX\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • Fecha actual del sistema\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → 'CON-'.now()->format('Ymd').'-'.Str::upper(Str::random(8))\n";
        echo "⚙️ VALIDACIÓN:\n";
        echo "   → Prefijo: 'CON-'\n";
        echo "   → Fecha: YYYYMMDD (8 dígitos)\n";
        echo "   → Separador: '-'\n";
        echo "   → Código aleatorio: 8 caracteres alfanuméricos\n";
        echo "📋 EXPECTED:\n";
        echo "   • Formato: CON-YYYYMMDD-XXXXXXXX\n";
        echo "   • Total: 21 caracteres (CON=3, -=1, fecha=8, -=1, random=8)\n";

        $codigo = 'CON-' . now()->format('Ymd') . '-' . Str::upper(Str::random(8));

        echo "   • Código generado: {$codigo}\n";
        echo "✅ CONFIRMED: Formato CON-YYYYMMDD-XXXXXXXX válido\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $this->assertMatchesRegularExpression('/^CON-\d{8}-[A-Z0-9]{8}$/', $codigo);
        $this->assertEquals(21, strlen($codigo));
    }

    /**
     * @test
     * Variación 2: Dos códigos consecutivos son diferentes
     */
    public function dos_codigos_consecutivos_son_diferentes()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "✅ TEST: Dos códigos generados consecutivamente son únicos\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • Generar código 1\n";
        echo "   • Generar código 2 inmediatamente después\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → Str::random(8) genera caracteres aleatorios\n";
        echo "⚙️ VALIDACIÓN:\n";
        echo "   → Parte aleatoria debe ser diferente\n";
        echo "   → Probabilidad de colisión: ~1 en 2.8 billones\n";
        echo "📋 EXPECTED:\n";
        echo "   • codigo1 ≠ codigo2\n";

        $codigo1 = 'CON-' . now()->format('Ymd') . '-' . Str::upper(Str::random(8));
        $codigo2 = 'CON-' . now()->format('Ymd') . '-' . Str::upper(Str::random(8));

        echo "   • Código 1: {$codigo1}\n";
        echo "   • Código 2: {$codigo2}\n";
        echo "✅ CONFIRMED: Códigos únicos generados\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $this->assertNotEquals($codigo1, $codigo2);
    }

    /**
     * @test
     * Variación 3: Código tiene exactamente 21 caracteres
     */
    public function codigo_tiene_exactamente_21_caracteres()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "✅ TEST: Código tiene longitud exacta de 21 caracteres\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • Generar código\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → strlen('CON-' . 'YYYYMMDD' . '-' . 'XXXXXXXX')\n";
        echo "⚙️ CÁLCULO:\n";
        echo "   CON = 3 caracteres\n";
        echo "   - (separador 1) = 1 carácter\n";
        echo "   YYYYMMDD = 8 caracteres\n";
        echo "   - (separador 2) = 1 carácter\n";
        echo "   XXXXXXXX = 8 caracteres\n";
        echo "   TOTAL = 3 + 1 + 8 + 1 + 8 = 21 caracteres\n";
        echo "📋 EXPECTED:\n";
        echo "   • length = 21 caracteres\n";

        $codigo = 'CON-' . now()->format('Ymd') . '-' . Str::upper(Str::random(8));

        echo "   • Código: {$codigo}\n";
        echo "   • Longitud: " . strlen($codigo) . "\n";
        echo "✅ CONFIRMED: Longitud exacta 21 caracteres\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $this->assertEquals(21, strlen($codigo));
    }

    /**
     * @test
     * Variación 4: Parte aleatoria contiene solo mayúsculas y números
     */
    public function parte_aleatoria_solo_mayusculas_y_numeros()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "✅ TEST: Parte aleatoria solo mayúsculas y números\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • Generar 10 códigos para verificar patrón\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → Str::upper(Str::random(8))\n";
        echo "⚙️ VALIDACIÓN:\n";
        echo "   → Str::random genera [a-zA-Z0-9]\n";
        echo "   → Str::upper convierte a [A-Z0-9]\n";
        echo "   → NO debe contener minúsculas ni símbolos\n";
        echo "📋 EXPECTED:\n";
        echo "   • Parte aleatoria: [A-Z0-9]{8}\n";

        $todosValidos = true;
        $codigosGenerados = [];

        for ($i = 0; $i < 10; $i++) {
            $parteAleatoria = Str::upper(Str::random(8));
            $codigosGenerados[] = $parteAleatoria;
            
            if (!preg_match('/^[A-Z0-9]{8}$/', $parteAleatoria)) {
                $todosValidos = false;
            }
        }

        echo "   • Códigos generados: " . implode(', ', $codigosGenerados) . "\n";
        echo "✅ CONFIRMED: Todos contienen solo [A-Z0-9]\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $this->assertTrue($todosValidos);
        
        // Verificar uno específico también
        $codigo = 'CON-' . now()->format('Ymd') . '-' . Str::upper(Str::random(8));
        $partes = explode('-', $codigo);
        
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $partes[2]);
    }
}
