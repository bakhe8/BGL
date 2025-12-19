<?php
/**
 * Migration Script: Add New Architecture Tables
 * Run: php storage/migrations/scripts/run_20251220_new_architecture.php
 */

require_once __DIR__ . '/../../../app/Support/autoload.php';

use App\Support\Database;

try {
    echo "=============================================================================\n";
    echo "Migration: Add New Session Architecture\n";
    echo "Date: 2025-12-20\n";
    echo "=============================================================================\n\n";
    
    $db = Database::connect();
    
    // Start transaction
    $db->beginTransaction();
    
    // 1. Load and execute main SQL file
    echo "Step 1: Creating new tables...\n";
    $sql = file_get_contents(__DIR__ . '/../20251220_add_new_architecture.sql');
    $db->exec($sql);
    echo "  ✓ Tables created: import_batches, action_sessions, guarantees, guarantee_actions\n\n";
    
    // 2. Add transition columns to imported_records (with checks)
    echo "Step 2: Adding transition columns to imported_records...\n";
    
    // Check if columns exist
    $columns = [];
    $result = $db->query("PRAGMA table_info('imported_records')");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['name'];
    }
    
    if (!in_array('migrated_guarantee_id', $columns)) {
        $db->exec("ALTER TABLE imported_records ADD COLUMN migrated_guarantee_id INTEGER NULL");
        echo "  ✓ Added migrated_guarantee_id column\n";
    } else {
        echo "  ℹ migrated_guarantee_id already exists\n";
    }
    
    if (!in_array('migrated_action_id', $columns)) {
        $db->exec("ALTER TABLE imported_records ADD COLUMN migrated_action_id INTEGER NULL");
        echo "  ✓ Added migrated_action_id column\n";
    } else {
        echo "  ℹ migrated_action_id already exists\n";
    }
    
    if (!in_array('import_batch_id', $columns)) {
        $db->exec("ALTER TABLE imported_records ADD COLUMN import_batch_id INTEGER NULL");
        echo "  ✓ Added import_batch_id column\n";
    } else {
        echo "  ℹ import_batch_id already exists\n";
    }
    
    // 3. Create indexes on new columns
    echo "\nStep 3: Creating indexes on transition columns...\n";
    $db->exec("CREATE INDEX IF NOT EXISTS idx_records_migrated_g ON imported_records(migrated_guarantee_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_records_migrated_a ON imported_records(migrated_action_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_records_batch ON imported_records(import_batch_id)");
    echo "  ✓ Indexes created\n\n";
    
    // 4. Verify tables were created
    echo "Step 4: Verifying new tables...\n";
    $tables = ['import_batches', 'action_sessions', 'guarantees', 'guarantee_actions'];
    foreach ($tables as $table) {
        $result = $db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='$table'");
        if ($result->fetchColumn() > 0) {
            echo "  ✓ Table '$table' exists\n";
        } else {
            throw new Exception("Failed to create table: $table");
        }
    }
    
    // Commit transaction
    $db->commit();
    
    echo "\n=============================================================================\n";
    echo "✅ Migration completed successfully!\n";
    echo "=============================================================================\n\n";
    
    // Show summary
    echo "Summary:\n";
    echo "  - 4 new tables created\n";
    echo "  - 3 transition columns added to imported_records\n";
    echo "  - All indexes created\n";
    echo "  - Old tables remain unchanged\n";
    echo "  - Ready for dual-write implementation\n\n";
    
    // Show table counts
    echo "Current database state:\n";
    foreach ($tables as $table) {
        $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "  - $table: $count records\n";
    }
    
    $oldCount = $db->query("SELECT COUNT(*) FROM imported_records")->fetchColumn();
    echo "  - imported_records: $oldCount records (unchanged)\n";
    
    echo "\n✅ Migration successful! You can now proceed to Phase 2 (Adapter implementation).\n\n";
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "\n❌ Migration failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nThe database has been rolled back to its previous state.\n";
    exit(1);
}
