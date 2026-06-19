<?php

require_once __DIR__ . '/../../../config/helpers.php';

use PHPUnit\Framework\TestCase;

class SocialProofPropertyTest extends TestCase
{
    /**
     * @test
     * Property 2: Social Proof Determinism
     * Validates: Requirements 2.2, 2.5
     */
    public function testSocialProofDeterminism(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $product = ['id' => random_int(1, 100000)];
            
            $result1 = generateSocialProof($product);
            $result2 = generateSocialProof($product);
            $result3 = generateSocialProof($product);
            
            $this->assertEquals($result1['rating'], $result2['rating']);
            $this->assertEquals($result1['rating'], $result3['rating']);
            
            $this->assertEquals($result1['review_count'], $result2['review_count']);
            $this->assertEquals($result1['review_count'], $result3['review_count']);
            
            $this->assertEquals($result1['sold_count'], $result2['sold_count']);
            $this->assertEquals($result1['sold_count'], $result3['sold_count']);
            
            $this->assertEquals($result1['sold_display'], $result2['sold_display']);
            $this->assertEquals($result1['sold_display'], $result3['sold_display']);
        }
    }

    /**
     * @test
     * Property 3: Social Proof Bounds
     * Validates: Requirements 2.3
     */
    public function testSocialProofBounds(): void
    {
        for ($i = 0; $i < 1000; $i++) {
            $product = ['id' => random_int(1, 1000000)];
            $result = generateSocialProof($product);
            
            $this->assertGreaterThanOrEqual(4.0, $result['rating']);
            $this->assertLessThanOrEqual(5.0, $result['rating']);
            
            $this->assertGreaterThanOrEqual(5, $result['review_count']);
            $this->assertLessThanOrEqual(200, $result['review_count']);
            
            $this->assertGreaterThanOrEqual(10, $result['sold_count']);
            $this->assertLessThanOrEqual(500, $result['sold_count']);
            
            $this->assertNotEmpty($result['sold_display']);
            $this->assertIsString($result['sold_display']);
        }
    }

    /**
     * @test
     * Edge case: Missing ID or non-integer ID
     */
    public function testSocialProofDeterminismMissingId(): void
    {
        $productNoId = [];
        $productNullId = ['id' => null];
        $productStringId = ['id' => 'abc'];
        
        $result1 = generateSocialProof($productNoId);
        $result2 = generateSocialProof($productNullId);
        $result3 = generateSocialProof($productStringId);
        
        // Should all be the same (fallbacks to id = 1)
        $this->assertEquals($result1, $result2);
        $this->assertEquals($result1, $result3);
        
        // Ensure bounds still hold for fallback
        $this->assertGreaterThanOrEqual(4.0, $result1['rating']);
        $this->assertLessThanOrEqual(5.0, $result1['rating']);
        $this->assertGreaterThanOrEqual(5, $result1['review_count']);
        $this->assertLessThanOrEqual(200, $result1['review_count']);
        $this->assertGreaterThanOrEqual(10, $result1['sold_count']);
        $this->assertLessThanOrEqual(500, $result1['sold_count']);
    }
}
