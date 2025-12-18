<?php
require __DIR__ . '/app/Support/autoload.php';
use App\Support\Database;

$db = Database::connect();

// استرجاع الأسماء الصحيحة من النسخة الاحتياطية أو إدخالها يدوياً
$corrections = [
    10 => 'Bank AlJazira',
    26 => 'Ahli United Bank',
    36 => 'Bank of China',
];

$stmt = $db->prepare('UPDATE banks SET official_name_en = ? WHERE id = ?');

foreach ($corrections as $id => $name) {
    $stmt->execute([$name, $id]);
    echo "✓ Updated Bank #{$id}: {$name}\n";
}

echo "\nتم الإصلاح بنجاح!\n";
