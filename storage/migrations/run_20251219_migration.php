<?php
/**
 * Run Database Migration: Add record_type column
 * Execute this file once to apply the migration
 */

require_once __DIR__ . '/../../app/Support/autoload.php';

use App\Support\Database;

try {
    $db = Database::connect();
    
    echo "Starting migration: Add record_type column...\n";
    
    // Read migration SQL
    $sql = file_get_contents(__DIR__ . '/20251219_add_record_type.sql');
    
    // Execute migration
    $db->exec($sql);
    
    echo "✅ Migration completed successfully!\n\n";
    
    // Verify
    $stmt = $db->query("SELECT record_type, COUNT(*) as count FROM imported_records GROUP BY record_type");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Verification:\n";
    foreach ($results as $row) {
        echo "  - {$row['record_type']}: {$row['count']} records\n";
    }
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
