<?php
require 'app/Support/Database.php';
use App\Support\Database;

echo "Repairing Schema...\n";

// Ensure we target layout DB text
Database::setDatabasePath(__DIR__ . '/storage/database/app.sqlite');
$pdo = Database::connection();

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS bank_learning_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        raw_input TEXT NOT NULL,
        normalized_input TEXT NOT NULL,
        suggested_bank_id INTEGER NULL,
        decision_result TEXT NULL,
        candidate_source TEXT NULL,
        score REAL NULL,
        score_raw REAL NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (suggested_bank_id) REFERENCES banks(id) ON DELETE SET NULL
    );
    ";
    
    $pdo->exec($sql);
    echo "SUCCESS: Created table 'bank_learning_log'.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
