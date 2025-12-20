<?php
require __DIR__ . '/app/Support/autoload.php';
use App\Support\Database;

$db = Database::connect();

// Check record 12901
$stmt = $db->prepare("SELECT * FROM imported_records WHERE id = ?");
$stmt->execute([12901]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    echo "Record 12901 not found!\n";
    exit;
}

echo "Record 12901:\n";
echo str_repeat("=", 80) . "\n";
echo "Guarantee Number: " . $record['guarantee_number'] . "\n";
echo "Raw Supplier Name: " . ($record['raw_supplier_name'] ?? 'NULL') . "\n";
echo "Supplier Display Name: " . ($record['supplier_display_name'] ?? 'NULL') . "\n";
echo "Supplier ID: " . ($record['supplier_id'] ?? 'NULL') . "\n";
echo "Created At: " . $record['created_at'] . "\n";
echo "\n";

// Check all records for this guarantee number
$guaranteeNum = $record['guarantee_number'];
$stmt = $db->prepare("SELECT id, record_type, supplier_id, supplier_display_name, raw_supplier_name, created_at FROM imported_records WHERE guarantee_number = ? ORDER BY created_at DESC");
$stmt->execute([$guaranteeNum]);

echo "All records for guarantee {$guaranteeNum}:\n";
echo str_repeat("=", 80) . "\n";
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf(
        "ID: %5d | Type: %-15s | Supplier ID: %-4s | Display: %-30s | Created: %s\n",
        $r['id'],
        $r['record_type'] ?? 'import',
        $r['supplier_id'] ?? 'NULL',
        substr($r['supplier_display_name'] ?? $r['raw_supplier_name'] ?? 'NULL', 0, 30),
        $r['created_at']
    );
}

// Check for modification records
$stmt = $db->prepare("SELECT id, comment, created_at FROM imported_records WHERE guarantee_number = ? AND record_type = 'modification' ORDER BY created_at DESC");
$stmt->execute([$guaranteeNum]);

echo "\nModification records:\n";
echo str_repeat("=", 80) . "\n";
$modifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($modifications)) {
    echo "No modification records found.\n";
} else {
    foreach ($modifications as $mod) {
        echo "ID: {$mod['id']} | Created: {$mod['created_at']}\n";
        echo "Comment: {$mod['comment']}\n";
        echo str_repeat("-", 80) . "\n";
    }
}
