<?php

namespace App\Services;

class ContractPdf
{
    public function render(object $contract): string
    {
        $lines = [
            'LUNNYS PERÚ - CONTRATO DE SERVICIO',
            'Contrato: '.$contract->codigo,
            'Cliente: '.trim($contract->nombres_razon_social.' '.$contract->apellidos),
            'Documento: '.$contract->tipo_documento.' '.$contract->numero_documento,
            'Plan: '.$contract->plan_nombre.' ('.$contract->numero_plan.')',
            'Precio mensual contratado: S/ '.number_format($contract->precio_contratado, 2),
            'Zona de cobertura: '.$contract->zona_nombre,
            'Dirección de servicio: '.$contract->direccion_servicio,
            'Fecha de inicio: '.$contract->fecha_inicio,
            'Duración: '.$contract->duracion_meses.' meses',
            'Fecha de finalización: '.$contract->fecha_fin,
            'Estado: '.$contract->estado,
            'Responsable: '.$contract->responsable_nombres.' '.$contract->responsable_apellidos,
            'Observaciones: '.($contract->observaciones ?: 'Sin observaciones'),
        ];
        $wrapped = [];
        foreach ($lines as $line) {
            foreach (mb_str_split(str_replace(["\r", "\n"], ['', ' '], $line), 78) as $part) {
                $wrapped[] = $part;
            }
        }
        $pages = array_chunk($wrapped, 29);
        $kids = [];
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];
        foreach ($pages as $index => $pageLines) {
            $pageId = 4 + $index * 2;
            $streamId = $pageId + 1;
            $kids[] = $pageId.' 0 R';
            $stream = "BT /F1 11 Tf 50 790 Td\n";
            foreach ($pageLines as $lineIndex => $line) {
                if ($lineIndex > 0) {
                    $stream .= "0 -24 Td\n";
                }
                $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $line);
                $stream .= '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded).") Tj\n";
            }
            $stream .= 'ET';
            if ($index === count($pages) - 1) {
                // Fixed signature fields let the verifier inspect the customer's field only.
                $stream .= "\n0.7 w 50 25 270 70 re S 335 25 210 70 re S\n";
                $stream .= "BT /F1 9 Tf 57 82 Td (Firma del cliente) Tj ET\n";
                $stream .= "BT /F1 9 Tf 342 82 Td (Firma del responsable) Tj ET";
            }
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$streamId.' 0 R >>';
            $objects[$streamId] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($pages).' >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $size = count($objects) + 1;
        $pdf .= "xref\n0 ".$size."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
        }

        return $pdf."trailer\n<< /Size ".$size." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
    }
}
