<?php
// Check timeline event display names
require_once __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) require_once $file;
});

$pdo = \App\Support\Database::connection();

echo "=== Last 3 Timeline Events ===\n\n";

$stmt = $pdo->query("
    SELECT id, event_type, field_name, old_value, new_value, 
           supplier_display_name, bank_display, created_at
    FROM guarantee_timeline_events 
    ORDER BY id DESC 
    LIMIT 3
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Event #{$row['id']}:\n";
    echo "  Type: {$row['event_type']}\n";
    echo "  Field: " . ($row['field_name'] ?? 'N/A') . "\n";
    echo "  Old: " . ($row['old_value'] ?? 'N/A') . "\n";
    echo "  New: " . ($row['new_value'] ?? 'N/A') . "\n";
    echo "  Supplier Display: " . ($row['supplier_display_name'] ?? 'NULL') . "\n";
    echo "  Bank Display: " . ($row['bank_display'] ?? 'NULL') . "\n";
    echo "  Created: {$row['created_at']}\n\n";
}
