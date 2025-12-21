<?php
require_once __DIR__ . '/../vendor/autoload.php';

$db = \App\Support\Database::connect();

// Check for status_change events
$stmt = $db->query("
    SELECT id, event_type, guarantee_number, old_value, new_value, created_at
    FROM guarantee_timeline_events
    WHERE event_type = 'status_change'
    ORDER BY id DESC
    LIMIT 5
");

echo "=== Status Change Events ===\n\n";
$count = 0;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $count++;
    echo "ID: {$row['id']}\n";
    echo "  Guarantee: {$row['guarantee_number']}\n";
    echo "  Change: {$row['old_value']} → {$row['new_value']}\n";
    echo "  Time: {$row['created_at']}\n\n";
}

if ($count === 0) {
    echo "No status_change events found!\n";
    echo "\nThis means logStatusChange() is NOT being called.\n";
}
