<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ImportedRecordRepository;

class StatsController
{
    private $records;

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
