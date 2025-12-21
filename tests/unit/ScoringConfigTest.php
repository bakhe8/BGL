<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Support/autoload.php';

use App\Support\ScoringConfig;

class ScoringConfigTest extends TestCase
{
    // ═══════════════════════════════════════════════════════════════════
    // getStarRating Tests
    // ═══════════════════════════════════════════════════════════════════

    public function testGetStarRating_ThreeStars()
    {
        $this->assertEquals(3, ScoringConfig::getStarRating(200), 'Score 200 should get 3 stars');
        $this->assertEquals(3, ScoringConfig::getStarRating(250), 'Score 250 should get 3 stars');
        $this->assertEquals(3, ScoringConfig::getStarRating(300.5), 'Score 300.5 should get 3 stars');
    }

    public function testGetStarRating_TwoStars()
    {
        $this->assertEquals(2, ScoringConfig::getStarRating(120), 'Score 120 should get 2 stars');
        $this->assertEquals(2, ScoringConfig::getStarRating(150), 'Score 150 should get 2 stars');
        $this->assertEquals(2, ScoringConfig::getStarRating(199.9), 'Score 199.9 should get 2 stars');
    }

    public function testGetStarRating_OneStar()
    {
        $this->assertEquals(1, ScoringConfig::getStarRating(0), 'Score 0 should get 1 star');
        $this->assertEquals(1, ScoringConfig::getStarRating(50), 'Score 50 should get 1 star');
        $this->assertEquals(1, ScoringConfig::getStarRating(119.9), 'Score 119.9 should get 1 star');
    }

    // ═══════════════════════════════════════════════════════════════════
    // calculateUsageBonus Tests
    // ═══════════════════════════════════════════════════════════════════

    public function testCalculateUsageBonus_Basic()
    {
        $this->assertEquals(15, ScoringConfig::calculateUsageBonus(1), '1 usage = 15 points');
        $this->assertEquals(30, ScoringConfig::calculateUsageBonus(2), '2 usages = 30 points');
        $this->assertEquals(45, ScoringConfig::calculateUsageBonus(3), '3 usages = 45 points');
    }

    public function testCalculateUsageBonus_MaxCap()
    {
        $this->assertEquals(75, ScoringConfig::calculateUsageBonus(5), '5 usages = 75 points (max)');
        $this->assertEquals(75, ScoringConfig::calculateUsageBonus(10), '10 usages = 75 points (capped)');
        $this->assertEquals(75, ScoringConfig::calculateUsageBonus(100), '100 usages = 75 points (capped)');
    }

    public function testCalculateUsageBonus_Zero()
    {
        $this->assertEquals(0, ScoringConfig::calculateUsageBonus(0), '0 usages = 0 points');
    }

    // ═══════════════════════════════════════════════════════════════════
    // Constants Tests
    // ═══════════════════════════════════════════════════════════════════

    public function testConstants_AreCorrect()
    {
        $this->assertEquals(200, ScoringConfig::STAR_3_THRESHOLD, 'STAR_3_THRESHOLD should be 200');
        $this->assertEquals(120, ScoringConfig::STAR_2_THRESHOLD, 'STAR_2_THRESHOLD should be 120');
        $this->assertEquals(15, ScoringConfig::USAGE_BONUS_PER_USE, 'USAGE_BONUS_PER_USE should be 15');
        $this->assertEquals(75, ScoringConfig::USAGE_BONUS_MAX, 'USAGE_BONUS_MAX should be 75');
        $this->assertEquals(50, ScoringConfig::BLOCK_PENALTY, 'BLOCK_PENALTY should be 50');
    }
}
