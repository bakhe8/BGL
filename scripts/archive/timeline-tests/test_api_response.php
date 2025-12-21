<?php
// Test API directly
$url = 'http://localhost:8000/api/guarantee-history.php?number=RLG6904293';
$response = file_get_contents($url);
echo $response;
