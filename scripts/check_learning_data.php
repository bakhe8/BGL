<?php
require 'app/Support/autoload.php';

try {
    $db = \App\Support\Database::connection();
    
    echo "--- Schema ---\n";
    $stm = $db->query("PRAGMA table_info(supplier_aliases_learning)");
    $cols = $stm->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo $col['name'] . " (" . $col['type'] . ")\n";
    }
    
    echo "\n--- Count ---\n";
    $count = $db->query('SELECT COUNT(*) FROM supplier_aliases_learning')->fetchColumn();
    echo "Total learning records: " . $count . "\n";
    
    if ($count > 0) {
        echo "\n--- Sample Data ---\n";
        $rows = $db->query('SELECT * FROM supplier_aliases_learning LIMIT 3')->fetchAll(PDO::FETCH_ASSOC);
        print_r($rows);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
