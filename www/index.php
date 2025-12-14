<?php
declare(strict_types=1);

use App\Controllers\ImportController;
use App\Controllers\DecisionController;
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

// Global Error Handler for API
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    // حاوية بسيطة لإنشاء التبعيات
    $importSessionRepo = new ImportSessionRepository();
    $importedRecordRepo = new ImportedRecordRepository();
    $importService = new ImportService($importSessionRepo, $importedRecordRepo);
    $importController = new ImportController($importService);
    $decisionController = new DecisionController($importedRecordRepo);
    $dictionaryController = new DictionaryController();
    $settingsController = new SettingsController();

    // ═══════════════════════════════════════════════════════════════════
    // الواجهات المتاحة (فقط):
    // 1. / → صفحة اتخاذ القرار (الرئيسية)
    // 2. /settings → صفحة الإعدادات
    // ═══════════════════════════════════════════════════════════════════
    
    if ($method === 'GET' && $uri === '/') {
        echo file_get_contents(__DIR__ . '/decision.html');
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
        $decisionController->index();
        exit;
    }

    if ($method === 'POST' && preg_match('#^/api/records/(\\d+)/decision$#', $uri, $m)) {
        $id = (int) $m[1];
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $decisionController->saveDecision($id, $payload);
        exit;
    }

    if ($method === 'GET' && preg_match('#^/api/records/(\\d+)/candidates$#', $uri, $m)) {
        $id = (int) $m[1];
        $decisionController->candidates($id);
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
        $dictionaryController->updateSupplier((int) $m[1], $payload);
        exit;
    }

    // NOTE: DELETE suppliers route removed - not needed per user requirements
    // The function deleteSupplier() was never implemented in DictionaryController

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
        $dictionaryController->updateBank((int) $m[1], $payload);
        exit;
    }

    // NOTE: DELETE banks route removed - not needed per user requirements
    // The function deleteBank() was never implemented in DictionaryController

    // NOTE: Alternatives routes removed - functions never implemented in DictionaryController
    // These were planned features but never used by the frontend
    // If needed in future, implement listAlternativeNames(), createAlternativeName(), deleteAlternativeName() first

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

} catch (Throwable $e) {
    // Catch-all for API errors to ensure JSON is returned
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Internal Server Error',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit;
}
