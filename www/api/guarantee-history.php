<?php
/**
 * API Endpoint: Guarantee History
 * Returns all records for a specific guarantee number across all sessions
 */

require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    $guaranteeNumber = trim($_GET['number'] ?? '');
    
    if (empty($guaranteeNumber)) {
        echo json_encode([
            'success' => false,
            'error' => 'رقم الضمان مطلوب'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Get all records with this guarantee number across ALL sessions
    $stmt = $db->prepare("
        SELECT 
            r.id,
            r.session_id,
            r.guarantee_number,
            r.contract_number,
            r.amount,
            r.expiry_date,
            r.type,
            r.raw_supplier_name,
            r.raw_bank_name,
            r.supplier_id,
            r.bank_id,
            r.supplier_display_name,
            r.bank_display,
            r.created_at,
            r.updated_at,
            s.official_name as supplier_name,
            s.short_name as supplier_short_name,
            b.official_name as bank_name,
            b.short_name as bank_short_name
        FROM records r
        LEFT JOIN suppliers s ON r.supplier_id = s.id
        LEFT JOIN banks b ON r.bank_id = b.id
        WHERE r.guarantee_number = :number
        ORDER BY r.session_id DESC, r.updated_at DESC
    ");
    
    $stmt->execute(['number' => $guaranteeNumber]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($records)) {
        echo json_encode([
            'success' => false,
            'error' => 'لم يتم العثور على سجلات لرقم الضمان: ' . $guaranteeNumber
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Process records to track changes
    $history = [];
    $previousRecord = null;
    
    foreach ($records as $record) {
        $changes = [];
        
        if ($previousRecord) {
            // Track supplier changes
            if ($record['supplier_id'] != $previousRecord['supplier_id']) {
                $changes[] = [
                    'field' => 'المورد',
                    'from' => $previousRecord['supplier_name'] ?? $previousRecord['raw_supplier_name'],
                    'to' => $record['supplier_name'] ?? $record['raw_supplier_name']
                ];
            }
            
            // Track bank changes
            if ($record['bank_id'] != $previousRecord['bank_id']) {
                $changes[] = [
                    'field' => 'البنك',
                    'from' => $previousRecord['bank_name'] ?? $previousRecord['raw_bank_name'],
                    'to' => $record['bank_name'] ?? $record['raw_bank_name']
                ];
            }
            
            // Track amount changes
            if ($record['amount'] != $previousRecord['amount']) {
                $changes[] = [
                    'field' => 'المبلغ',
                    'from' => number_format((float)$previousRecord['amount'], 2),
                    'to' => number_format((float)$record['amount'], 2)
                ];
            }
            
            // Track expiry date changes
            if ($record['expiry_date'] != $previousRecord['expiry_date']) {
                $changes[] = [
                    'field' => 'تاريخ الانتهاء',
                    'from' => $previousRecord['expiry_date'],
                    'to' => $record['expiry_date']
                ];
            }
        }
        
        // Determine status
        $hasSupplier = !empty($record['supplier_id']);
        $hasBank = !empty($record['bank_id']);
        $status = ($hasSupplier && $hasBank) ? 'جاهز' : 'معلق';
        
        $history[] = [
            'record_id' => $record['id'],
            'session_id' => $record['session_id'],
            'date' => $record['updated_at'] ?? $record['created_at'],
            'supplier' => $record['supplier_name'] ?? $record['raw_supplier_name'],
            'supplier_id' => $record['supplier_id'],
            'bank' => $record['bank_name'] ?? $record['raw_bank_name'],
            'bank_id' => $record['bank_id'],
            'amount' => $record['amount'],
            'expiry_date' => $record['expiry_date'],
            'type' => $record['type'],
            'status' => $status,
            'changes' => $changes,
            'is_first' => $previousRecord === null
        ];
        
        $previousRecord = $record;
    }
    
    echo json_encode([
        'success' => true,
        'guarantee_number' => $guaranteeNumber,
        'total_records' => count($records),
        'history' => $history
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'خطأ في الخادم: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
