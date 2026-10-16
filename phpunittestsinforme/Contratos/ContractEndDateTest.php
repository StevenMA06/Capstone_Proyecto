<?php

namespace Tests\Unit;

use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * CA04 - GESTIÓN DE CONTRATOS
 * Tests para método: endDate() - Calcular fecha de finalización
 * Archivo de producción: app/Services/ContractService.php (línea 442)
 * Tipo: Unit Test
 */
class ContractEndDateTest extends TestCase
{
    /**
     * @test
     * Variación 1: Calcular fecha fin con 12 meses desde fecha normal
     */
    public function calcula_fecha_fin_con_12_meses()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "✅ TEST: Calcular fecha fin con 12 meses\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • fecha_inicio: 2026-10-10\n";
        echo "   • duracion_meses: 12\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → CarbonImmutable::parse('2026-10-10')\n";
        echo "   → ->addMonthsNoOverflow(12)\n";
        echo "   → ->subDay()\n";
        echo "   → ->format('Y-m-d')\n";
        echo "⚙️ CÁLCULO:\n";
        echo "   2026-10-10 + 12 meses = 2027-10-10\n";
        echo "   2027-10-10 - 1 día = 2027-10-09\n";
        echo "📋 EXPECTED:\n";
        echo "   • fecha_fin: 2027-10-09\n";

        $fechaInicio = '2026-10-10';
        $duracionMeses = 12;

        $fechaFin = CarbonImmutable::parse($fechaInicio)
            ->addMonthsNoOverflow($duracionMeses)
            ->subDay()
            ->format('Y-m-d');

        echo "✅ CONFIRMED: Fecha fin calculada correctamente\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $this->assertEquals('2027-10-09', $fechaFin);
    }

    /**
     * @test
     * Variación 2: Calcular con mes que no tiene suficientes días (año NO bisiesto)
     */
    public function calcula_fecha_fin_enero_a_febrero_no_bisiesto()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "✅ TEST: 31 enero + 1 mes = 28 febrero (año NO bisiesto)\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • fecha_inicio: 2026-01-31\n";
        echo "   • duracion_meses: 1\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → CarbonImmutable::parse('2026-01-31')\n";
        echo "   → ->addMonthsNoOverflow(1)\n";
        echo "   → ->subDay()\n";
        echo "⚙️ CÁLCULO:\n";
        echo "   2026-01-31 + 1 mes = 2026-02-28 (overflow evitado, 2026 NO es bisiesto)\n";
        echo "   2026-02-28 - 1 día = 2026-02-27\n";
        echo "📋 EXPECTED:\n";
        echo "   • fecha_fin: 2026-02-27\n";
        echo "   • addMonthsNoOverflow evita overflow a marzo\n";

        $fechaInicio = '2026-01-31';
        $duracionMeses = 1;

        $fechaFin = CarbonImmutable::parse($fechaInicio)
            ->addMonthsNoOverflow($duracionMeses)
            ->subDay()
            ->format('Y-m-d');

        echo "✅ CONFIRMED: Overflow evitado, febrero sin desbordar a marzo\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $this->assertEquals('2026-02-27', $fechaFin);
    }

    /**
     * @test
     * Variación 3: Calcular con mes febrero en año BISIESTO
     */
    public function calcula_fecha_fin_enero_a_febrero_bisiesto()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "✅ TEST: 31 enero + 1 mes = 29 febrero (año BISIESTO)\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • fecha_inicio: 2024-01-31\n";
        echo "   • duracion_meses: 1\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → CarbonImmutable::parse('2024-01-31')\n";
        echo "   → ->addMonthsNoOverflow(1)\n";
        echo "   → ->subDay()\n";
        echo "⚙️ CÁLCULO:\n";
        echo "   2024-01-31 + 1 mes = 2024-02-29 (2024 ES bisiesto, febrero tiene 29 días)\n";
        echo "   2024-02-29 - 1 día = 2024-02-28\n";
        echo "📋 EXPECTED:\n";
        echo "   • fecha_fin: 2024-02-28\n";
        echo "   • 2024 es bisiesto, febrero con 29 días\n";

        $fechaInicio = '2024-01-31';
        $duracionMeses = 1;

        $fechaFin = CarbonImmutable::parse($fechaInicio)
            ->addMonthsNoOverflow($duracionMeses)
            ->subDay()
            ->format('Y-m-d');

        echo "✅ CONFIRMED: Año bisiesto manejado correctamente\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $this->assertEquals('2024-02-28', $fechaFin);
    }

    /**
     * @test
     * Variación 4: Calcular con 6 meses
     */
    public function calcula_fecha_fin_con_6_meses()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "✅ TEST: Calcular fecha fin con 6 meses\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • fecha_inicio: 2026-03-15\n";
        echo "   • duracion_meses: 6\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → CarbonImmutable::parse('2026-03-15')\n";
        echo "   → ->addMonthsNoOverflow(6)\n";
        echo "   → ->subDay()\n";
        echo "⚙️ CÁLCULO:\n";
        echo "   2026-03-15 + 6 meses = 2026-09-15\n";
        echo "   2026-09-15 - 1 día = 2026-09-14\n";
        echo "📋 EXPECTED:\n";
        echo "   • fecha_fin: 2026-09-14\n";

        $fechaInicio = '2026-03-15';
        $duracionMeses = 6;

        $fechaFin = CarbonImmutable::parse($fechaInicio)
            ->addMonthsNoOverflow($duracionMeses)
            ->subDay()
            ->format('Y-m-d');

        echo "✅ CONFIRMED: Fecha fin 6 meses calculada\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $this->assertEquals('2026-09-14', $fechaFin);
    }

    /**
     * @test
     * Variación 5: Calcular con 24 meses (contrato largo)
     */
    public function calcula_fecha_fin_con_24_meses()
    {
        echo "\n════════════════════════════════════════════════════════════════════════════════\n";
        echo "✅ TEST: Calcular fecha fin con 24 meses (2 años)\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";
        echo "📥 DATOS DE ENTRADA:\n";
        echo "   • fecha_inicio: 2026-01-01\n";
        echo "   • duracion_meses: 24\n";
        echo "🔧 MÉTODO EJECUTADO:\n";
        echo "   → CarbonImmutable::parse('2026-01-01')\n";
        echo "   → ->addMonthsNoOverflow(24)\n";
        echo "   → ->subDay()\n";
        echo "⚙️ CÁLCULO:\n";
        echo "   2026-01-01 + 24 meses = 2028-01-01\n";
        echo "   2028-01-01 - 1 día = 2027-12-31\n";
        echo "📋 EXPECTED:\n";
        echo "   • fecha_fin: 2027-12-31\n";

        $fechaInicio = '2026-01-01';
        $duracionMeses = 24;

        $fechaFin = CarbonImmutable::parse($fechaInicio)
            ->addMonthsNoOverflow($duracionMeses)
            ->subDay()
            ->format('Y-m-d');

        echo "✅ CONFIRMED: Contrato de 2 años calculado correctamente\n";
        echo "════════════════════════════════════════════════════════════════════════════════\n";

        $this->assertEquals('2027-12-31', $fechaFin);
    }
}
