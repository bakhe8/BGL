<?php
/**
 * Test Script: End-to-End Workflow Verification
 * Run this: php scripts/test_workflow_v2.php
 */

require_once __DIR__ . '/../app/Support/autoload.php';

use App\Repositories\ImportSessionRepository;
use App\Models\ImportSession;
use App\Models\ImportedRecord;
use App\Repositories\ImportedRecordRepository;
use App\Controllers\DecisionController;
use App\Repositories\SupplierSuggestionRepository;
use App\Repositories\UserDecisionRepository;

echo "=== Starting Workflow Test ===\n\n";

try {
    $db = \App\Support\Database::connection();
    
    // 1. Create Test Session
    echo "1. Creating Test Session...\n";
    $sessionRepo = new ImportSessionRepository();
    // Repository expects: create(string $sessionType)
    // It returns an ImportSession object
    $session = $sessionRepo->create('manual');
    echo "   Session ID: {$session->id}\n";
    
    // 2. Create Test Records (one main, one for propagation)
    echo "\n2. Creating Test Records...\n";
    $recordRepo = new ImportedRecordRepository();
    
    // Record 1 (The one we will decide on)
    $rec1 = new ImportedRecord(
        null, $session->id, 'ABC TRADING TEST', 'AL RAJHI', 1000.00, 'G123', null, 'manual', null, null, null, null, null, null, null, null, null
    );
    $rec1->bankId = 1; // Pre-set bank to simplify
    $rec1 = $recordRepo->create($rec1);
    
    // Record 2 (Should receive propagation)
    $rec2 = new ImportedRecord(
        null, $session->id, 'ABC TRADING TEST', 'AL RAJHI', 2000.00, 'G124', null, 'manual', null, null, null, null, null, null, null, null, null
    );
    $rec2->bankId = 1;
    $rec2 = $recordRepo->create($rec2);
    
    echo "   Created records: ID {$rec1->id} & {$rec2->id}\n";
    
    // 3. Test Suggestion Generation (Decision Page Logic)
    echo "\n3. Testing Suggestions (Cache Logic)...\n";
    // We simulate this by checking repository directly, as decision.php output is HTML/JSON mixed
    $suggestionRepo = new SupplierSuggestionRepository();
    $normName = (new \App\Support\Normalizer())->normalizeSupplierName('ABC TRADING TEST');
    
    // Expect: No cache initially
    $hasCache = $suggestionRepo->hasCachedSuggestions($normName);
    echo "   Cache exists before? " . ($hasCache ? 'Yes' : 'No') . "\n";
    
    // Force cache creation (simulating Controller logic)
    $suggestionRepo->saveSuggestions($normName, [
        [
            'supplier_id' => 1,
            'display_name' => 'ABC Official Supplier',
            'source' => 'dictionary',
            'fuzzy_score' => 0.95,
            'usage_count' => 0
        ]
    ]);
    echo "   Cache created manually.\n";
    
    // Check cache again
    $suggestions = $suggestionRepo->getSuggestions($normName);
    echo "   Cached suggestions count: " . count($suggestions) . "\n";
    echo "   First suggestion: " . ($suggestions[0]['display_name'] ?? 'N/A') . "\n";
    
    // 4. Test Saving Decision + Logging + Propagation
    echo "\n4. Testing Save Decision...\n";
    // We can't easily instantiate controller with all deps injected manually without container,
    // so we'll use the repositories directly to verify logic or try to instantiate controller if simple.
    
    // Let's use the actual controller logic simulation
    // mocking payload
    $payload = [
        'match_status' => 'ready',
        'supplier_id' => 1, // Let's say we picked ID 1
        'raw_supplier_name' => 'ABC TRADING TEST',
        'raw_bank_name' => 'AL RAJHI'
    ];
    
    // Instantiate Controller
    $controller = new DecisionController($recordRepo);
    
    // Capture output buffer to suppress JSON output
    ob_start();
    $controller->saveDecision($rec1->id, $payload);
    $output = ob_get_clean();
    $jsonResponse = json_decode($output, true);
    
    echo "   Save Response Success: " . ($jsonResponse['success'] ? 'Yes' : 'No') . "\n";
    echo "   Propagated Count: " . ($jsonResponse['propagated_count'] ?? 'N/A') . "\n";
    
    // 5. Verify Database State
    echo "\n5. Verifying Database State...\n";
    
    // Check User Decisions
    $decisionRepo = new UserDecisionRepository();
    $lastDecision = $decisionRepo->getLastDecision($rec1->id);
    echo "   Record 1 Decision Source: " . ($lastDecision['decision_source'] ?? 'NOT FOUND') . "\n";
    
    // Check Propagation Decision (Record 2)
    $lastDecision2 = $decisionRepo->getLastDecision($rec2->id);
    echo "   Record 2 Decision Source: " . ($lastDecision2['decision_source'] ?? 'NOT FOUND (Expected: propagation)') . "\n";
    
    // Check Usage Count Update
    $suggestions = $suggestionRepo->getSuggestions($normName);
    $newUsage = $suggestions[0]['usage_count'] ?? -1;
    echo "   New Usage Count: " . $newUsage . " (Expected: 1)\n";
    
    echo "\n=== Test Complete ===\n";

} catch (Exception $e) {
    echo "\n❌ TEST FAILED: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
