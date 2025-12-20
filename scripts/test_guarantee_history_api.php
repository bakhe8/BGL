<?php
require __DIR__ . '/../app/Support/autoload.php';

// Test timeline API with guaranteed timeline events
$testNumber = 'TEST/SERVICE/001';

echo "Testing guarantee-history.php API with: $testNumber\n";
echo str_repeat("=", 80) . "\n\n";

$url = "http://localhost:8000/www/api/guarantee-history.php?number=" . urlencode($testNumber);
$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data['success']) {
    echo "✅ API Success!\n\n";
    echo "Total Records: {$data['total_records']}\n\n";
    
    foreach ($data['history'] as $event) {
        echo "Event #{$event['id']}\n";
        echo "  Source: {$event['source']}\n";
        echo "  Event Type: {$event['event_type']}\n";
        
        if ($event['source'] === 'timeline') {
            echo "  Badge: {$event['badge']}\n";
            echo "  Description: {$event['description']}\n";
            if (!empty($event['field_name'])) {
                echo "  Field: {$event['field_name']}\n";
                echo "  From: {$event['old_value']} → To: {$event['new_value']}\n";
            }
        }
        
        echo "  Created: {$event['created_at']}\n";
        echo "\n";
    }
} else {
    echo "❌ API Error: {$data['error']}\n";
}
