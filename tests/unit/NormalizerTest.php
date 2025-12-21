<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Support/autoload.php';

use App\Support\Normalizer;

class NormalizerTest extends TestCase
{
    private Normalizer $normalizer;

    public function __construct(TestRunner $runner)
    {
        parent::__construct($runner);
        $this->normalizer = new Normalizer();
    }

    // ═══════════════════════════════════════════════════════════════════
    // normalizeSupplierName Tests
    // ═══════════════════════════════════════════════════════════════════

    public function testNormalizeSupplierName_Basic()
    {
        $result = $this->normalizer->normalizeSupplierName('شركة الرياض للتجارة');
        $this->assertTrue(strlen($result) > 0, 'Should return non-empty string');
        $this->assertTrue(strpos($result, 'شركة') === false, 'Should remove "شركة" stop word');
    }

    public function testNormalizeSupplierName_EnglishStopWords()
    {
        $result = $this->normalizer->normalizeSupplierName('ABC Trading Company Ltd.');
        $this->assertTrue(strpos(strtolower($result), 'trading') === false, 'Should remove "trading"');
        $this->assertTrue(strpos(strtolower($result), 'company') === false, 'Should remove "company"');
        $this->assertTrue(strpos(strtolower($result), 'ltd') === false, 'Should remove "ltd"');
    }

    public function testNormalizeSupplierName_EmptyString()
    {
        $result = $this->normalizer->normalizeSupplierName('');
        $this->assertEquals('', $result, 'Empty input should return empty string');
    }

    public function testNormalizeSupplierName_ArabicCharNormalization()
    {
        $result1 = $this->normalizer->normalizeSupplierName('محمد أحمد');
        $result2 = $this->normalizer->normalizeSupplierName('محمد احمد');
        $this->assertEquals($result1, $result2, 'Should normalize أ to ا');
    }

    // ═══════════════════════════════════════════════════════════════════
    // normalizeBankName Tests
    // ═══════════════════════════════════════════════════════════════════

    public function testNormalizeBankName_RemovesSpaces()
    {
        $result = $this->normalizer->normalizeBankName('Riyad Bank');
        $this->assertEquals('riyadbank', $result, 'Should remove spaces and lowercase');
    }

    public function testNormalizeBankName_ArabicName()
    {
        $result = $this->normalizer->normalizeBankName('بنك الرياض');
        $this->assertTrue(strlen($result) > 0, 'Should handle Arabic bank names');
        $this->assertTrue(strpos($result, ' ') === false, 'Should have no spaces');
    }

    // ═══════════════════════════════════════════════════════════════════
    // normalizeBankShortCode Tests
    // ═══════════════════════════════════════════════════════════════════

    public function testNormalizeBankShortCode_Basic()
    {
        $result = $this->normalizer->normalizeBankShortCode('snb');
        $this->assertEquals('SNB', $result, 'Should uppercase short code');
    }

    public function testNormalizeBankShortCode_WithSymbols()
    {
        $result = $this->normalizer->normalizeBankShortCode('al-rajhi');
        $this->assertEquals('ALRAJHI', $result, 'Should remove symbols');
    }

    // ═══════════════════════════════════════════════════════════════════
    // makeSupplierKey Tests
    // ═══════════════════════════════════════════════════════════════════

    public function testMakeSupplierKey_RemovesSpaces()
    {
        $result = $this->normalizer->makeSupplierKey('مؤسسة الفهد للتجارة');
        $this->assertTrue(strpos($result, ' ') === false, 'Key should have no spaces');
    }

    public function testMakeSupplierKey_Consistency()
    {
        $key1 = $this->normalizer->makeSupplierKey('ABC Company');
        $key2 = $this->normalizer->makeSupplierKey('abc company');
        $this->assertEquals($key1, $key2, 'Keys should be case-insensitive');
    }
}
