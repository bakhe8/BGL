<?php
require __DIR__ . '/../app/Support/autoload.php';

$db = \App\Support\Database::connect();

// Get record 12840
$stmt = $db->prepare('SELECT id, guarantee_number, session_id FROM imported_records WHERE id = ?');
$stmt->execute([12840]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Record Info:\n";
echo json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

if ($record && $record['guarantee_number']) {
    // Check timeline events
    $stmt = $db->prepare('SELECT COUNT(*) FROM guarantee_timeline_events WHERE guarantee_number = ?');
    $stmt->execute([$record['guarantee_number']]);
    $timelineCount = $stmt->fetchColumn();
    
    echo "Timeline Events Count: $timelineCount\n\n";
    
    // Check imported records
    $stmt = $db->prepare('SELECT COUNT(*) FROM imported_records WHERE guarantee_number = ?');
    $stmt->execute([$record['guarantee_number']]);
    $importCount = $stmt->fetchColumn();
    
    echo "Import Records Count: $importCount\n";
}
