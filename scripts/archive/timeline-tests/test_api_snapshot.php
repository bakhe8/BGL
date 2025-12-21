<?php
// Test API response
require_once __DIR__ . '/../vendor/autoload.php';

$guarantee = '04762IGS2300004';
$url = "http://localhost:8000/api/guarantee-history.php?number=" . urlencode($guarantee);

$response = file_get_contents($url);
$data = json_decode($response, true);

echo "=== API Response Test ===\n\n";

if ($data && isset($data['history'])) {
    echo "Total events: " . count($data['history']) . "\n\n";
    
    // Show first 3 events
    foreach (array_slice($data['history'], 0, 3) as $i => $event) {
        echo "Event #" . ($i + 1) . ":\n";
        echo "  ID: " . ($event['id'] ?? 'N/A') . "\n";
        echo "  Type: " . ($event['event_type'] ?? 'N/A') . "\n";
        echo "  Snapshot: " . (isset($event['snapshot']) && $event['snapshot'] ? 'YES (' . strlen(json_encode($event['snapshot'])) . ' bytes)' : 'NULL') . "\n";
        if (isset($event['snapshot']) && $event['snapshot']) {
            echo "  Snapshot keys: " . implode(', ', array_keys($event['snapshot'])) . "\n";
        }
        echo "\n";
    }
} else {
    echo "ERROR: " . ($data['error'] ?? 'Unknown error') . "\n";
}
