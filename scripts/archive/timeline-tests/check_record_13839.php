<?php
require 'app/Support/autoload.php';

$db = \App\Support\Database::connection();

echo "=== Checking Record 13839 ===\n\n";

// Get timeline event
$stmt = $db->prepare("
    SELECT id, event_type, snapshot_data, created_at
    FROM guarantee_timeline_events
    WHERE record_id = 13839 AND event_type = 'status_change'
    ORDER BY id DESC LIMIT 1
");
$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if ($event) {
    echo "Event ID: {$event['id']}\n";
    echo "Created: {$event['created_at']}\n\n";
    
    if ($event['snapshot_data']) {
        $snapshot = json_decode($event['snapshot_data'], true);
        echo "Snapshot:\n";
        echo "  Supplier Name: " . ($snapshot['supplier_name'] ?? 'N/A') . "\n";
        echo "  Bank Name: " . ($snapshot['bank_name'] ?? 'N/A') . "\n";
    } else {
        echo "No snapshot!\n";
    }
} else {
    echo "No status_change event found!\n";
}

// Get actual record
$stmt = $db->prepare("SELECT bank_id FROM imported_records WHERE id = 13839");
$stmt->execute();
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if ($record && $record['bank_id']) {
    echo "\nBank ID: {$record['bank_id']}\n";
    
    $stmt = $db->prepare("SELECT official_name FROM banks WHERE id = :id");
    $stmt->execute([':id' => $record['bank_id']]);
    $bankName = $stmt->fetchColumn();
    echo "Bank official_name in DB: {$bankName}\n";
}
