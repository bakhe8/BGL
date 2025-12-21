<?php
/**
 * Test saveDecision with Timeline Logging
 */

// Autoloader
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) require_once $file;
});

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\DecisionController;
use App\Repositories\ImportedRecordRepository;

echo "=== Testing saveDecision with Timeline Logging ===\n\n";

// Get the latest record
$repo = new ImportedRecordRepository();
$pdo = \App\Support\Database::connection();

$stmt = $pdo->query("SELECT id, guarantee_number, supplier_id, bank_id FROM imported_records ORDER BY id DESC LIMIT 1");
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    die("No records found!\n");
}

echo "Testing with Record:\n";
echo "  ID: {$record['id']}\n";
echo "  Guarantee: {$record['guarantee_number']}\n";
echo "  Current Supplier ID: " . ($record['supplier_id'] ?? 'NULL') . "\n";
echo "  Current Bank ID: " . ($record['bank_id'] ?? 'NULL') . "\n\n";

// Prepare test data - change supplier to different ID
$newSupplierId = ($record['supplier_id'] == 1) ? 2 : 1;
$newBankId = ($record['bank_id'] == 1) ? 2 : 1;

echo "Will change to:\n";
echo "  New Supplier ID: $newSupplierId\n";
echo "  New Bank ID: $newBankId\n\n";

// Create controller
$controller = new DecisionController($repo);

// Simulate POST data
$_SERVER['REQUEST_METHOD'] = 'POST';
$payload = [
    'match_status' => 'ready',
    'supplier_id' => $newSupplierId,
    'bank_id' => $newBankId,
    'supplier_name' => 'Test Supplier',
    'bank_name' => 'Test Bank',
    'supplier_display_name' => 'Test Supplier Display',
    'bank_display' => 'Test Bank Display'
];

echo "Calling saveDecision...\n";
echo "Check debug.log for Timeline messages!\n\n";

// Capture output
ob_start();
try {
    $controller->saveDecision($record['id'], $payload);
    $output = ob_get_clean();
    
    echo "Response:\n";
    echo $output . "\n";
    
    // Check if timeline events were created
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM guarantee_timeline_events 
        WHERE guarantee_number = ? 
        AND created_at > datetime('now', '-1 minute')
    ");
    $stmt->execute([$record['guarantee_number']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "\n=== RESULT ===\n";
    if ($result['count'] > 0) {
        echo "✅ SUCCESS! Created {$result['count']} timeline event(s)\n";
    } else {
        echo "❌ FAILED! No timeline events created\n";
        echo "Check debug.log for 'Timeline:' messages\n";
    }
    
} catch (\Throwable $e) {
    ob_end_clean();
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== Check debug.log ===\n";
echo "Last 5 lines:\n";
system('powershell -Command "Get-Content debug.log | Select-Object -Last 5"');
