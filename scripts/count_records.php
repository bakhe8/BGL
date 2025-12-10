<?php
declare(strict_types=1);

$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/database/app.sqlite');
$count = $pdo->query('SELECT COUNT(*) FROM imported_records')->fetchColumn();
echo "imported_records: {$count}\n";
