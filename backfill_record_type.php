<?php
/**
 * Fix record_type for old records
 * 
 * This script updates record_type for all existing records based on their session type.
 * Run this ONCE after deploying the record_type fix.
 */

require_once __DIR__ . '/app/Support/autoload.php';

use App\Support\Database;

$db = Database::connection();

echo "Starting record_type backfill for old records...\n\n";

// Get all sessions with their types
$sessionsStmt = $db->query("
    SELECT id, session_type 
    FROM import_sessions 
    WHERE session_type IN ('extension_action', 'release_action', 'reduction_action', 'modification_action')
");

$sessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($sessions) . " action sessions to process.\n\n";

$updated = 0;
$skipped = 0;

$db->beginTransaction();

try {
    foreach ($sessions as $session) {
        $sessionId = $session['id'];
        $sessionType = $session['session_type'];
        
        // Check if records already have record_type set
        $checkStmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM imported_records 
            WHERE session_id = :session_id 
            AND (record_type IS NULL OR record_type = 'import')
        ");
        $checkStmt->execute(['session_id' => $sessionId]);
        $needsUpdate = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($needsUpdate > 0) {
            // Update records for this session
            $updateStmt = $db->prepare("
                UPDATE imported_records 
                SET record_type = :record_type 
                WHERE session_id = :session_id 
                AND (record_type IS NULL OR record_type = 'import')
            ");
            
            $updateStmt->execute([
                'record_type' => $sessionType,
                'session_id' => $sessionId
            ]);
            
            $updated += $updateStmt->rowCount();
            echo "✓ Session #{$sessionId} ({$sessionType}): Updated {$updateStmt->rowCount()} records\n";
        } else {
            $skipped++;
        }
    }
    
    $db->commit();
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✅ BACKFILL COMPLETE!\n";
    echo "   Updated: {$updated} records\n";
    echo "   Skipped: {$skipped} sessions (already correct)\n";
    echo str_repeat("=", 60) . "\n";
    
} catch (Exception $e) {
    $db->rollBack();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Transaction rolled back. No changes were made.\n";
    exit(1);
}
