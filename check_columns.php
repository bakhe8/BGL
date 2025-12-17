<?php
require_once __DIR__ . '/app/Support/autoload.php';

use App\Support\Database;

$db = Database::connection();

echo "Checking supplier_aliases_learning structure...\n";
echo str_repeat("=", 60) . "\n\n";

$stmt = $db->query("PRAGMA table_info(supplier_aliases_learning)");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo sprintf("%-20s %-15s %s\n", 
        $col['name'], 
        $col['type'],
        $col['notnull'] ? 'NOT NULL' : 'NULL'
    );
}

echo "\n\nChecking bank_aliases_learning structure...\n";
echo str_repeat("=", 60) . "\n\n";

$stmt = $db->query("PRAGMA table_info(bank_aliases_learning)");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo sprintf("%-20s %-15s %s\n", 
        $col['name'], 
        $col['type'],
        $col['notnull'] ? 'NOT NULL' : 'NULL'
    );
}
