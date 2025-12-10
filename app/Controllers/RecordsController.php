<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ImportedRecordRepository;
use App\Services\CandidateService;
use App\Services\ConflictDetector;
use App\Support\Normalizer;

class RecordsController
{
    private $candidates;
    private $conflicts;
    private $records;

    public function __construct(ImportedRecordRepository $records, CandidateService $candidates = null, ConflictDetector $conflicts = null)
    {
        $this->records = $records;
        $this->candidates = $candidates ?: new CandidateService(
            new \App\Repositories\SupplierRepository(),
            new \App\Repositories\SupplierAlternativeNameRepository(),
            new Normalizer(),
            new \App\Repositories\BankRepository(),
            new \App\Repositories\BankAlternativeNameRepository(),
            new \App\Repositories\SupplierOverrideRepository(),
            new \App\Support\Settings()
        );
        $this->conflicts = $conflicts ?: new ConflictDetector();
    }

    public function index(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $data = $this->records->all();
        echo json_encode(array('success' => true, 'data' => $data));
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

        // مدخلات اختيارية للتحديث اليدوي
        $fields = array(
            'raw_supplier_name' => 255,
            'raw_bank_name' => 255,
            'guarantee_number' => 255,
        );

        foreach ($fields as $field => $max) {
            if (isset($payload[$field])) {
                $val = trim((string)$payload[$field]);
                if (strlen($val) > $max) {
                    http_response_code(422);
                    echo json_encode(array('success' => false, 'message' => "{$field} يتجاوز الحد الأقصى."));
                    return;
                }
                $update[$field] = $val;
            }
        }

        if (isset($payload['amount'])) {
            $cleanAmount = preg_replace('/[^\d\.\-]/', '', (string)$payload['amount']);
            $update['amount'] = $cleanAmount === '' ? null : $cleanAmount;
        }

        foreach (array('issue_date', 'expiry_date') as $dateField) {
            if (isset($payload[$dateField])) {
                $val = trim((string)$payload[$dateField]);
                if ($val !== '' && strtotime($val) === false) {
                    http_response_code(422);
                    echo json_encode(array('success' => false, 'message' => "{$dateField} ليس تاريخاً صالحاً"));
                    return;
                }
                $update[$dateField] = $val === '' ? null : date('Y-m-d', strtotime($val));
            }
        }

        $this->records->updateDecision($id, $update);
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

        $supplierCandidates = $this->candidates->supplierCandidates($record->rawSupplierName);
        $bankCandidates = $this->candidates->bankCandidates($record->rawBankName);

        $conflicts = $this->conflicts->detect(
            array('supplier' => $supplierCandidates, 'bank' => $bankCandidates),
            array(
                'raw_supplier_name' => $record->rawSupplierName,
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
