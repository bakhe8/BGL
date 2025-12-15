<?php
declare(strict_types=1);

$file = __DIR__ . '/../storage/uploads/sample.xlsx';
$zip = new ZipArchive();
if ($zip->open($file) !== true) {
    exit("Failed to open {$file}\n");
}
for ($i = 0; $i < $zip->numFiles; $i++) {
    $s = $zip->statIndex($i, ZipArchive::FL_NODIR);
    echo $s['name'] . PHP_EOL;
}
$zip->close();
