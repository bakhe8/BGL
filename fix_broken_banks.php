<?php
require __DIR__ . '/app/Support/autoload.php';

use App\Support\Database;

$db = Database::connect();

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║          إصلاح البنوك التي تأثرت بالتوحيد الخاطئ              ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// البنوك التي تحتاج إصلاح
$fixes = [
    21 => [
        'old' => 'Bank',
        'correct' => 'Doha Bank'
    ],
    25 => [
        'old' => '- Bahrain Islamic Bank',
        'correct' => 'Bahrain Islamic Bank'
    ],
    40 => [
        'old' => 'Paribas',
        'correct' => 'BNP Paribas'
    ],
];

$stmt = $db->prepare('UPDATE banks SET official_name_en = ? WHERE id = ?');

foreach ($fixes as $id => $fix) {
    echo "بنك #{$id}:\n";
    echo "  القديم: {$fix['old']}\n";
    echo "  الصحيح: {$fix['correct']}\n";
    
    $stmt->execute([$fix['correct'], $id]);
    echo "  ✓ تم التحديث\n\n";
}

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                     اكتمل الإصلاح! ✅                         ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
