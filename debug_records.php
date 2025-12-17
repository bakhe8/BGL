<?php
require __DIR__ . '/app/Support/autoload.php';
use App\Support\Database;

$db = Database::connection();

echo "Checking records 12029 and 12030:\n";
echo str_repeat('=', 60) . "\n\n";

$stmt = $db->prepare('SELECT id, raw_supplier_name, supplier_id, supplier_display_name FROM imported_records WHERE id IN (12029, 12030)');
$stmt->execute();

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Record #{$row['id']}:\n";
    echo "  raw_supplier_name: " . ($row['raw_supplier_name'] ?? 'NULL') . "\n";
    echo "  supplier_id: " . ($row['supplier_id'] ?? 'NULL') . "\n";
    echo "  supplier_display_name: " . ($row['supplier_display_name'] ?? 'NULL') . "\n";
    echo "\n";
}

echo "\nChecking learning data:\n";
echo str_repeat('=', 60) . "\n";

$stmt2 = $db->query('SELECT * FROM supplier_aliases_learning LIMIT 5');
echo "Sample learning records:\n";
while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
