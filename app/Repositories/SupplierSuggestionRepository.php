<?php
/**
 * =============================================================================
 * SupplierSuggestionRepository - الجدول الرئيسي للتعلم والاقتراحات
 * =============================================================================
 * 
 * VERSION: 5.0 (2025-12-17)
 * 
 * الوظائف الرئيسية:
 * ─────────────────
 * 1. تخزين الاقتراحات المحسوبة (Cache-First)
 * 2. تتبع الاستخدام (usage_count)
 * 3. الحظر التدريجي (block_count)
 * 4. حساب النقاط والنجوم
 * 
 * خوارزمية التقييم:
 * ────────────────
 * total_score = (fuzzy_score × 100) + source_weight + min(usage × 15, 75)
 * effective_score = total_score - (block_count × 50)
 * 
 * النجوم:
 * ──────
 * - ≥200 = 3 نجوم ⭐⭐⭐
 * - ≥120 = 2 نجوم ⭐⭐
 * - < 120 = 1 نجمة ⭐
 * 
 * الحظر التدريجي:
 * ──────────────
 * - كل حظر = -50 نقطة
 * - المورد يختفي عندما effective_score ≤ 0
 * - يمكن التعافي بالاستخدام (+15 لكل اختيار)
 * 
 * @see docs/09-Supplier-System-Refactoring.md
 * =============================================================================
 */

namespace App\Repositories;

use App\Support\Database;
use App\Support\ScoringConfig;
use PDO;

class SupplierSuggestionRepository
{
    private PDO $db;
    
    // Source weights for scoring (использует ScoringConfig для النجوم)
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
     * UPDATED: Includes block_count and applies penalty, filters negative scores
     * 
     * @return array Suggestions ordered by effective_score DESC (only positive scores)
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
                star_rating,
                COALESCE(block_count, 0) as block_count,
                (total_score - COALESCE(block_count, 0) * " . ScoringConfig::BLOCK_PENALTY . ") as effective_score
            FROM supplier_suggestions
            WHERE normalized_input = ?
            AND (total_score - COALESCE(block_count, 0) * " . ScoringConfig::BLOCK_PENALTY . ") > 0
            ORDER BY effective_score DESC
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
     * Calculate total score (without block penalty - that's applied at query time)
     * Formula: (fuzzy × 100) + source_weight + min(usage × BONUS_PER_USE, BONUS_MAX)
     */
    private function calculateScore(float $fuzzyScore, int $sourceWeight, int $usageCount): float
    {
        $fuzzyPoints = $fuzzyScore * 100;
        $usageBonus = ScoringConfig::calculateUsageBonus($usageCount);
        return $fuzzyPoints + $sourceWeight + $usageBonus;
    }
    
    /**
     * ═══════════════════════════════════════════════════════════════════
     * GRADUAL BLOCKING (NEW - 2025-12-17)
     * ═══════════════════════════════════════════════════════════════════
     * Increment block count for a suggestion.
     * Each block adds a -50 penalty to effective score.
     * Supplier only disappears when effective score goes negative.
     * 
     * @param string $normalizedInput Normalized raw name
     * @param int $supplierId Supplier to block
     * @return bool Success
     */
    public function incrementBlock(string $normalizedInput, int $supplierId): bool
    {
        // Check if exists
        $stmt = $this->db->prepare("
            SELECT id, block_count FROM supplier_suggestions
            WHERE normalized_input = ? AND supplier_id = ?
            LIMIT 1
        ");
        $stmt->execute([$normalizedInput, $supplierId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing) {
            // Create a new entry with block_count = 1 and low score
            return $this->createBlockedEntry($normalizedInput, $supplierId);
        }
        
        // Increment block count
        $updateStmt = $this->db->prepare("
            UPDATE supplier_suggestions
            SET block_count = COALESCE(block_count, 0) + 1,
                last_updated = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        return $updateStmt->execute([$existing['id']]);
    }
    
    /**
     * Create a blocked entry for a supplier that wasn't in suggestions
     */
    private function createBlockedEntry(string $normalizedInput, int $supplierId): bool
    {
        $supplierStmt = $this->db->prepare("SELECT official_name FROM suppliers WHERE id = ?");
        $supplierStmt->execute([$supplierId]);
        $supplier = $supplierStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$supplier) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO supplier_suggestions (
                normalized_input, supplier_id, display_name, source,
                fuzzy_score, source_weight, usage_count, block_count,
                total_score, star_rating, last_updated
            ) VALUES (?, ?, ?, 'dictionary', 0.5, 40, 0, 1, 90, 1, CURRENT_TIMESTAMP)
        ");
        
        return $stmt->execute([
            $normalizedInput,
            $supplierId,
            $supplier['official_name'],
        ]);
    }
    
    /**
     * Assign star rating based on total score
     * Uses ScoringConfig for unified thresholds
     */
    private function assignStarRating(float $totalScore): int
    {
        return ScoringConfig::getStarRating($totalScore);
    }
    
    /**
     * Get supplier IDs that are effectively blocked (negative effective_score)
     * Used by CandidateService to filter dictionary/alternatives searches
     * 
     * @return array<int> Array of blocked supplier IDs
     */
    public function getBlockedSupplierIds(string $normalizedInput): array
    {
        $stmt = $this->db->prepare("
            SELECT supplier_id
            FROM supplier_suggestions
            WHERE normalized_input = ?
            AND (total_score - COALESCE(block_count, 0) * " . ScoringConfig::BLOCK_PENALTY . ") <= 0
        ");
        $stmt->execute([$normalizedInput]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'supplier_id');
    }
    
    // ═══════════════════════════════════════════════════════════════════
    // NOTE: clearCache() و getAllCachedInputs() تم حذفها (2025-12-19)
    // ═══════════════════════════════════════════════════════════════════
    // السبب: هذه الدوال لم تكن مستخدمة في أي من:
    // - نظام التعلم التلقائي
    // - واجهة المستخدم
    // - زر التحديث أو الطباعة
    // 
    // إذا احتجت لتنظيف الكاش يدوياً، استخدم SQL مباشرة:
    // DELETE FROM supplier_suggestions WHERE normalized_input = '...'
    // ═══════════════════════════════════════════════════════════════════
}

