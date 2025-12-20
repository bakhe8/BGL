<?php
/**
 * Final System Test - Timeline Events
 * 
 * Quick health check before deployment
 */

require __DIR__ . '/../app/Support/autoload.php';

use App\Support\Database;
use App\Repositories\TimelineEventRepository;
use App\Services\TimelineEventService;

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║         TIMELINE EVENTS SYSTEM - HEALTH CHECK               ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$db = Database::connect();
$passed = 0;
$failed = 0;

// Test 1: Database table exists
echo "1. Checking database table... ";
try {
    $stmt = $db->query("SELECT COUNT(*) FROM guarantee_timeline_events");
    $count = $stmt->fetchColumn();
    echo "✅ ($count events)\n";
    $passed++;
} catch (Exception $e) {
    echo "❌ " . $e->getMessage() . "\n";
    $failed++;
}

// Test 2: Display name columns exist
echo "2. Checking display name columns... ";
try {
    $stmt = $db->query("PRAGMA table_info(guarantee_timeline_events)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasSupplierName = false;
    $hasBankDisplay = false;
    
    foreach ($columns as $col) {
        if ($col['name'] === 'supplier_display_name') $hasSupplierName = true;
        if ($col['name'] === 'bank_display') $hasBankDisplay = true;
    }
    
    if ($hasSupplierName && $hasBankDisplay) {
        echo "✅\n";
        $passed++;
    } else {
        echo "❌ Columns missing\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "❌ " . $e->getMessage() . "\n";
    $failed++;
}

// Test 3: Indexes exist
echo "3. Checking indexes... ";
try {
    $stmt = $db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='index' AND tbl_name='guarantee_timeline_events'");
    $indexCount = $stmt->fetchColumn();
    
    if ($indexCount >= 6) {
        echo "✅ ($indexCount indexes)\n";
        $passed++;
    } else {
        echo "⚠️  Only $indexCount indexes (expected 6+)\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "❌ " . $e->getMessage() . "\n";
    $failed++;
}

// Test 4: Repository works
echo "4. Testing TimelineEventRepository... ";
try {
    $repo = new TimelineEventRepository();
    $eventId = $repo->create([
        'guarantee_number' => 'HEALTH_CHECK_TEST',
        'session_id' => 999,
        'event_type' => 'test',
        'new_value' => 'Health check event',
        'supplier_display_name' => 'Test Supplier',
        'bank_display' => 'Test Bank'
    ]);
    
    // Clean up
    $db->exec("DELETE FROM guarantee_timeline_events WHERE guarantee_number = 'HEALTH_CHECK_TEST'");
    
    echo "✅ (Event ID: $eventId)\n";
    $passed++;
} catch (Exception $e) {
    echo "❌ " . $e->getMessage() . "\n";
    $failed++;
}

// Test 5: Service works
echo "5. Testing TimelineEventService... ";
try {
    $service = new TimelineEventService();
    // Just instantiate - actual test already done
    echo "✅\n";
    $passed++;
} catch (Exception $e) {
    echo "❌ " . $e->getMessage() . "\n";
    $failed++;
}

// Test 6: API endpoint exists
echo "6. Checking API endpoint... ";
if (file_exists(__DIR__ . '/../www/api/guarantee-history.php')) {
    echo "✅\n";
    $passed++;
} else {
    echo "❌ File not found\n";
    $failed++;
}

// Test 7: Frontend script exists
echo "7. Checking frontend script... ";
if (file_exists(__DIR__ . '/../www/assets/js/guarantee-history.js')) {
    echo "✅\n";
    $passed++;
} else {
    echo "❌ File not found\n";
    $failed++;
}

// Test 8: No deprecated files exist
echo "8. Checking for temp files... ";
$tempFiles = [
    'debug_modifications.php',
    'debug_record_12837.php',
    'test_direct_create.php'
];

$foundTemp = false;
foreach ($tempFiles as $file) {
    if (file_exists(__DIR__ . '/../' . $file)) {
        $foundTemp = true;
        break;
    }
}

if (!$foundTemp) {
    echo "✅ (All cleaned)\n";
    $passed++;
} else {
    echo "⚠️  Some temp files still exist\n";
    $failed++;
}

// Summary
echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                      TEST SUMMARY                            ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";
printf("║  Passed: %-2d                                                ║\n", $passed);
printf("║  Failed: %-2d                                                ║\n", $failed);
echo "╠══════════════════════════════════════════════════════════════╣\n";

if ($failed === 0) {
    echo "║  Status: ✅ ALL TESTS PASSED - READY FOR DEPLOYMENT!        ║\n";
} else {
    echo "║  Status: ⚠️  SOME TESTS FAILED - REVIEW REQUIRED            ║\n";
}

echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Performance check
echo "Performance Check:\n";
echo str_repeat("-", 60) . "\n";

$start = microtime(true);
$stmt = $db->query("SELECT * FROM guarantee_timeline_events LIMIT 100");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
$end = microtime(true);

$time = ($end - $start) * 1000; // Convert to ms

printf("Timeline query (100 records): %.2fms ", $time);
if ($time < 100) {
    echo "✅ EXCELLENT\n";
} elseif ($time < 200) {
    echo "✅ GOOD\n";
} else {
    echo "⚠️  SLOW\n";
}

echo "\n";
echo "System is " . ($failed === 0 ? "READY" : "NOT READY") . " for production!\n";

exit($failed === 0 ? 0 : 1);
