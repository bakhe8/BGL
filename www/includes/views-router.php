<?php
/**
 * Simple Router for New View Locations
 * Maps URLs to new unified Views structure in app/Views/
 */

// Stats page
if ($_SERVER['REQUEST_URI'] === '/stats' || $_SERVER['REQUEST_URI'] === '/stats.php') {
    require __DIR__ . '/../../app/Views/pages/stats.php';
    exit;
}

// Reports page
if ($_SERVER['REQUEST_URI'] === '/reports' || $_SERVER['REQUEST_URI'] === '/reports.php') {
    require __DIR__ . '/../../app/Views/pages/reports.php';
    exit;
}

// Print letter
if (strpos($_SERVER['REQUEST_URI'], '/letters/print-letter.php') === 0) {
    require __DIR__ . '/../../app/Views/letters/print-letter.php';
    exit;
}
