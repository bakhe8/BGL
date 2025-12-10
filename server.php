<?php
// Router script for PHP built-in server. Serves static files from www/; otherwise forwards to www/index.php.
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . '/www' . $uri;

if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false; // serve the static file
}

require __DIR__ . '/www/index.php';
