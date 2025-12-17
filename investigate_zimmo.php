<?php
require_once __DIR__ . '/app/Support/autoload.php';

use App\Support\Database;

$db = Database::connection();

echo "=" . str_repeat("=", 79) . "\n";
echo "التحقق من حالة ZIMMO - ما الذي حدث فعلاً؟\n";
echo "=" . str_repeat("=", 79) . "\n\n";

// 1. فحص السجل 12014
echo "### 1. السجل الحالي (12014)\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT id, session_id, raw_supplier_name, supplier_id, 
           supplier_display_name, match_status
    FROM imported_records
    WHERE id = 12014
");
$stmt->execute();
$current = $stmt->fetch(PDO::FETCH_ASSOC);

if ($current) {
    echo "Raw Name (من الإكسل): \"{$current['raw_supplier_name']}\"\n";
    echo "Supplier ID: {$current['supplier_id']}\n";
    echo "Display Name: " . ($current['supplier_display_name'] ?? 'NULL') . "\n";
    echo "Match Status: {$current['match_status']}\n\n";
}

// 2. فحص Supplier #130
echo "### 2. المورد #130 في القاموس\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("SELECT id, official_name, normalized_name FROM suppliers WHERE id = 130");
$stmt->execute();
$supplier = $stmt->fetch(PDO::FETCH_ASSOC);

if ($supplier) {
    echo "Official Name: \"{$supplier['official_name']}\"\n";
    echo "Normalized: \"{$supplier['normalized_name']}\"\n\n";
}

// 3. فحص جدول التعلم
echo "### 3. سجلات التعلم لـ ZIMMO\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT id, raw_name, normalized_raw, linked_supplier_id, 
           learning_status, created_at, updated_at
    FROM supplier_learning
    WHERE raw_name LIKE '%ZIMMO%' OR normalized_raw LIKE '%zmm%'
    ORDER BY updated_at DESC
");
$stmt->execute();
$learningRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "عدد سجلات التعلم: " . count($learningRecords) . "\n\n";

foreach ($learningRecords as $lr) {
    echo "  Learning Record #{$lr['id']}:\n";
    echo "    Raw: \"{$lr['raw_name']}\"\n";
    echo "    Normalized: \"{$lr['normalized_raw']}\"\n";
    echo "    Supplier ID: {$lr['linked_supplier_id']}\n";
    echo "    Status: {$lr['learning_status']}\n";
    echo "    Updated: {$lr['updated_at']}\n\n";
}

// 4. فحص جميع السجلات القديمة
echo "### 4. السجلات السابقة مع ZIMMO\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT id, session_id, raw_supplier_name, supplier_id,
           supplier_display_name, match_status, created_at
    FROM imported_records
    WHERE raw_supplier_name LIKE '%ZIMMO%'
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$oldRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "عدد السجلات: " . count($oldRecords) . "\n\n";

foreach ($oldRecords as $or) {
    echo "  Record #{$or['id']} (Session {$or['session_id']}):\n";
    echo "    Raw: \"{$or['raw_supplier_name']}\"\n";
    echo "    Display: \"" . ($or['supplier_display_name'] ?? 'NULL') . "\"\n";
    echo "    Supplier ID: " . ($or['supplier_id'] ?? 'NULL') . "\n";
    echo "    Status: {$or['match_status']}\n";
    echo "    Date: {$or['created_at']}\n\n";
}

// 5. البحث عن "زومو"
echo "### 5. البحث عن إدخالات المستخدم السابقة (زومو)\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT id, session_id, raw_supplier_name, supplier_display_name, 
           supplier_id, match_status
    FROM imported_records
    WHERE supplier_display_name LIKE '%زومو%'
    ORDER BY created_at DESC
");
$stmt->execute();
$zumoRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "سجلات تحتوي 'زومو' في Display Name: " . count($zumoRecords) . "\n\n";

foreach ($zumoRecords as $zr) {
    echo "  Record #{$zr['id']} (Session {$zr['session_id']}):\n";
    echo "    Raw: \"{$zr['raw_supplier_name']}\"\n";
    echo "    Display: \"{$zr['supplier_display_name']}\"\n";
    echo "    Supplier ID: " . ($zr['supplier_id'] ?? 'NULL') . "\n\n";
}

echo "=" . str_repeat("=", 79) . "\n";
