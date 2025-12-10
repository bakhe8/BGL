<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ImportedRecordRepository;

class RecordsController
{
    public function __construct(private ImportedRecordRepository $records)
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
        foreach (['raw_supplier_name', 'raw_bank_name', 'amount', 'guarantee_number', 'issue_date', 'expiry_date'] as $field) {
            if (isset($payload[$field])) {
                $update[$field] = $payload[$field];
            }
        }

        $this->records->updateDecision($id, $update);
        $updated = $this->records->find($id);

        echo json_encode(['success' => true, 'data' => $updated]);
    }
}
