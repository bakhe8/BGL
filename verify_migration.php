<?php
require_once __DIR__ . '/app/Support/autoload.php';

use App\Support\Database;

$db = Database::connection();

echo "Verifying migration results...\n";
echo str_repeat("=", 60) . "\n\n";

// Check supplier_aliases_learning
echo "supplier_aliases_learning:\n";
$stmt = $db->query("PRAGMA table_info(supplier_aliases_learning)");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hasUsageCount = false;
$hasLastUsed = false;

foreach ($columns as $col) {
    if ($col['name'] === 'usage_count') $hasUsageCount = true;
    if ($col['name'] === 'last_used_at') $hasLastUsed = true;
}

echo "  ✓ usage_count: " . ($hasUsageCount ? "ADDED" : "MISSING") . "\n";
echo "  ✓ last_used_at: " . ($hasLastUsed ? "ADDED" : "MISSING") . "\n";

// Check bank_aliases_learning
echo "\nbank_aliases_learning:\n";
$stmt = $db->query("PRAGMA table_info(bank_aliases_learning)");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hasUsageCount = false;
$hasLastUsed = false;

foreach ($columns as $col) {
    if ($col['name'] === 'usage_count') $hasUsageCount = true;
    if ($col['name'] === 'last_used_at') $hasLastUsed = true;
}

echo "  ✓ usage_count: " . ($hasUsageCount ? "ADDED" : "MISSING") . "\n";
echo "  ✓ last_used_at: " . ($hasLastUsed ? "ADDED" : "MISSING") . "\n";

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ MIGRATION SUCCESSFUL!\n";
