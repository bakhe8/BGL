<?php
declare(strict_types=1);

$file = __DIR__ . '/../storage/uploads/sample.xlsx';

$shared = simplexml_load_file("zip://{$file}#xl/sharedStrings.xml");
$sheet = simplexml_load_file("zip://{$file}#xl/worksheets/sheet1.xml");

if (!$sheet || !$shared) {
    exit("Failed to read XLSX\n");
}

$headers = [];
foreach ($sheet->sheetData->row[0]->c as $cell) {
    $t = (string)($cell['t'] ?? '');
    $val = (string)$cell->v;
    if ($t === 's') {
        $idx = (int)$val;
        $val = (string)$shared->si[$idx]->t;
    }
    $headers[] = $val;
}

print_r($headers);
