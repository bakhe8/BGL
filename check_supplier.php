<?php
require __DIR__ . '/app/Support/autoload.php';

use App\Repositories\SupplierRepository;
use App\Repositories\ImportedRecordRepository;

$supplierRepo = new SupplierRepository();
$recordRepo = new ImportedRecordRepository();

// Get record 12308
$record = $recordRepo->find(12308);
echo "=== Record 12308 ===\n";
echo "Supplier ID: " . ($record->supplierId ?? 'NULL') . "\n";
echo "Supplier Display Name: " . ($record->supplierDisplayName ?? 'NULL') . "\n";
echo "Raw Supplier Name: " . ($record->rawSupplierName ?? 'NULL') . "\n";
echo "\n";

// Get supplier 150 if exists
if ($record->supplierId) {
    $allSuppliers = $supplierRepo->allNormalized();
    $supplier = array_filter($allSuppliers, fn($s) => $s['id'] == $record->supplierId);
    if (!empty($supplier)) {
        $supplier = reset($supplier);
        echo "=== Supplier #150 ===\n";
        echo json_encode($supplier, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo "\n";
    }
}

// Check if there's an auto-match score
echo "\n=== Checking for auto-match ===\n";
use App\Services\CandidateService;
use App\Repositories\SupplierAlternativeNameRepository;
use App\Repositories\BankRepository;
use App\Support\Normalizer;

$candidateService = new CandidateService(
    $supplierRepo,
    new SupplierAlternativeNameRepository(),
    new Normalizer(),
    new BankRepository()
);

$result = $candidateService->supplierCandidates($record->rawSupplierName ?? '');
echo "Best match score: " . (($result['candidates'][0]['score_raw'] ?? 0) * 100) . "%\n";
echo "Best match ID: " . ($result['candidates'][0]['supplier_id'] ?? 'None') . "\n";
echo "Best match name: " . ($result['candidates'][0]['name'] ?? 'None') . "\n";
