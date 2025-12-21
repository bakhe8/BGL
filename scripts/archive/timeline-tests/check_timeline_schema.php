<?php
// Check timeline events schema
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) require_once $file;
});

require_once __DIR__ . '/../vendor/autoload.php';

$pdo = \App\Support\Database::connection();

echo "=== guarantee_timeline_events Schema ===\n\n";

$stmt = $pdo->query("PRAGMA table_info(guarantee_timeline_events)");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo str_pad($row['name'], 30) . " " . $row['type'] . "\n";
}

echo "\n=== Sample Event Data ===\n\n";

$stmt = $pdo->query("SELECT * FROM guarantee_timeline_events ORDER BY id DESC LIMIT 1");
$sample = $stmt->fetch(PDO::FETCH_ASSOC);

if ($sample) {
    foreach ($sample as $key => $value) {
        echo str_pad($key, 30) . " = " . ($value ?? 'NULL') . "\n";
    }
}
