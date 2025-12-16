<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ImportedRecordRepository;

/**
 * Stats Controller
 * 
 * يوفر إحصائيات لوحة التحكم عن السجلات المستوردة
 * 
 * الإحصائيات تشمل:
 * - إجمالي السجلات
 * - السجلات حسب الحالة (pending, ready, approved)
 * - إجمالي المبالغ
 * 
 * @package App\Controllers
 */
class StatsController
{
    /** @var ImportedRecordRepository */
    private $records;

    /**
     * Constructor
     * 
     * @param ImportedRecordRepository|null $records Repository instance (auto-created if null)
     */
    public function __construct(?ImportedRecordRepository $records = null)
    {
        $this->records = $records ?: new ImportedRecordRepository();
    }

    /**
     * Return JSON statistics for the dashboard
     */
    public function index(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $data = $this->records->getStats();
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
