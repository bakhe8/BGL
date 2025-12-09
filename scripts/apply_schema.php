<?php
declare(strict_types=1);

require __DIR__ . '/../app/Support/autoload.php';

use App\Support\Database;

$schemaPath = __DIR__ . '/../docs/04-Database/schema.sql';
if (!file_exists($schemaPath)) {
    fwrite(STDERR, "Schema file not found: {$schemaPath}\n");
    exit(1);
}

$sql = file_get_contents($schemaPath);
$pdo = Database::connection();
$pdo->exec($sql);

echo "Schema applied to storage/database/app.sqlite\n";
