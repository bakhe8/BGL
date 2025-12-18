<?php
require __DIR__ . '/app/Support/autoload.php';

use App\Support\Database;
use App\Support\Normalizer;

$db = Database::connect();
$normalizer = new Normalizer();

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║          تحديث normalized_key لجميع البنوك                    ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// جلب جميع البنوك
$stmt = $db->query('SELECT id, official_name, official_name_en, normalized_key FROM banks');
$banks = $stmt->fetchAll();

$updateStmt = $db->prepare('UPDATE banks SET normalized_key = ? WHERE id = ?');

foreach ($banks as $bank) {
    // نستخدم الاسم العربي كمصدر أساسي للمفتاح المعياري
    // هذا يضمن أن المطابقة بالعربي (الأكثر شيوعاً) ستكون دقيقة 100%
    $newKey = $normalizer->normalizeBankName($bank['official_name']);
    
    // إذا كان الاسم العربي غير موجود (نادر)، نستخدم الإنجليزي
    if (empty($newKey) && !empty($bank['official_name_en'])) {
        $newKey = $normalizer->normalizeBankName($bank['official_name_en']);
    }

    $oldKey = $bank['normalized_key'];
    
    if ($newKey !== $oldKey) {
        $updateStmt->execute([$newKey, $bank['id']]);
        echo "تحديث بنك #{$bank['id']} ({$bank['official_name']}):\n";
        echo "   '{$oldKey}' -> '{$newKey}'\n";
    }
}

echo "\nتم تحديث المفاتيح المعيارية بنجاح!\n";
