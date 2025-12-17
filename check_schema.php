<?php
require_once __DIR__ . '/app/Support/autoload.php';

use App\Support\Database;

$db = Database::connection();

echo "Checking database schema...\n";
echo str_repeat("=", 60) . "\n\n";

// List all tables
$stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "Tables in database:\n";
foreach ($tables as $table) {
    echo "  - $table\n";
}

echo "\n";

// Check for learning-related tables
$learningTables = array_filter($tables, fn($t) => str_contains($t, 'learning'));

if (empty($learningTables)) {
    echo "⚠️ No learning tables found!\n";
    echo "Looking for tables with 'alias' or 'supplier' in name...\n\n";
    
    $relevantTables = array_filter($tables, fn($t) => 
        str_contains($t, 'alias') || 
        str_contains($t, 'supplier') ||
        str_contains($t, 'bank')
    );
    
    echo "Relevant tables:\n";
    foreach ($relevantTables as $table) {
        echo "  - $table\n";
        
        // Show structure
        $stmt = $db->query("PRAGMA table_info($table)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "    Columns: ";
        echo implode(', ', array_column($columns, 'name'));
        echo "\n";
    }
} else {
    echo "Learning tables found:\n";
    foreach ($learningTables as $table) {
        echo "  - $table\n";
    }
}
