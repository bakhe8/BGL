<?php
/**
 * API Endpoint: Guarantee History
 * Returns complete history for a guarantee from both old and new tables
 */

require_once __DIR__ . '/../../app/Support/autoload.php';

use App\Repositories\GuaranteeRepository;
use App\Repositories\GuaranteeActionRepository;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    $db = \App\Support\Database::connect();
    $guaranteeRepo = new GuaranteeRepository();
    $actionRepo = new GuaranteeActionRepository();
    
    $guaranteeNumber = trim($_GET['number'] ?? '');
    
    if (empty($guaranteeNumber)) {
        echo json_encode([
            'success' => false,
            'error' => 'رقم الضمان مطلوب'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $history = [];
    
    // ========================================================================
    // Get records from NEW tables (guarantees + guarantee_actions)
    // ========================================================================
    
    // 1. Get guarantee records
    $guarantees = $guaranteeRepo->findByNumber($guaranteeNumber);
    foreach ($guarantees as $g) {
        // Find corresponding old record
        $stmt = $db->prepare("
            SELECT id, session_id 
            FROM imported_records 
            WHERE migrated_guarantee_id = ?
            LIMIT 1
        ");
        $stmt->execute([$g['id']]);
        $oldRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $history[] = [
            'id' => 'g_' . $g['id'],
            'record_id' => $oldRecord ? $oldRecord['id'] : null,
            'session_id' => $oldRecord ? $oldRecord['session_id'] : null,
            'source' => 'new',
            'type' => 'import',
            'guarantee_number' => $g['guarantee_number'],
            'contract_number' => $g['contract_number'],
            'amount' => $g['amount'],
            'expiry_date' => $g['expiry_date'],
            'issue_date' => $g['issue_date'],
            'guarantee_type' => $g['type'],
            'supplier_id' => $g['supplier_id'],
            'bank_id' => $g['bank_id'],
            'supplier_display_name' => $g['supplier_display_name'],
            'bank_display' => $g['bank_display'],
            'import_type' => $g['import_type'],
            'match_status' => $g['match_status'],
            'created_at' => $g['created_at'],
            'date' => $g['created_at'],
            'status' => $g['match_status'] === 'ready' ? 'جاهز' : 'يحتاج قرار',
            'record_type' => null,
            'is_first' => false
        ];
    }
    
    // 2. Get action records
    $actions = $actionRepo->findByGuaranteeNumber($guaranteeNumber);
    foreach ($actions as $a) {
        // Find corresponding old record - try migrated link first
        $stmt = $db->prepare("
            SELECT id, session_id 
            FROM imported_records 
            WHERE migrated_action_id = ?
            LIMIT 1
        ");
        $stmt->execute([$a['id']]);
        $oldRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no migrated link, search directly (for newly created actions)
        if (!$oldRecord) {
            $actionType = $a['action_type'] . '_action';
            $stmt = $db->prepare("
                SELECT id, session_id 
                FROM imported_records 
                WHERE guarantee_number = ? 
                  AND record_type = ?
                  AND created_at = ?
                LIMIT 1
            ");
            $stmt->execute([$guaranteeNumber, $actionType, $a['created_at']]);
            $oldRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        $history[] = [
            'id' => 'a_' . $a['id'],
            'record_id' => $oldRecord ? $oldRecord['id'] : null,
            'session_id' => $oldRecord ? $oldRecord['session_id'] : null,
            'source' => 'new',
            'type' => 'action',
            'guarantee_number' => $a['guarantee_number'],
            'contract_number' => null,
            'amount' => $a['new_amount'],
            'expiry_date' => $a['new_expiry_date'],
            'issue_date' => null,
            'guarantee_type' => null,
            'supplier_id' => $a['supplier_id'],
            'bank_id' => $a['bank_id'],
            'supplier_display_name' => $a['supplier_display_name'],
            'bank_display' => $a['bank_display'],
            'import_type' => null,
            'match_status' => $a['action_status'],
            'created_at' => $a['created_at'],
            'date' => $a['created_at'],
            'status' => $a['action_status'] === 'issued' ? 'جاهز' : 'يحتاج قرار',
            'record_type' => $a['action_type'] . '_action',
            'is_first' => false
        ];
    }
    
    // ========================================================================
    // Get records from OLD table (for backwards compatibility)
    // Only get records that haven't been migrated
    // ========================================================================
    $stmt = $db->prepare("
        SELECT 
            r.id,
            r.session_id,
            r.guarantee_number,
            r.contract_number,
            r.amount,
            r.expiry_date,
            r.issue_date,
            r.type,
            r.supplier_id,
            r.bank_id,
            r.supplier_display_name,
            r.bank_display,
            r.record_type,
            r.match_status,
            r.created_at,
            r.migrated_guarantee_id,
            r.migrated_action_id
        FROM imported_records r
        WHERE r.guarantee_number = :number
        ORDER BY r.created_at DESC
    ");
    
    $stmt->execute(['number' => $guaranteeNumber]);
    $oldRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($oldRecords as $r) {
        $history[] = [
            'id' => 'old_' . $r['id'],
            'record_id' => $r['id'], // IMPORTANT: Use actual record ID for links
            'session_id' => $r['session_id'], // IMPORTANT: Include session_id for links
            'source' => 'old',
            'type' => $r['record_type'] ? 'action' : 'import',
            'guarantee_number' => $r['guarantee_number'],
            'contract_number' => $r['contract_number'],
            'amount' => $r['amount'],
            'expiry_date' => $r['expiry_date'],
            'issue_date' => $r['issue_date'],
            'guarantee_type' => $r['type'],
            'supplier_id' => $r['supplier_id'],
            'bank_id' => $r['bank_id'],
            'supplier_display_name' => $r['supplier_display_name'],
            'bank_display' => $r['bank_display'],
            'import_type' => null,
            'match_status' => $r['match_status'],
            'created_at' => $r['created_at'],
            'record_type' => $r['record_type'],
            'date' => $r['created_at'],
            'status' => $r['match_status'] === 'ready' ? 'جاهز' : 'يحتاج قرار',
            'is_first' => false
        ];
    }
    
    // ========================================================================
    // Get supplier and bank names
    // ========================================================================
    foreach ($history as &$record) {
        if ($record['supplier_id']) {
            $stmt = $db->prepare("SELECT official_name FROM suppliers WHERE id = ?");
            $stmt->execute([$record['supplier_id']]);
            $supplierName = $stmt->fetchColumn() ?: null;
            $record['supplier'] = $supplierName; // JS expects 'supplier'
            $record['supplier_name'] = $supplierName; // Keep for compatibility
        } else {
            $record['supplier'] = $record['supplier_display_name'] ?: null;
            $record['supplier_name'] = $record['supplier_display_name'] ?: null;
        }
        
        if ($record['bank_id']) {
            $stmt = $db->prepare("SELECT official_name FROM banks WHERE id = ?");
            $stmt->execute([$record['bank_id']]);
            $bankName = $stmt->fetchColumn() ?: null;
            $record['bank'] = $bankName; // JS expects 'bank'
            $record['bank_name'] = $bankName; // Keep for compatibility
        } else {
            $record['bank'] = $record['bank_display'] ?: null;
            $record['bank_name'] = $record['bank_display'] ?: null;
        }
    }
    
    // ========================================================================
    // Sort by date (newest first)
    // ========================================================================
    usort($history, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
    
    // ========================================================================
    // Return Results
    // ========================================================================
    echo json_encode([
        'success' => true,
        'guarantee_number' => $guaranteeNumber,
        'total_records' => count($history),
        'history' => $history
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'خطأ في الخادم: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
