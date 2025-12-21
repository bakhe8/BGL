<?php
// Check latest timeline events
require_once __DIR__ . '/../vendor/autoload.php';

$db = \App\Support\Database::connect();
$stmt = $db->query('
    SELECT id, event_type, guarantee_number, old_value, new_value, created_at 
    FROM guarantee_timeline_events 
    ORDER BY id DESC 
    LIMIT 15
');

echo "=== Latest Timeline Events ===\n\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']}\n";
    echo "  Type: {$row['event_type']}\n";
    echo "  Guarantee: {$row['guarantee_number']}\n";
    if ($row['old_value'] || $row['new_value']) {
        echo "  Change: {$row['old_value']} → {$row['new_value']}\n";
    }
    echo "  Time: {$row['created_at']}\n\n";
}
