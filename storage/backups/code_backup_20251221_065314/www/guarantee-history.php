<?php
/**
 * API Endpoint: Guarantee History
 * Returns complete history for a guarantee from timeline_events table
 * 
 * VERSION 2.1.0 (2025-12-20) - Timeline Events Integration
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
    // UNIFIED QUERY: Timeline Events + Import Records
    // ========================================================================
    // This query combines:
    // 1. Timeline events (extensions, releases, changes)
    // 2. Original import records (for context)
    // All sorted chronologically
    // ========================================================================
    
    $stmt = $db->prepare("
        SELECT 
            te.id,
            te.record_id,
            te.session_id,
            NULL as import_batch_id,
            'timeline' as source,
            0 as source_priority,
            te.event_type,
            te.field_name,
            te.old_value,
            te.new_value,
            te.old_id,
            te.new_id,
            te.supplier_display_name,
            te.bank_display,
            te.change_type,
            te.snapshot_data,
            te.created_at,
            NULL as match_status,
            NULL as amount,
            NULL as expiry_date,
            NULL as supplier_id,
            NULL as bank_id
        FROM guarantee_timeline_events te
        WHERE te.guarantee_number = :number
        ORDER BY te.created_at DESC, te.id DESC
        LIMIT 100
    ");
    
    $stmt->execute(['number' => $guaranteeNumber]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ========================================================================
    // Build Timeline Response
    // ========================================================================
    $history = [];
    
    foreach ($results as $row) {
        if ($row['source'] === 'timeline') {
            // Timeline Event
            $history[] = buildTimelineEvent($row, $db);
        } else {
            // Import Record
            $history[] = buildImportRecord($row, $db);
        }
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

/**
 * Build timeline event response
 */
function buildTimelineEvent(array $row, PDO $db): array
{
    $eventType = $row['event_type'];
    
    // Determine badge and description
    $badge = getEventBadge($eventType);
    $description = getEventDescription($row);
    
    return [
        'id' => $row['id'],
        'record_id' => $row['record_id'],
        'session_id' => $row['session_id'],
        'import_batch_id' => null,
        'source' => $row['event_type'] === 'import' ? 'import' : 'timeline',
        'type' => 'event',
        'event_type' => $eventType,
        'badge' => $badge,
        'description' => $description,
        'field_name' => $row['field_name'],
        'old_value' => $row['old_value'] ?? null,
        'new_value' => $row['new_value'] ?? null,
        'old_id' => $row['old_id'],
        'new_id' => $row['new_id'],
        'supplier_display_name' => $row['supplier_display_name'] ?? null,
        'bank_display' => $row['bank_display'] ?? null,
        'change_type' => $row['change_type'],
        'created_at' => $row['created_at'],
        'date' => $row['created_at'],
        'changes' => buildChanges($row), // For compatibility
        'is_first' => false,
        'snapshot' => isset($row['snapshot_data']) && $row['snapshot_data'] ? json_decode($row['snapshot_data'], true) : null  // Historical letter data
    ];
}

/**
 * Build import record response
 */
function buildImportRecord(array $row, PDO $db): array
{
    // Get supplier name
    $supplierName = null;
    if ($row['supplier_id']) {
        $stmt = $db->prepare("SELECT official_name FROM suppliers WHERE id = ?");
        $stmt->execute([$row['supplier_id']]);
        $supplierName = $stmt->fetchColumn() ?: $row['supplier_display_name'];
    }
    
    // Get bank name
    $bankName = null;
    if ($row['bank_id']) {
        $stmt = $db->prepare("SELECT official_name FROM banks WHERE id = ?");
        $stmt->execute([$row['bank_id']]);
        $bankName = $stmt->fetchColumn() ?: $row['bank_display'];
    }
    
    return [
        'id' => $row['id'],
        'record_id' => $row['record_id'],
        'session_id' => $row['session_id'],
        'import_batch_id' => $row['import_batch_id'],
        'source' => 'import',
        'type' => 'import',
        'event_type' => 'import',
        'badge' => 'استيراد',
        'created_at' => $row['created_at'],
        'date' => $row['created_at'],
        'match_status' => $row['match_status'],
        'status' => $row['match_status'] === 'ready' ? 'جاهز' : 'يحتاج قرار',
        'amount' => $row['amount'],
        'expiry_date' => $row['expiry_date'],
        'supplier_id' => $row['supplier_id'],
        'bank_id' => $row['bank_id'],
        'supplier' => $supplierName,
        'supplier_name' => $supplierName,
        'supplier_display_name' => $row['supplier_display_name'],
        'bank' => $bankName,
        'bank_name' => $bankName,
        'bank_display' => $row['bank_display'],
        'is_first' => false,
        'changes' => []
    ];
}

/**
 * Get event badge label
 */
function getEventBadge(string $eventType): string
{
    $badges = [
        'import' => 'استيراد',
        'extension' => 'تمديد',
        'release' => 'إفراج',
        'supplier_change' => 'تعديل المورد',
        'bank_change' => 'تعديل البنك',
        'amount_change' => 'تعديل المبلغ',
        'expiry_change' => 'تعديل التاريخ',
        'status_change' => 'مطابقة',
        'modification' => 'تعديل'
    ];
    
    return $badges[$eventType] ?? $eventType;
}

/**
 * Get event description
 */
function getEventDescription(array $row): string
{
    $eventType = $row['event_type'];
    
    switch ($eventType) {
        case 'extension':
            return "تمديد من {$row['old_value']} إلى {$row['new_value']}";
            
        case 'release':
            return "تم إصدار خطاب إفراج";
            
        case 'supplier_change':
            return "تغيير المورد من {$row['old_value']} إلى {$row['new_value']}";
            
        case 'bank_change':
            return "تغيير البنك من {$row['old_value']} إلى {$row['new_value']}";
            
        case 'amount_change':
            return "تغيير المبلغ من {$row['old_value']} إلى {$row['new_value']}";
            
        case 'status_change':
            // Show matching transformation in consistent format
            $lines = [];
            $lines[] = "<strong>match_status:</strong> {$row['old_value']} ← {$row['new_value']}";
            
            if (!empty($row['snapshot_data'])) {
                $snapshot = json_decode($row['snapshot_data'], true);
                if ($snapshot && isset($snapshot['transformation'])) {
                    $before = $snapshot['transformation']['before'] ?? [];
                    $after = $snapshot['transformation']['after'] ?? [];
                    
                    // Show supplier transformation
                    if (!empty($before['supplier']) && !empty($after['supplier'])) {
                        $lines[] = "<strong>supplier:</strong> {$before['supplier']} ← {$after['supplier']}";
                    }
                    
                    // Show bank transformation
                    if (!empty($before['bank']) && !empty($after['bank'])) {
                        $lines[] = "<strong>bank:</strong> {$before['bank']} ← {$after['bank']}";
                    }
                }
            }
            
            // Wrap each line in div for proper spacing
            $formatted = array_map(fn($line) => "<div>{$line}</div>", $lines);
            return implode('', $formatted);
            
        default:
            return $row['new_value'] ?? '';
    }
}

/**
 * Build changes array (for compatibility with old frontend)
 */
function buildChanges(array $row): array
{
    if (!$row['field_name'] || !$row['old_value']) {
        return [];
    }
    
    $fieldLabels = [
        'supplier' => 'المورد',
        'bank' => 'البنك',
        'amount' => 'المبلغ',
        'expiry_date' => 'تاريخ الانتهاء'
    ];
    
    return [[
        'field' => $fieldLabels[$row['field_name']] ?? $row['field_name'],
        'from' => $row['old_value'],
        'to' => $row['new_value']
    ]];
}
