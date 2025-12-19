<?php
/**
 * API Endpoint: Guarantee History
 * Returns complete history for a guarantee - SIMPLIFIED VERSION
 */

require_once __DIR__ . '/../../app/Support/autoload.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    $db = \App\Support\Database::connect();
    
    $guaranteeNumber = trim($_GET['number'] ?? '');
    
    if (empty($guaranteeNumber)) {
        echo json_encode([
            'success' => false,
            'error' => 'رقم الضمان مطلوب'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // ========================================================================
    // Get ALL records from imported_records table
    // This is the single source of truth - all records are here
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
            r.created_at
        FROM imported_records r
        WHERE r.guarantee_number = :number
        ORDER BY r.created_at DESC
    ");
    
    $stmt->execute(['number' => $guaranteeNumber]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $history = [];
    
    foreach ($records as $r) {
        $history[] = [
            'id' => $r['id'],
            'record_id' => $r['id'], // Always valid
            'session_id' => $r['session_id'], // Always valid
            'source' => 'imported_records',
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
            'match_status' => $r['match_status'],
            'created_at' => $r['created_at'],
            'date' => $r['created_at'],
            'status' => $r['match_status'] === 'ready' ? 'جاهز' : 'يحتاج قرار',
            'record_type' => $r['record_type'],
            'is_first' => false
        ];
    }
    
    // ========================================================================
    // Get supplier and bank names
    // ========================================================================
    foreach ($history as &$record) {
        // Try to get official name from suppliers table
        $supplierName = null;
        if ($record['supplier_id']) {
            $stmt = $db->prepare("SELECT official_name FROM suppliers WHERE id = ?");
            $stmt->execute([$record['supplier_id']]);
            $supplierName = $stmt->fetchColumn() ?: null;
        }
        // Fallback to display_name if official_name not found
        if (!$supplierName) {
            $supplierName = $record['supplier_display_name'] ?: null;
        }
        $record['supplier'] = $supplierName;
        $record['supplier_name'] = $supplierName;
        
        // Try to get official name from banks table
        $bankName = null;
        if ($record['bank_id']) {
            $stmt = $db->prepare("SELECT official_name FROM banks WHERE id = ?");
            $stmt->execute([$record['bank_id']]);
            $bankName = $stmt->fetchColumn() ?: null;
        }
        // Fallback to display_name if official_name not found
        if (!$bankName) {
            $bankName = $record['bank_display'] ?: null;
        }
        $record['bank'] = $bankName;
        $record['bank_name'] = $bankName;
    }
    
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
