<?php
require_once __DIR__ . '/../../app/Support/autoload.php';
use App\Support\Database;

header('Content-Type: text/plain; charset=utf-8');

$db = Database::connect();

echo "=== Checking record_type values ===\n\n";

$stmt = $db->query("SELECT id, guarantee_number, record_type, session_id 
                    FROM imported_records 
                    WHERE session_id IN (405, 411) OR guarantee_number = 'MAN-TEST-002'
                    ORDER BY session_id, id");

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf("ID: %-5s | Session: %-3s | Type: %-15s | Guarantee: %s\n",
        $r['id'],
        $r['session_id'],
        $r['record_type'] ?? 'NULL',
        $r['guarantee_number']
    );
}

echo "\n=== Checking all import records (first 10) ===\n\n";
$stmt2 = $db->query("SELECT id, record_type FROM imported_records WHERE record_type IS NULL OR record_type = 'import' LIMIT 10");
while ($r = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    printf("ID: %s | Type: %s\n", $r['id'], $r['record_type'] ?? 'NULL');
}
