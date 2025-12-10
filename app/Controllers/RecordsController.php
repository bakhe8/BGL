<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ImportedRecordRepository;
use App\Services\CandidateService;

class RecordsController
{
    public function __construct(
        private ImportedRecordRepository $records,
        private CandidateService $candidates = new CandidateService(
            new \App\Repositories\SupplierRepository(),
            new \App\Repositories\SupplierAlternativeNameRepository(),
        ),
    )
    {
    }

    public function index(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $data = $this->records->all();
        echo json_encode(['success' => true, 'data' => $data]);
    }

    public function saveDecision(int $id, array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $record = $this->records->find($id);
        if (!$record) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'السجل غير موجود']);
            return;
        }

        $status = $payload['match_status'] ?? null;
        if (!in_array($status, ['ready', 'needs_review'], true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'حالة غير صالحة']);
            return;
        }

        $update = [
            'match_status' => $status,
        ];

        // مدخلات اختيارية للتحديث اليدوي
        $fields = [
            'raw_supplier_name' => 255,
            'raw_bank_name' => 255,
            'guarantee_number' => 255,
        ];

        foreach ($fields as $field => $max) {
            if (isset($payload[$field])) {
                $val = trim((string)$payload[$field]);
                if (strlen($val) > $max) {
                    http_response_code(422);
                    echo json_encode(['success' => false, 'message' => "{$field} يتجاوز الحد الأقصى."]);
                    return;
                }
                $update[$field] = $val;
            }
        }

        if (isset($payload['amount'])) {
            $cleanAmount = preg_replace('/[^\d\.\-]/', '', (string)$payload['amount']);
            $update['amount'] = $cleanAmount === '' ? null : $cleanAmount;
        }

        foreach (['issue_date', 'expiry_date'] as $dateField) {
            if (isset($payload[$dateField])) {
                $val = trim((string)$payload[$dateField]);
                if ($val !== '' && strtotime($val) === false) {
                    http_response_code(422);
                    echo json_encode(['success' => false, 'message' => "{$dateField} ليس تاريخاً صالحاً"});
                    return;
                }
                $update[$dateField] = $val === '' ? null : date('Y-m-d', strtotime($val));
            }
        }

        $this->records->updateDecision($id, $update);
        $updated = $this->records->find($id);

        echo json_encode(['success' => true, 'data' => $updated]);
    }

    public function candidates(int $id): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $record = $this->records->find($id);
        if (!$record) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'السجل غير موجود']);
            return;
        }

        $supplierCandidates = $this->candidates->supplierCandidates($record->rawSupplierName);

        echo json_encode([
            'success' => true,
            'data' => [
                'supplier' => $supplierCandidates,
            ],
        ]);
    }
}
