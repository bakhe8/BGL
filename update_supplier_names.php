<?php
/**
 * سكريبت تحديث supplier_display_name للسجلات القديمة
 * يملأ الأسماء من جدول suppliers
 */

require __DIR__ . '/app/Support/autoload.php';

use App\Support\Database;

// الاتصال بقاعدة البيانات
$db = Database::connect();

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║     تحديث supplier_display_name للسجلات القديمة              ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// الخطوة 1: فحص عدد السجلات المتأثرة
echo "📊 الخطوة 1: فحص السجلات المتأثرة...\n";
echo "─────────────────────────────────────────────────────────────────\n";

$countStmt = $db->prepare("
    SELECT COUNT(*) as count
    FROM imported_records
    WHERE supplier_id IS NOT NULL 
    AND supplier_display_name IS NULL
");
$countStmt->execute();
$count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];

echo "  عدد السجلات التي ستُحدَّث: {$count}\n\n";

if ($count == 0) {
    echo "✅ لا توجد سجلات تحتاج تحديث!\n";
    exit(0);
}

// الخطوة 2: عرض أمثلة (أول 5 سجلات)
echo "📋 الخطوة 2: أمثلة من السجلات (أول 5):\n";
echo "─────────────────────────────────────────────────────────────────\n";

$sampleStmt = $db->prepare("
    SELECT 
        r.id,
        r.supplier_id,
        r.supplier_display_name,
        r.raw_supplier_name,
        s.official_name as supplier_official_name
    FROM imported_records r
    LEFT JOIN suppliers s ON s.id = r.supplier_id
    WHERE r.supplier_id IS NOT NULL 
    AND r.supplier_display_name IS NULL
    LIMIT 5
");
$sampleStmt->execute();
$samples = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($samples as $sample) {
    echo "  Record #{$sample['id']}:\n";
    echo "    supplier_id: {$sample['supplier_id']}\n";
    echo "    raw_name: {$sample['raw_supplier_name']}\n";
    echo "    سيصبح display_name: {$sample['supplier_official_name']}\n";
    echo "\n";
}

// الخطوة 3: تنفيذ التحديث
echo "🔧 الخطوة 3: تنفيذ التحديث...\n";
echo "─────────────────────────────────────────────────────────────────\n";

$updateStmt = $db->prepare("
    UPDATE imported_records 
    SET supplier_display_name = (
        SELECT official_name 
        FROM suppliers 
        WHERE suppliers.id = imported_records.supplier_id
    )
    WHERE supplier_id IS NOT NULL 
    AND supplier_display_name IS NULL
");

$result = $updateStmt->execute();
$updatedCount = $updateStmt->rowCount();

echo "  ✅ تم تحديث {$updatedCount} سجل بنجاح!\n\n";

// الخطوة 4: التحقق من النتائج
echo "✔️  الخطوة 4: التحقق من النتائج...\n";
echo "─────────────────────────────────────────────────────────────────\n";

$verifyStmt = $db->prepare("
    SELECT 
        id,
        supplier_id,
        supplier_display_name,
        raw_supplier_name
    FROM imported_records
    WHERE supplier_id IS NOT NULL 
    ORDER BY id DESC
    LIMIT 5
");
$verifyStmt->execute();
$verified = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($verified as $v) {
    $status = !empty($v['supplier_display_name']) ? '✓' : '✗';
    echo "  {$status} Record #{$v['id']}: display_name = " . ($v['supplier_display_name'] ?? 'NULL') . "\n";
}

echo "\n";

// الخطوة 5: إحصائيات نهائية
echo "📊 الإحصائيات النهائية:\n";
echo "─────────────────────────────────────────────────────────────────\n";

$statsStmt = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN supplier_id IS NOT NULL THEN 1 ELSE 0 END) as has_id,
        SUM(CASE WHEN supplier_display_name IS NOT NULL THEN 1 ELSE 0 END) as has_display
    FROM imported_records
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

echo "  إجمالي السجلات: {$stats['total']}\n";
echo "  لديها supplier_id: {$stats['has_id']}\n";
echo "  لديها supplier_display_name: {$stats['has_display']}\n";
echo "  السجلات المكتملة: " . round(($stats['has_display'] / $stats['total']) * 100, 1) . "%\n";

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                    اكتمل التحديث بنجاح! ✅                    ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
