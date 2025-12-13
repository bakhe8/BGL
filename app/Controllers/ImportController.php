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
        ini_set('memory_limit', '-1');
        header('Content-Type: application/json; charset=utf-8');

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'لم يتم استلام الملف أو حدث خطأ في الرفع.']);
            return;
        }


        // File Type Validation - Basic check
        $fileName = $_FILES['file']['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($ext, ['xlsx', 'xls'], true)) {
            http_response_code(415);
            echo json_encode(['success' => false, 'message' => 'نوع الملف غير مسموح. يجب أن يكون ملف Excel (.xlsx)']);
            return;
        }


        try {
            $tmpPath = $_FILES['file']['tmp_name'];

            // حفظ نسخة من الملف في uploads
            $uploadDir = storage_path('uploads');
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    throw new \RuntimeException('فشل إنشاء مجلد الرفع');
                }
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
