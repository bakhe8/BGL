<?php
declare(strict_types=1);

use App\Controllers\ImportController;
use App\Controllers\RecordsController;
use App\Repositories\ImportSessionRepository;
use App\Repositories\ImportedRecordRepository;
use App\Services\ImportService;

require __DIR__ . '/../app/Support/autoload.php';

// بسيط: التوجيه بناءً على REQUEST_URI و METHOD
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// حاوية بسيطة لإنشاء التبعيات
$importSessionRepo = new ImportSessionRepository();
$importedRecordRepo = new ImportedRecordRepository();
$importService = new ImportService($importSessionRepo, $importedRecordRepo);
$importController = new ImportController($importService);
$recordsController = new RecordsController($importedRecordRepo);

// صفحات HTML
if ($method === 'GET' && ($uri === '/' || $uri === '/import')) {
    echo file_get_contents(__DIR__ . '/import.html');
    exit;
}

// API
if ($method === 'POST' && $uri === '/api/import/excel') {
    $importController->upload();
    exit;
}

if ($method === 'GET' && $uri === '/api/records') {
    $recordsController->index();
    exit;
}

http_response_code(404);
echo 'Not Found';
