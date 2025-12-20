<?php
/**
 * Check if timeline service is being called
 */

// Add explicit logging
$testLog = __DIR__ . '/../timeline_test.log';
file_put_contents($testLog, date('[Y-m-d H:i:s] ') . "Test script started\n", FILE_APPEND);

try {
    require __DIR__ . '/../app/Support/autoload.php';
    
    $service = new \App\Services\TimelineEventService();
    file_put_contents($testLog, date('[Y-m-d H:i:s] ') . "Service instantiated successfully\n", FILE_APPEND);
    
    // Try to log a test event
    $eventId = $service->logSupplierChange(
        'TEST/DEBUG/001',
        99999,
        null,
        999,
        null,
        'Test Supplier'
    );
    
    file_put_contents($testLog, date('[Y-m-d H:i:s] ') . "Event created with ID: $eventId\n", FILE_APPEND);
    echo "✅ Test event created: $eventId\n";
    echo "Check timeline_test.log for details\n";
    
} catch (Exception $e) {
    file_put_contents($testLog, date('[Y-m-d H:i:s] ') . "ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    echo "❌ Error: " . $e->getMessage() . "\n";
}
