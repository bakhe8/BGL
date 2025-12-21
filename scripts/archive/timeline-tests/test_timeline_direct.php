<?php
/**
 * Direct test of saveDecision to trigger Timeline logging
 */

// Autoloader
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) require_once $file;
});

require_once __DIR__ . '/../vendor/autoload.php';

use App\Repositories\ImportedRecordRepository;

echo "=== Direct Save Test ===\n\n";

// Get latest record via PDO
$pdo = \App\Support\Database::connection();
$stmt = $pdo->query("SELECT * FROM imported_records ORDER BY id DESC LIMIT 1");
$recordData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$recordData) {
    die("No record found!\n");
}

$repo = new ImportedRecordRepository();
$record = $repo->find($recordData['id']);

echo "Record ID: {$record->id}\n";
echo "Guarantee: {$record->guaranteeNumber}\n";
echo "Supplier ID: " . ($record->supplierId ?? 'NULL') . "\n";
echo "Bank ID: " . ($record->bankId ?? 'NULL') . "\n\n";

// Change supplier/bank
$newSupplierId = $record->supplierId == 1 ? 2 : 1;
$newBankId = $record->bankId == 1 ? 2 : 1;

echo "Changing to: Supplier=$newSupplierId, Bank=$newBankId\n\n";

// Update via repository (simulating what DecisionController does)
$update = [
    'match_status' => 'ready',
    'supplier_id' => $newSupplierId,
    'bank_id' => $newBankId,
    'supplier_display_name' => 'Test Supplier',
    'bank_display' => 'Test Bank'
];

echo "Updating record...\n";
$repo->updateDecision($record->id, $update);

echo "Update complete!\n\n";

// Now manually trigger Timeline logging (simulating DecisionController logic)
echo "Triggering Timeline logging...\n";

$logFile = __DIR__ . '/../debug.log';
file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "TEST: Manual timeline test for record {$record->id}\n", FILE_APPEND);

if (!$record->guaranteeNumber) {
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "TEST: CRITICAL - No guarantee number!\n", FILE_APPEND);
    echo "ERROR: No guarantee number! Cannot test timeline.\n";
} else {
    $timelineService = new \App\Services\TimelineEventService();
    
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "TEST: Logging supplier change\n", FILE_APPEND);
    
    try {
        $eventId = $timelineService->logSupplierChange(
            $record->guaranteeNumber,
            $record->id,
            $record->supplierId,
            $newSupplierId,
            'Old Supplier',
            'New Supplier',
            $record->sessionId
        );
        
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "TEST: Timeline event created! ID=$eventId\n", FILE_APPEND);
        echo "✅ Timeline event created! ID=$eventId\n";
    } catch (\Throwable $e) {
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "TEST: FAILED - " . $e->getMessage() . "\n", FILE_APPEND);
        echo "❌ FAILED: " . $e->getMessage() . "\n";
    }
}

echo "\nCheck debug.log for 'TEST:' messages\n";
