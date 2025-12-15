<?php
/**
 * Test Script - System Health Check
 */

require __DIR__ . '/../app/Support/autoload.php';

use App\Support\Database;
use App\Repositories\ImportedRecordRepository;
use App\Repositories\BankRepository;
use App\Repositories\SupplierRepository;

echo "=== System Health Check ===\n\n";

try {
    $pdo = Database::connection();
    echo "✓ Database connection: OK\n";
    
    // Test 1: Check records
    $recordRepo = new ImportedRecordRepository();
    $records = $recordRepo->all();
    echo "✓ Total Records: " . count($records) . "\n";
    
    // Test 2: Check banks
    $bankRepo = new BankRepository();
    $banks = $bankRepo->allNormalized();
    echo "✓ Total Banks: " . count($banks) . "\n";
    
    // Check if banks have addresses
    $banksWithAddress = array_filter($banks, fn($b) => !empty($b['address_line_1']));
    echo "✓ Banks with Address: " . count($banksWithAddress) . "\n";
    
    // Test 3: Check suppliers
    $supplierRepo = new SupplierRepository();
    $suppliers = $supplierRepo->allNormalized();
    echo "✓ Total Suppliers: " . count($suppliers) . "\n";
    
    // Test 4: Check sessions
    $sessionIds = [];
    foreach ($records as $r) {
        $sessionIds[] = $r->sessionId;
    }
    $sessions = array_unique($sessionIds);
    echo "✓ Import Sessions: " . count($sessions) . "\n";
    
    // Test 5: Check decisions (records with both supplier and bank)
    $decidedCount = 0;
    foreach ($records as $r) {
        if (!empty($r->selectedSupplier) && !empty($r->selectedBank)) {
            $decidedCount++;
        }
    }
    echo "✓ Records with Decision: " . $decidedCount . "\n";
    
    echo "\n=== All Tests Passed ===\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
