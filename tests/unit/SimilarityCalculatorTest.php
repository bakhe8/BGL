<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Support/SimilarityCalculator.php';

use App\Support\SimilarityCalculator;

class SimilarityCalculatorTest extends TestCase
{
    // No constructor needed as methods are static

    public function testPerfectMatch()
    {
        $score = SimilarityCalculator::safeLevenshteinRatio('Al Rajhi', 'Al Rajhi');
        $this->assertEquals(1.0, $score, 'Identical strings should have score 1.0');
    }

    public function testFuzzyMatch()
    {
        // One character difference (Typo)
        $score = SimilarityCalculator::safeLevenshteinRatio('Mohamed', 'Mohammed');
        $this->assertTrue($score > 0.8, "Expected high similarity for Mohamed vs Mohammed, got $score");
    }

    public function testDifferentStrings()
    {
        $score = SimilarityCalculator::safeLevenshteinRatio('Al Rajhi', 'Saudi National Bank');
        $this->assertTrue($score < 0.3, "Expected low similarity for different banks, got $score");
    }

    public function testEmptyStrings()
    {
        $score = SimilarityCalculator::safeLevenshteinRatio('', '');
        $this->assertEquals(0.0, $score, 'Empty strings comparison should be 0.0 per implementation');
        
        $score = SimilarityCalculator::safeLevenshteinRatio('ABC', '');
        $this->assertEquals(0.0, $score, 'Comparison with empty string should be 0.0');
    }
    
    public function testJaccardSimilarity()
    {
        $s1 = 'Trading and Contracting';
        $s2 = 'Contracting and Trading';
        
        // Explicitly test Jaccard
        $score = SimilarityCalculator::tokenJaccardSimilarity($s1, $s2);
        $this->assertTrue($score > 0.8, "Jaccard logic should handle reordered words. Score: $score");
        
        // Safe Levenshtein should fallback/handle strict params, but here strings are short (<255)
        // so it calls levenshtein. Levenshtein on reordered words is low.
        // But let's trust the tokenJaccard function logic directly.
    }
}
