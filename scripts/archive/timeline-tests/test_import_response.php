<?php
/**
 * Test Import Service Response
 * Verify that first_record_id is being returned
 */

require_once __DIR__ . '/../app/bootstrap.php';

use App\Services\ImportService;
use App\Repositories\ImportSessionRepository;
use App\Repositories\ImportedRecordRepository;

// Create minimal test Excel file
$testData = [
    ['المورد', 'البنك', 'المبلغ', 'رقم الضمان', 'رقم العقد', 'تاريخ الانتهاء'],
    ['مورد تجريبي', 'البنك الأهلي', '10000', 'TEST/2025/001', 'CT-001', '2025-12-31']
];

// Create temporary Excel file
$tempFile = sys_get_temp_dir() . '/test_import_' . time() . '.xlsx';

// Use PhpSpreadsheet to create Excel
$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

foreach ($testData as $rowIndex => $rowData) {
    foreach ($rowData as $colIndex => $cellValue) {
        $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 1, $cellValue);
    }
}

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save($tempFile);

echo "Created test file: $tempFile\n\n";

// Test Import Service
$sessionRepo = new ImportSessionRepository();
$recordRepo = new ImportedRecordRepository();
$importService = new ImportService($sessionRepo, $recordRepo);

try {
    $result = $importService->importExcel($tempFile);
    
    echo "Import Result:\n";
    echo "=============\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    // Check if first_record_id exists
    if (isset($result['first_record_id'])) {
        echo "✅ first_record_id is present: " . $result['first_record_id'] . "\n";
        
        // Verify the record exists
        $record = $recordRepo->find($result['first_record_id']);
        if ($record) {
            echo "✅ Record exists in database\n";
            echo "   Guarantee Number: " . $record->guaranteeNumber . "\n";
            echo "   Supplier: " . $record->rawSupplierName . "\n";
        } else {
            echo "❌ Record NOT found in database!\n";
        }
    } else {
        echo "❌ first_record_id is MISSING from result!\n";
        echo "This means the code change didn't work.\n";
    }
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

// Cleanup
if (file_exists($tempFile)) {
    unlink($tempFile);
    echo "\nCleaned up test file.\n";
}
