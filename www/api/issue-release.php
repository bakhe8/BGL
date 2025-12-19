<?php
/**
 * API Endpoint: Issue Release
 * Creates a release action record using the new architecture
 */

require_once __DIR__ . '/../../app/Support/autoload.php';

use App\Support\Database;
use App\Repositories\GuaranteeActionRepository;
use App\Repositories\ImportSessionRepository;
use App\Adapters\GuaranteeDataAdapter;

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
    
    // Initialize repositories
    $actionRepo = new GuaranteeActionRepository();
    $sessionRepo = new ImportSessionRepository();
    $adapter = new GuaranteeDataAdapter();
    
    // Create OLD session (for compatibility)
    $session = $sessionRepo->create('release_action');
    $sessionId = $session->id;
    
    // Prepare action data
    $actionData = [
        'guarantee_number' => $record['guarantee_number'],
        'guarantee_id' => null,
        'action_type' => 'release',
        'action_date' => date('Y-m-d'),
        'previous_expiry_date' => $record['expiry_date'],
        'new_expiry_date' => null, // Released - no new expiry
        'previous_amount' => $record['amount'],
        'new_amount' => null,
        'notes' => 'إفراج عن ضمان',
        'supplier_id' => $record['supplier_id'],
        'bank_id' => $record['bank_id'],
        'supplier_display_name' => $record['supplier_display_name'],
        'bank_display' => $record['bank_display'],
        'action_status' => 'issued',
        'is_locked' => 1, // Lock immediately
        'raw_supplier_name' => $record['raw_supplier_name'],
        'raw_bank_name' => $record['raw_bank_name'],
        'amount' => $record['amount'],
        'issue_date' => $record['issue_date'],
        'type' => $record['type'],
        'contract_number' => $record['contract_number'],
    ];
    
    // Use adapter for dual-write (writes to both old and new tables)
    $ids = $adapter->createAction($actionData, $sessionId, null);
    
    echo json_encode([
        'success' => true,
        'message' => 'تم إصدار خطاب الإفراج بنجاح',
        'record_id' => $ids['old_id'],
        'session_id' => $sessionId,
        'action_id' => $ids['new_id']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'خطأ في الخادم: ' . $e->getMessage()
    ]);
}
