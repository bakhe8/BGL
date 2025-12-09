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
}
