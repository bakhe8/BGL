<?php
/**
 * Quick Test: Check Import API Response
 */

// Simple autoloader
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/../vendor/autoload.php';

// Simple database check
$dbPath = __DIR__ . '/../storage/database.sqlite';
echo "Database: " . ($dbPath) . "\n";
echo "Exists: " . (file_exists($dbPath) ? 'Yes' : 'No') . "\n\n";

// Test the changed code directly
echo "Checking ImportService.php code...\n";
$serviceCode = file_get_contents(__DIR__ . '/../app/Services/ImportService.php');

// Check if our changes are present
if (strpos($serviceCode, 'first_record_id') !== false) {
    echo "✅ Code contains 'first_record_id'\n";
    
    // Count occurrences
    $count = substr_count($serviceCode, 'first_record_id');
    echo "   Found $count occurrences\n";
    
    // Check if it's in the return statement
    if (strpos($serviceCode, "'first_record_id' => \$firstRecordId") !== false) {
        echo "✅ Return statement includes first_record_id\n";
    } else {
        echo "❌ Return statement does NOT include first_record_id!\n";
    }
    
    // Check initialization
    if (strpos($serviceCode, '$firstRecordId = null') !== false) {
        echo "✅ Variable initialization found\n";
    } else {
        echo "❌ Variable NOT initialized!\n";
    }
    
    // Check assignment
    if (strpos($serviceCode, "\$firstRecordId = \$ids['old_id']") !== false) {
        echo "✅ Assignment to first record found\n";
    } else {
        echo "❌ Assignment NOT found!\n";
    }
    
} else {
    echo "❌ Code does NOT contain 'first_record_id'!\n";
    echo "This means the file was not saved or changes were reverted.\n";
}

echo "\n";
echo "Checking decision.js...\n";
$jsCode = file_get_contents(__DIR__ . '/../www/assets/js/decision.js');

if (strpos($jsCode, 'first_record_id') !== false) {
    echo "✅ JavaScript contains 'first_record_id'\n";
    
    if (strpos($jsCode, 'json.data.first_record_id') !== false) {
        echo "✅ JavaScript checks for first_record_id in response\n";
    } else {
        echo "❌ JavaScript does NOT check for first_record_id!\n";
    }
} else {
    echo "❌ JavaScript does NOT contain 'first_record_id'!\n";
}

echo "\n=== RECOMMENDATION ===\n";
echo "If files show ✅, the issue is likely browser cache.\n";
echo "Tell the user to:\n";
echo "1. Hard refresh the page (Ctrl+Shift+R)\n";
echo "2. Or clear browser cache\n";
echo "3. Or open in incognito/private window\n";
