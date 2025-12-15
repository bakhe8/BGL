<?php
require __DIR__ . '/../app/Support/autoload.php';
use App\Support\Database;

$pdo = Database::connection();
$pdo->exec('ALTER TABLE banks ADD COLUMN department VARCHAR(255) DEFAULT "إدارة الضمانات"');
echo "✓ Department column added successfully!\n";

// Verify
$result = $pdo->query("SELECT department FROM banks LIMIT 1");
echo "Test value: " . ($result->fetchColumn() ?: 'NULL') . "\n";
