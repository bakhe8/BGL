<?php
require_once __DIR__ . '/app/Support/autoload.php';

use App\Repositories\ImportedRecordRepository;
use App\Services\CandidateService;
use App\Repositories\SupplierRepository;
use App\Repositories\BankRepository;
use App\Repositories\SupplierAlternativeNameRepository;
use App\Support\Normalizer;

$records = new ImportedRecordRepository();
$suppliers = new SupplierRepository();
$banks = new BankRepository();
$candidateService = new CandidateService($suppliers, new SupplierAlternativeNameRepository(), new Normalizer(), $banks);

$recordId = 11550;
$record = $records->find($recordId);

if ($record) {
    echo "✓ Record found: {$record->rawSupplierName}\n";
    echo "  Raw Bank: {$record->rawBankName}\n\n";
    
    $supplierResult = $candidateService->supplierCandidates($record->rawSupplierName ?? '');
    $supplierCandidates = $supplierResult['candidates'] ?? [];
    
    echo "Supplier Candidates: " . count($supplierCandidates) . "\n";
    if (!empty($supplierCandidates)) {
        echo "First 3:\n";
        foreach (array_slice($supplierCandidates, 0, 3) as $c) {
            $score = round(($c['score_raw'] ?? $c['score'] ?? 0) * 100);
            echo "  - {$c['name']} ({$score}%)\n";
        }
    } else {
        echo "  No supplier candidates found!\n";
    }
    
    echo "\n";
    
    $bankResult = $candidateService->bankCandidates($record->rawBankName ?? '');
    $bankCandidates = $bankResult['candidates'] ?? [];
    
    echo "Bank Candidates: " . count($bankCandidates) . "\n";
    if (!empty($bankCandidates)) {
        echo "First 3:\n";
        foreach (array_slice($bankCandidates, 0, 3) as $c) {
            $score = round(($c['score_raw'] ?? $c['score'] ?? 0) * 100);
            echo "  - {$c['name']} ({$score}%)\n";
        }
    } else {
        echo "  No bank candidates found!\n";
    }
} else {
    echo "✗ Record $recordId not found\n";
}
