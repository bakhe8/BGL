/**
 * Simple Router for Unified Views (v2.0 Architecture)
 * Maps URLs to the new centralized View structure in `app/Views/`
 * 
 * Part of the BGL 2.0 refactoring to separate Logic from Presentation.
 * This router intercepts legacy paths like `/pages/stats.php` and routes them
 * to the correct location in `app/Views/pages/`.
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
