<?php
declare(strict_types=1);

/**
 * Main Entry Point - Clean & Minimal
 * ===================================
 * 
 * This file is the main entry point for the BGL application.
 * It delegates work to separate files for better organization.
 * 
 * @version 4.0 - Refactored Architecture
 * @date 2025-12-18
 */

require_once __DIR__ . '/../app/Support/autoload.php';

// Prevent browser caching to ensure fresh data
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

use App\Repositories\ImportedRecordRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\BankRepository;
use App\Repositories\ImportSessionRepository;
use App\Repositories\SupplierAlternativeNameRepository;
use App\Repositories\SupplierSuggestionRepository;
use App\Repositories\UserDecisionRepository;
use App\Repositories\BankLearningRepository;
use App\Services\CandidateService;
use App\Support\Normalizer;

// ═══════════════════════════════════════════════════════════════════
// ROUTING
// ═══════════════════════════════════════════════════════════════════

// Handle View requests (stats, reports, letters)
require __DIR__ . '/includes/views-router.php';

// Handle API requests
require __DIR__ . '/includes/router.php';

// Check for batch print mode
if (isset($_GET['print_batch']) && $_GET['print_batch'] == '1') {
    require __DIR__ . '/includes/decision-logic.php';
    require __DIR__ . '/../app/Views/pages/batch-print.php';
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// DECISION PAGE (Main Application)
// ═══════════════════════════════════════════════════════════════════

// Prepare data for decision page
require __DIR__ . '/includes/decision-logic.php';

// Render decision page view - this file has all PHP logic and HTML from original index.php (lines 676-1233)
// We kept it as one file to avoid breaking the complex HTML structure
require __DIR__ . '/../app/Views/pages/decision-page.php';
