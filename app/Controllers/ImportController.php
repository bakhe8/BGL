<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\ImportService;

class ImportController
{
    public function __construct(private ImportService $importService)
    {
    }

    public function upload(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'لم يتم استلام الملف أو حدث خطأ في الرفع.']);
            return;
        }

        try {
            $tmpPath = $_FILES['file']['tmp_name'];

            // حفظ نسخة من الملف في uploads
            $uploadDir = storage_path('uploads');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $destPath = $uploadDir . '/upload_' . date('Ymd_His') . '.xlsx';
            move_uploaded_file($tmpPath, $destPath);

            $result = $this->importService->importExcel($destPath);

            echo json_encode(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'فشل الاستيراد: ' . $e->getMessage(),
            ]);
        }
    }
}
