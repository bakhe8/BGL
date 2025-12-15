<?php
try {
    $pdo = new PDO('sqlite:storage/database/app.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // List tables
    echo "Tables in database:\n";
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    print_r($tables);
    
    // Attempt to guess and query if 'records' or similar exists
    $targetTable = null;
    foreach ($tables as $t) {
        if (strpos($t, 'record') !== false || strpos($t, 'guarantee') !== false) {
            $targetTable = $t;
            break;
        }
    }
    
    if ($targetTable) {
        echo "\nQuerying table '$targetTable' for 9410...\n";
        // Check columns of this table
        $cols = $pdo->query("PRAGMA table_info($targetTable)")->fetchAll(PDO::FETCH_ASSOC);
        $colNames = array_column($cols, 'name');
        echo "Columns: " . implode(', ', $colNames) . "\n";
        
        $hasGN = in_array('guarantee_number', $colNames);
        $hasExpiry = in_array('expiry_date', $colNames);
        
        if ($hasGN && $hasExpiry) {
            $stmt = $pdo->prepare("SELECT * FROM $targetTable WHERE guarantee_number = :gn OR id = :gn");
            $stmt->execute(['gn' => '9410']);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            print_r($results);
        } else {
            echo "Table $targetTable found but missing required columns.\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
