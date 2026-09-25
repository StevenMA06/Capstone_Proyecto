<?php

namespace App\Services;

class ContractPdf
{
    public function render(object $contract): string
    {
        $tokens = $this->content($contract);
        $pages = [];
        $page = [];
        $available = 590;

        foreach ($tokens as $token) {
            if ($available < $token['height']) {
                $pages[] = $page;
                $page = [];
                $available = 590;
                if ($token['type'] === 'line') {
                    $continuation = $this->section('OBSERVACIONES (CONTINUACIÓN)');
                    $page[] = $continuation;
                    $available -= $continuation['height'];
                }
            }
            $page[] = $token;
            $available -= $token['height'];
        }
        $pages[] = $page;

        return $this->buildPdf($pages, $contract);
    }

    private function content(object $contract): array
    {
        $tokens = [
            $this->section('DATOS DEL TITULAR Y DEL SERVICIO'),
            $this->pair('Cliente', trim($contract->nombres_razon_social.' '.$contract->apellidos), 'Documento', $contract->tipo_documento.' '.$contract->numero_documento),
            $this->pair('Plan contratado', $contract->plan_nombre.' · '.$contract->numero_plan, 'Tarifa mensual', 'S/ '.number_format($contract->precio_contratado, 2)),
            $this->pair('Zona de cobertura', $contract->zona_nombre, 'Dirección de instalación', $contract->direccion_servicio),
            $this->pair('Inicio de vigencia', $this->date($contract->fecha_inicio), 'Fin de vigencia', $this->date($contract->fecha_fin)),
            $this->pair('Duración', $contract->duracion_meses.' meses', 'Responsable comercial', trim($contract->responsable_nombres.' '.$contract->responsable_apellidos)),
            $this->section('CONDICIONES PRINCIPALES'),
        ];

        foreach ([
            '1. Objeto. Lunnys Perú prestará el servicio indicado en este documento en la dirección registrada, sujeto a disponibilidad técnica y cobertura vigente.',
            '2. Vigencia. El contrato rige desde la fecha de inicio hasta la fecha de finalización señalada. Cualquier renovación o reactivación se registrará como un nuevo período contractual.',
            '3. Tarifa y pagos. El cliente abonará la tarifa mensual contratada dentro de los plazos comunicados por la empresa. La falta de pago se gestionará según las reglas vigentes del servicio.',
            '4. Conformidad. Las partes declaran que los datos consignados son correctos y aceptan las condiciones del servicio. La firma del cliente confirma su conocimiento y aceptación.',
        ] as $clause) {
            $tokens[] = $this->paragraph($clause);
        }

        $tokens[] = $this->section('OBSERVACIONES');
        $observations = trim((string) ($contract->observaciones ?? '')) ?: 'Sin observaciones adicionales.';
        foreach ($this->wrap($observations, 92) as $line) {
            $tokens[] = ['type' => 'line', 'text' => $line, 'height' => 13];
        }
        $tokens[] = ['type' => 'notice', 'text' => 'Documento preparado para la revisión y firma de las partes.', 'height' => 28];

        return $tokens;
    }

    private function section(string $title): array
    {
        return ['type' => 'section', 'text' => $title, 'height' => 27];
    }

    private function pair(string $leftLabel, string $leftValue, string $rightLabel, string $rightValue): array
    {
        $left = $this->wrap($leftValue, 37);
        $right = $this->wrap($rightValue, 37);

        return [
            'type' => 'pair', 'leftLabel' => $leftLabel, 'left' => $left,
            'rightLabel' => $rightLabel, 'right' => $right,
            'height' => 20 + max(count($left), count($right)) * 12,
        ];
    }

    private function paragraph(string $text): array
    {
        $lines = $this->wrap($text, 100);

        return ['type' => 'paragraph', 'lines' => $lines, 'height' => count($lines) * 13 + 7];
    }

    private function wrap(string $text, int $width): array
    {
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?: '';
        $words = preg_split('/\s+/u', $text) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line.' '.$word;
            if (mb_strlen($candidate) <= $width) {
                $line = $candidate;

                continue;
            }
            if ($line !== '') {
                $lines[] = $line;
            }
            $line = $word;
        }
        if ($line !== '' || $lines === []) {
            $lines[] = $line;
        }

