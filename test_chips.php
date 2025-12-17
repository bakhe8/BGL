<?php
require_once __DIR__ . '/app/Support/autoload.php';

use App\Repositories\ImportedRecordRepository;
use App\Repositories\ImportSessionRepository;
use App\Services\CandidateService;
use App\Repositories\SupplierRepository;
use App\Repositories\BankRepository;
use App\Repositories\SupplierAlternativeNameRepository;
use App\Support\Normalizer;

$records = new ImportedRecordRepository();
$sessions = new ImportSessionRepository();
$suppliers = new SupplierRepository();
$banks = new BankRepository();
$candidateService = new CandidateService($suppliers, new SupplierAlternativeNameRepository(), new Normalizer(), $banks);

// Get latest sessions
$allSessions = $sessions->getAllSessions();
echo "Total Sessions: " . count($allSessions) . "\n\n";

// Get records from recent sessions
$testRecords = [];
$targetCount = 20;

foreach ($allSessions as $session) {
    if (count($testRecords) >= $targetCount) break;
    
    $sessionRecords = $records->allBySession($session['session_id']);
    
    foreach ($sessionRecords as $record) {
        if (count($testRecords) >= $targetCount) break;
        
        // Only include records that have raw names but no decisions yet
        if (!empty($record->rawSupplierName) && empty($record->supplierId)) {
            $testRecords[] = $record;
        }
    }
}

echo "Found " . count($testRecords) . " test records\n";
echo str_repeat('=', 80) . "\n\n";

// Test each record
$resultsWithChips = 0;
$resultsWithoutChips = 0;
$results = [];

foreach ($testRecords as $idx => $record) {
    $num = $idx + 1;
    
    // Calculate candidates
    $supplierResult = $candidateService->supplierCandidates($record->rawSupplierName ?? '');
    $supplierCandidates = $supplierResult['candidates'] ?? [];
    
    $bankResult = $candidateService->bankCandidates($record->rawBankName ?? '');
    $bankCandidates = $bankResult['candidates'] ?? [];
    
    // Top 3 candidates
    $top3Suppliers = array_slice($supplierCandidates, 0, 3);
    $top3Banks = array_slice($bankCandidates, 0, 3);
    
    // Count visible chips (hide if 100% match and selected)
$visibleSupplierChips = count(array_filter($top3Suppliers, function($c) use ($record) {
        return ($record->supplierId ?? null) != ($c['supplier_id'] ?? null);
    }));
    
    $visibleBankChips = count(array_filter($top3Banks, function($c) use ($record) {
        return ($record->bankId ?? null) != ($c['bank_id'] ?? null);
    }));
    
    $hasChips = ($visibleSupplierChips > 0) || ($visibleBankChips > 0);
    
    if ($hasChips) {
        $resultsWithChips++;
    } else {
        $resultsWithoutChips++;
    }
    
    $result = [
        'num' => $num,
        'record_id' => $record->id,
        'session_id' => $record->sessionId,
        'raw_supplier' => substr($record->rawSupplierName ?? '-', 0, 40),
        'raw_bank' => substr($record->rawBankName ?? '-', 0, 20),
        'supplier_chips' => $visibleSupplierChips,
        'bank_chips' => $visibleBankChips,
        'has_chips' => $hasChips,
        'url' => "http://localhost:8000/decision.php?session_id={$record->sessionId}&record_id={$record->id}",
        'top_suppliers' => array_map(function($c) {
            return [
                'name' => substr($c['name'], 0, 30),
                'score' => round(($c['score_raw'] ?? $c['score'] ?? 0) * 100)
            ];
        }, $top3Suppliers),
        'top_banks' => array_map(function($c) {
            return [
                'name' => substr($c['name'], 0, 30),
                'score' => round(($c['score_raw'] ?? $c['score'] ?? 0) * 100)
            ];
        }, $top3Banks)
    ];
    
    $results[] = $result;
    
    echo "[$num] Record ID: {$record->id} (Session: {$record->sessionId})\n";
    echo "    Raw: {$result['raw_supplier']} / {$result['raw_bank']}\n";
    echo "    Chips: Supplier={$visibleSupplierChips}, Bank={$visibleBankChips}" . ($hasChips ? " ✓" : " ✗") . "\n";
    
    if (!empty($top3Suppliers)) {
        echo "    Top Suppliers:\n";
        foreach ($result['top_suppliers'] as $s) {
            echo "      - {$s['name']} ({$s['score']}%)\n";
        }
    }
    
    if (!empty($top3Banks)) {
        echo "    Top Banks:\n";
        foreach ($result['top_banks'] as $b) {
            echo "      - {$b['name']} ({$b['score']}%)\n";
        }
    }
    
    echo "\n";
}

echo str_repeat('=', 80) . "\n";
echo "SUMMARY:\n";
echo "  Records with chips: $resultsWithChips\n";
echo "  Records without chips: $resultsWithoutChips\n";
echo "\nSample URLs to test:\n";

// Show 5 URLs with chips
$withChips = array_filter($results, fn($r) => $r['has_chips']);
$sample = array_slice($withChips, 0, 5);

foreach ($sample as $r) {
    echo "  [{$r['num']}] {$r['url']}\n";
    echo "      Expected: {$r['supplier_chips']} supplier + {$r['bank_chips']} bank chips\n";
}

// Export to JSON for browser testing
file_put_contents(__DIR__ . '/test_results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\n✓ Results exported to test_results.json\n";
