<?php
require_once __DIR__ . '/app/Support/autoload.php';
use App\Support\Database;

try {
    $pdo = Database::connection();
    $stmt = $pdo->query("SELECT COUNT(*) FROM imported_records");
    echo "Count: " . $stmt->fetchColumn() . "\n";
    $stmt = $pdo->query("SELECT * FROM imported_records LIMIT 1");
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    } else {
        echo "No records found.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
