<?php
require 'app/Support/autoload.php';

try {
    $db = \App\Support\Database::connection();
    
    echo "--- Checking supplier_suggestions ---\n";
    $stm = $db->query("SELECT normalized_input, display_name, source, usage_count, total_score, star_rating FROM supplier_suggestions ORDER BY id DESC LIMIT 5");
    $rows = $stm->fetchAll(PDO::FETCH_ASSOC);
    print_r($rows);
    
    echo "\n--- Checking user_decisions ---\n";
    $stm = $db->query("SELECT * FROM user_decisions ORDER BY id DESC LIMIT 5");
    $rows = $stm->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "No user decisions yet (expected).\n";
    } else {
        print_r($rows);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
