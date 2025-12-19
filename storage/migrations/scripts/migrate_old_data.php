<?php
/**
 * Simple Data Migration Script
 * Migrates old data to new architecture
 */

require_once __DIR__ . '/../../../app/Support/autoload.php';

use App\Support\Database;
use App\Repositories\ImportBatchRepository;
use App\Repositories\GuaranteeRepository;
use App\Repositories\GuaranteeActionRepository;

try {
    echo "=============================================================================\n";
    echo "Data Migration: Old → New Architecture\n";
    echo "=============================================================================\n\n";
    
    $db = Database::connect();
    $batchRepo = new ImportBatchRepository();
    $guaranteeRepo = new GuaranteeRepository();
    $actionRepo = new GuaranteeActionRepository();
    
    // Check current state
    $oldCount = $db->query("SELECT COUNT(*) FROM imported_records WHERE migrated_guarantee_id IS NULL AND (record_type IS NULL OR record_type='import')")->fetchColumn();
    $actionCount = $db->query("SELECT COUNT(*) FROM imported_records WHERE migrated_action_id IS NULL AND record_type IN ('extension_action','release_action')")->fetchColumn();
    
    echo "📊 Found records to migrate:\n";
    echo "  Import records: $oldCount\n";
    echo "  Action records: $actionCount\n\n";
    
    if ($oldCount == 0 && $actionCount == 0) {
        echo "✅ No records need migration! Everything is up to date.\n\n";
        exit(0);
    }
    
    $db->beginTransaction();
    
    // ===== Step 1: Migrate Import Records =====
    echo "Step 1: Migrating import records...\n";
    $migratedImports = 0;
    
    $stmt = $db->query("
        SELECT * FROM imported_records 
        WHERE migrated_guarantee_id IS NULL 
          AND (record_type IS NULL OR record_type = 'import')
        ORDER BY session_id, id
    ");
    
    $currentSessionId = null;
    $currentBatchId = null;
    
    while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Create batch for new session
        if ($currentSessionId !== $record['session_id']) {
            $currentSessionId = $record['session_id'];
            
            // Get session info
            $sessionStmt = $db->prepare("SELECT * FROM import_sessions WHERE id = ?");
            $sessionStmt->execute([$currentSessionId]);
            $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
            
            $batchType = 'excel_import';
            if ($session) {
                $type = $session['source_type'] ?? $session['session_type'] ?? '';
                if (strpos($type, 'manual') !== false) $batchType = 'manual_batch';
                elseif (strpos($type, 'paste') !== false || strpos($type, 'text') !== false) $batchType = 'text_paste';
            }
            
            $currentBatchId = $batchRepo->create([
                'batch_type' => $batchType,
                'description' => "Migrated - Session #$currentSessionId",
                'filename' => null
            ]);
        }
        
        // Insert guarantee
        $guaranteeId = $guaranteeRepo->create([
            'guarantee_number' => $record['guarantee_number'],
            'raw_supplier_name' => $record['raw_supplier_name'],
            'raw_bank_name' => $record['raw_bank_name'],
            'contract_number' => $record['contract_number'],
            'amount' => $record['amount'],
            'issue_date' => $record['issue_date'],
            'expiry_date' => $record['expiry_date'],
            'type' => $record['type'],
            'comment' => $record['comment'],
            'supplier_id' => $record['supplier_id'],
            'bank_id' => $record['bank_id'],
            'supplier_display_name' => $record['supplier_display_name'],
            'bank_display' => $record['bank_display'],
            'match_status' => $record['match_status'] ?? 'needs_review',
            'import_batch_id' => $currentBatchId,
            'import_type' => $batchType === 'manual_batch' ? 'manual' : ($batchType === 'text_paste' ? 'paste' : 'excel'),
            'import_date' => substr($record['created_at'] ?? date('Y-m-d'), 0, 10)
        ]);
        
        // Update old record
        $db->exec("
            UPDATE imported_records 
            SET migrated_guarantee_id = $guaranteeId, import_batch_id = $currentBatchId
            WHERE id = {$record['id']}
        ");
        
        $migratedImports++;
        if ($migratedImports % 100 == 0) {
            echo "  Progress: $migratedImports records\r";
        }
    }
    
    echo "\n  ✓ Migrated $migratedImports import records\n\n";
    
    // ===== Step 2: Migrate Action Records =====
    echo "Step 2: Migrating action records...\n";
    $migratedActions = 0;
    
    $actionStmt = $db->query("
        SELECT * FROM imported_records 
        WHERE migrated_action_id IS NULL 
          AND record_type IN ('extension_action', 'release_action')
        ORDER BY id
    ");
    
    while ($record = $actionStmt->fetch(PDO::FETCH_ASSOC)) {
        $actionType = $record['record_type'] === 'extension_action' ? 'extension' : 'release';
        
        $actionId = $actionRepo->create([
            'guarantee_number' => $record['guarantee_number'],
            'action_type' => $actionType,
            'action_date' => substr($record['created_at'] ?? date('Y-m-d'), 0, 10),
            'new_expiry_date' => $record['expiry_date'],
            'notes' => $record['comment'],
            'supplier_id' => $record['supplier_id'],
            'bank_id' => $record['bank_id'],
            'supplier_display_name' => $record['supplier_display_name'],
            'bank_display' => $record['bank_display'],
            'action_status' => 'issued',
            'is_locked' => 1
        ]);
        
        $db->exec("
            UPDATE imported_records 
            SET migrated_action_id = $actionId
            WHERE id = {$record['id']}
        ");
        
        $migratedActions++;
    }
    
    echo "  ✓ Migrated $migratedActions action records\n\n";
    
    $db->commit();
    
    // Final Report
    echo "=============================================================================\n";
    echo "✅ Migration Complete!\n";
    echo "=============================================================================\n\n";
    
    $totalBatches = $db->query("SELECT COUNT(*) FROM import_batches")->fetchColumn();
    $totalGuarantees = $db->query("SELECT COUNT(*) FROM guarantees")->fetchColumn();
    $totalActions = $db->query("SELECT COUNT(*) FROM guarantee_actions")->fetchColumn();
    
    echo "📊 Final Counts:\n";
    echo "  import_batches: $totalBatches\n";
    echo "  guarantees: $totalGuarantees\n";
    echo "  guarantee_actions: $totalActions\n\n";
    
    echo "✅ All data migrated successfully!\n";
    echo "✅ Old data preserved in imported_records\n";
    echo "✅ Links created via migrated_* columns\n\n";
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
        echo "\n⚠️  Transaction rolled back\n";
    }
    
    echo "\n❌ Migration Failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (Line " . $e->getLine() . ")\n\n";
    exit(1);
}
