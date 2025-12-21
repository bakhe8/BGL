<?php
// Run database migration: Add snapshot_data column
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) require_once $file;
});

require_once __DIR__ . '/../vendor/autoload.php';

$pdo = \App\Support\Database::connection();

echo "=== Adding snapshot_data column to guarantee_timeline_events ===\n\n";

try {
    // Check if column already exists
    $stmt = $pdo->query("PRAGMA table_info(guarantee_timeline_events)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $exists = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'snapshot_data') {
            $exists = true;
            break;
        }
    }
    
    if ($exists) {
        echo "✓ Column 'snapshot_data' already exists - no action needed\n";
    } else {
        echo "Adding column 'snapshot_data'...\n";
        $pdo->exec("ALTER TABLE guarantee_timeline_events ADD COLUMN snapshot_data TEXT");
        echo "✅ Column added successfully!\n";
    }
    
    echo "\n=== Verification ===\n";
    $stmt = $pdo->query("PRAGMA table_info(guarantee_timeline_events)");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['name'] === 'snapshot_data') {
            echo "✓ snapshot_data: " . $row['type'] . "\n";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ Migration completed successfully!\n";
