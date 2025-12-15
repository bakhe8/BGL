<?php
require 'app/Support/Database.php';
use App\Support\Database;

Database::setDatabasePath(__DIR__ . '/storage/database/app.sqlite');
$pdo = Database::connection();
$stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "Tables in database:\n";
foreach ($tables as $table) {
    echo "- " . $table . "\n";
}
