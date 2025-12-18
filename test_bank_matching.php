<?php
require __DIR__ . '/app/Support/autoload.php';

use App\Services\MatchingService;
use App\Repositories\SupplierRepository;
use App\Repositories\SupplierAlternativeNameRepository;
use App\Repositories\BankRepository;
use App\Repositories\BankLearningRepository;
use App\Support\Normalizer;

$normalizer = new Normalizer();
$suppliers = new SupplierRepository();
$supplierAlts = new SupplierAlternativeNameRepository();
$banks = new BankRepository();
$bankLearning = new BankLearningRepository();

// Argument order: $suppliers, $supplierAlts, $banks, $normalizer, $overrides (default), $settings (default), $candidates (default), $bankLearning
$matcher = new MatchingService(
    $suppliers,
    $supplierAlts,
    $banks,
    $normalizer,
    new \App\Repositories\SupplierOverrideRepository(), // overrides
    new \App\Support\Settings(), // settings
    null, // candidates
    $bankLearning
);

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                اختبار مطابقة البنوك                            ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// أمثلة من أسماء بنوك كما تأتي من Excel
$testCases = [
    'بنك الدوحة',
    'Doha Bank',
    'DOHA',
    'بنك البحرين الإسلامي',
    'Bahrain Islamic Bank',
    'BISB',
    'BNP Paribas',
    'بنك بي إن بي باريبا',
    'البنك الأهلي السعودي',
    'Saudi National Bank',
    'SNB',
    'Bank Albilad',
    'ALBILAD',
    'بنك غير موجود 123',
];

foreach ($testCases as $test) {
    $result = $matcher->matchBank($test);
    
    $bankId = $result['bank_id'] ?? '-';
    $finalName = $result['final_name'] ?? '-';
    
    $status = isset($result['bank_id']) ? '✓' : '✗';
    $color = isset($result['bank_id']) ? "" : " [لم يتم العثور عليه]";

    echo "{$status} المدخل: '{$test}'{$color}\n";
    if (isset($result['bank_id'])) {
        echo "   → ID: {$bankId} | الاسم: {$finalName}\n";
    }
    echo "--------------------------------------------------\n";
}

echo "\nاختبار اكتمل!\n";
