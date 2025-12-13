<?php
try {
    $pdo = new PDO('sqlite:database/database.sqlite');
    $stmt = $pdo->query("PRAGMA table_info('imported_records')");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo $e->getMessage();
}
