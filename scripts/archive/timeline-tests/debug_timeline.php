<?php
/**
 * Debug: Check Timeline Events for a Record
 */

// Autoloader
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) require_once $file;
});

require_once __DIR__ . '/../vendor/autoload.php';

use App\Support\Database;

$pdo = Database::connection();

// Get record_id from command line or use latest
$recordId = $argv[1] ?? null;

if (!$recordId) {
    // Get latest record
    $stmt = $pdo->query("SELECT id, guarantee_number, raw_supplier_name FROM imported_records ORDER BY id DESC LIMIT 1");
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($record) {
        $recordId = $record['id'];
        echo "Using latest record: ID={$recordId}, Guarantee={$record['guarantee_number']}\n\n";
    } else {
        die("No records found!\n");
    }
}

echo "=== TIMELINE EVENTS for Record ID: $recordId ===\n\n";

// Get record details
$stmt = $pdo->prepare("SELECT * FROM imported_records WHERE id = ?");
$stmt->execute([$recordId]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    die("Record $recordId not found!\n");
}

echo "Record Details:\n";
echo "  Guarantee: " . ($record['guarantee_number'] ?? 'N/A') . "\n";
echo "  Supplier: " . ($record['raw_supplier_name'] ?? 'N/A') . "\n";
echo "  Bank: " . ($record['raw_bank_name'] ?? 'N/A') . "\n";
echo "  Amount: " . ($record['amount'] ?? 'N/A') . "\n";
echo "  Status: " . ($record['match_status'] ?? 'N/A') . "\n";
echo "\n";

// Check timeline_events table
echo "=== Timeline Events (guarantee_timeline_events) ===\n";
$stmt = $pdo->prepare("
    SELECT id, event_type, field_name, old_value, new_value, created_at 
    FROM guarantee_timeline_events 
    WHERE guarantee_number = ? OR record_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$record['guarantee_number'], $recordId]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "❌ NO timeline events found!\n";
    echo "This means changes are NOT being logged to guarantee_timeline_events table.\n\n";
} else {
    echo "✅ Found " . count($events) . " timeline events:\n\n";
    foreach ($events as $event) {
        echo "  - Event #{$event['id']}: {$event['event_type']}\n";
        echo "    Field: " . ($event['field_name'] ?? 'N/A') . "\n";
        echo "    Old: " . ($event['old_value'] ?? 'N/A') . "\n";
        echo "    New: " . ($event['new_value'] ?? 'N/A') . "\n";
        echo "    Time: {$event['created_at']}\n\n";
    }
}

// Check imported_records table for modification records
echo "=== Modification Records (imported_records with record_type='modification') ===\n";
$stmt = $pdo->prepare("
    SELECT id, record_type, created_at, comment 
    FROM imported_records 
    WHERE guarantee_number = ? AND record_type = 'modification'
    ORDER BY created_at DESC
");
$stmt->execute([$record['guarantee_number']]);
$modifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($modifications)) {
    echo "No modification records found (legacy system).\n\n";
} else {
    echo "Found " . count($modifications) . " modification records:\n\n";
    foreach ($modifications as $mod) {
        echo "  - Modification #{$mod['id']}\n";
        echo "    Time: {$mod['created_at']}\n";
        $comment = json_decode($mod['comment'], true);
        if ($comment && isset($comment['changes'])) {
            echo "    Changes: " . json_encode($comment['changes'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        }
        echo "\n";
    }
}

echo "=== DIAGNOSIS ===\n";
if (empty($events)) {
    echo "❌ Timeline events are NOT being created!\n";
    echo "\nPossible causes:\n";
    echo "1. TimelineEventService is not being called in saveDecision\n";
    echo "2. An exception is being thrown and caught silently\n";
    echo "3. The guarantee_number is empty or invalid\n";
    echo "\nCheck:\n";
    echo "- DecisionController::saveDecision (lines 458-500)\n";
    echo "- TimelineEventService methods\n";
    echo "- PHP error logs\n";
} else {
    echo "✅ Timeline events ARE being created correctly!\n";
    echo "If they don't appear in the UI, check:\n";
    echo "1. guarantee-history.php API\n";
    echo "2. guarantee-history.js frontend\n";
    echo "3. Browser cache\n";
}
