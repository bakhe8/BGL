<?php
/**
 * Run Timeline Events Migration
 * 
 * This script creates the guarantee_timeline_events table
 * and all necessary indexes.
 */

require __DIR__ . '/../app/Support/autoload.php';

use App\Support\Database;

try {
    $db = Database::connect();
    
    echo "Starting migration: create_timeline_events...\n";
    echo str_repeat("=", 80) . "\n";
    
    // Read migration file
    $sql = file_get_contents(__DIR__ . '/../database/migrations/001_create_timeline_events.sql');
    
    // Execute migration
    $db->exec($sql);
    
    echo "✅ Table created successfully!\n\n";
    
    // Verify table
    $stmt = $db->query("
        SELECT sql FROM sqlite_master 
        WHERE type='table' AND name='guarantee_timeline_events'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "Table structure:\n";
        echo str_repeat("-", 80) . "\n";
        echo $result['sql'] . "\n\n";
    }
    
    // Verify indexes
    $stmt = $db->query("
        SELECT name FROM sqlite_master 
        WHERE type='index' AND tbl_name='guarantee_timeline_events'
    ");
    $indexes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Indexes created:\n";
    echo str_repeat("-", 80) . "\n";
    foreach ($indexes as $index) {
        echo "✅ $index\n";
    }
    
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
