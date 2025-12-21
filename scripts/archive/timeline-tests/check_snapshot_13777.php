<?php
require 'app/Support/autoload.php';

$db = \App\Support\Database::connect();

// Get latest timeline event for record 13777
$stmt = $db->prepare("
    SELECT 
        id,
        event_type,
        guarantee_number,
        snapshot_data,
        created_at
    FROM guarantee_timeline_events
    WHERE record_id = 13777
      AND event_type = 'status_change'
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if ($event) {
    echo "Event ID: {$event['id']}\n";
    echo "Type: {$event['event_type']}\n";
    echo "Created: {$event['created_at']}\n\n";
    
    if ($event['snapshot_data']) {
        $snapshot = json_decode($event['snapshot_data'], true);
        echo "Snapshot data:\n";
        echo "  Supplier: " . ($snapshot['supplier_name'] ?? 'N/A') . "\n";
        echo "  Bank: " . ($snapshot['bank_name'] ?? 'N/A') . "\n";
    } else {
        echo "No snapshot data\n";
    }
} else {
    echo "No status_change event found for record 13777\n";
}
