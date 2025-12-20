<?php
require __DIR__ . '/app/Support/autoload.php';

use App\Support\Database;

$db = Database::connect();

// Get guarantee number for record 12837
$stmt = $db->prepare('SELECT guarantee_number FROM imported_records WHERE id = 12837');
$stmt->execute();
$guaranteeNum = $stmt->fetchColumn();

echo "Guarantee Number: $guaranteeNum\n\n";
echo "All records for this guarantee:\n";
echo str_repeat("=", 80) . "\n";

// Get all records for this guarantee
$stmt = $db->prepare('
    SELECT 
        id, 
        session_id, 
        record_type, 
        raw_supplier_name, 
        supplier_display_name,
        supplier_id,
        created_at
    FROM imported_records 
    WHERE guarantee_number = ?
    ORDER BY created_at ASC
');
$stmt->execute([$guaranteeNum]);

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf(
        "ID: %5d | Session: %3d | Type: %-15s | Raw: %-25s | Display: %-25s | Created: %s\n",
        $r['id'],
        $r['session_id'],
        $r['record_type'] ?? 'NULL',
        substr($r['raw_supplier_name'], 0, 25),
        substr($r['supplier_display_name'] ?? 'NULL', 0, 25),
        $r['created_at']
    );
}
