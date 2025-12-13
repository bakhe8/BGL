<?php
require __DIR__ . '/../app/Support/autoload.php';

use App\Repositories\ImportSessionRepository;
use App\Repositories\ImportedRecordRepository;
use App\Services\ImportService;

// Setup
$importSessionRepo = new ImportSessionRepository();
$importedRecordRepo = new ImportedRecordRepository();
$importService = new ImportService($importSessionRepo, $importedRecordRepo);

$file = __DIR__ . '/../storage/uploads/upload_20251213_095113.xlsx';

echo "Attempting to import: $file\n";

if (!file_exists($file)) {
    die("File not found!\n");
}

try {
    $result = $importService->importExcel($file);
    echo "Import Result:\n";
    print_r($result);
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
