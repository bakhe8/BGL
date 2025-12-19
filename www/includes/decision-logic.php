<?php
/**
 * Decision Page Logic
 * Prepares all data needed for the decision page view
 */

declare(strict_types=1);

use App\Repositories\ImportedRecordRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\BankRepository;
use App\Repositories\ImportSessionRepository;
use App\Repositories\SupplierAlternativeNameRepository;
use App\Repositories\SupplierSuggestionRepository;
use App\Repositories\UserDecisionRepository;
use App\Repositories\BankLearningRepository;
use App\Services\CandidateService;
use App\Support\Normalizer;

// Initialize repositories
$importSessionRepo = new ImportSessionRepository();
$records = new ImportedRecordRepository();
$suppliers = new SupplierRepository();
$banks = new BankRepository();
$sessions = new ImportSessionRepository();
$normalizer = new Normalizer();
$bankLearning = new BankLearningRepository();
$candidateService = new CandidateService($suppliers, new SupplierAlternativeNameRepository(), $normalizer, $banks);

// Repositories for refactored system
$suggestionRepo = new SupplierSuggestionRepository();
$decisionRepo = new UserDecisionRepository();

// NEW: Support for new architecture
use App\Repositories\ImportBatchRepository;
use App\Repositories\GuaranteeRepository;
$batchRepo = new ImportBatchRepository();
$guaranteeRepo = new GuaranteeRepository();

// Get parameters - support both old (session_id) and new (batch_id)
$sessionId = isset($_GET['session_id']) ? (int) $_GET['session_id'] : null;
$batchId = isset($_GET['batch_id']) ? (int) $_GET['batch_id'] : null;
$recordId = isset($_GET['record_id']) ? (int) $_GET['record_id'] : null;
$filter = $_GET['filter'] ?? 'all'; // all, pending, approved

// Get available sessions/batches for dropdown
$allSessions = $sessions->getAllSessions();

// NEW: Also get batches and merge them into the list
$allBatches = $batchRepo->all();
foreach ($allBatches as $batch) {
    // Add batches to the sessions list for compatibility
    $allSessions[] = [
        'session_id' => $batch['id'],
        'batch_id' => $batch['id'],
        'is_batch' => true,
        'batch_type' => $batch['batch_type'],
        'description' => $batch['description'],
        'record_count' => $batch['total_records'],
        'created_at' => $batch['created_at']
    ];
}

// Sort by ID desc (newest first)
usort($allSessions, fn($a, $b) => ($b['session_id'] ?? 0) <=> ($a['session_id'] ?? 0));

// Default to latest session/batch if none specified
if (!$sessionId && !$batchId && !empty($allSessions)) {
    if (isset($allSessions[0]['batch_id'])) {
        $batchId = (int) $allSessions[0]['batch_id'];
    } else {
        $sessionId = (int) $allSessions[0]['session_id'];
    }
}

// Get all records for the session/batch
if ($batchId) {
    // Get records from new architecture
    $guarantees = $guaranteeRepo->allByBatch($batchId);
    // Convert to ImportedRecord format for compatibility
    $allRecords = array_map(function($g) use ($records) {
        return $records->find($g['id']); // This won't work - we need a different approach
    }, $guarantees);
    // TODO: Ideally we should create a unified method that returns records from both sources
} elseif ($sessionId) {
    // Get records from old architecture
    $allRecords = $records->allBySession($sessionId);
} else {
    $allRecords = [];
}

// Apply filter
$filteredRecords = array_filter($allRecords, function($r) use ($filter) {
    if ($filter === 'all') return true;
    $isCompleted = in_array($r->matchStatus, ['ready', 'approved']);
    if ($filter === 'approved') return $isCompleted;
    if ($filter === 'pending') return !$isCompleted;
    return true;
});
$filteredRecords = array_values($filteredRecords);

// Find current record
$currentIndex = 0;
$currentRecord = null;

