<?php
/**
 * API Endpoint: Issue Extension
 * Creates an extension action record using the new architecture
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
    
    // ═══════════════════════════════════════════════════════════════════
    // DAILY SESSION: All actions share ONE session per day
    // ═══════════════════════════════════════════════════════════════════
    // This gets or creates today's session. Multiple actions on the same
    // day will all use the SAME session (not create separate sessions).
    //
    // Example:
    // - 9am: Extension 1 → Session #500
    // - 2pm: Extension 2 → Session #500 (same!)
    // - 4pm: Release 1  → Session #500 (same!)
    //
    // Result: Session #500 contains 3 records
    // ═══════════════════════════════════════════════════════════════════
    $session = $sessionRepo->getOrCreateDailySession('daily_actions');
    $sessionId = $session->id;
    
    // Calculate new expiry date (current + 1 year)
    $newExpiryDate = $record['expiry_date'];
    if ($record['expiry_date']) {
        try {
            $date = new DateTime($record['expiry_date']);
            $date->modify('+1 year');
            $newExpiryDate = $date->format('Y-m-d');
        } catch (Exception $e) {
            // Keep original if date parsing fails
        }
    }
    
    // Prepare action data
    $actionData = [
        'guarantee_number' => $record['guarantee_number'],
        'guarantee_id' => null, // Can be linked later if needed
        'action_type' => 'extension',
        'action_date' => date('Y-m-d'),
        'previous_expiry_date' => $record['expiry_date'],
        'new_expiry_date' => $newExpiryDate, // Calculated: current + 1 year
        'previous_amount' => $record['amount'],
        'new_amount' => $record['amount'],
        'notes' => null,
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
        'message' => 'تم إنشاء سجل التمديد بنجاح',
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
