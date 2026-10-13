<?php

namespace Tests\Unit;

use App\Services\ContractPdf;
use PHPUnit\Framework\TestCase;

class ContractPdfTest extends TestCase
{
    public function test_generated_contract_is_a_pdf_with_essential_fields(): void
    {
        $record = (object) [
            'codigo' => 'CON-001', 'nombres_razon_social' => 'Ana', 'apellidos' => 'Pérez',
            'tipo_documento' => 'DNI', 'numero_documento' => '12345678',
            'plan_nombre' => 'Internet Hogar', 'numero_plan' => 'P-1',
            'precio_contratado' => 89.90, 'zona_nombre' => 'Trujillo',
            'direccion_servicio' => 'Calle Uno', 'fecha_inicio' => '2026-09-01',
            'duracion_meses' => 12, 'fecha_fin' => '2027-08-31', 'estado' => 'PENDIENTE',
            'responsable_nombres' => 'Luis', 'responsable_apellidos' => 'García',
            'responsable_cargo' => 'Asistente de Ventas',
            'observaciones' => null,
        ];
        $pdf = (new ContractPdf)->render($record);

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('CON-001', $pdf);
        $this->assertStringContainsString('89.90', $pdf);
        $this->assertStringContainsString('CONTRATO DE INTERNET', $pdf);
        $this->assertStringContainsString('Ana P', $pdf);
        $this->assertStringContainsString('DNI 12345678', $pdf);
        $this->assertStringContainsString('VENDEDOR', $pdf);
        $this->assertStringContainsString(iconv('UTF-8', 'Windows-1252//TRANSLIT', 'Código del contrato:'), $pdf);
        $this->assertStringNotContainsString('N.° de contrato', $pdf);
        $this->assertStringContainsString("LUNNY'S PERU R2D E.I.R.L.", $pdf);
        $this->assertStringContainsString('Comprado', $pdf);
        $this->assertStringContainsString('Fecha de inicio', $pdf);
        $this->assertStringContainsString('1 0 0 1 54 218 Tm (OBSERVACIONES)', $pdf);
        $this->assertStringNotContainsString('Lunnys Perú · Documento contractual', $pdf);
        $this->assertStringNotContainsString('Página 1 de 1', $pdf);
        $this->assertStringContainsString('startxref', $pdf);
        $this->assertStringEndsWith('%%EOF', $pdf);

        $record->observaciones = str_repeat('Observación extensa. ', 250);
        $longPdf = (new ContractPdf)->render($record);
        preg_match('/\/Count (\d+)/', $longPdf, $matches);
        $this->assertGreaterThan(1, (int) ($matches[1] ?? 0));
        $this->assertStringContainsString('CONTRATO SERVICIO DE INTERNET', $longPdf);
    }
}
