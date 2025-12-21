<?php
require __DIR__ . '/../app/Support/autoload.php';
use App\Support\Database;

try {
    $pdo = Database::connection();
    echo "Indexes on imported_records:\n";
    $stmt = $pdo->query("PRAGMA index_list('imported_records')");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($indexes);

    foreach ($indexes as $idx) {
        echo "Info for {$idx['name']}:\n";
        $info = $pdo->query("PRAGMA index_info('{$idx['name']}')")->fetchAll(PDO::FETCH_ASSOC);
        print_r($info);
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage();
}
