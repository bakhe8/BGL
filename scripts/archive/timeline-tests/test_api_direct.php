<?php
// Test guarantee-history API directly
$number = $argv[1] ?? 'MD2513500006';

$url = "http://localhost:8000/www/api/guarantee-history.php?number=" . urlencode($number);

echo "Testing API: $url\n\n";

$context = stream_context_create(['http' => ['timeout' => 5]]);
$response = @file_get_contents($url, false, $context);

if ($response === false) {
    echo "ERROR: Failed to get response!\n";
    exit(1);
}

echo "Response length: " . strlen($response) . " bytes\n";
echo "First 500 chars:\n";
echo substr($response, 0, 500) . "\n\n";

// Try to decode JSON
$json = @json_decode($response, true);

if ($json === null) {
    echo "ERROR: Response is not valid JSON!\n";
    echo "This means the API is returning HTML or an error page.\n";
} else {
    echo "✅ Valid JSON response\n";
    echo "Success: " . ($json['success'] ? 'true' : 'false') . "\n";
    
    if (isset($json['history']) && is_array($json['history'])) {
        echo "History count: " . count($json['history']) . "\n";
        
        if (count($json['history']) > 0) {
            echo "\nFirst event:\n";
            print_r($json['history'][0]);
        }
    }
}
