<?php
require __DIR__ . '/app/Support/autoload.php';
use App\Support\Database;

$db = Database::connect();
$stmt = $db->query('
    SELECT id, official_name, official_name_en, short_code 
    FROM banks 
    WHERE id IN (9, 10, 18, 26, 36) 
    ORDER BY id
');

$banks = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "البنوك بعد الإصلاح:\n";
echo "═════════════════════════════════════════════════════════════════\n";
foreach ($banks as $b) {
    echo "ID #{$b['id']}: {$b['official_name']}\n";
    echo "  → {$b['official_name_en']} (code: {$b['short_code']})\n\n";
}
