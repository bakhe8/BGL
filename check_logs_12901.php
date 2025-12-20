<?php
// Check PHP error log for record 12901 modifications
echo "Checking PHP error log for logModificationIfNeeded messages...\n";
echo str_repeat("=", 80) . "\n\n";

// Try to find error log
$logPaths = [
    'C:\\xampp\\apache\\logs\\error.log',
    'C:\\wamp\\logs\\php_error.log',
    ini_get('error_log'),
];

$foundLog = false;
foreach ($logPaths as $path) {
    if ($path && file_exists($path)) {
        echo "Found log: $path\n";
        echo str_repeat("-", 80) . "\n";
        
        // Read last 200 lines
        $lines = file($path);
        $recent = array_slice($lines, -200);
        
        // Filter for relevant messages
        $found = false;
        foreach ($recent as $line) {
            if (stripos($line, '12901') !== false || 
                stripos($line, 'logModificationIfNeeded') !== false ||
                stripos($line, 'OG/CC046034') !== false) {
                echo $line;
                $found = true;
            }
        }
        
        if (!$found) {
            echo "No relevant log entries found for record 12901\n";
        }
        
        $foundLog = true;
        break;
    }
}

if (!$foundLog) {
    echo "No error log found. Checking if error logging is enabled...\n";
    echo "error_log setting: " . ini_get('error_log') . "\n";
    echo "log_errors: " . ini_get('log_errors') . "\n";
}
