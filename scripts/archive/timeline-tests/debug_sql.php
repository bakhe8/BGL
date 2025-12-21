<?php
require __DIR__ . '/../app/Support/autoload.php';

$db = \App\Support\Database::connect();

// Get record details
$stmt = $db->prepare('SELECT * FROM imported_records WHERE guarantee_number = ?');
$stmt->execute(['TEST123']);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Records for TEST123:\n";
echo "====================\n";
foreach ($records as $i => $record) {
    echo "\nRecord #" . ($i+1) . ":\n";
    echo "  ID: " . $record['id'] . "\n";
    echo "  guarantee_number: " . $record['guarantee_number'] . "\n";
    echo "  record_type: " . ($record['record_type'] ?? 'NULL') . "\n";
    echo "  session_id: " . $record['session_id'] . "\n";
    echo "  import_batch_id: " . ($record['import_batch_id'] ?? 'NULL') . "\n";
    echo "  supplier_id: " . ($record['supplier_id'] ?? 'NULL') . "\n";
    echo "  bank_id: " . ($record['bank_id'] ?? 'NULL') . "\n";
    echo "  amount: " . ($record['amount'] ?? 'NULL') . "\n";
}

echo "\n\nTesting the SQL query from API:\n";
echo "================================\n";

$stmt = $db->prepare("
    SELECT 
        r.id,
        r.id as record_id,
        r.session_id,
        r.import_batch_id,
        'import' as source,
        CASE 
            WHEN r.record_type IS NULL OR r.record_type = 'import' THEN 'import'
            ELSE r.record_type
        END as event_type
    FROM imported_records r
    WHERE r.guarantee_number = :number
      AND (r.record_type IS NULL OR r.record_type = 'import')
");

$stmt->execute(['number' => 'TEST123']);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Results count: " . count($results) . "\n";
foreach ($results as $i => $result) {
    echo "\nResult #" . ($i+1) . ":\n";
    print_r($result);
}