if ($recordId) {
    // CRITICAL FIX: Find the record directly by ID, not within filtered session
    // This ensures extension/release actions from different sessions display correctly
    $currentRecord = $records->find($recordId);
    
    if ($currentRecord) {
        // Update sessionId to match the record's session
        $sessionId = $currentRecord->sessionId;
        
        // Reload all records for this session
        $allRecords = $records->allBySession($sessionId);
        
        // Apply filter
        $filteredRecords = array_filter($allRecords, function($r) use ($filter) {
            if ($filter === 'all') return true;
            $isCompleted = in_array($r->matchStatus, ['ready', 'approved']);
            if ($filter === 'approved') return $isCompleted;
            if ($filter === 'pending') return !$isCompleted;
            return true;
        });
        $filteredRecords = array_values($filteredRecords);
        
        // Find the index of current record in filtered list
        foreach ($filteredRecords as $index => $r) {
            if ($r->id === $recordId) {
                $currentIndex = $index;
                break;
            }
        }
    }
} else {
    if (!$currentRecord && !empty($filteredRecords)) {
        // Smart Jump: Find the first pending record to save user time
        foreach ($filteredRecords as $index => $r) {
            if (!in_array($r->matchStatus, ['ready', 'approved'])) {
                $currentRecord = $r;
                $currentIndex = $index;
                break;
            }
        }
        // Fallback: If all are ready, just show the first one
        if (!$currentRecord) {
            $currentRecord = $filteredRecords[0];
        }
    }
}

// Calculate navigation
$totalRecords = count($filteredRecords);
$hasPrev = $currentIndex > 0;
$hasNext = $currentIndex < $totalRecords - 1;
$prevId = $hasPrev ? $filteredRecords[$currentIndex - 1]->id : null;
$nextId = $hasNext ? $filteredRecords[$currentIndex + 1]->id : null;

// Smart Skip Logic: Find next pending record for the "Save & Next" action
$nextPendingId = null;
for ($i = $currentIndex + 1; $i < $totalRecords; $i++) {
    $r = $filteredRecords[$i];
    if (!in_array($r->matchStatus, ['ready', 'approved'])) {
        $nextPendingId = $r->id;
        break;
    }
}
// Fallback: If no pending records ahead, standard next behavior
if (!$nextPendingId && $hasNext) {
    $nextPendingId = $nextId;
}

// Stats for current session (filtered)
$stats = [
    'total' => count($allRecords),
    'approved' => count(array_filter($allRecords, fn($r) => in_array($r->matchStatus, ['ready', 'approved']))),
    'pending' => count(array_filter($allRecords, fn($r) => !in_array($r->matchStatus, ['ready', 'approved']))),
];

// Get all suppliers and banks for autocomplete & lookups
$allSuppliers = $suppliers->allNormalized();
$allBanks = $banks->allNormalized();

