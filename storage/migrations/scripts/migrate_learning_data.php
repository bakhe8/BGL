<?php
/**
 * Migration: Populate supplier_suggestions from existing learning data
 * Run this: php storage/migrations/migrate_learning_data.php
 */

require_once __DIR__ . '/../../app/Support/autoload.php';

use App\Repositories\SupplierSuggestionRepository;
use App\Repositories\SupplierRepository;
use App\Support\Database;
use App\Support\Normalizer;

echo "=== Migrating Learning Data to Suggestions ===\n\n";

try {
    $db = Database::connection();
    $suggestionRepo = new SupplierSuggestionRepository();
    $supplierRepo = new SupplierRepository();
    $normalizer = new Normalizer();
    
    // 1. Fetch all learning data
    $stmt = $db->query("SELECT * FROM supplier_aliases_learning WHERE learning_status = 'supplier_alias'");
    $learningRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($learningRecords) . " learning records.\n";
    
    $migratedCount = 0;
    
    foreach ($learningRecords as $record) {
        $rawName = $record['original_supplier_name'];
        $normalizedName = $normalizer->normalizeSupplierName($rawName);
        $supplierId = (int)$record['linked_supplier_id'];
        $usageCount = (int)$record['usage_count'];
        
        // Skip invalid records
        if (empty($normalizedName) || empty($supplierId)) {
            echo "⚠️ Skipping invalid record: {$rawName}\n";
            continue;
        }
        
        // Get supplier display name
        $supplier = $supplierRepo->find($supplierId);
        if (!$supplier) {
            echo "⚠️ Supplier ID {$supplierId} not found (for {$rawName})\n";
            continue;
        }
        
        // Prepare suggestion data
        // We bypass 'saveSuggestions' array wrapper to use low-level insert/replace 
        // to set usage_count exactly as recorded
        
        // Check if already exists (to avoid overwriting newer data if re-run)
        if ($suggestionRepo->hasCachedSuggestions($normalizedName)) {
            // Check if THIS specific supplier is suggested
            $existing = $suggestionRepo->getSuggestions($normalizedName);
            $found = false;
            foreach ($existing as $ex) {
                if ($ex['supplier_id'] == $supplierId && $ex['source'] == 'learning') {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                echo "ℹ️ Already exists: {$normalizedName} -> {$supplier->officialName}\n";
                // Optionally update usage count if needed, but for now skip
                continue;
            }
        }
        
        // Use repository to calculate scores correctly
        // Format for saveSuggestions:
        $suggestionData = [
            [
                'supplier_id' => $supplierId,
                'display_name' => $supplier->officialName,
                'source' => 'learning',     // This gives 100 source_weight
                'fuzzy_score' => 1.0,       // Exact learned match
                'usage_count' => $usageCount
            ]
        ];
        
        $suggestionRepo->saveSuggestions($normalizedName, $suggestionData);
        $migratedCount++;
        echo "✅ Migrated: {$rawName} -> {$supplier->officialName} (Usage: {$usageCount})\n";
    }
    
    echo "\n=== Migration Complete ===\n";
    echo "Total migrated: {$migratedCount}\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
