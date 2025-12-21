<?php
// Test the guarantee history API
$guaranteeNumber = 'TEST123';

$_GET['number'] = $guaranteeNumber;
ob_start();
require __DIR__ . '/../www/api/guarantee-history.php';
$output = ob_get_clean();

echo "API Response for guarantee: $guaranteeNumber\n";
echo "=====================================\n";
echo $output;
echo "\n";
