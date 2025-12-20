<?php
require __DIR__ . '/app/Support/autoload.php';
use App\Support\Database;

$db = Database::connect();

// Get session 470 record  
$stmt = $db->prepare("SELECT * FROM imported_records WHERE session_id = 470 AND guarantee_number LIKE '%OG/CC046034%' ORDER BY id DESC LIMIT 1");
$stmt->execute();
$session470 = $stmt->fetch(PDO::FETCH_ASSOC);

// Get session 465 record
$stmt = $db->prepare("SELECT * FROM imported_records WHERE session_id = 465 AND guarantee_number LIKE '%OG/CC046034%' ORDER BY id DESC LIMIT 1");
$stmt->execute();
$session465 = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Comparison between sessions:\n";
echo str_repeat("=", 100) . "\n\n";

if ($session465) {
    echo "Session 465 (PREVIOUS):\n";
    echo "  Supplier ID: " . ($session465['supplier_id'] ?? 'NULL') . "\n";
    echo "  Supplier Display: " . ($session465['supplier_display_name'] ?? 'NULL') . "\n";
    echo "  Raw Supplier: " . ($session465['raw_supplier_name'] ?? 'NULL') . "\n";
    echo "\n";
}

if ($session470) {
    echo "Session 470 (CURRENT - Record 12901):\n";
    echo "  Supplier ID: " . ($session470['supplier_id'] ?? 'NULL') . "\n";
    echo "  Supplier Display: " . ($session470['supplier_display_name'] ?? 'NULL') . "\n";
    echo "  Raw Supplier: " . ($session470['raw_supplier_name'] ?? 'NULL') . "\n";
    echo "\n";
}

echo str_repeat("=", 100) . "\n";
echo "ANALYSIS:\n";
if ($session465 && $session470) {
    $supplierIdChanged = ($session465['supplier_id'] != $session470['supplier_id']);
    $displayNameChanged = ($session465['supplier_display_name'] != $session470['supplier_display_name']);
    
    echo "Supplier ID changed? " . ($supplierIdChanged ? "YES" : "NO") . "\n";
    echo "Display Name changed? " . ($displayNameChanged ? "YES" : "NO") . "\n";
    echo "\n";
    
    if (!$supplierIdChanged && $displayNameChanged) {
        echo "⚠️  ISSUE IDENTIFIED:\n";
        echo "The display name changed but supplier_id remained the same.\n";
        echo "Current logic only tracks changes when supplier_id changes!\n";
        echo "\nThis means changing from a suggestion to Excel name (same supplier)\n";
        echo "will NOT be tracked as a modification.\n";
    }
}
