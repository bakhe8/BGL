<?php
/**
 * Migration: Add Suggestion & Decision Tables
 * Run this once: php storage/migrations/run_migration.php
 */

require_once __DIR__ . '/../../app/Support/autoload.php';

echo "=== Running Migration: Add Suggestion & Decision Tables ===\n\n";

try {
    // Get database connection
    $db = \App\Support\Database::connection();
    
    // =========================================================================
    // TABLE 1: supplier_suggestions
    // =========================================================================
    echo "Creating supplier_suggestions table...\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS supplier_suggestions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            normalized_input VARCHAR(500) NOT NULL,
            supplier_id INTEGER NOT NULL,
            display_name VARCHAR(500) NOT NULL,
            source VARCHAR(50) NOT NULL,
            fuzzy_score REAL DEFAULT 0.0,
            source_weight INTEGER DEFAULT 0,
            usage_count INTEGER DEFAULT 0,
            total_score REAL DEFAULT 0.0,
            star_rating INTEGER DEFAULT 1,
            last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(normalized_input, supplier_id, source)
        )
    ");
    echo "✅ Table supplier_suggestions created\n";
    
    // Indexes for supplier_suggestions
    $db->exec("CREATE INDEX IF NOT EXISTS idx_suggestions_input ON supplier_suggestions(normalized_input)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_suggestions_score ON supplier_suggestions(total_score DESC)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_suggestions_supplier ON supplier_suggestions(supplier_id)");
    echo "✅ Indexes for supplier_suggestions created\n\n";
    
    // =========================================================================
    // TABLE 2: user_decisions
    // =========================================================================
    echo "Creating user_decisions table...\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS user_decisions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            record_id INTEGER NOT NULL,
            session_id INTEGER NOT NULL,
            raw_name VARCHAR(500) NOT NULL,
            normalized_name VARCHAR(500) NOT NULL,
            chosen_supplier_id INTEGER NOT NULL,
            chosen_display_name VARCHAR(500),
            decision_source VARCHAR(50) NOT NULL,
            decided_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (chosen_supplier_id) REFERENCES suppliers(id),
            FOREIGN KEY (record_id) REFERENCES imported_records(id)
        )
    ");
    echo "✅ Table user_decisions created\n";
    
    // Indexes for user_decisions
    $db->exec("CREATE INDEX IF NOT EXISTS idx_decisions_normalized ON user_decisions(normalized_name)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_decisions_supplier ON user_decisions(chosen_supplier_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_decisions_record ON user_decisions(record_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_decisions_session ON user_decisions(session_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_decisions_source ON user_decisions(decision_source)");
    echo "✅ Indexes for user_decisions created\n\n";
    
    // =========================================================================
    // VERIFICATION
    // =========================================================================
    echo "=== Verifying Tables ===\n\n";
    
    // Check supplier_suggestions
    $columns1 = $db->query("PRAGMA table_info(supplier_suggestions)")->fetchAll();
    echo "supplier_suggestions columns (" . count($columns1) . "):\n";
    foreach ($columns1 as $col) {
        echo "  - {$col['name']} ({$col['type']})\n";
    }
    
    echo "\n";
    
    // Check user_decisions
    $columns2 = $db->query("PRAGMA table_info(user_decisions)")->fetchAll();
    echo "user_decisions columns (" . count($columns2) . "):\n";
    foreach ($columns2 as $col) {
        echo "  - {$col['name']} ({$col['type']})\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    echo "===========================================\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
