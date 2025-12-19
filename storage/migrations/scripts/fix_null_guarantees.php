<?php
require_once __DIR__ . '/../../app/Support/autoload.php';
use App\Support\Database;

$db = Database::connect();

echo "Fixing NULL guarantee numbers...\n";

$db->exec("UPDATE imported_records 
           SET guarantee_number = 'UNKNOWN-' || CAST(id AS TEXT)
           WHERE guarantee_number IS NULL OR guarantee_number = ''");

echo "✅ Fixed NULL guarantee numbers\n";

// Verify
$check = $db->query("SELECT COUNT(*) as count FROM imported_records WHERE guarantee_number IS NULL OR guarantee_number = ''")->fetch(PDO::FETCH_ASSOC);
echo "Remaining NULL guarantees: " . $check['count'] . "\n";
