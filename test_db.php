<?php
// Database Integrity Tests
$db = new PDO('sqlite:storage/database.sqlite');

echo "=== DATABASE INTEGRITY TESTS ===\n\n";

// 1. Total Records
$result = $db->query("SELECT COUNT(*) as total FROM imported_records")->fetch();
echo "1. Total Records: " . $result['total'] . "\n\n";

// 2. Records with NULL session_id
$result = $db->query("SELECT COUNT(*) as count FROM imported_records WHERE session_id IS NULL")->fetch();
echo "2. Records with NULL session_id: " . $result['count'] . " " . ($result['count'] > 0 ? "⚠️ WARNING" : "✓ OK") . "\n\n";

// 3. Duplicate guarantee numbers
echo "3. Duplicate Guarantee Numbers:\n";
$stmt = $db->query("SELECT guarantee_number, COUNT(*) as count FROM imported_records GROUP BY guarantee_number HAVING COUNT(*) > 1 LIMIT 5");
$duplicates = $stmt->fetchAll();
if (count($duplicates) > 0) {
    foreach ($duplicates as $dup) {
        echo "   - {$dup['guarantee_number']}: {$dup['count']} times\n";
    }
    echo "   ℹ️ This is OK (historical records)\n\n";
} else {
    echo "   ✓ No duplicates found\n\n";
}

// 4. Duplicate normalized supplier names
echo "4. Duplicate Normalized Supplier Names:\n";
$stmt = $db->query("SELECT normalized_name, COUNT(*) as count FROM suppliers GROUP BY normalized_name HAVING COUNT(*) > 1");
$dupSuppliers = $stmt->fetchAll();
if (count($dupSuppliers) > 0) {
    echo "   ⚠️ WARNING: Found duplicates:\n";
    foreach ($dupSuppliers as $dup) {
        echo "   - {$dup['normalized_name']}: {$dup['count']} times\n";
    }
    echo "\n";
} else {
    echo "   ✓ No duplicate suppliers\n\n";
}

// 5. Supplier aliases count
$result = $db->query("SELECT COUNT(*) as count FROM supplier_alternative_names")->fetch();
echo "5. Supplier Aliases (Learning Data): " . $result['count'] . " entries\n\n";

// 6. Records distribution by match_status
echo "6. Match Status Distribution:\n";
$stmt = $db->query("SELECT match_status, COUNT(*) as count FROM imported_records GROUP BY match_status");
foreach ($stmt->fetchAll() as $row) {
    $status = $row['match_status'] ?: 'NULL';
    echo "   - {$status}: {$row['count']}\n";
}
echo "\n";

// 7. Records without suppliers
$result = $db->query("SELECT COUNT(*) as count FROM imported_records WHERE supplier_id IS NULL")->fetch();
echo "7. Records without Supplier: " . $result['count'] . "\n\n";

// 8. Records without banks
$result = $db->query("SELECT COUNT(*) as count FROM imported_records WHERE bank_id IS NULL")->fetch();
echo "8. Records without Bank: " . $result['count'] . "\n\n";

// 9. Session distribution
echo "9. Session Distribution:\n";
$stmt = $db->query("SELECT session_id, COUNT(*) as count FROM imported_records GROUP BY session_id ORDER BY session_id DESC LIMIT 5");
foreach ($stmt->fetchAll() as $row) {
    $sid = $row['session_id'] ?: 'NULL';
    echo "   - Session {$sid}: {$row['count']} records\n";
}
echo "\n";

// 10. Total suppliers and banks
$suppliers = $db->query("SELECT COUNT(*) as count FROM suppliers")->fetch();
$banks = $db->query("SELECT COUNT(*) as count FROM banks")->fetch();
echo "10. Dictionary Size:\n";
echo "   - Suppliers: {$suppliers['count']}\n";
echo "   - Banks: {$banks['count']}\n\n";

echo "=== END OF DATABASE TESTS ===\n";
