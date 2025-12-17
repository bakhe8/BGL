<?php
require_once __DIR__ . '/app/Support/autoload.php';

use App\Repositories\ImportedRecordRepository;
use App\Repositories\SupplierLearningRepository;
use App\Repositories\SupplierRepository;
use App\Services\CandidateService;
use App\Repositories\SupplierAlternativeNameRepository;
use App\Repositories\BankRepository;
use App\Support\Normalizer;
use App\Support\Database;

echo "=" . str_repeat("=", 79) . "\n";
echo "تحليل شامل لنظام التعلم - حالة ZIMMO TRADING COMPANY LTD.\n";
echo "=" . str_repeat("=", 79) . "\n\n";

// 1. فحص السجل 12014
echo "### الجزء 1: فحص السجل 12014\n";
echo str_repeat("-", 80) . "\n";

$records = new ImportedRecordRepository();
$record = $records->find(12014);

if ($record) {
    echo "✓ Record found:\n";
    echo "  ID: {$record->id}\n";
    echo "  Session: {$record->sessionId}\n";
    echo "  Raw Supplier Name: {$record->rawSupplierName}\n";
    echo "  Current Supplier ID: " . ($record->supplierId ?? 'NULL') . "\n";
    echo "  Match Status: {$record->matchStatus}\n";
} else {
    echo "✗ Record 12014 not found!\n";
    exit(1);
}

echo "\n";

// 2. فحص جدول supplier_learning
echo "### الجزء 2: فحص جدول supplier_learning\n";
echo str_repeat("-", 80) . "\n";

$db = Database::getInstance();
$learningRepo = new SupplierLearningRepository();

// البحث المباشر
$stmt = $db->prepare("
    SELECT id, raw_name, supplier_id, normalized_raw, created_at, updated_at
    FROM supplier_learning
    WHERE raw_name LIKE :search
    ORDER BY updated_at DESC
");
$search = '%ZIMMO%';
$stmt->execute(['search' => $search]);
$learningRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Learning records matching 'ZIMMO': " . count($learningRecords) . "\n\n";

if (!empty($learningRecords)) {
    foreach ($learningRecords as $lr) {
        echo "  Learning Record #{$lr['id']}:\n";
        echo "    Raw Name: {$lr['raw_name']}\n";
        echo "    Supplier ID: {$lr['supplier_id']}\n";
        echo "    Normalized: {$lr['normalized_raw']}\n";
        echo "    Created: {$lr['created_at']}\n";
        echo "    Updated: {$lr['updated_at']}\n";
        echo "\n";
    }
} else {
    echo "  ⚠️ لا توجد سجلات تعلم لـ ZIMMO!\n\n";
}

// 3. فحص البحث بالـ Normalized
$normalizer = new Normalizer();
$normalizedSearch = $normalizer->normalize($record->rawSupplierName);

echo "Normalized search: '$normalizedSearch'\n";

$stmt = $db->prepare("
    SELECT id, raw_name, supplier_id, normalized_raw
    FROM supplier_learning
    WHERE normalized_raw = :norm
");
$stmt->execute(['norm' => $normalizedSearch]);
$exactNormMatch = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Exact normalized match: " . count($exactNormMatch) . "\n\n";

if (!empty($exactNormMatch)) {
    foreach ($exactNormMatch as $m) {
        echo "  ✓ Found exact normalized match:\n";
        echo "    Raw: {$m['raw_name']}\n";
        echo "    Supplier ID: {$m['supplier_id']}\n";
        echo "    Normalized: {$m['normalized_raw']}\n\n";
    }
}

echo "\n";

// 4. اختبار CandidateService
echo "### الجزء 3: اختبار CandidateService\n";
echo str_repeat("-", 80) . "\n";

$suppliers = new SupplierRepository();
$banks = new BankRepository();
$candidateService = new CandidateService(
    $suppliers,
    new SupplierAlternativeNameRepository(),
    $normalizer,
    $banks
);

$result = $candidateService->supplierCandidates($record->rawSupplierName);
$candidates = $result['candidates'] ?? [];

echo "Candidates generated: " . count($candidates) . "\n\n";

if (empty($candidates)) {
    echo "  ✗ لا توجد اقتراحات!\n\n";
} else {
    echo "  Top 5 candidates:\n";
    foreach (array_slice($candidates, 0, 5) as $idx => $c) {
        $num = $idx + 1;
        $score = round(($c['score_raw'] ?? $c['score'] ?? 0) * 100);
        $source = $c['source'] ?? 'unknown';
        echo "    {$num}. {$c['name']} ({$score}%) - Source: {$source}\n";
    }
    echo "\n";
}

// 5. فحص جميع الموردين
echo "### الجزء 4: البحث في جدول الموردين\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT id, official_name, normalized_name
    FROM suppliers
    WHERE official_name LIKE :search OR normalized_name LIKE :norm
");
$stmt->execute(['search' => '%ZIMMO%', 'norm' => "%{$normalizedSearch}%"]);
$supplierMatches = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Suppliers matching 'ZIMMO': " . count($supplierMatches) . "\n\n";

foreach ($supplierMatches as $s) {
    echo "  Supplier #{$s['id']}:\n";
    echo "    Official Name: {$s['official_name']}\n";
    echo "    Normalized: {$s['normalized_name']}\n\n";
}

// 6. فحص السجلات القديمة
echo "### الجزء 5: فحص السجلات القديمة لنفس المورد\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare("
    SELECT id, session_id, raw_supplier_name, supplier_id, match_status, created_at
    FROM imported_records
    WHERE raw_supplier_name LIKE :search
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute(['search' => '%ZIMMO%']);
$oldRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Previous records with 'ZIMMO': " . count($oldRecords) . "\n\n";

foreach ($oldRecords as $or) {
    echo "  Record #{$or['id']} (Session {$or['session_id']}):\n";
    echo "    Raw: {$or['raw_supplier_name']}\n";
    echo "    Supplier ID: " . ($or['supplier_id'] ?? 'NULL') . "\n";
    echo "    Status: {$or['match_status']}\n";
    echo "    Date: {$or['created_at']}\n\n";
}

echo "=" . str_repeat("=", 79) . "\n";
echo "التحليل اكتمل\n";
echo "=" . str_repeat("=", 79) . "\n";
