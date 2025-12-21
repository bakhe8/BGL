<?php
/**
 * Test TimelineEventService
 */

require __DIR__ . '/../app/Support/autoload.php';

use App\Services\TimelineEventService;

try {
    echo "Testing TimelineEventService...\n";
    echo str_repeat("=", 80) . "\n\n";
    
    $service = new TimelineEventService();
    
    // Test 1: Log supplier change
    echo "Test 1: Log supplier change\n";
    echo str_repeat("-", 80) . "\n";
    $eventId = $service->logSupplierChange(
        'TEST/SERVICE/001',
        1001,
        10,
        20,
        'شركة أ المحدودة',
        'شركة ب المحدودة'
    );
    echo "✅ Created supplier change event ID: $eventId\n\n";
    
    // Test 2: Log bank change
    echo "Test 2: Log bank change\n";
    echo str_repeat("-", 80) . "\n";
    $bankEventId = $service->logBankChange(
        'TEST/SERVICE/001',
        1001,
        5,
        8,
        'بنك الرياض',
        'مصرف الراجحي'
    );
    echo "✅ Created bank change event ID: $bankEventId\n\n";
    
    // Test 3: Log amount change
    echo "Test 3: Log amount change\n";
    echo str_repeat("-", 80) . "\n";
    $amountEventId = $service->logAmountChange(
        'TEST/SERVICE/001',
        1001,
        '10000',
        '15000'
    );
    echo "✅ Created amount change event ID: $amountEventId\n\n";
    
    // Test 4: Log extension
    echo "Test 4: Log extension\n";
    echo str_repeat("-", 80) . "\n";
    $session = (new \App\Repositories\ImportSessionRepository())->getOrCreateDailySession('daily_actions');
    $extensionId = $service->logExtension(
        'TEST/SERVICE/001',
        1001,
        '2025-01-01',
        '2026-01-01',
        $session->id
    );
    echo "✅ Created extension event ID: $extensionId\n\n";
    
    // Test 5: Log release
    echo "Test 5: Log release\n";
    echo str_repeat("-", 80) . "\n";
    $releaseId = $service->logRelease(
        'TEST/SERVICE/001',
        1001,
        $session->id
    );
    echo "✅ Created release event ID: $releaseId\n\n";
    
    // Test 6: Verify all events created
    echo "Test 6: Verify events in database\n";
    echo str_repeat("-", 80) . "\n";
    $repo = new \App\Repositories\TimelineEventRepository();
    $events = $repo->getByGuaranteeNumber('TEST/SERVICE/001');
    echo "✅ Found " . count($events) . " events for TEST/SERVICE/001:\n";
    foreach ($events as $event) {
        echo sprintf(
            "  - %-20s: %s → %s\n",
            $event['event_type'],
            $event['old_value'] ?? 'N/A',
            $event['new_value'] ?? 'N/A'
        );
    }
    echo "\n";
    
    echo str_repeat("=", 80) . "\n";
    echo "All service tests passed! ✅\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
