<?php
require 'app/Support/autoload.php';

$db = \App\Support\Database::connect();

echo "=== Banks Table Sample ===\n\n";
$result = $db->query("SELECT id, name, officialName FROM banks LIMIT 5");
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']}\n";
    echo "  name: {$row['name']}\n";
    echo "  officialName: {$row['officialName']}\n";
    echo "\n";
}
