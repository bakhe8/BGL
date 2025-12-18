<?php
/**
 * استرجاع البنوك المحذوفة
 * إضافة 3 بنوك لإعادة العدد إلى 37
 */

require __DIR__ . '/app/Support/autoload.php';

use App\Support\Database;
use App\Support\Normalizer;

$db = Database::connect();
$normalizer = new Normalizer();

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║              استرجاع البنوك المحذوفة                          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// البنوك التي سنعيد إضافتها
$banksToAdd = [
    [
        'id' => 37,
        'official_name' => 'البنك الزراعي الصيني',
        'official_name_en' => 'Agricultural Bank of China',
        'short_code' => 'ABC',
    ],
    [
        'id' => 38,
        'official_name' => 'بنك دبي التجاري',
        'official_name_en' => 'Commercial Bank of Dubai',
        'short_code' => 'CBD',
    ],
    [
        'id' => 39,
        'official_name' => 'البنك الأهلي الكويتي',
        'official_name_en' => 'Ahli Bank of Kuwait',
        'short_code' => 'ABK',
    ],
];

$stmt = $db->prepare("
    INSERT OR REPLACE INTO banks (
        id,
        official_name,
        official_name_en,
        normalized_key,
        short_code,
        created_at,
        updated_at
    ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
");

$added = 0;

foreach ($banksToAdd as $bank) {
    $normalizedKey = $normalizer->normalizeBankName($bank['official_name']);
    
    try {
        $stmt->execute([
            $bank['id'],
            $bank['official_name'],
            $bank['official_name_en'],
            $normalizedKey,
            $bank['short_code'],
        ]);
        
        echo "✓ تمت إضافة: {$bank['official_name']} (ID: {$bank['id']})\n";
        $added++;
    } catch (Exception $e) {
        echo "✗ فشل: {$bank['official_name']} - {$e->getMessage()}\n";
    }
}

echo "\n";

// التحقق من العدد النهائي
$countStmt = $db->query('SELECT COUNT(*) as count FROM banks');
$finalCount = $countStmt->fetch()['count'];

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                    النتيجة النهائية                           ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "  تمت إضافة: {$added} بنك\n";
echo "  العدد النهائي: {$finalCount}\n";

if ($finalCount == 37) {
    echo "\n  🎉 ممتاز! تم استرجاع جميع البنوك بنجاح!\n";
} else {
    echo "\n  ⚠️ العدد الحالي: {$finalCount} (المطلوب: 37)\n";
}

echo "\n";
