<?php
declare(strict_types=1);

// Standalone Entry Point for Intelligence Reports Plugin
// This file is completely independent of the main application flow (index.php).

use App\Controllers\ReportController;

require_once __DIR__ . '/../../Support/autoload.php';

// Route Handlers specific to this plugin
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Helper to handle API errors
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    $controller = new ReportController();

    $api = $_GET['api'] ?? '';

    // 1. View: /reports.php (No API param)
    if ($method === 'GET' && empty($api)) {
        $controller->index();
        exit;
    }

    // 2. API: ?api=efficiency
    if ($method === 'GET' && $api === 'efficiency') {
        $controller->getEfficiencyStats();
        exit;
    }

    // 3. API: ?api=banks
    if ($method === 'GET' && $api === 'banks') {
        $controller->getBankStats();
        exit;
    }

    // 4. API: ?api=suppliers
    if ($method === 'GET' && $api === 'suppliers') {
        $controller->getTopSuppliers();
        exit;
    }

    // Default: Redirect to View
    header('Location: /pages/reports.php');
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Report Plugin Error',
        'error' => $e->getMessage()
    ]);
    exit;
}
