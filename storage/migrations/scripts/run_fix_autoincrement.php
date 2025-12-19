<?php
/**
 * Run the fix_autoincrement migration
 */

require_once __DIR__ . '/../../app/Support/autoload.php';

use App\Support\Database;

try {
    $db = Database::connect();
    
    echo "Starting migration to fix AUTOINCREMENT...\n\n";
    
    // Read migration SQL
    $sql = file_get_contents(__DIR__ . '/20251219_fix_autoincrement.sql');
    
    // Execute migration
    $db->exec($sql);
    
    echo "✅ Migration completed successfully!\n\n";
    
    // Verify
    $count = $db->query("SELECT COUNT(*) as total FROM imported_records")->fetch(PDO::FETCH_ASSOC);
    $maxId = $db->query("SELECT MAX(id) as max_id FROM imported_records")->fetch(PDO::FETCH_ASSOC);
    
    echo "Verification:\n";
    echo "  - Total records: {$count['total']}\n";
    echo "  - Max ID: {$maxId['max_id']}\n\n";
    
    // Test insert
    echo "Testing INSERT...\n";
    $testStmt = $db->prepare("INSERT INTO imported_records (
        session_id, guarantee_number, raw_supplier_name, raw_bank_name,
        amount, record_type, created_at
    ) VALUES (999, 'MIGRATION-TEST', 'Test', 'Test', 100, 'import', datetime('now'))");
    $testStmt->execute();
    $lastId = $db->lastInsertId();
    
    $checkStmt = $db->prepare("SELECT id FROM imported_records WHERE guarantee_number = 'MIGRATION-TEST'");
    $checkStmt->execute();
    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    echo "  - Last Insert ID: $lastId\n";
    echo "  - Fetched ID: " . ($result['id'] ?? 'NULL') . "\n";
    
    if ($result['id'] == $lastId) {
        echo "  ✅ AUTOINCREMENT is working correctly!\n";
    } else {
        echo "  ❌ AUTOINCREMENT still broken!\n";
    }
    
    // Cleanup
    $db->exec("DELETE FROM imported_records WHERE guarantee_number = 'MIGRATION-TEST'");
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
