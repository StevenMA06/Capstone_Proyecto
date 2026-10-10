<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

/** XLSX OpenXML with explicit text cells, including any leading formula characters. */
class ReportWorkbook
{
    private function xml(string $text): string
    {
        return htmlspecialchars(preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function sheet(array $rows, bool $filter = false): string
    {
        $last = chr(64 + count($rows[0])); $count = count($rows);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<dimension ref="A1:'.$last.$count.'"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="'.count($rows[0]).'" width="28" customWidth="1"/></cols><sheetData>';
        foreach ($rows as $i => $row) {
            $num = $i + 1; $xml .= '<row r="'.$num.'">';
            foreach ($row as $j => $value) $xml .= '<c r="'.chr(65+$j).$num.'" t="inlineStr" s="'.($i===0 ? 1 : 0).'"><is><t xml:space="preserve">'.$this->xml((string) $value).'</t></is></c>';
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';
        if ($filter) $xml .= '<autoFilter ref="A1:'.$last.$count.'"/>';
        return $xml.'<pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/></worksheet>';
    }

    public function render(array $report): string
    {
        $path = tempnam(sys_get_temp_dir(), 'lunnys_report_');
        if ($path === false) throw new RuntimeException('No se pudo preparar el archivo Excel.');
        $zip = new ZipArchive;
        try {
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('No se pudo crear el archivo Excel.');
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Datos" sheetId="1" r:id="rId1"/><sheet name="Parámetros" sheetId="2" r:id="rId2"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip->addFromString('xl/styles.xml', '<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF087B59"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="49" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
            $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet(array_merge([$report['columns']], $report['rows']), true));
            $params = $report['parameters'];
            if (! $report['rows']) $params[] = ['Resultado', 'Sin registros para el período seleccionado.'];
            $zip->addFromString('xl/worksheets/sheet2.xml', $this->sheet(array_merge([['Parámetro', 'Valor']], $params)));
            if (! $zip->close()) throw new RuntimeException('No se pudo finalizar el archivo Excel.');
            $content = file_get_contents($path);
            if ($content === false) throw new RuntimeException('No se pudo leer el archivo Excel.');
            return $content;
        } finally {
            if (is_file($path)) unlink($path);
        }
    }
}

