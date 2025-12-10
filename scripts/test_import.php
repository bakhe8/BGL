<?php
declare(strict_types=1);

require __DIR__ . '/../app/Support/autoload.php';

use App\Repositories\ImportSessionRepository;
use App\Repositories\ImportedRecordRepository;
use App\Services\ImportService;
use App\Support\XlsxReader;

$svc = new ImportService(new ImportSessionRepository(), new ImportedRecordRepository(), new XlsxReader());

try {
    $res = $svc->importExcel(__DIR__ . '/../storage/uploads/sample.xlsx');
    print_r($res);
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}
