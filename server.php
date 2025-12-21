<?php
// Router script for PHP built-in server. Serves static files from www/; otherwise forwards to www/index.php.

// Log all requests for debugging
$logFile = __DIR__ . '/debug.log';
$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "SERVER: $method $uri\n", FILE_APPEND);

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// DesignLab routing - إضافة جديدة
if (strpos($uri, '/lab') === 0) {
    // توجيه كل /lab/* إلى lab.php
    require __DIR__ . '/lab.php';
    exit;
}

// Design Lab assets
if (strpos($uri, '/design-lab/') === 0) {
    $file = __DIR__ . $uri;
    if (file_exists($file) && !is_dir($file)) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $types = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'php' => 'text/html',
        ];
        if (isset($types[$ext])) {
            header('Content-Type: ' . $types[$ext]);
        }
        readfile($file);
        exit;
    }
}

$file = __DIR__ . '/www' . $uri;

// Serve static assets manually from www/ so that docroot doesn't need to be changed
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    
    
    // Enable standalone PHP scripts (API endpoints, plugins, etc.)
    if ($ext === 'php') {
        require $file;
        exit;
    }

    $types = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];
    if (isset($types[$ext])) {
        header('Content-Type: ' . $types[$ext]);
    }
    readfile($file);
    exit;
}

require __DIR__ . '/www/index.php';
