<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ImportedRecordRepository;
use App\Services\CandidateService;
use App\Services\ConflictDetector;
use App\Support\Normalizer;
use App\Repositories\BankRepository;
use App\Repositories\SupplierAlternativeNameRepository;
use App\Repositories\LearningLogRepository;
use App\Repositories\BankLearningRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\SupplierOverrideRepository;
use App\Repositories\SupplierSuggestionRepository;
use App\Repositories\UserDecisionRepository;
use App\Support\Settings;
use App\Services\MatchingService;
use App\Services\AutoAcceptService;

/**
 * Decision Controller
 * 
 * يتعامل مع عمليات اتخاذ القرار للسجلات المستوردة
 * 
 * @package App\Controllers
 */
class DecisionController
{
    private $candidates;
    private $conflicts;
    private $records;
    private $banks;
    private $supplierAlts;
    private $normalizer;
    private $learningLog;
    private $bankLearning;
    private $matchingService;

    /**
     * Initialize Decision Controller with required dependencies
     * 
     * @param ImportedRecordRepository $records Main repository for imported records
     * @param CandidateService|null $candidates Service for finding supplier/bank matches (auto-created if null)
     * @param ConflictDetector|null $conflicts Service for detecting name conflicts (auto-created if null)
     * @param BankRepository|null $banks Repository for bank dictionary (auto-created if null)
     * @param SupplierAlternativeNameRepository|null $supplierAlts Repository for supplier aliases (auto-created if null)
     * @param Normalizer|null $normalizer Text normalization utility (auto-created if null)
     * @param LearningLogRepository|null $learningLog Repository for learning system logs (auto-created if null)
     */
    public function __construct(ImportedRecordRepository $records, ?CandidateService $candidates = null, ?ConflictDetector $conflicts = null, ?BankRepository $banks = null, ?SupplierAlternativeNameRepository $supplierAlts = null, ?Normalizer $normalizer = null, ?LearningLogRepository $learningLog = null)
    {
        $this->records = $records;
        $this->candidates = $candidates ?: new CandidateService(
            new SupplierRepository(),
            new SupplierAlternativeNameRepository(),
            new Normalizer(),
            new BankRepository(),
            new SupplierOverrideRepository(),
            new Settings()
        );
        $this->conflicts = $conflicts ?: new ConflictDetector();
        $this->banks = $banks ?: new BankRepository();
        $this->supplierAlts = $supplierAlts ?: new SupplierAlternativeNameRepository();
        $this->normalizer = $normalizer ?: new Normalizer();
        $this->learningLog = $learningLog ?: new LearningLogRepository();
        $this->bankLearning = new BankLearningRepository();
        $this->matchingService = new MatchingService(
            new SupplierRepository(),
            new SupplierAlternativeNameRepository(),
            new BankRepository()
        );
    }

