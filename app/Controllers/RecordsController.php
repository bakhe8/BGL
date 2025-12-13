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
use App\Repositories\SupplierLearningRepository;
use App\Support\Settings;

class RecordsController
{
    private $candidates;
    private $conflicts;
    private $records;
    private $banks;
    private $supplierAlts;
    private $normalizer;
    private $learningLog;
    private $bankLearning;
    private $supplierLearning;

    public function __construct(ImportedRecordRepository $records, CandidateService $candidates = null, ConflictDetector $conflicts = null, BankRepository $banks = null, SupplierAlternativeNameRepository $supplierAlts = null, Normalizer $normalizer = null, LearningLogRepository $learningLog = null)
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
        $this->supplierLearning = new SupplierLearningRepository();
    }

    public function index(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $sessionId = isset($_GET['session_id']) ? (int) $_GET['session_id'] : null;
        $data = $sessionId ? $this->records->allBySession($sessionId) : $this->records->all();
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

            // Calculate Max Score (On-the-fly)
            // Note: This matches logic expected by frontend (0-100)
            $cand = $this->candidates->supplierCandidates($arr['rawSupplierName'] ?? '');
            $best = $cand['candidates'][0] ?? null;
            $arr['maxScore'] = $best ? ($best['score'] * 100) : 0;

            return $arr;
        }, $data);
        echo json_encode(array('success' => true, 'data' => $enriched));
    }

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
        if (!in_array($status, array('ready', 'needs_review'), true)) {
            http_response_code(422);
            echo json_encode(array('success' => false, 'message' => 'حالة غير صالحة'));
            return;
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
            // If multiple dots, keep only the first one? Or just trust user input somewhat but cleaned.
            // Simple check: if multiple dots, it might be invalid. Let's strictly allow one dot.
            if (substr_count($cleanAmount, '.') > 1) {
                // simple heuristic: remove all but last dot? or just fail? 
                // Let's just keep it simple as before but ensure not empty.
                $cleanAmount = (float) $cleanAmount;
            }
            $update['amount'] = $cleanAmount === '' ? null : $cleanAmount;
        }

        $this->records->updateDecision($id, $update);

        // Learning: Supplier
        try {
            if (!empty($update['supplier_id']) && !empty($update['raw_supplier_name'])) {
                $norm = $this->normalizer->normalizeSupplierName($update['raw_supplier_name']);

                // Refresh supplier name for display freeze
                try {
                    $supplierName = (new SupplierRepository())->find((int) $update['supplier_id'])?->officialName;
                    if ($supplierName) {
                        $this->records->updateDecision($id, ['supplier_display_name' => $supplierName]);
                    }
                } catch (\Throwable $e) {
                    // Ignore display name fetch error
                }

                // تسجيل التعلم alias
                $this->supplierLearning->upsert($norm, $update['raw_supplier_name'], 'supplier_alias', (int) $update['supplier_id'], 'review');

                // Sync to Dictionary (Visible Alias)
                try {
                    $this->supplierAlts->create(
                        (int) $update['supplier_id'],
                        $update['raw_supplier_name'],
                        $norm,
                        'manual_review'
                    );
                } catch (\Throwable $e) {
                    // Ignore if exists
                }
            } elseif (!empty($payload['supplier_blocked_id']) && !empty($update['raw_supplier_name'])) {
                /**
                 * Blocking Mechanism (آلية الحظر):
                 * CRITICAL UPDATE [User Feedback]:
                 * The logic must be: Block a specific CANDIDATE (supplier_blocked_id) from being suggested 
                 * for this specific Raw Name. It should NOT block the Raw Name itself globally.
                 * 
                 * Current Impl: Accepts supplier_blocked_id.
                 * Requirement: Ensure MatcherService respects this 'blocked' relationship.
                 *
                 * When user REJECTS a suggested supplier and chooses a different one:
                 * - Save 'supplier_blocked' in supplier_learning table with the ID of the BAD suggestion.
                 * - Future imports will skip this supplier_id for this normalized name.
                 */
                $norm = $this->normalizer->normalizeSupplierName($update['raw_supplier_name']);
                $blockedId = (int) $payload['supplier_blocked_id'];
                $this->supplierLearning->upsert($norm, $update['raw_supplier_name'], 'supplier_blocked', $blockedId, 'review');
            }
        } catch (\Throwable $e) {
            // Log supplier learning errors to storage/logs/app.log
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

        $updated = $this->records->find($id);

        echo json_encode(array('success' => true, 'data' => $updated));
    }

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
}
