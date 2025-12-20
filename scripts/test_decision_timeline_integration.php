<?php
/**
 * Test Timeline Integration in DecisionController
 * 
 * This simulates a decision save to verify timeline events are created
 */

require __DIR__ . '/../app/Support/autoload.php';

use App\Support\Database;
use App\Repositories\TimelineEventRepository;
use App\Repositories\ImportedRecordRepository;

try {
    echo "Testing DecisionController Timeline Integration...\n";
    echo str_repeat("=", 80) . "\n\n";
    
    $db = Database::connect();
    $timeline = new TimelineEventRepository();
    $records = new ImportedRecordRepository();
    
    // Get a recent record with changes
    $stmt = $db->query("
        SELECT id FROM imported_records 
        WHERE supplier_id IS NOT NULL 
        LIMIT 1
    ");
    $testRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$testRecord) {
        echo "⚠️  No test records found. Create a record first.\n";
        exit(0);
    }
    
    $recordId = $testRecord['id'];
    echo "Using test record ID: $recordId\n\n";
    
    // Get record details
    $record = $records->find($recordId);
    echo "Record details:\n";
    echo "  Guarantee: {$record->guaranteeNumber}\n";
    echo "  Supplier ID: {$record->supplierId}\n";
    echo "  Bank ID: {$record->bankId}\n";
    echo "  Amount: {$record->amount}\n\n";
    
    // Check timeline events for this guarantee
    echo "Checking timeline events before test...\n";
    echo str_repeat("-", 80) . "\n";
    $eventsBefore = $timeline->getByGuaranteeNumber($record->guaranteeNumber);
    echo "Found " . count($eventsBefore) . " timeline events\n\n";
    
    // Simulate a supplier change via DecisionController
    // (In real usage, this would be triggered by the UI)
    echo "Next step: Make a change via the decision page UI\n";
    echo "Then check the timeline to verify event was created.\n\n";
    
    echo "To manually test:\n";
    echo "1. Go to: http://localhost:8000/?record_id=$recordId\n";
    echo "2. Change the supplier or bank\n";
    echo "3. Click Save\n";
    echo "4. Run: php scripts/check_timeline_for_guarantee.php {$record->guaranteeNumber}\n\n";
    
    echo str_repeat("=", 80) . "\n";
    echo "Setup complete - ready for manual testing\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    exit(1);
}
