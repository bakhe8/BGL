<?php
/**
 * Supplier Suggestion Repository
 * 
 * Manages the supplier_suggestions cache table.
 * This table stores pre-computed suggestions to avoid runtime calculations.
 * 
 * @see docs/09-Supplier-System-Refactoring.md
 */

namespace App\Repositories;

use App\Support\Database;
use PDO;

class SupplierSuggestionRepository
{
    private PDO $db;
    
    // Source weights for scoring
    private const SOURCE_WEIGHTS = [
        'learning' => 100,      // من التعلم السابق
        'user_history' => 80,   // من قرار مستخدم
        'alternatives' => 60,   // من الأسماء البديلة
        'dictionary' => 40,     // من القاموس الرسمي
    ];
    
    public function __construct()
    {
        $this->db = Database::connection();
    }
    
    /**
     * Check if suggestions exist for a normalized input
     */
    public function hasCachedSuggestions(string $normalizedInput): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM supplier_suggestions 
            WHERE normalized_input = ?
        ");
        $stmt->execute([$normalizedInput]);
        return (int)$stmt->fetchColumn() > 0;
    }
    
    /**
     * Get cached suggestions for a normalized input
     * 
     * @return array Suggestions ordered by total_score DESC
     */
    public function getSuggestions(string $normalizedInput, int $limit = 6): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                supplier_id,
                display_name,
                source,
                fuzzy_score,
                source_weight,
                usage_count,
                total_score,
                star_rating
            FROM supplier_suggestions
            WHERE normalized_input = ?
            ORDER BY total_score DESC
            LIMIT ?
        ");
        $stmt->execute([$normalizedInput, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Save suggestions to cache
     * 
     * @param string $normalizedInput Normalized Excel name
     * @param array $suggestions Array of suggestion data
     */
    public function saveSuggestions(string $normalizedInput, array $suggestions): void
    {
        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO supplier_suggestions (
                normalized_input, supplier_id, display_name, source,
                fuzzy_score, source_weight, usage_count,
                total_score, star_rating, last_updated
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        
        foreach ($suggestions as $s) {
            $sourceWeight = self::SOURCE_WEIGHTS[$s['source']] ?? 40;
            $usageCount = $s['usage_count'] ?? 0;
            $fuzzyScore = $s['fuzzy_score'] ?? $s['score'] ?? 0.0;
            
            // Calculate total score
            $totalScore = $this->calculateScore($fuzzyScore, $sourceWeight, $usageCount);
            $starRating = $this->assignStarRating($totalScore);
            
            $stmt->execute([
                $normalizedInput,
                $s['supplier_id'],
                $s['display_name'] ?? $s['name'] ?? '',
                $s['source'] ?? 'dictionary',
                $fuzzyScore,
                $sourceWeight,
                $usageCount,
                $totalScore,
                $starRating,
            ]);
        }
    }
    
    /**
     * Increment usage count for a specific suggestion
     * Called when user selects a suggestion
     */
    public function incrementUsage(string $normalizedInput, int $supplierId): bool
    {
        // First, check if exists
        $stmt = $this->db->prepare("
            SELECT id, fuzzy_score, source_weight, usage_count 
            FROM supplier_suggestions
            WHERE normalized_input = ? AND supplier_id = ?
            LIMIT 1
        ");
        $stmt->execute([$normalizedInput, $supplierId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing) {
            // Create new entry if doesn't exist
            return $this->createEntry($normalizedInput, $supplierId);
        }
        
        // Increment and recalculate
        $newUsageCount = (int)$existing['usage_count'] + 1;
        $newTotalScore = $this->calculateScore(
            (float)$existing['fuzzy_score'],
            (int)$existing['source_weight'],
            $newUsageCount
        );
        $newStarRating = $this->assignStarRating($newTotalScore);
        
        $updateStmt = $this->db->prepare("
            UPDATE supplier_suggestions
            SET usage_count = ?,
                total_score = ?,
                star_rating = ?,
                last_updated = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        return $updateStmt->execute([
            $newUsageCount,
            $newTotalScore,
            $newStarRating,
            $existing['id'],
        ]);
    }
    
    /**
     * Create a new suggestion entry (for first-time usage)
     */
    private function createEntry(string $normalizedInput, int $supplierId): bool
    {
        // Fetch supplier name from suppliers table
        $supplierStmt = $this->db->prepare("SELECT official_name FROM suppliers WHERE id = ?");
        $supplierStmt->execute([$supplierId]);
        $supplier = $supplierStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$supplier) {
            return false;
        }
        
        $sourceWeight = self::SOURCE_WEIGHTS['user_history'];
        $usageCount = 1;
        $fuzzyScore = 1.0; // User explicitly selected it
        $totalScore = $this->calculateScore($fuzzyScore, $sourceWeight, $usageCount);
        $starRating = $this->assignStarRating($totalScore);
        
        $stmt = $this->db->prepare("
            INSERT INTO supplier_suggestions (
                normalized_input, supplier_id, display_name, source,
                fuzzy_score, source_weight, usage_count,
                total_score, star_rating, last_updated
            ) VALUES (?, ?, ?, 'user_history', ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        
        return $stmt->execute([
            $normalizedInput,
            $supplierId,
            $supplier['official_name'],
            $fuzzyScore,
            $sourceWeight,
            $usageCount,
            $totalScore,
            $starRating,
        ]);
    }
    
    /**
     * Calculate total score
     * Formula: (fuzzy × 100) + source_weight + min(usage × 15, 75)
     */
    private function calculateScore(float $fuzzyScore, int $sourceWeight, int $usageCount): float
    {
        $fuzzyPoints = $fuzzyScore * 100;
        $usageBonus = min($usageCount * 15, 75);
        return $fuzzyPoints + $sourceWeight + $usageBonus;
    }
    
    /**
     * Assign star rating based on total score
     * >=220 = 3 stars, >=160 = 2 stars, else = 1 star
     */
    private function assignStarRating(float $totalScore): int
    {
        if ($totalScore >= 220) return 3;
        if ($totalScore >= 160) return 2;
        return 1;
    }
    
    /**
     * Delete cached suggestions for a normalized input
     * (Call when cache needs refresh)
     */
    public function clearCache(string $normalizedInput): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM supplier_suggestions WHERE normalized_input = ?
        ");
        return $stmt->execute([$normalizedInput]);
    }
    
    /**
     * Get all cached normalized inputs (for debugging/admin)
     */
    public function getAllCachedInputs(): array
    {
        $stmt = $this->db->query("
            SELECT DISTINCT normalized_input, COUNT(*) as suggestion_count
            FROM supplier_suggestions
            GROUP BY normalized_input
            ORDER BY suggestion_count DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
