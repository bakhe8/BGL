<?php
require_once __DIR__ . '/../../../app/Support/autoload.php';

use App\Support\Database;

$db = Database::connect();

echo "Checking new tables...\n\n";

$tables = ['import_batches', 'action_sessions', 'guarantees', 'guarantee_actions'];

foreach ($tables as $table) {
    try {
        $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "✓ Table '$table' exists with $count records\n";
    } catch (Exception $e) {
        echo "✗ Table '$table' NOT found!\n";
    }
}

echo "\nChecking transition columns in imported_records...\n";
$result = $db->query("PRAGMA table_info('imported_records')");
$columns = [];
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $columns[] = $row['name'];
}

$transitionCols = ['migrated_guarantee_id', 'migrated_action_id', 'import_batch_id'];
foreach ($transitionCols as $col) {
    if (in_array($col, $columns)) {
        echo "✓ Column '$col' exists\n";
    } else {
        echo "✗ Column '$col' NOT found!\n";
    }
}

echo "\n✅ Verification complete!\n";
