<?php

namespace App\Services;

/** Small text-table PDF exporter; built-in WinAnsi fonts cover Spanish report labels. */
class ReportPdf
{
    private function text(float $x, float $y, string $text, int $size = 9, bool $bold = false): string
    {
        $bytes = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        $bytes = preg_replace('/[\x00-\x1f\x7f]/', ' ', $bytes ?: '');
        $bytes = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $bytes);
        return 'BT /'.($bold ? 'F2' : 'F1')." $size Tf 1 0 0 1 $x $y Tm ($bytes) Tj ET\n";
    }

    private function wrap(string $text, int $limit): array
    {
        $lines = [];
        foreach (preg_split('/\R/u', $text) as $part) {
            while (mb_strlen($part) > $limit) {
                $cut = mb_strrpos(mb_substr($part, 0, $limit + 1), ' ');
                $cut = $cut && $cut > $limit / 2 ? $cut : $limit;
                $lines[] = mb_substr($part, 0, $cut);
                $part = ltrim(mb_substr($part, $cut));
            }
            $lines[] = $part;
        }
        return $lines ?: [''];
    }

    public function render(array $report): string
    {
        $width = 770 / count($report['columns']);
        // Conservative width prevents text clipping even with wide capital letters.
        $limit = (int) floor(($width - 14) / 9);
        $pages = []; $page = []; $height = 0;
        foreach ($report['rows'] as $row) {
            $cells = array_map(fn ($value) => $this->wrap((string) ($value ?? '—'), $limit), $row);
            $rowHeight = max(array_map('count', $cells)) * 12 + 12;
            if ($height + $rowHeight > 365 && $page) {
                $pages[] = $page; $page = []; $height = 0;
            }
            $page[] = [$cells, $rowHeight]; $height += $rowHeight;
        }
        if ($page || ! $pages) $pages[] = $page;
        $streams = [];
        foreach ($pages as $index => $rows) {
            $stream = "0.07 0.22 0.17 rg\n".$this->text(36, 554, 'Lunnys Perú R.2.D. - Reporte de '.$report['title'], 17, true);
            $stream .= $this->text(36, 532, 'Consulta generada desde la base de datos operativa', 10, true);
            $params = array_column($report['parameters'], 1, 0);
            $stream .= $this->text(36, 512, 'Período: '.$params['Desde'].' al '.$params['Hasta'].' | Filtro: '.($params['Campo o corte'] ?? $params['Campo de fecha'] ?? $report['dateField'] ?? 'Período seleccionado'));
            $stream .= $this->text(36, 495, 'Generado: '.$params['Generado'].' | Total de registros: '.$params['Total de registros']);
            $headerHeight = max(array_map(fn ($label) => count($this->wrap($label, $limit)), $report['columns'])) * 12 + 12;
            $y = 480;
            $stream .= '0.91 0.95 0.93 rg 36 '.($y-$headerHeight).' 770 '.$headerHeight." re f 0.07 0.22 0.17 rg\n";
            foreach ($report['columns'] as $col => $label) {
                foreach ($this->wrap($label, $limit) as $line => $text) $stream .= $this->text(42+$col*$width, $y-16-$line*12, $text, 9, true);
            }
            $y -= $headerHeight;
            foreach ($rows as [$cells, $rowHeight]) {
                $stream .= '0.82 0.87 0.84 RG 0.4 w 36 '.($y-$rowHeight).' 770 '.$rowHeight." re S\n";
                foreach ($cells as $col => $lines) {
                    foreach ($lines as $line => $text) $stream .= $this->text(42+$col*$width, $y-15-$line*12, $text);
                }
                $y -= $rowHeight;
            }
            if (! $rows) $stream .= $this->text(42, $y-28, 'Sin registros para los filtros seleccionados.');
            $stream .= $this->text(36, 28, 'Lunnys Perú R.2.D. | Página '.($index+1).' de '.count($pages));
            $streams[] = $stream;
        }
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => '',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];
        $kids = [];
        foreach ($streams as $i => $stream) {
            $id = 5 + $i*2; $contentId = $id+1; $kids[] = "$id 0 R";
            $objects[$id] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $contentId 0 R >>";
            $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream."endstream";
        }
        $objects[2] = '<< /Type /Pages /Count '.count($kids).' /Kids ['.implode(' ', $kids).'] >>';
        $pdf = "%PDF-1.4\n"; $offsets = [0];
        foreach ($objects as $id => $object) { $offsets[$id] = strlen($pdf); $pdf .= "$id 0 obj\n$object\nendobj\n"; }
        $xref = strlen($pdf); $pdf .= 'xref'."\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) $pdf .= sprintf("%010d 00000 n \n", $offset);
        return $pdf.'trailer << /Size '.(count($objects)+1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    }
}
