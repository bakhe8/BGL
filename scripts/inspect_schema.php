<?php
require __DIR__ . '/../app/Support/autoload.php';
use App\Support\Database;

try {
    $pdo = Database::connection();
    $tables = ['suppliers', 'banks', 'supplier_alternative_names', 'bank_learning'];

    foreach ($tables as $table) {
        echo "\n=== schema: $table ===\n";
        $stmt = $pdo->query("PRAGMA table_info('$table')");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            echo " - {$c['name']} ({$c['type']})\n";
        }
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage();
}
