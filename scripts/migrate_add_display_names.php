<?php
/**
 * Run Migration: Add Display Names to Timeline Events
 */

require __DIR__ . '/../app/Support/autoload.php';

use App\Support\Database;

try {
    $db = Database::connect();
    
    echo "Running migration: add_display_names_to_timeline...\n";
    echo str_repeat("=", 80) . "\n\n";
    
    // Read migration file
    $sql = file_get_contents(__DIR__ . '/../database/migrations/002_add_display_names_to_timeline.sql');
    
    // Execute migration
    $db->exec($sql);
    
    echo "✅ Columns added successfully!\n\n";
    
    // Verify columns
    $stmt = $db->query("PRAGMA table_info(guarantee_timeline_events)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Table columns:\n";
    echo str_repeat("-", 80) . "\n";
    foreach ($columns as $col) {
        echo "  - {$col['name']} ({$col['type']})\n";
    }
    
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
