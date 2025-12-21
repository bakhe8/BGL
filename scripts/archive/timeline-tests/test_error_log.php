<?php
// Test where error_log writes to
error_log("TEST MESSAGE FROM PHP: " . date('Y-m-d H:i:s'));
error_log("This should appear in the error log file!");

echo "Log test sent at: " . date('Y-m-d H:i:s') . "\n";
echo "Error log setting: " . ini_get('error_log') . "\n";
echo "Check debug.log or php_errors.log\n";
