<?php

namespace App\Services\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * CSV and XLSX exports with no third-party dependency.
 * XLSX is a minimal, standards-compliant SpreadsheetML package (shared-string free,
 * inline strings, numeric cells typed as numbers).
 * PDF exports are rendered client-side from the same JSON (see frontend lib/export.ts).
 */
class Exporter
{
    /** @param list<array<string,mixed>> $rows */
    public function download(string $format, string $filename, array $rows, ?array $columns = null): StreamedResponse
    {
        $columns ??= $rows ? array_keys($rows[0]) : [];

        return match ($format) {
            'xlsx' => $this->xlsx($filename, $rows, $columns),
            default => $this->csv($filename, $rows, $columns),
        };
    }

    private function csv(string $filename, array $rows, array $columns): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $columns) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, array_map([$this, 'heading'], $columns));
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($c) => $this->cell($row[$c] ?? null, true), $columns));
            }
            fclose($out);
        }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    private function xlsx(string $filename, array $rows, array $columns): StreamedResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ffx');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf fontId="0"/><xf fontId="1" applyFont="1"/></cellXfs></styleSheet>');

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetData>';
        $xml .= $this->row(1, array_map([$this, 'heading'], $columns), true);
        foreach (array_values($rows) as $i => $row) {
            $xml .= $this->row($i + 2, array_map(fn ($c) => $row[$c] ?? null, $columns));
        }
        $xml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();

        return response()->streamDownload(function () use ($tmp) {
            readfile($tmp);
            @unlink($tmp);
        }, $filename.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'no-store']);
    }

    private function row(int $n, array $values, bool $bold = false): string
    {
        $out = "<row r=\"{$n}\">";
        foreach (array_values($values) as $i => $v) {
            $ref = $this->col($i).$n;
            $style = $bold ? ' s="1"' : '';
            $v = $this->cell($v, false);
            if (is_int($v) || is_float($v) || (is_string($v) && preg_match('/^-?(0|[1-9]\d{0,13})(\.\d+)?$/', $v))) {
                $out .= "<c r=\"{$ref}\"{$style}><v>{$v}</v></c>";
            } else {
                $out .= "<c r=\"{$ref}\" t=\"inlineStr\"{$style}><is><t xml:space=\"preserve\">".htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
            }
        }

        return $out.'</row>';
    }

    private function col(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + (($i - 1) % 26)).$s;
        }

        return $s;
    }

    private function heading(string $c): string
    {
        return ucfirst(str_replace('_', ' ', $c));
    }

    private function cell(mixed $v, bool $csv): mixed
    {
        if (is_bool($v)) {
            return $v ? 'Yes' : 'No';
        }
        if (is_array($v)) {
            return json_encode($v);
        }
        // CSV/formula injection guard
        if ($csv && is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) && ! is_numeric($v)) {
            return "'".$v;
        }

        return $v;
    }
}
