<?php
require_once __DIR__ . '/app/Support/autoload.php';

use App\Support\Database;

$db = Database::connection();

echo "INVESTIGATION: ZIMMO Display Name Issue\n";
echo str_repeat("=", 80) . "\n\n";

// 1. Check recent ZIMMO records
echo "1. السجلات الأخيرة لـ ZIMMO:\n";
$stmt = $db->query("
    SELECT id, session_id, raw_supplier_name, supplier_display_name, supplier_id, match_status
    FROM imported_records
    WHERE raw_supplier_name LIKE '%ZIMMO%'
    ORDER BY created_at DESC
    LIMIT 10
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $display = $row['supplier_display_name'] ?: 'NULL';
    echo "  Record #{$row['id']} (Session {$row['session_id']}):\n";
    echo "    Raw: {$row['raw_supplier_name']}\n";
    echo "    Display: {$display}\n";
    echo "    Supplier ID: {$row['supplier_id']}\n";
    echo "    Status: {$row['match_status']}\n\n";
}

// 2. Check supplier record
echo "2. المورد #130:\n";
$stmt = $db->query("SELECT id, official_name FROM suppliers WHERE id = 130");
$supplier = $stmt->fetch(PDO::FETCH_ASSOC);
if ($supplier) {
    echo "  Official Name: {$supplier['official_name']}\n\n";
}

// 3. Search for user's input
echo "3. البحث عن 'زومو':\n";
$stmt = $db->query("
    SELECT COUNT(*) as cnt
    FROM imported_records
    WHERE supplier_display_name LIKE '%زومو%'
");
$count = $stmt->fetch(PDO::FETCH_ASSOC);
echo "  Records with 'زومو' in display_name: {$count['cnt']}\n\n";

// 4. Check learning table
echo "4. سجلات التعلم:\n";
$stmt = $db->query("
    SELECT id, raw_name, normalized_raw, linked_supplier_id
    FROM supplier_learning
    WHERE raw_name LIKE '%ZIMMO%' OR normalized_raw LIKE '%zmm%'
    LIMIT 5
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  Learning #{$row['id']}:\n";
    echo "    Raw: {$row['raw_name']}\n";
    echo "    Normalized: {$row['normalized_raw']}\n";
    echo "    Supplier ID: {$row['linked_supplier_id']}\n\n";
}
