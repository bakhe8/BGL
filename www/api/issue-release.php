<?php
/**
 * API Endpoint: Issue Release
 * Creates a release action record without opening print window
 */

require_once __DIR__ . '/../../app/Support/autoload.php';

use App\Support\Database;

header('Content-Type: application/json; charset=utf-8');

try {
    $guaranteeNo = $_POST['guarantee_no'] ?? null;
    
    if (!$guaranteeNo) {
        echo json_encode(['success' => false, 'error' => 'رقم الضمان مطلوب']);
        exit;
    }
    
    $db = Database::connect();
    
    // Fetch latest record for this guarantee
    $stmt = $db->prepare("
        SELECT * FROM imported_records 
        WHERE guarantee_number = :g_no 
        ORDER BY session_id DESC, id DESC 
        LIMIT 1
    ");
    $stmt->execute([':g_no' => $guaranteeNo]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        echo json_encode(['success' => false, 'error' => 'لم يتم العثور على سجل']);
        exit;
    }
    
    // Create release session
    $releaseSession = $db->prepare("INSERT INTO import_sessions (session_type, record_count) VALUES (:type, 1)");
    $releaseSession->execute([':type' => 'release_action']);
    $newSessionId = (int)$db->lastInsertId();
    
    // Insert release action record
    $logStmt = $db->prepare("
        INSERT INTO imported_records (
            session_id, guarantee_number,
            raw_supplier_name, raw_bank_name,
            amount, expiry_date, issue_date, type, contract_number,
            supplier_id, bank_id,
            supplier_display_name, bank_display,
            match_status, record_type,
            created_at
        ) VALUES (
            :session_id, :guarantee_number,
            :raw_supplier, :raw_bank,
            :amount, :expiry, :issue, :type, :contract,
            :supplier_id, :bank_id,
            :supplier_display, :bank_display,
            :match_status, 'release_action',
            :created_at
        )
    ");
    
    $logStmt->execute([
        ':session_id' => $newSessionId,
        ':guarantee_number' => $record['guarantee_number'],
        ':raw_supplier' => $record['raw_supplier_name'],
        ':raw_bank' => $record['raw_bank_name'],
        ':amount' => $record['amount'],
        ':expiry' => $record['expiry_date'],
        ':issue' => $record['issue_date'],
        ':type' => $record['type'],
        ':contract' => $record['contract_number'],
        ':supplier_id' => $record['supplier_id'],
        ':bank_id' => $record['bank_id'],
        ':supplier_display' => $record['supplier_display_name'],
        ':bank_display' => $record['bank_display'],
        ':match_status' => $record['match_status'],
        ':created_at' => date('Y-m-d H:i:s')
    ]);
    
    $newRecordId = (int)$db->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'تم إصدار خطاب الإفراج بنجاح',
        'record_id' => $newRecordId,
        'session_id' => $newSessionId
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'خطأ في الخادم: ' . $e->getMessage()
    ]);
}
