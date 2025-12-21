<?php
// Check if snapshots are being saved
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) require_once $file;
});

require_once __DIR__ . '/../vendor/autoload.php';

$pdo = \App\Support\Database::connection();

echo "=== Last 10 Timeline Events ===\n\n";

$stmt = $pdo->query("
    SELECT id, event_type, guarantee_number, supplier_display_name, 
           CASE WHEN snapshot_data IS NULL THEN 'NULL' 
                WHEN snapshot_data = '' THEN 'EMPTY'
                ELSE 'HAS DATA' 
           END as snapshot_status,
           created_at
    FROM guarantee_timeline_events 
    ORDER BY id DESC 
    LIMIT 10
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']} | Event: {$row['event_type']} | Guarantee: {$row['guarantee_number']}\n";
    echo "  Supplier: {$row['supplier_display_name']}\n";
    echo "  Snapshot: {$row['snapshot_status']}\n";
    echo "  Created: {$row['created_at']}\n\n";
}
