<?php
// Test logModificationIfNeeded directly
require __DIR__ . '/app/Support/autoload.php';

use App\Controllers\DecisionController;
use App\Repositories\ImportedRecordRepository;

$recordsRepo = new ImportedRecordRepository();

// Find a record to test with
$record = $recordsRepo->find(12837);

if (!$record) {
    echo "Record 12837 not found!\n";
    exit(1);
}

echo "Found record 12837:\n";
echo "Supplier ID: " . ($record->supplierId ?? 'NULL') . "\n";
echo "Supplier Name: " . ($record->supplierDisplayName ?? 'NULL') . "\n";
echo "\n";

// Create controller
$controller = new DecisionController($recordsRepo);

// Test with simulated update data (change supplier)
$updateData = [
    'supplier_id' => 156, // Different from current (should be 10)
    'supplier_display_name' => 'GULF HORIZON INTERNATIONAL MEDICAL',
    'bank_id' => $record->bankId,
    'match_status' => 'ready'
];

echo "Attempting to call logModificationIfNeeded with test data...\n";
echo "Update Data: " . json_encode($updateData) . "\n\n";

// Use reflection to call private method
$reflection = new ReflectionClass($controller);
$method = $reflection->getMethod('logModificationIfNeeded');
$method->setAccessible(true);

try {
    $method->invoke($controller, 12837, $updateData);
    echo "✅ Method executed without exception\n";
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

// Check if modification record was created
$db = new PDO('sqlite:database/bgl.sqlite');
$stmt = $db->query("SELECT COUNT(*) as count FROM imported_records WHERE record_type = 'modification'");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "\nModification records in DB: " . $result['count'] . "\n";
