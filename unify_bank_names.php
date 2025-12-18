<?php
/**
 * توحيد أسماء البنوك الإنجليزية
 * إزالة short_code من official_name_en لتحسين المطابقة التلقائية
 */

require __DIR__ . '/app/Support/autoload.php';

use App\Support\Database;

$db = Database::connect();

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║           توحيد أسماء البنوك الإنجليزية                       ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "الهدف: إزالة short_code من official_name_en لتحسين المطابقة\n";
echo "الفائدة: مطابقة أسرع وأدق في Smart Paste والاستيراد التلقائي\n\n";

// الخطوة 1: فحص البنوك التي تحتاج تحديث
echo "📊 الخطوة 1: فحص البنوك التي تحتاج تحديث...\n";
echo "─────────────────────────────────────────────────────────────────\n";

$stmt = $db->query("
    SELECT 
        id,
        official_name,
        official_name_en,
        short_code
    FROM banks
    WHERE short_code IS NOT NULL
    AND official_name_en IS NOT NULL
    ORDER BY id
");

$banks = $stmt->fetchAll(PDO::FETCH_ASSOC);
$needsUpdate = [];

foreach ($banks as $bank) {
    $englishName = $bank['official_name_en'];
    $shortCode = $bank['short_code'];
    
    // فحص إذا كان short_code موجود في الاسم
    if (stripos($englishName, $shortCode) !== false) {
        // تنظيف الاسم
        $cleanName = $englishName;
        
        // إزالة short_code بين أقواس: (SNB)
        $cleanName = preg_replace('/\s*\(' . preg_quote($shortCode, '/') . '\)\s*/i', '', $cleanName);
        
        // إزالة short_code بدون أقواس في البداية: "SNB Bank" -> "Bank"
        $cleanName = preg_replace('/^' . preg_quote($shortCode, '/') . '\s+/i', '', $cleanName);
        
        // إزالة short_code في النهاية: "Bank SNB" -> "Bank"
        $cleanName = preg_replace('/\s+' . preg_quote($shortCode, '/') . '$/i', '', $cleanName);
        
        // تنظيف المسافات الزائدة
        $cleanName = trim($cleanName);
        $cleanName = preg_replace('/\s+/', ' ', $cleanName);
        
        if ($cleanName !== $englishName) {
            $needsUpdate[] = [
                'id' => $bank['id'],
                'official_name' => $bank['official_name'],
                'old_english' => $englishName,
                'new_english' => $cleanName,
                'short_code' => $shortCode,
            ];
        }
    }
}

echo "  عدد البنوك التي تحتاج تحديث: " . count($needsUpdate) . "\n\n";

if (empty($needsUpdate)) {
    echo "✅ جميع البنوك موحدة بالفعل!\n";
    exit(0);
}

// الخطوة 2: عرض التغييرات المقترحة
echo "📋 الخطوة 2: التغييرات المقترحة:\n";
echo "─────────────────────────────────────────────────────────────────\n";

foreach ($needsUpdate as $i => $bank) {
    echo "  " . ($i+1) . ". {$bank['official_name']}\n";
    echo "     قبل: {$bank['old_english']}\n";
    echo "     بعد: {$bank['new_english']}\n";
    echo "     short_code: {$bank['short_code']} (سيبقى منفصلاً)\n";
    echo "\n";
}

// الخطوة 3: تأكيد التنفيذ
echo "🔧 الخطوة 3: تنفيذ التحديث...\n";
echo "─────────────────────────────────────────────────────────────────\n";

$updateStmt = $db->prepare("
    UPDATE banks 
    SET official_name_en = ?
    WHERE id = ?
");

$successCount = 0;
$failCount = 0;

foreach ($needsUpdate as $bank) {
    try {
        $updateStmt->execute([$bank['new_english'], $bank['id']]);
        $successCount++;
    } catch (Exception $e) {
        echo "  ✗ فشل تحديث البنك #{$bank['id']}: {$e->getMessage()}\n";
        $failCount++;
    }
}

echo "  ✅ تم تحديث {$successCount} بنك بنجاح\n";
if ($failCount > 0) {
    echo "  ✗ فشل تحديث {$failCount} بنك\n";
}
echo "\n";

// الخطوة 4: التحقق من النتائج
echo "✔️  الخطوة 4: التحقق من النتائج...\n";
echo "─────────────────────────────────────────────────────────────────\n";

$verifyStmt = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN official_name_en LIKE '%(' || short_code || ')%' THEN 1 ELSE 0 END) as with_code_in_name
    FROM banks
    WHERE short_code IS NOT NULL
    AND official_name_en IS NOT NULL
");

$stats = $verifyStmt->fetch(PDO::FETCH_ASSOC);

echo "  إجمالي البنوك مع short_code: {$stats['total']}\n";
echo "  البنوك التي لا تزال تحتوي على short_code في الاسم: {$stats['with_code_in_name']}\n";

if ($stats['with_code_in_name'] == 0) {
    echo "\n  🎉 ممتاز! جميع الأسماء موحدة الآن!\n";
} else {
    echo "\n  ⚠️ بعض البنوك لا تزال تحتوي على short_code\n";
}

echo "\n";

// الخطوة 5: أمثلة من النتائج
echo "📋 الخطوة 5: أمثلة من النتائج النهائية:\n";
echo "─────────────────────────────────────────────────────────────────\n";

$exampleStmt = $db->query("
    SELECT 
        id,
        official_name,
        official_name_en,
        short_code
    FROM banks
    WHERE short_code IS NOT NULL
    AND official_name_en IS NOT NULL
    ORDER BY id
    LIMIT 5
");

$examples = $exampleStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($examples as $bank) {
    echo "  {$bank['official_name']}\n";
    echo "    → English: {$bank['official_name_en']}\n";
    echo "    → Code: {$bank['short_code']}\n";
    echo "\n";
}

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                   اكتمل التوحيد بنجاح! ✅                     ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "الفوائد المتحققة:\n";
echo "─────────────────────────────────────────────────────────────────\n";
echo "✓ تحسين دقة المطابقة التلقائية\n";
echo "✓ مطابقة أسرع في Smart Paste\n";
echo "✓ نظام أنظف وأسهل في الصيانة\n";
echo "✓ short_code محفوظ في عمود منفصل للاستخدام عند الحاجة\n";
