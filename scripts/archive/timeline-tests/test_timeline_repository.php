<?php
/**
 * Test TimelineEventRepository
 * 
 * Verifies that the repository works correctly
 */

require __DIR__ . '/../app/Support/autoload.php';

use App\Repositories\TimelineEventRepository;
use App\Repositories\ImportSessionRepository;

try {
    echo "Testing TimelineEventRepository...\n";
    echo str_repeat("=", 80) . "\n\n";
    
    $repo = new TimelineEventRepository();
    $sessionRepo = new ImportSessionRepository();
    
    // Get or create daily session
    $session = $sessionRepo->getOrCreateDailySession('daily_actions');
    echo "✅ Using session ID: {$session->id}\n\n";
    
    // Test 1: Create supplier change event
    echo "Test 1: Create supplier change event\n";
    echo str_repeat("-", 80) . "\n";
    $eventId = $repo->create([
        'guarantee_number' => 'TEST/001',
        'record_id' => 999,
        'session_id' => $session->id,
        'event_type' => 'supplier_change',
        'field_name' => 'supplier',
        'old_value' => 'شركة أ',
        'new_value' => 'شركة ب',
        'old_id' => 10,
        'new_id' => 20,
        'change_type' => 'entity_change'
    ]);
    echo "✅ Created event ID: $eventId\n\n";
    
    // Test 2: Create extension event
    echo "Test 2: Create extension event\n";
    echo str_repeat("-", 80) . "\n";
    $extensionId = $repo->create([
        'guarantee_number' => 'TEST/001',
        'record_id' => 999,
        'session_id' => $session->id,
        'event_type' => 'extension',
        'field_name' => 'expiry_date',
        'old_value' => '2025-01-01',
        'new_value' => '2026-01-01',
        'change_type' => 'action'
    ]);
    echo "✅ Created extension event ID: $extensionId\n\n";
    
    // Test 3: Get events by guarantee number
    echo "Test 3: Get events for TEST/001\n";
    echo str_repeat("-", 80) . "\n";
    $events = $repo->getByGuaranteeNumber('TEST/001');
    echo "✅ Found " . count($events) . " events:\n";
    foreach ($events as $event) {
        echo "  - {$event['event_type']}: {$event['field_name']} from '{$event['old_value']}' to '{$event['new_value']}'\n";
    }
    echo "\n";
    
    // Test 4: Get supplier usage count
    echo "Test 4: Get supplier usage count\n";
    echo str_repeat("-", 80) . "\n";
    $usageCount = $repo->getSupplierUsageCount(20);
    echo "✅ Supplier 20 usage count: $usageCount\n\n";
    
    // Test 5: Get event statistics
    echo "Test 5: Get event statistics\n";
    echo str_repeat("-", 80) . "\n";
    $stats = $repo->getEventStatistics();
    echo "✅ Event statistics:\n";
    foreach ($stats as $stat) {
        echo sprintf(
            "  - %-20s: %3d events (First: %s, Latest: %s)\n",
            $stat['event_type'],
            $stat['count'],
            $stat['first_event'],
            $stat['latest_event']
        );
    }
    echo "\n";
    
    // Test 6: Get recent events
    echo "Test 6: Get recent events\n";
    echo str_repeat("-", 80) . "\n";
    $recent = $repo->getRecent(5);
    echo "✅ Found " . count($recent) . " recent events\n\n";
    
    echo str_repeat("=", 80) . "\n";
    echo "All tests passed! ✅\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