        return $lines;
    }

    private function buildPdf(array $pages, object $contract): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $kids = [];
        $pageCount = count($pages);

        foreach ($pages as $index => $tokens) {
            $pageId = 5 + $index * 2;
            $streamId = $pageId + 1;
            $kids[] = $pageId.' 0 R';
            $stream = $this->pageStream($tokens, $contract, $index + 1, $pageCount, $index === $pageCount - 1);
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$streamId.' 0 R >>';
            $objects[$streamId] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$pageCount.' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $size = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        for ($id = 1; $id < $size; $id++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$id])."\n";
        }

        return $pdf."trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    private function pageStream(array $tokens, object $contract, int $page, int $pageCount, bool $last): string
    {
        $stream = "q 0.035 0.29 0.19 rg 0 755 595 87 re f Q\n";
        $stream .= $this->text('LUNNYS PERÚ', 50, 814, 16, true, '1 1 1');
        $stream .= $this->text('CONTRATO DE PRESTACIÓN DE SERVICIO', 50, 788, 13, true, '1 1 1');
        $stream .= $this->text('Contrato: '.$contract->codigo, 50, 768, 9.5, false, '0.86 0.95 0.89');
        $stream .= "q 0.035 0.47 0.275 rg 0 750 595 5 re f Q\n";
        $y = 725;

        foreach ($tokens as $token) {
            if ($token['type'] === 'section') {
                $stream .= 'q 0.92 0.96 0.93 rg 46 '.($y - 17)." 503 22 re f Q\n";
                $stream .= $this->text($token['text'], 54, $y - 10, 9, true, '0.035 0.36 0.22');
            } elseif ($token['type'] === 'pair') {
                $stream .= $this->text($token['leftLabel'], 50, $y - 9, 7.5, true, '0.35 0.43 0.38');
                $stream .= $this->text($token['rightLabel'], 310, $y - 9, 7.5, true, '0.35 0.43 0.38');
                foreach ($token['left'] as $line => $value) {
                    $stream .= $this->text($value, 50, $y - 22 - $line * 12, 9.5, $line === 0, '0.12 0.20 0.16');
                }
                foreach ($token['right'] as $line => $value) {
                    $stream .= $this->text($value, 310, $y - 22 - $line * 12, 9.5, $line === 0, '0.12 0.20 0.16');
                }
                $stream .= 'q 0.88 0.91 0.89 RG 0.45 w 50 '.($y - $token['height'] + 4).' m 545 '.($y - $token['height'] + 4)." l S Q\n";
            } elseif ($token['type'] === 'paragraph') {
                foreach ($token['lines'] as $line => $value) {
                    $stream .= $this->text($value, 50, $y - 10 - $line * 13, 9, false, '0.16 0.22 0.18');
                }
            } elseif ($token['type'] === 'line') {
                $stream .= $this->text($token['text'], 50, $y - 9, 9, false, '0.16 0.22 0.18');
            } elseif ($token['type'] === 'notice') {
                $stream .= 'q 0.97 0.98 0.97 rg 50 '.($y - 20)." 495 24 re f Q\n";
                $stream .= $this->text($token['text'], 60, $y - 12, 8.5, false, '0.28 0.38 0.31');
            }
            $y -= $token['height'];
        }

        if ($last) {
            // These fixed boxes are part of the automatic signature detection contract.
            $clientName = trim($contract->nombres_razon_social.' '.$contract->apellidos);
            $clientIdentity = $this->clip($clientName.' · '.$contract->tipo_documento.' '.$contract->numero_documento, 61);
            $responsibleName = trim($contract->responsable_nombres.' '.$contract->responsable_apellidos);
            $responsibleRole = trim((string) ($contract->responsable_cargo ?? 'Asistente de Ventas')) ?: 'Asistente de Ventas';
            $responsibleIdentity = $this->clip($responsibleName.' · '.$responsibleRole, 47);
            $stream .= "q 0.55 0.65 0.59 RG 0.8 w 50 25 270 70 re S 335 25 210 70 re S Q\n";
            $stream .= $this->text('Firma del cliente', 57, 82, 8.5, true, '0.18 0.31 0.23');
            $stream .= $this->text('Firma del Asistente de Ventas', 342, 82, 8.5, true, '0.18 0.31 0.23');
            $stream .= $this->text($clientIdentity, 57, 33, 6.5, false, '0.35 0.43 0.38');
            $stream .= $this->text($responsibleIdentity, 342, 33, 6.5, false, '0.35 0.43 0.38');
        }
        $stream .= "q 0.035 0.36 0.22 RG 0.5 w 50 113 m 545 113 l S Q\n";
        $stream .= $this->text('Lunnys Perú · Documento contractual', 50, 104, 7.5, false, '0.42 0.50 0.45');
        $stream .= $this->text("Página {$page} de {$pageCount}", 485, 104, 7.5, false, '0.42 0.50 0.45');

        return $stream;
    }

    private function text(string $value, float $x, float $y, float $size, bool $bold = false, string $color = '0 0 0'): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded ?: '');

        return "BT {$color} rg /".($bold ? 'F2' : 'F1')." {$size} Tf 1 0 0 1 {$x} {$y} Tm ({$escaped}) Tj ET\n";
    }

    private function date(string $value): string
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', substr($value, 0, 10));

        return $date ? $date->format('d/m/Y') : $value;
    }

    private function clip(string $value, int $length): string
    {
        return mb_strlen($value) <= $length ? $value : rtrim(mb_substr($value, 0, $length - 3)).'...';
    }
}
