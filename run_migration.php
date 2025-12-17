<?php
require_once __DIR__ . '/app/Support/autoload.php';

use App\Support\Database;

echo "Running migration: add_usage_tracking\n";
echo str_repeat("=", 60) . "\n";

$db = Database::connection();

try {
    $sql = file_get_contents(__DIR__ . '/storage/migrations/20251217_add_usage_tracking.sql');
    $db->exec($sql);
    
    echo "✓ Migration executed successfully\n\n";
    
    // Verify changes
    echo "Verifying supplier_learning table...\n";
    $stmt = $db->query("PRAGMA table_info(supplier_learning)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasUsageCount = false;
    $hasLastUsed = false;
    
    foreach ($columns as $col) {
        if ($col['name'] === 'usage_count') $hasUsageCount = true;
        if ($col['name'] === 'last_used_at') $hasLastUsed = true;
    }
    
    echo "  - usage_count: " . ($hasUsageCount ? "✓ Added" : "✗ Missing") . "\n";
    echo "  - last_used_at: " . ($hasLastUsed ? "✓ Added" : "✗ Missing") . "\n";
    
    echo "\nVerifying bank_learning table...\n";
    $stmt = $db->query("PRAGMA table_info(bank_learning)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasUsageCount = false;
    $hasLastUsed = false;
    
    foreach ($columns as $col) {
        if ($col['name'] === 'usage_count') $hasUsageCount = true;
        if ($col['name'] === 'last_used_at') $hasLastUsed = true;
    }
    
    echo "  - usage_count: " . ($hasUsageCount ? "✓ Added" : "✗ Missing") . "\n";
    echo "  - last_used_at: " . ($hasLastUsed ? "✓ Added" : "✗ Missing") . "\n";
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
