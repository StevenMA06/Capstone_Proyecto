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
        // The printed company form ends at y=254 (the modem choice row). Leave
        // enough vertical space before the next section starts so the heading
        // and its background never cover that row.
        $tokens = [['type' => 'company_form', 'height' => 497]];
        $tokens[] = $this->section('OBSERVACIONES');
        $observations = trim((string) ($contract->observaciones ?? '')) ?: 'Sin observaciones adicionales.';
        foreach ($this->wrap($observations, 92) as $line) {
            $tokens[] = ['type' => 'line', 'text' => $line, 'height' => 13];
        }
        $tokens[] = $this->paragraph('El presente contrato será resuelto, a solicitud de una de las partes, para lo cual se tendrá que comunicar dicha decisión con 30 días de anticipación, sin perjuicio de ninguna de las partes.');

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
        $stream .= $this->text("LUNNY'S PERU R2D E.I.R.L.", 50, 814, 16, true, '1 1 1');
        $stream .= $this->text('CONTRATO DE INTERNET', 50, 788, 13, true, '1 1 1');
        $stream .= $this->text('Código del contrato: '.$contract->codigo, 50, 768, 9.5, false, '0.86 0.95 0.89');
        $stream .= "q 0.035 0.47 0.275 rg 0 750 595 5 re f Q\n";
        $y = 725;

        foreach ($tokens as $token) {
            if ($token['type'] === 'company_form') {
                $stream .= $this->companyForm($contract);
            } elseif ($token['type'] === 'section') {
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
            // Keep the customer's handwritten signature inside the detector's fixed region.
            // Match the paper form: signatures above a line, with the role caption below it.
            // Keep the line below the detector's signature scan area to avoid false positives.
            $stream .= "q 0.70 0.76 0.72 RG 0.7 w 50 32 m 320 32 l 335 32 m 545 32 l S Q\n";
            $stream .= $this->text('CONTRATO SERVICIO DE INTERNET', 82, 20, 7.5, true, '0.18 0.31 0.23');
            $stream .= $this->text('VENDEDOR', 410, 20, 7.5, true, '0.18 0.31 0.23');
        }
        return $stream;
    }

    private function companyForm(object $contract): string
    {
        $name = trim($contract->nombres_razon_social.' '.$contract->apellidos);
        $phone = trim((string) ($contract->cliente_telefono ?? ''));
        $alternate = trim((string) ($contract->cliente_telefono_alterno ?? ''));
        $reference = trim((string) ($contract->cliente_referencia ?? ''));
        $email = trim((string) ($contract->cliente_correo ?? ''));
        $stream = '';
        $stream .= $this->sectionBar('DATOS DEL CLIENTE', 716);
        $stream .= $this->field('Nombres y apellidos', $name, 52, 680, 315);
        $stream .= $this->field('D.N.I.', $contract->tipo_documento.' '.$contract->numero_documento, 380, 680, 165);
        $stream .= $this->field('Correo', $email ?: 'No registrado', 52, 646, 315);
        $stream .= $this->field('Nombre previo', '', 380, 646, 165, true);
        $stream .= $this->field('Fecha de inicio', $this->date($contract->fecha_inicio), 52, 610, 220);
        $stream .= $this->field('Fecha de término', $this->date($contract->fecha_fin), 310, 610, 235);

        $stream .= $this->sectionBar('TIPO DE INSTALACIÓN', 580);
        $stream .= $this->choice('Fibra óptica', 52, 548);
        $stream .= $this->choice('Inalámbrico', 210, 548);

        $stream .= $this->sectionBar('DIRECCIÓN DE INSTALACIÓN', 520);
        $stream .= $this->field('Dirección', $contract->direccion_servicio, 52, 486, 493);
        $stream .= $this->field('Barrio / distrito', $contract->cliente_distrito ?? '', 52, 452, 220);
        $stream .= $this->field('Teléfono', $phone, 310, 452, 235);
        $stream .= $this->field('Puntos de referencia', $reference ?: 'No registrado', 52, 418, 493);
        $stream .= $this->field('Celular alterno', $alternate ?: 'No registrado', 52, 384, 220);

        $stream .= $this->sectionBar('SERVICIO A SUMINISTRAR', 352);
        $stream .= $this->field('Plan contratado', trim($contract->plan_nombre.' · '.$contract->numero_plan), 52, 318, 315);
        $stream .= $this->field('Mensualidad', 'S/ '.number_format((float) $contract->precio_contratado, 2), 380, 318, 165);
        $stream .= $this->text('Internet  [X]       Cable  [ ]       Canal Premium  [ ]', 52, 282, 9, false, '0.12 0.20 0.16');
        $stream .= $this->text('Módem:', 52, 254, 9, true, '0.12 0.20 0.16');
        $stream .= $this->choice('Comprado', 100, 254);
        $stream .= $this->choice('Propio', 245, 254);
        $stream .= $this->choice('Prestado', 390, 254);
        return $stream;
    }

    private function sectionBar(string $label, int $y): string
    {
        return "q 0.89 0.92 0.90 rg 48 ".($y - 12)." 499 20 re f Q\n".$this->text($label, 54, $y - 5, 8, true, '0.035 0.36 0.22');
    }

    private function field(string $label, string $value, int $x, int $y, int $width, bool $writeIn = false): string
    {
        $stream = '';
        if ($writeIn) $stream .= "q 0.97 0.98 0.97 rg {$x} ".($y - 17)." {$width} 23 re f Q\n";
        $stream .= $this->text($label, $x, $y, 7, true, '0.35 0.43 0.38');
        $stream .= $this->text($this->clip($value, max(12, (int) ($width / 5))), $x, $y - 12, 9, false, '0.12 0.20 0.16');
        $stream .= "q 0.55 0.65 0.59 RG 0.5 w {$x} ".($y - 17)." m ".($x + $width)." ".($y - 17)." l S Q\n";
        return $stream;
    }

    private function choice(string $label, int $x, int $y): string
    {
        return $this->text('[ ]', $x, $y, 9, false, '0.12 0.20 0.16')
            .$this->text($label, $x + 20, $y, 9, false, '0.12 0.20 0.16');
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
