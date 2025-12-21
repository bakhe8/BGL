<?php
// Test if TimelineEventService has snapshot code
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) require_once $file;
});

require_once __DIR__ . '/../vendor/autoload.php';

$service = new \App\Services\TimelineEventService();

// Use reflection to check if captureSnapshot method exists
$reflection = new ReflectionClass($service);

echo "=== TimelineEventService Methods ===\n\n";

foreach ($reflection->getMethods(ReflectionMethod::IS_PRIVATE | ReflectionMethod::IS_PUBLIC) as $method) {
    if (strpos($method->getName(), 'snapshot') !== false || strpos($method->getName(), 'Snapshot') !== false) {
        echo "✓ Found: " . $method->getName() . " (";
        echo $method->isPrivate() ? 'private' : 'public';
        echo ")\n";
    }
}

echo "\n=== Checking logSupplierChange signature ===\n";
$method = $reflection->getMethod('logSupplierChange');
echo "Parameters: " . $method->getNumberOfParameters() . "\n";

// Check file modification time
$file = __DIR__ . '/../app/Services/TimelineEventService.php';
echo "\n=== File Info ===\n";
echo "Last modified: " . date('Y-m-d H:i:s', filemtime($file)) . "\n";
echo "File size: " . filesize($file) . " bytes\n";

// Check for snapshot_data in code
$code = file_get_contents($file);
if (strpos($code, 'snapshot_data') !== false) {
    echo "✓ Code contains 'snapshot_data'\n";
} else {
    echo "✗ Code does NOT contain 'snapshot_data'\n";
}

if (strpos($code, 'captureSnapshot') !== false) {
    echo "✓ Code contains 'captureSnapshot' method\n";
} else {
    echo "✗ Code does NOT contain 'captureSnapshot' method\n";
}
