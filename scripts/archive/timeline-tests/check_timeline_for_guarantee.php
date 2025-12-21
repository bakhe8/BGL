<?php
/**
 * Check Timeline Events for a Guarantee
 */

require __DIR__ . '/../app/Support/autoload.php';

use App\Repositories\TimelineEventRepository;

$guaranteeNumber = $argv[1] ?? '094364G';

try {
    echo "Checking timeline events for guarantee: $guaranteeNumber\n";
    echo str_repeat("=", 80) . "\n\n";
    
    $timeline = new TimelineEventRepository();
    $events = $timeline->getByGuaranteeNumber($guaranteeNumber);
    
    if (empty($events)) {
        echo "No timeline events found.\n";
        exit(0);
    }
    
    echo "Found " . count($events) . " timeline events:\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($events as $event) {
        echo sprintf(
            "[%s] %s\n",
            $event['created_at'],
            strtoupper($event['event_type'])
        );
        
        if ($event['field_name']) {
            echo sprintf(
                "  Field: %s\n  From: %s\n  To: %s\n",
                $event['field_name'],
                $event['old_value'] ?? 'N/A',
                $event['new_value'] ?? 'N/A'
            );
        }
        
        echo "  Session ID: {$event['session_id']}\n";
        echo "  Record ID: {$event['record_id']}\n";
        echo "\n";
    }
    
    echo str_repeat("=", 80) . "\n";
    echo "Total events: " . count($events) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