    /**
     * List all imported records with their current decision status
     * 
     * Returns JSON array of all records with:
     * - Basic info (ID, amount, dates, guarantee number)
     * - Selected supplier and bank (if decided)
     * - Status (pending/ready/approved)
     * - Learning flags
     * 
     * @return void Outputs JSON response
     */
    public function index(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $sessionId = isset($_GET['session_id']) ? (int) $_GET['session_id'] : null;
        
        // Default to latest session if none specified (Session Scope vs Global Scope)
        if (!$sessionId) {
            $sessions = $this->records->getAvailableSessions();
            $sessionId = !empty($sessions) ? (int)$sessions[0]['session_id'] : null;
        }

        $data = $sessionId ? $this->records->allBySession($sessionId) : [];
        // Note: If no sessions exist, return empty array.
        $bankMap = [];
        $bankNormMap = [];
        $supplierMap = [];
        foreach ((new SupplierRepository())->allNormalized() as $s) {
            $supplierMap[$s['id']] = [
                'official_name' => $s['official_name'] ?? null,
                'normalized_name' => $s['normalized_name'] ?? null,
            ];
        }
        foreach ($this->banks->allNormalized() as $b) {
            $bankMap[$b['id']] = [
                'official_name_ar' => $b['official_name'] ?? null,
                'official_name_en' => $b['official_name_en'] ?? null,
                'normalized_key' => $b['normalized_key'] ?? null,
            ];
            if (!empty($b['normalized_key'])) {
                $disp = $b['official_name'] ?? null;
                if ($disp && !isset($bankNormMap[$b['normalized_key']])) {
                    $bankNormMap[$b['normalized_key']] = $disp;
                }
            }
        }
        $enriched = array_map(function ($r) use ($bankMap, $bankNormMap, $supplierMap) {
            $arr = get_object_vars($r);
            $b = isset($arr['bankId'], $bankMap[$arr['bankId']]) ? $bankMap[$arr['bankId']] : null;
            // إذا كان السجل يحمل bankDisplay مجمد من وقت الاستيراد نعرضه أولاً
            $arr['bankDisplay'] = $arr['bankDisplay'] ?? null;
            if (!$arr['bankDisplay']) {
                $arr['bankDisplay'] = $b['official_name_ar'] ?? null;
            }
            if (!$arr['bankDisplay']) {
                $nk = $arr['normalizedBank'] ?? '';
                if ($nk && isset($bankNormMap[$nk])) {
                    $arr['bankDisplay'] = $bankNormMap[$nk];
                }
            }
            // supplier display freeze
            $arr['supplierDisplay'] = $arr['supplierDisplayName'] ?? null;
            if (!$arr['supplierDisplay'] && isset($arr['supplierId'], $supplierMap[$arr['supplierId']])) {
                $arr['supplierDisplay'] = $supplierMap[$arr['supplierId']]['official_name'];
            }
            if (!$arr['supplierDisplay']) {
                $arr['supplierDisplay'] = $arr['rawSupplierName'] ?? null;
            }

            return $arr;
        }, $data);
        echo json_encode(array('success' => true, 'data' => $enriched));
    }

