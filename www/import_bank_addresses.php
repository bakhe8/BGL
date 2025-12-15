<?php
/**
 * Import Bank Addresses from JSON
 * Matches bank names and updates address information
 */

require __DIR__ . '/../app/Support/autoload.php';

use App\Support\Database;
use App\Support\Normalizer;

try {
    echo "=== Bank Address Import Utility ===\n\n";
    
    // Load JSON data
    $jsonPath = __DIR__ . '/../storage/bank_contacts.json';
    if (!file_exists($jsonPath)) {
        throw new Exception("JSON file not found: $jsonPath");
    }
    
    $jsonData = json_decode(file_get_contents($jsonPath), true);
    if (!$jsonData || !isset($jsonData['banks'])) {
        throw new Exception("Invalid JSON format");
    }
    
    $pdo = Database::connection();
    $normalizer = new Normalizer();
    
    // Get all banks from database
    $stmt = $pdo->query("SELECT id, official_name, official_name_ar FROM banks");
    $dbBanks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($dbBanks) . " banks in database\n";
    echo "Found " . count($jsonData['banks']) . " bank addresses in JSON\n\n";
    
    $matched = 0;
    $updated = 0;
    $skipped = 0;
    
    foreach ($dbBanks as $bank) {
        $dbName = $bank['official_name'];
        $dbNameAr = $bank['official_name_ar'] ?: $dbName;
        $bankId = $bank['id'];
        
        // Try exact match first
        $addressData = null;
        if (isset($jsonData['banks'][$dbName])) {
            $addressData = $jsonData['banks'][$dbName];
            echo "✓ Exact match: $dbName\n";
        } elseif (isset($jsonData['banks'][$dbNameAr])) {
            $addressData = $jsonData['banks'][$dbNameAr];
            echo "✓ Arabic match: $dbNameAr\n";
        } else {
            // Try fuzzy match
            $dbNormalized = $normalizer->normalizeBankName($dbName);
            foreach ($jsonData['banks'] as $jsonName => $data) {
                $jsonNormalized = $normalizer->normalizeBankName($jsonName);
                if ($dbNormalized === $jsonNormalized) {
                    $addressData = $data;
                    echo "✓ Fuzzy match: $dbName -> $jsonName\n";
                    break;
                }
            }
        }
        
        if ($addressData) {
            $matched++;
            
            // Prepare address lines
            $addressLine1 = $addressData['addressLines'][0] ?? null;
            $addressLine2 = $addressData['addressLines'][1] ?? null;
            
            // Skip if "—" (dash placeholder)
            if ($addressLine1 === '—') {
                echo "  ⚠ Skipped (no address): $dbName\n";
                $skipped++;
                continue;
            }
            
            // Update database
            $updateStmt = $pdo->prepare("
                UPDATE banks 
                SET department = :dept,
                    address_line_1 = :addr1,
                    address_line_2 = :addr2,
                    contact_email = :email
                WHERE id = :id
            ");
            
            $updateStmt->execute([
                'dept' => $addressData['department'],
                'addr1' => $addressLine1,
                'addr2' => $addressLine2,
                'email' => $addressData['email'],
                'id' => $bankId
            ]);
            
            echo "  → Updated: {$addressData['department']}\n";
            echo "  → Address: $addressLine1\n";
            if ($addressLine2) echo "  → Address 2: $addressLine2\n";
            echo "  → Email: {$addressData['email']}\n\n";
            
            $updated++;
        } else {
            echo "✗ No match: $dbName\n\n";
        }
    }
    
    echo "\n=== Import Summary ===\n";
    echo "Matched: $matched/" . count($dbBanks) . "\n";
    echo "Updated: $updated\n";
    echo "Skipped: $skipped\n";
    echo "Not found: " . (count($dbBanks) - $matched) . "\n";
    echo "\n✅ Import completed!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
