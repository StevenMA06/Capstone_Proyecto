<?php

namespace Tests\Unit;

use App\Http\Requests\ReportExportRequest;
use App\Services\ReportPdf;
use App\Services\ReportWorkbook;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class ReportExportTest extends TestCase
{
    private function report(array $rows): array
    {
        return ['title' => 'Ventas', 'columns' => ['Código', 'Cliente'], 'rows' => $rows,
            'parameters' => [['Desde','2026-04-01'],['Hasta','2026-09-30'],['Campo de fecha','Fecha de venta'],
                ['Generado','10/10/2026 09:00:00 (America/Lima)'],['Total de registros',(string) count($rows)]]];
    }

    public function test_pdf_accepts_current_service_parameters_and_nullable_numeric_cells(): void
    {
        $report = $this->report([[42, null]]);
        $report['parameters'][2] = ['Campo o corte', 'Fecha de solicitud'];
        $pdf = (new ReportPdf)->render($report);
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('Fecha de solicitud', $pdf);
        $this->assertStringContainsString('(42)', $pdf);
    }

    public function test_pdf_contains_all_rows_across_multiple_pages(): void
    {
        $rows = array_map(fn ($n) => ['VEN-'.$n, 'Cliente de ejemplo '.$n], range(1, 86));
        $pdf = (new ReportPdf)->render($this->report($rows));
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('VEN-86', $pdf);
        $this->assertGreaterThan(1, substr_count($pdf, '/Type /Page /Parent'));
        $this->assertStringContainsString('Total de registros: 86', $pdf);
    }

    public function test_excel_preserves_formula_like_strings_as_text(): void
    {
        $bytes = (new ReportWorkbook)->render($this->report([['00123', '=HYPERLINK("https://example.com")'], ['00234', 'José & María']]));
        $path = tempnam(sys_get_temp_dir(), 'report_test_');
        try {
            file_put_contents($path, $bytes); $zip = new ZipArchive; $zip->open($path);
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringContainsString('00123', $xml);
            $this->assertStringContainsString('t="inlineStr"', $xml);
            $this->assertStringNotContainsString('<f>', $xml);
            $this->assertStringContainsString('José &amp; María', $xml);
            $this->assertNotFalse($zip->getFromName('xl/worksheets/sheet2.xml'));$zip->close();
        } finally {unlink($path);}
    }

    public function test_empty_pdf_has_explicit_message(): void
    {
        $pdf = (new ReportPdf)->render($this->report([]));
        $this->assertStringContainsString('Sin registros', $pdf);
        $this->assertStringContainsString('Total de registros: 0', $pdf);
    }

    public function test_office_cannot_export_inventory_and_guest_is_denied(): void
    {
        $request = ReportExportRequest::create('/reports/export','POST',['type'=>'equipos_estado']);
        $request->setUserResolver(fn () => new class {
            public function can($ability) { return true; }
            public function isManager() { return false; }
        });
        $this->assertFalse($request->authorize());
        $request->merge(['type'=>'ventas_mes']);$this->assertTrue($request->authorize());
        $request->setUserResolver(fn () => null);$this->assertFalse($request->authorize());
    }
}