    /**
     * API: List available sessions
     */
    public function listSessions(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $sessions = $this->records->getAvailableSessions();
            echo json_encode(['success' => true, 'data' => $sessions]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * حفظ قرار المورد/البنك لسجل معين
     * 
     * هذه الدالة تقوم بـ:
     * 1. التحقق من صلاحية البيانات المُرسلة
     * 2. تحديث السجل بالمورد والبنك المختارين
     * 3. تسجيل التعلم (alias) للاستخدام المستقبلي
     * 4. نشر القرار فوراً على السجلات المتشابهة في نفس الجلسة
     * 
     * @param int $id معرّف السجل
     * @param array $payload البيانات المُرسلة:
     *   - match_status: 'ready' أو 'needs_review' (مطلوب)
     *   - supplier_id: معرّف المورد المختار
     *   - bank_id: معرّف البنك المختار
     *   - raw_supplier_name: اسم المورد الخام (للتعلم)
     *   - raw_bank_name: اسم البنك الخام (للتعلم)
     * 
     * @return void يُرجع JSON يحتوي:
     *   - success: true/false
     *   - data: بيانات السجل المُحدّث
     *   - propagated_count: عدد السجلات الأخرى التي تم تحديثها
     * 
     * @see docs/06-Decision-Page.md للتوثيق الكامل
     */
    public function saveDecision(int $id, array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $record = $this->records->find($id);
        if (!$record) {
            http_response_code(404);
            echo json_encode(array('success' => false, 'message' => 'السجل غير موجود'));
            return;
        }

        $status = $payload['match_status'] ?? null;
        if (!in_array($status, array('ready', 'needs_review', 'approved'), true)) {
            http_response_code(422);
            echo json_encode(array('success' => false, 'message' => 'حالة غير صالحة'));
            return;
        }
        // Normalize 'approved' to 'ready' for consistency
        if ($status === 'approved') {
            $status = 'ready';
        }

        $update = array(
            'match_status' => $status,
        );

        // قرارات المورد/البنك المختارة (IDs)
        $hasSupplierDecision = isset($payload['supplier_id']) && $payload['supplier_id'];
        $hasSupplierBlocked = isset($payload['supplier_blocked_id']) && $payload['supplier_blocked_id'];
        if (!$hasSupplierDecision && !$hasSupplierBlocked) {
            http_response_code(422);
            echo json_encode(array('success' => false, 'message' => 'يجب اختيار مورد أو رفض مرشح قبل الحفظ'));
            return;
        }
        if (isset($payload['supplier_id'])) {
            $update['supplier_id'] = $payload['supplier_id'] ?: null;
        }
        if (isset($payload['bank_id'])) {
            $update['bank_id'] = $payload['bank_id'] ?: null;
        }

        // إذا توفر كلا المعرّفين نحول الحالة إلى ready تلقائياً
        if (!empty($update['supplier_id']) && !empty($update['bank_id'])) {
            $update['match_status'] = 'ready';
        }

        // مدخلات اختيارية للتحديث اليدوي (مقروءة فقط غالباً)
        $fields = array(
            'raw_supplier_name' => 255,
            'raw_bank_name' => 255,
            'guarantee_number' => 255,
        );

        foreach ($fields as $field => $max) {
            if (isset($payload[$field])) {
                $val = trim((string) $payload[$field]);
                if (strlen($val) > $max) {
                    http_response_code(422);
                    echo json_encode(array('success' => false, 'message' => "{$field} يتجاوز الحد الأقصى."));
                    return;
                }
                $update[$field] = $val;
            }
        }

        if (isset($payload['amount'])) {
            // Fix: handle multiple dots or invalid format safely
            $cleanAmount = preg_replace('/[^\d\.\-]/', '', (string) $payload['amount']);
            // If multiple dots, keep only the last one (decimal separator)
            if (substr_count($cleanAmount, '.') > 1) {
                // Remove all dots except the last one
                $lastDotPos = strrpos($cleanAmount, '.');
                $beforeLastDot = str_replace('.', '', substr($cleanAmount, 0, $lastDotPos));
                $cleanAmount = $beforeLastDot . substr($cleanAmount, $lastDotPos);
            }
            // Ensure clean amount is a valid number
            if ($cleanAmount === '' || $cleanAmount === '-' || $cleanAmount === '.') {
                $update['amount'] = null;
            } else {
                $update['amount'] = is_numeric($cleanAmount) ? $cleanAmount : null;
            }
        }

        // ═══════════════════════════════════════════════════════════════════
        // TIMELINE EVENT LOGGING - MUST RUN BEFORE UPDATE!
        // ═══════════════════════════════════════════════════════════════════
        // Critical: Capture snapshot BEFORE updating to get OLD values
        // ═══════════════════════════════════════════════════════════════════
        try {
            $timelineService = new \App\Services\TimelineEventService();
            
            // Log supplier change
            if (isset($update['supplier_id']) && $update['supplier_id'] != $record->supplierId) {
                // Get NEW supplier name from database
                $newSupplierName = 'Unknown';
                try {
                    $supplierRepo = new SupplierRepository();
                    $newSupplier = $supplierRepo->find((int) $update['supplier_id']);
                    if ($newSupplier) {
                        $newSupplierName = $newSupplier->officialName ?? 'Unknown';
                    }
                } catch (\Throwable $e) {
                    // Fallback to display name from update
                    $newSupplierName = $update['supplier_display_name'] ?? 'Unknown';
                }
                
                $timelineService->logSupplierChange(
                    $record->guaranteeNumber,
                    $id,
                    $record->supplierId,
                    (int) $update['supplier_id'],
                    $record->supplierDisplayName ?? $record->rawSupplierName,
                    $newSupplierName,
                    $record->sessionId
                );
            }

            // Log bank change
            if (isset($update['bank_id']) && $update['bank_id'] != $record->bankId) {
                // Get NEW bank name from database
                $newBankName = 'Unknown';
                try {
                    $bankRepo = new BankRepository();
                    $newBank = $bankRepo->find((int) $update['bank_id']);
                    if ($newBank) {
                        $newBankName = $newBank->officialName ?? 'Unknown';
                    }
                } catch (\Throwable $e) {
                    $newBankName = $update['bank_display'] ?? 'Unknown';
                }
                
                $timelineService->logBankChange(
                    $record->guaranteeNumber,
                    $id,
                    $record->bankId,
                    (int) $update['bank_id'],
                    $record->bankDisplay ?? $record->rawBankName,
                    $newBankName,
                    $record->sessionId
                );
            }

            // Log amount change
            if (isset($update['amount']) && $update['amount'] != $record->amount) {
                $timelineService->logAmountChange(
                    $record->guaranteeNumber,
                    $id,
                    $record->amount,
                    $update['amount'],
                    $record->sessionId
                );
            }
            
            // Log status change (pending → ready)
            // This happens when user makes a matching decision
            if (isset($update['match_status']) && $update['match_status'] != $record->matchStatus) {
                $timelineService->logStatusChange(
                    $record->guaranteeNumber,
                    $id,
                    $record->matchStatus ?? 'pending',
                    $update['match_status'],
                    $record->sessionId
                );
            }
        } catch (\Throwable $e) {
            // Silent fail - timeline logging should never break saveDecision
            error_log("Timeline event logging failed: " . $e->getMessage());
        }

        // NOW update record with new values
        $this->records->updateDecision($id, $update);

        // ═══════════════════════════════════════════════════════════════════
        // SUPPLIER LEARNING & DISPLAY NAME (Cleanup 2025-12-17)
        // ═══════════════════════════════════════════════════════════════════
        // BUG FIX: Use $record->rawSupplierName instead of $update['raw_supplier_name']
        // because the frontend rarely sends raw_supplier_name in the payload
        try {
            $rawSupplierName = $record->rawSupplierName ?? $update['raw_supplier_name'] ?? null;
            if (!empty($update['supplier_id']) && !empty($rawSupplierName)) {
                $norm = $this->normalizer->normalizeSupplierName($rawSupplierName);

                // 1. Refresh supplier name for display freeze
                try {
                    $supplierName = (new SupplierRepository())->find((int) $update['supplier_id'])?->officialName;
                    if ($supplierName) {
                        $this->records->updateDecision($id, ['supplier_display_name' => $supplierName]);
                    }
                } catch (\Throwable $e) {
                    \App\Support\Logger::warning('Failed to fetch supplier display name', ['error' => $e->getMessage(), 'supplier_id' => $update['supplier_id']]);
                }

                // 2. Sync to Dictionary (Visible Alias)
                // This creates an official alternative name in supplier_alternative_names table
                try {
                    $this->supplierAlts->create(
                        (int) $update['supplier_id'],
                        $rawSupplierName,
                        $norm,
                        'manual_review'
                    );
                } catch (\Throwable $e) {
                    // Expected: duplicate entry - no need to log
                }
                
                // NOTE (Cleanup): Removed OLD learning write to supplier_aliases_learning
                // Alias learning is now handled by supplier_suggestions cache (Phase 4)
                
            } elseif (!empty($payload['supplier_blocked_id']) && !empty($rawSupplierName)) {
                // ═══════════════════════════════════════════════════════════════════
                // GRADUAL BLOCKING (Migrated 2025-12-17)
                // ═══════════════════════════════════════════════════════════════════
                // Instead of immediate hide, we increment block_count in supplier_suggestions.
                // Each block adds -50 penalty. Supplier only disappears when score goes negative.
                // This allows recovery if user selects it again (usage_count adds +15 per use).
                // ═══════════════════════════════════════════════════════════════════
                $norm = $this->normalizer->normalizeSupplierName($rawSupplierName);
                $blockedId = (int) $payload['supplier_blocked_id'];
                
                // Use NEW gradual blocking system
                $suggestionRepo = new SupplierSuggestionRepository();
                $suggestionRepo->incrementBlock($norm, $blockedId);
                
                \App\Support\Logger::info('Supplier blocked (gradual)', [
                    'normalized_input' => $norm,
                    'blocked_supplier_id' => $blockedId,
                ]);
            }
        } catch (\Throwable $e) {
            \App\Support\Logger::error('Supplier Learning Error', [
                'message' => $e->getMessage(),
                'record_id' => $id,
            ]);
        }

        // Learning: Bank
        try {
            if (!empty($update['bank_id']) && !empty($update['raw_bank_name'])) {
                $normBank = $this->normalizer->normalizeBankName($update['raw_bank_name']);
                if ($normBank !== '') {
                    $this->bankLearning->upsert($normBank, $update['raw_bank_name'], 'alias', (int) $update['bank_id']);
                }
            } elseif (empty($update['bank_id']) && !empty($update['raw_bank_name'])) {
                // المستخدم رفض التطابق، نسجل blocked عام لهذا الاسم (بدون بنك محدد)
                $normBank = $this->normalizer->normalizeBankName($update['raw_bank_name']);
                if ($normBank !== '') {
                    $this->bankLearning->upsert($normBank, $update['raw_bank_name'], 'blocked', null);
                }
            }
        } catch (\Throwable $e) {
            // Log bank learning errors to storage/logs/app.log
            \App\Support\Logger::error('Bank Learning Error', [
                'message' => $e->getMessage(),
                'record_id' => $id,
            ]);
        }

        // ═══════════════════════════════════════════════════════════════════
        // NEW: Log decision and update cache (Phase 4 Refactoring)
        // ═══════════════════════════════════════════════════════════════════
        try {
            if (!empty($update['supplier_id']) && !empty($record->rawSupplierName)) {
                $decisionRepo = new UserDecisionRepository();
                $suggestionRepo = new SupplierSuggestionRepository();
                $norm = $this->normalizer->normalizeSupplierName($record->rawSupplierName);
                
                // Get supplier display name
                $supplierDisplayName = null;
                try {
                    $supplierDisplayName = (new SupplierRepository())->find((int) $update['supplier_id'])?->officialName;
                } catch (\Throwable $e) { /* ignore */ }
                
                // Log the decision
                $decisionRepo->logDecision(
                    $id,
                    $record->sessionId,
                    $record->rawSupplierName,
                    $norm,
                    (int) $update['supplier_id'],
                    $supplierDisplayName ?? '',
                    UserDecisionRepository::SOURCE_USER_CLICK
                );
                
                // Update suggestion cache (increment usage)
                $suggestionRepo->incrementUsage($norm, (int) $update['supplier_id']);
                
                \App\Support\Logger::info('Decision logged', [
                    'record_id' => $id,
                    'supplier_id' => $update['supplier_id'],
                    'source' => 'user_click',
                ]);
            }
        } catch (\Throwable $e) {
            \App\Support\Logger::error('Decision logging failed', [
                'message' => $e->getMessage(),
                'record_id' => $id,
            ]);
        }

        // نشر القرار فوراً على السجلات الأخرى بنفس اسم المورد في نفس الجلسة
        $propagatedCount = 0;
        try {
            if (!empty($update['supplier_id']) && !empty($record->rawSupplierName)) {
                $supplierDisplayName = null;
                try {
                    $supplierDisplayName = (new SupplierRepository())->find((int) $update['supplier_id'])?->officialName;
                } catch (\Throwable $e) {
                    \App\Support\Logger::warning('Failed to fetch supplier name for propagation', ['error' => $e->getMessage()]);
                }

                $propagatedIds = $this->records->bulkUpdateSupplierByRawName(
                    $record->sessionId,
                    $record->rawSupplierName,
                    $id,
                    (int) $update['supplier_id'],
                    $supplierDisplayName
                );
                
                $propagatedCount = count($propagatedIds);

                if ($propagatedCount > 0) {
                    // NEW: Log propagated decisions (Phase 4)
                    try {
                        $norm = $this->normalizer->normalizeSupplierName($record->rawSupplierName);
                        $decisionRepo->logPropagation(
                            $propagatedIds,
                            $record->sessionId,
                            $record->rawSupplierName,
                            $norm,
                            (int) $update['supplier_id'],
                            $supplierDisplayName ?? ''
                        );
                    } catch (\Throwable $e) { /* ignore propagation logging errors */ }
                    
                    \App\Support\Logger::info('Decision propagated', [
                        'record_id' => $id,
                        'supplier_id' => $update['supplier_id'],
                        'propagated_count' => $propagatedCount,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            \App\Support\Logger::error('Propagation failed', [
                'message' => $e->getMessage(),
                'record_id' => $id,
            ]);
        }

        $updated = $this->records->find($id);

        // ═══════════════════════════════════════════════════════════════════
        // Timeline logging is now handled in updateDecision() above
        // Old logModificationIfNeeded() removed as deprecated
        // ═══════════════════════════════════════════════════════════════════



        echo json_encode(array(
            'success' => true,
            'data' => $updated,
            'propagated_count' => $propagatedCount,
        ));
    }

    /**
     * Get candidate suggestions for a specific record
     * 
     * Uses fuzzy matching to find best supplier and bank matches
     * based on the raw imported names.
     * 
     * @param int $id Record ID to get candidates for
     * @return void Outputs JSON response with supplier and bank candidates array
     */
    public function candidates(int $id): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $record = $this->records->find($id);
        if (!$record) {
            http_response_code(404);
            echo json_encode(array('success' => false, 'message' => 'السجل غير موجود'));
            return;
        }

        $rawSupplier = isset($_GET['raw_supplier_name']) ? (string) $_GET['raw_supplier_name'] : $record->rawSupplierName;
        $supplierCandidates = $this->candidates->supplierCandidates($rawSupplier);
        $bankCandidates = $this->candidates->bankCandidates($record->rawBankName);

        $conflicts = $this->conflicts->detect(
            array('supplier' => $supplierCandidates, 'bank' => $bankCandidates),
            array(
                'raw_supplier_name' => $rawSupplier,
                'raw_bank_name' => $record->rawBankName,
            )
        );

        echo json_encode(array(
            'success' => true,
            'data' => array(
                'supplier' => $supplierCandidates,
                'bank' => $bankCandidates,
                'conflicts' => $conflicts,
            ),
        ));
    }

    /**
     * Recalculate matches for all non-approved records in the LATEST SESSION
     */
    public function recalculate(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // 1. Find latest session
        // Only fetching IDs to find max might be slow if many records, 
        // but typically efficient enough. Or fetch one record order by session desc.
        // Let's grab all records (cached in memory mostly or query optimized)
        // Better: Query DB for latest session ID. 
        // For now, reuse repository logic: fetch all, find max session.
        $all = $this->records->all();
        if (empty($all)) {
            echo json_encode(['success' => true, 'data' => ['processed' => 0]]);
            return;
        }

        $maxSession = 0;
        foreach ($all as $r) {
            if ($r->sessionId > $maxSession) {
                $maxSession = $r->sessionId;
            }
        }

        // 2. Filter target records covering the session
        $targets = array_filter($all, function($r) use ($maxSession) {
            // استخدام == بدلاً من === لتجنب مشاكل نوع البيانات (int vs string)
            return $r->sessionId == $maxSession && $r->matchStatus !== 'approved';
        });

        $processed = 0;
        $updated = 0;

        foreach ($targets as $record) {
            $processed++;
            
            // Re-run matching
            // Supplier
            $suppMatch = $this->matchingService->matchSupplier($record->rawSupplierName);
            
            // Bank
            $bankMatch = $this->matchingService->matchBank($record->rawBankName);

            // Determine status
            $newStatus = $suppMatch['match_status'];
            
            // Constraint: Needs both IDs to be ready
            if (empty($suppMatch['supplier_id']) || empty($bankMatch['bank_id'])) {
                $newStatus = 'needs_review';
            }

            // Only update if something changed
            // We check if we got a BETTER decision or different one.
            // If current status is ready and we find same supplier -> no change
            // If current is needs_review and we find ready -> update!
            // If current is ready (old supplier) and we find different supplier -> update!
            
            // NOTE: If user manually set a supplier, this MIGHT overwrite it if it wasn't 'approved'.
            // But user asked for this: "updates based on dictionary".
            // If manual override was done, it should have been saved as alias if it was a real fix.
            // If it was valid 'ready' record, it stays ready.
            
            $payload = [
                'match_status' => $newStatus,
                'supplier_id' => $suppMatch['supplier_id'] ?? null,
                'bank_id' => $bankMatch['bank_id'] ?? null,
                'normalized_supplier' => $suppMatch['normalized'] ?? null,
                'normalized_bank' => $bankMatch['normalized'] ?? null,
            ];
            
            // Only execute update if IDs changed or status changed to ready
            // Simple approach: Always update to remain consistent with "Recalculate" meaning.
            $this->records->updateDecision($record->id, $payload);
            $updated++;
        }

        echo json_encode([
            'success' => true, 
            'data' => [
                'processed' => $processed, 
                'session_id' => $maxSession
            ]
        ]);
    }
}