// Get candidates for current record
$supplierCandidates = [];
$bankCandidates = [];
if ($currentRecord) {
    // ═══════════════════════════════════════════════════════════════════
    // SUPPLIER CANDIDATES: Use cache-first approach (Phase 3 Refactoring)
    // ═══════════════════════════════════════════════════════════════════
    $rawSupplierName = $currentRecord->rawSupplierName ?? '';
    $normalizedSupplierName = $normalizer->normalizeSupplierName($rawSupplierName);
    
    if (!empty($normalizedSupplierName)) {
        // Try cache first
        if ($suggestionRepo->hasCachedSuggestions($normalizedSupplierName)) {
            // Use cached suggestions
            $cachedSuggestions = $suggestionRepo->getSuggestions($normalizedSupplierName);
            foreach ($cachedSuggestions as $cs) {
                $supplierCandidates[] = [
                    'supplier_id' => $cs['supplier_id'],
                    'name' => $cs['display_name'],
                    'score' => $cs['total_score'] / 100, // Normalize for compatibility
                    'score_raw' => $cs['fuzzy_score'],
                    'star_rating' => $cs['star_rating'],
                    'is_learning' => ($cs['source'] === 'learning'),
                    'source' => $cs['source'],
                    'usage_count' => $cs['usage_count'],
                ];
            }
        } else {
            // Generate from CandidateService and cache
            $supplierResult = $candidateService->supplierCandidates($rawSupplierName);
            $supplierCandidates = $supplierResult['candidates'] ?? [];
            
            // Save to cache for next time
            $suggestionsToCache = [];
            foreach ($supplierCandidates as $cand) {
                $suggestionsToCache[] = [
                    'supplier_id' => $cand['supplier_id'],
                    'display_name' => $cand['name'],
                    'source' => $cand['is_learning'] ?? false ? 'learning' : 'dictionary',
                    'fuzzy_score' => $cand['score_raw'] ?? $cand['score'] ?? 0,
                    'usage_count' => $cand['usage_count'] ?? 0,
                ];
            }
            if (!empty($suggestionsToCache)) {
                $suggestionRepo->saveSuggestions($normalizedSupplierName, $suggestionsToCache);
            }
        }
    }
    
    // Bank candidates (still using old method - future phase)
    $bankResult = $candidateService->bankCandidates($currentRecord->rawBankName ?? '');
    $bankCandidates = $bankResult['candidates'] ?? [];
    
    // ═══════════════════════════════════════════════════════════════════
    // POPULATE DISPLAY NAMES FIRST (CRITICAL ORDER)
    // ═══════════════════════════════════════════════════════════════════
    // Must happen BEFORE current selection chip logic
    if (!empty($currentRecord->supplierId) && empty($currentRecord->supplierDisplayName)) {
        foreach ($allSuppliers as $s) {
            if ($s['id'] == $currentRecord->supplierId) {
                $currentRecord->supplierDisplayName = $s['official_name'];
                break;
            }
        }
    }
    if (!empty($currentRecord->bankId) && empty($currentRecord->bankDisplay)) {
        foreach ($allBanks as $b) {
            if ($b['id'] == $currentRecord->bankId) {
                $currentRecord->bankDisplay = $b['official_name'];
                break;
            }
        }
    }
    
    // ═══════════════════════════════════════════════════════════════════
    // CURRENT SELECTION INDICATOR (Refactored with UserDecisionRepository)
    // ═══════════════════════════════════════════════════════════════════
    // OPTION 2: Don't create chip if selected name = raw Excel name (avoid duplication)
    $shouldShowSelectionChip = !empty($currentRecord->supplierId) && 
                               !empty($currentRecord->supplierDisplayName) &&
                               ($currentRecord->supplierDisplayName !== $currentRecord->rawSupplierName);
    
    if ($shouldShowSelectionChip) {
        // Get decision source from history (NEW: using UserDecisionRepository)
        $lastDecision = $decisionRepo->getLastDecision($currentRecord->id);
        
        if ($lastDecision) {
            // Use stored decision source
            $selectionBadge = UserDecisionRepository::getSourceLabel($lastDecision['decision_source']);
        } else {
            // Fallback for existing records without decision history
            // Note: Old SupplierLearningRepository was deprecated - use simple default
            $selectionBadge = 'الاختيار الحالي';
        }
        
        array_unshift($supplierCandidates, [
            'supplier_id' => $currentRecord->supplierId,
            'name' => $currentRecord->supplierDisplayName,
            'is_current_selection' => true,
            'selection_badge' => $selectionBadge,
            'star_rating' => 3,
            'score' => 1.0,
            'score_raw' => 1.0,
        ]);
    }


    // Auto-select 100% match candidates if not already linked (Threshold 0.99)
    if (empty($currentRecord->supplierId) && !empty($supplierCandidates)) {
        $bestSupplier = $supplierCandidates[0];
        $score = $bestSupplier['score_raw'] ?? $bestSupplier['score'] ?? 0;
        if ($score >= 0.99) {
            $currentRecord->supplierId = $bestSupplier['supplier_id'];
            $currentRecord->supplierDisplayName = $bestSupplier['name'];
        }
    }
    if (empty($currentRecord->bankId) && !empty($bankCandidates)) {
        $bestBank = $bankCandidates[0];
        $score = $bestBank['score_raw'] ?? $bestBank['score'] ?? 0;
        if ($score >= 0.99) {
            $currentRecord->bankId = $bestBank['bank_id'];
            $currentRecord->bankDisplay = $bestBank['name'];
        }
    }
}

// Build query string for navigation
$buildUrl = function($newRecordId = null, $newFilter = null, $newSessionId = null) use ($sessionId, $filter) {
    $params = [];
    $params['session_id'] = $newSessionId ?? $sessionId;
    if ($newRecordId) $params['record_id'] = $newRecordId;
    $params['filter'] = $newFilter ?? $filter;
    return '/?' . http_build_query($params);
};

// Filter text for save button
$filterText = 'سجل';
if ($filter === 'approved') $filterText = 'سجل جاهز';
elseif ($filter === 'pending') $filterText = 'سجل يحتاج قرار';
