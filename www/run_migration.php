<?php
/**
 * Simple migration runner for SQLite
 */

require __DIR__ . '/../app/Support/autoload.php';

use App\Support\Database;

try {
    echo "Running migration: 003_add_bank_addresses.sql\n";
    
    $pdo = Database::connection();
    
    // Read and execute migration
    $sql = file_get_contents(__DIR__ . '/../storage/migrations/003_add_bank_addresses.sql');
    
    // Split by semicolon and execute each statement
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($stmt) => !empty($stmt) && !str_starts_with($stmt, '--')
    );
    
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        echo "Executing: " . substr($statement, 0, 50) . "...\n";
        $pdo->exec($statement);
    }
    
    echo "✓ Migration completed successfully!\n";
    
    // Verify columns were added
    $result = $pdo->query("PRAGMA table_info(banks)");
    echo "\nCurrent banks table schema:\n";
    foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $column) {
        echo "  - {$column['name']} ({$column['type']})\n";
    }
    
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
