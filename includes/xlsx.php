<?php
declare(strict_types=1);

/**
 * Minimální export do Excelu (.xlsx) bez knihoven. Buňky: ['n' => číslo] | ['d' => 'RRRR-MM-DD'] | ['s' => text] | null.
 * Částky dostanou formát „# ##0,00“, data formát data podle Excelu. Bez rozšíření zip se pošle CSV pro český Excel.
 */
function xlsx_download(string $filename, string $sheet, array $header, array $rows): never
{
    while (ob_get_level()) ob_end_clean();
    if (!class_exists('ZipArchive')) xlsx_csv_fallback($filename, $header, $rows);

    $x = fn(string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $col = function (int $i): string { $s = ''; for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s; return $s; };
    $cell = function (string $ref, $c) use ($x): string {
        if ($c === null || $c === '') return '';
        if (isset($c['n'])) return '<c r="' . $ref . '" s="1"><v>' . (0 + $c['n']) . '</v></c>';
        if (isset($c['d'])) {                                  // jen platné RRRR-MM-DD, jinak prázdná buňka
            $dt = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $c['d'], new DateTimeZone('UTC'));
            return $dt ? '<c r="' . $ref . '" s="2"><v>' . (intdiv($dt->getTimestamp(), 86400) + 25569) . '</v></c>' : '';
        }
        $txt = is_array($c) ? (string) ($c['s'] ?? '') : (string) $c;
        return '<c r="' . $ref . '" t="inlineStr"' . (is_array($c) && !empty($c['b']) ? ' s="3"' : '') . '><is><t xml:space="preserve">' . $x($txt) . '</t></is></c>';
    };
    $data = '';
    foreach (array_merge([array_map(fn($h) => ['s' => $h, 'b' => 1], $header)], $rows) as $r => $cells) {
        $data .= '<row r="' . ($r + 1) . '">';
        foreach (array_values($cells) as $i => $c) $data .= $cell($col($i) . ($r + 1), $c);
        $data .= '</row>';
    }
    $cols = '';
    foreach ($header as $i => $h) $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . max(11, min(42, mb_strlen($h) + 4)) . '" customWidth="1"/>';
    $last = $col(count($header) - 1) . (count($rows) + 1);

    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $x(mb_substr($sheet, 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="14" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs></styleSheet>',
        'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols>' . $cols . '</cols><sheetData>' . $data . '</sheetData><autoFilter ref="A1:' . $last . '"/></worksheet>',
    ];
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    foreach ($files as $name => $xml) $zip->addFromString($name, $xml);
    $zip->close();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Content-Length: ' . filesize($tmp));
    header('Cache-Control: private, no-store');
    readfile($tmp);
    @unlink($tmp);
    exit;
}

/** CSV pro český Excel: UTF-8 s BOM, středník, desetinná čárka */
function xlsx_csv_fallback(string $filename, array $header, array $rows): never
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $header, ';');
    foreach ($rows as $cells) {
        fputcsv($out, array_map(function ($c) {
            if ($c === null) return '';
            if (isset($c['n'])) return number_format((float) $c['n'], 2, ',', '');
            if (isset($c['d'])) return ($t = strtotime($c['d'])) ? date('j.n.Y', $t) : '';
            return is_array($c) ? (string) ($c['s'] ?? '') : (string) $c;
        }, array_values($cells)), ';');
    }
    exit;
}
