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

// Find records with PARTIAL matches (< 100%)
$partialMatches = [];
$targetCount = 20;

foreach ($allSessions as $session) {
    if (count($partialMatches) >= $targetCount) break;
    
    $sessionRecords = $records->allBySession($session['session_id']);
    
    foreach ($sessionRecords as $record) {
        if (count($partialMatches) >= $targetCount) break;
        
        // Calculate candidates
        $supplierResult = $candidateService->supplierCandidates($record->rawSupplierName ?? '');
        $supplierCandidates = $supplierResult['candidates'] ?? [];
        
        $bankResult = $candidateService->bankCandidates($record->rawBankName ?? '');
        $bankCandidates = $bankResult['candidates'] ?? [];
        
        // Check if has PARTIAL matches (score < 99%)
        $hasPartialSupplier = false;
        $hasPartialBank = false;
        
        foreach ($supplierCandidates as $c) {
            $score = ($c['score_raw'] ?? $c['score'] ?? 0);
            if ($score < 0.99 && $score > 0.5) {
                $hasPartialSupplier = true;
                break;
            }
        }
        
        foreach ($bankCandidates as $c) {
            $score = ($c['score_raw'] ?? $c['score'] ?? 0);
            if ($score < 0.99 && $score > 0.5) {
                $hasPartialBank = true;
                break;
            }
        }
        
        if ($hasPartialSupplier || $hasPartialBank) {
            $partialMatches[] = [
                'record' => $record,
                'supplier_candidates' => $supplierCandidates,
                'bank_candidates' => $bankCandidates,
                'has_partial_supplier' => $hasPartialSupplier,
                'has_partial_bank' => $hasPartialBank
            ];
        }
    }
}

echo "Found " . count($partialMatches) . " records with partial matches\n";
echo str_repeat('=', 80) . "\n\n";

foreach ($partialMatches as $idx => $data) {
    $num = $idx + 1;
    $record = $data['record'];
    $supplierCandidates = $data['supplier_candidates'];
    $bankCandidates = $data['bank_candidates'];
    
    // Count visible chips (exclude 100% matches that are already selected)
    $visibleSupplierChips = [];
    foreach (array_slice($supplierCandidates, 0, 3) as $c) {
        // Hide if already selected OR if 100% match
        if (($record->supplierId ?? null) == ($c['supplier_id'] ?? null)) continue;
        
        $score = ($c['score_raw'] ?? $c['score'] ?? 0);
        if ($score >= 0.99) continue; // Auto-selected, won't show chip
        
        $visibleSupplierChips[] = $c;
    }
    
    $visibleBankChips = [];
    foreach (array_slice($bankCandidates, 0, 3) as $c) {
        if (($record->bankId ?? null) == ($c['bank_id'] ?? null)) continue;
        
        $score = ($c['score_raw'] ?? $c['score'] ?? 0);
        if ($score >= 0.99) continue;
        
        $visibleBankChips[] = $c;
    }
    
    echo "[$num] Record ID: {$record->id} (Session: {$record->sessionId})\n";
    echo "    Raw: " . substr($record->rawSupplierName ?? '-', 0, 40) . " / " . substr($record->rawBankName ?? '-', 0, 20) . "\n";
    echo "    Expected Chips: Supplier=" . count($visibleSupplierChips) . ", Bank=" . count($visibleBankChips) . "\n";
    echo "    URL: http://localhost:8000/decision.php?session_id={$record->sessionId}&record_id={$record->id}\n";
    
    if (!empty($visibleSupplierChips)) {
        echo "    Supplier Chips (should be visible):\n";
        foreach ($visibleSupplierChips as $c) {
            $score = round(($c['score_raw'] ?? $c['score'] ?? 0) * 100);
            echo "      - {$c['name']} ({$score}%)\n";
        }
    }
    
    if (!empty($visibleBankChips)) {
        echo "    Bank Chips (should be visible):\n";
        foreach ($visibleBankChips as $c) {
            $score = round(($c['score_raw'] ?? $c['score'] ?? 0) * 100);
            echo "      - {$c['name']} ({$score}%)\n";
        }
    }
    
    echo "\n";
}

echo str_repeat('=', 80) . "\n";
echo "To test, open any URL above and check if the chips match the expected list.\n";
