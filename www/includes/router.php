<?php
/**
 * API Router
 * Handles all /api/* requests
 */

declare(strict_types=1);

use App\Repositories\ImportedRecordRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\BankRepository;
use App\Repositories\ImportSessionRepository;
use App\Repositories\SupplierAlternativeNameRepository;
use App\Support\Normalizer;

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (str_starts_with($uri, '/api/')) {
    // Ensure JSON header for all API responses
    header('Content-Type: application/json; charset=utf-8');

    // Instantiate Repositories manually for API context ONLY if needed
    // But since the main repo logic is below, we must instantiate dependencies HERE for the API to work.
    $apiImportSessionRepo = new ImportSessionRepository();
    $apiRecords = new ImportedRecordRepository();
    
    // Instantiate Controllers
    $importService = new \App\Services\ImportService($apiImportSessionRepo, $apiRecords);
    $importController = new \App\Controllers\ImportController($importService);
    $decisionController = new \App\Controllers\DecisionController($apiRecords);
    $dictionaryController = new \App\Controllers\DictionaryController();
    $settingsController = new \App\Controllers\SettingsController();
    $statsController = new \App\Controllers\StatsController($apiRecords);

    try {
        // 1. Save Decision
        if ($method === 'POST' && preg_match('#^/api/records/(\d+)/decision$#', $uri, $m)) {
            $payload = json_decode(file_get_contents('php://input'), true) ?? [];
            $decisionController->saveDecision((int)$m[1], $payload);
            exit;
        }

        // 2. Add New Supplier
        if ($method === 'POST' && $uri === '/api/dictionary/suppliers') {
            $payload = json_decode(file_get_contents('php://input'), true) ?? [];
            $dictionaryController->createSupplier($payload);
            exit;
        }

        // 3. Recalculate Matches
        if ($method === 'POST' && $uri === '/api/records/recalculate') {
            $decisionController->recalculate();
            exit;
        }

        // 4. File Import
        if ($method === 'POST' && $uri === '/api/import/excel') {
            $importController->upload();
            exit;
        }

        // 5. Dictionary Settings APIs
        if ($method === 'POST' && $uri === '/api/settings/import-dictionary') {
            $payload = json_decode(file_get_contents('php://input'), true) ?? [];
            $settingsController->importDictionary($payload);
            exit;
        }

        // 6. Manual Entry API
        if ($method === 'POST' && $uri === '/api/import/manual') {
            $manualEntryController = new \App\Controllers\ManualEntryController(
                $apiImportSessionRepo,
                $apiRecords,
                new \App\Services\MatchingService(
                    new SupplierRepository(),
                    new SupplierAlternativeNameRepository(),
                    new BankRepository()
                )
            );
            $payload = json_decode(file_get_contents('php://input'), true) ?? [];
            $manualEntryController->handle($payload);
            exit;
        }

        // 7. Text Import API (Smart Paste)
        if ($method === 'POST' && $uri === '/api/import/text') {
            $textImportController = new \App\Controllers\TextImportController(
                new \App\Services\TextParsingService(new Normalizer()),
                $apiImportSessionRepo,
                $apiRecords,
                new \App\Services\MatchingService(
                    new SupplierRepository(),
                    new SupplierAlternativeNameRepository(),
                    new BankRepository()
                )
            );
            $payload = json_decode(file_get_contents('php://input'), true) ?? [];
            $textImportController->handle($payload);
            exit;
        }
        
        // Fallback checks happen in server.php or fall through 404
        
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}
