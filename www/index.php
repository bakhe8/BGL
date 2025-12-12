<?php
declare(strict_types=1);

use App\Controllers\ImportController;
use App\Controllers\RecordsController;
use App\Controllers\DictionaryController;
use App\Controllers\SettingsController;
use App\Repositories\ImportSessionRepository;
use App\Repositories\ImportedRecordRepository;
use App\Services\ImportService;

require __DIR__ . '/../app/Support/autoload.php';

// بسيط: التوجيه بناءً على REQUEST_URI و METHOD
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// خدمة الملفات الثابتة (CSS/JS/ICO/صور) داخل www
$staticPath = __DIR__ . $uri;
if ($uri !== '/' && file_exists($staticPath) && !is_dir($staticPath)) {
    $ext = pathinfo($staticPath, PATHINFO_EXTENSION);
    $types = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff2' => 'font/woff2',
    ];
    if (isset($types[$ext])) {
        header('Content-Type: ' . $types[$ext]);
    }
    readfile($staticPath);
    exit;
}

// حاوية بسيطة لإنشاء التبعيات
$importSessionRepo = new ImportSessionRepository();
$importedRecordRepo = new ImportedRecordRepository();
$importService = new ImportService($importSessionRepo, $importedRecordRepo);
$importController = new ImportController($importService);
$recordsController = new RecordsController($importedRecordRepo);
$dictionaryController = new DictionaryController();
$settingsController = new SettingsController();

// صفحات HTML
if ($method === 'GET' && ($uri === '/' || $uri === '/import')) {
    echo file_get_contents(__DIR__ . '/import.html');
    exit;
}

if ($method === 'GET' && $uri === '/records') {
    echo file_get_contents(__DIR__ . '/records.html');
    exit;
}

if ($method === 'GET' && ($uri === '/dictionary' || $uri === '/dictionary/suppliers' || $uri === '/dictionary/banks')) {
    echo file_get_contents(__DIR__ . '/dictionary.html');
    exit;
}

if ($method === 'GET' && $uri === '/settings') {
    echo file_get_contents(__DIR__ . '/settings.html');
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

if ($method === 'POST' && preg_match('#^/api/records/(\\d+)/decision$#', $uri, $m)) {
    $id = (int)$m[1];
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $recordsController->saveDecision($id, $payload);
    exit;
}

if ($method === 'GET' && preg_match('#^/api/records/(\\d+)/candidates$#', $uri, $m)) {
    $id = (int)$m[1];
    $recordsController->candidates($id);
    exit;
}

if ($method === 'GET' && $uri === '/api/dictionary/suppliers') {
    $dictionaryController->listSuppliers();
    exit;
}

if ($method === 'POST' && $uri === '/api/dictionary/suppliers') {
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $dictionaryController->createSupplier($payload);
    exit;
}

if ($method === 'POST' && preg_match('#^/api/dictionary/suppliers/(\\d+)$#', $uri, $m)) {
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $dictionaryController->updateSupplier((int)$m[1], $payload);
    exit;
}

if ($method === 'DELETE' && preg_match('#^/api/dictionary/suppliers/(\\d+)$#', $uri, $m)) {
    $dictionaryController->deleteSupplier((int)$m[1]);
    exit;
}

if ($method === 'GET' && $uri === '/api/dictionary/banks') {
    $dictionaryController->listBanks();
    exit;
}

if ($method === 'POST' && $uri === '/api/dictionary/banks') {
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $dictionaryController->createBank($payload);
    exit;
}

if ($method === 'POST' && preg_match('#^/api/dictionary/banks/(\\d+)$#', $uri, $m)) {
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $dictionaryController->updateBank((int)$m[1], $payload);
    exit;
}

if ($method === 'DELETE' && preg_match('#^/api/dictionary/banks/(\\d+)$#', $uri, $m)) {
    $dictionaryController->deleteBank((int)$m[1]);
    exit;
}

if ($method === 'GET' && preg_match('#^/api/dictionary/suppliers/(\\d+)/alternatives$#', $uri, $m)) {
    $dictionaryController->listAlternativeNames((int)$m[1]);
    exit;
}

if ($method === 'POST' && preg_match('#^/api/dictionary/suppliers/(\\d+)/alternatives$#', $uri, $m)) {
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $dictionaryController->createAlternativeName((int)$m[1], $payload);
    exit;
}

if ($method === 'DELETE' && preg_match('#^/api/dictionary/alternatives/(\\d+)$#', $uri, $m)) {
    $dictionaryController->deleteAlternativeName((int)$m[1]);
    exit;
}

if ($method === 'POST' && $uri === '/api/dictionary/suggest-alias') {
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $dictionaryController->suggestAlias($payload);
    exit;
}

if ($method === 'GET' && $uri === '/api/settings') {
    $settingsController->all();
    exit;
}

if ($method === 'POST' && $uri === '/api/settings') {
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $settingsController->save($payload);
    exit;
}

if ($method === 'POST' && $uri === '/api/settings/backup') {
    $settingsController->backup();
    exit;
}

if ($method === 'POST' && $uri === '/api/settings/export-dictionary') {
    $settingsController->exportDictionary();
    exit;
}

if ($method === 'POST' && $uri === '/api/settings/import-dictionary') {
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $settingsController->importDictionary($payload);
    exit;
}

http_response_code(404);
echo 'Not Found';
